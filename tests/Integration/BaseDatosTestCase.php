<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constantes.php';

/**
 * Base para pruebas de integración contra la base aislada sistema_ventas_test.
 *
 * - La primera clase que la usa recrea sistema_ventas_test desde database.sql (una vez por ejecución).
 * - Antes de cada prueba vacía todas las tablas y vuelve a cargar los datos iniciales de database.sql,
 *   así cada prueba arranca desde el mismo estado.
 * - Nunca toca sistema_ventas: aborta si DB_NAME no es sistema_ventas_test y descarta el
 *   CREATE DATABASE / USE del script.
 */
abstract class BaseDatosTestCase extends TestCase {
    protected const BASE_PRUEBAS = 'sistema_ventas_test';
    // Hash de "Admin123*" (el mismo de database.sql); evita calcular bcrypt en cada usuario de prueba.
    private const HASH_CLAVE = '$2y$12$Eqdy02ijfddc4nwCAxfCzecCzuiKHtlfJxgsIZtDH3DfJZlCmCjm2';

    protected static PDO $db;
    private static bool $esquemaCreado = false;
    /** @var string[] */
    private static array $semilla = [];
    /** @var string[] */
    private static array $tablas = [];

    public static function setUpBeforeClass(): void {
        if (getenv('DB_NAME') !== self::BASE_PRUEBAS) {
            throw new RuntimeException('Las pruebas de integración requieren DB_NAME=' . self::BASE_PRUEBAS . ' (ver phpunit.xml).');
        }
        if (self::$esquemaCreado) return;

        [$esquema, self::$semilla] = self::leerScript(__DIR__ . '/../../database.sql');
        $servidor = self::conexionServidor();
        $servidor->exec('DROP DATABASE IF EXISTS ' . self::BASE_PRUEBAS);
        $servidor->exec('CREATE DATABASE ' . self::BASE_PRUEBAS . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

        self::$db = (new Database())->getConnection();
        $actual = self::$db->query('SELECT DATABASE()')->fetchColumn();
        if ($actual !== self::BASE_PRUEBAS) throw new RuntimeException("Conectado a '{$actual}' en lugar de " . self::BASE_PRUEBAS . '.');

        self::ejecutar($esquema);
        self::$tablas = self::$db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        self::$esquemaCreado = true;
    }

    protected function setUp(): void {
        parent::setUp();
        $_SESSION = [];
        self::$db->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach (self::$tablas as $t) self::$db->exec("TRUNCATE TABLE `{$t}`");
        self::ejecutar(self::$semilla); // termina con SET FOREIGN_KEY_CHECKS = 1
    }

    // ---------- Preparación del script ----------

    /** Divide database.sql en [sentencias de esquema, sentencias de datos iniciales]. */
    private static function leerScript(string $ruta): array {
        $sql = file_get_contents($ruta);
        if ($sql === false) throw new RuntimeException("No se pudo leer {$ruta}.");
        $partes = preg_split('/^-- DATOS INICIALES\s*$/m', $sql, 2);
        if (count($partes) !== 2) throw new RuntimeException('database.sql no tiene la sección "-- DATOS INICIALES".');
        $esquema = array_values(array_filter(self::sentencias($partes[0]),
            fn(string $s) => !preg_match('/^(CREATE\s+DATABASE|USE)\b/i', $s)));
        $semilla = self::sentencias($partes[1]);
        foreach (array_merge($esquema, $semilla) as $s) {
            if (preg_match('/\bsistema_ventas\b/i', $s)) throw new RuntimeException('El script nombra la base real: ' . substr($s, 0, 80));
        }
        return [$esquema, $semilla];
    }

    /** @return string[] sentencias sin comentarios de línea, separadas por ';' al final de línea. */
    private static function sentencias(string $sql): array {
        $sinComentarios = preg_replace('/^\s*--.*$/m', '', $sql);
        return array_values(array_filter(array_map('trim', preg_split('/;\s*$/m', $sinComentarios)), fn($s) => $s !== ''));
    }

    private static function ejecutar(array $sentencias): void {
        foreach ($sentencias as $s) self::$db->exec($s);
    }

    /** Conexión al servidor sin base seleccionada, con las mismas credenciales de config/database.php. */
    private static function conexionServidor(): PDO {
        $r = new ReflectionClass(Database::class);
        $valor = fn(string $p) => (string)$r->getProperty($p)->getDefaultValue();
        return new PDO('mysql:host=' . $valor('host') . ';charset=utf8mb4', $valor('username'), $valor('password'), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
    }

    // ---------- Utilidades para preparar datos ----------

    /** Crea un rol con sus permisos (nombres de la tabla permisos). Devuelve id_rol. */
    protected function crearRol(string $nombre, float $descuentoMaximo = 10.00, array $permisos = []): int {
        self::$db->prepare('INSERT INTO roles (nombre, descripcion, descuento_maximo) VALUES (:n, NULL, :d)')
            ->execute(['n' => $nombre, 'd' => $descuentoMaximo]);
        $id = (int)self::$db->lastInsertId();
        $this->asignarPermisos($id, $permisos);
        return $id;
    }

    protected function asignarPermisos(int $rolId, array $permisos): void {
        $s = self::$db->prepare('INSERT IGNORE INTO rol_permisos (id_rol, id_permiso) SELECT :r, id_permiso FROM permisos WHERE nombre = :p');
        foreach ($permisos as $p) {
            $s->execute(['r' => $rolId, 'p' => $p]);
            if ($s->rowCount() === 0 && !$this->existePermiso($p)) throw new InvalidArgumentException("Permiso inexistente: {$p}");
        }
    }

    private function existePermiso(string $p): bool {
        $s = self::$db->prepare('SELECT 1 FROM permisos WHERE nombre = :p');
        $s->execute(['p' => $p]);
        return (bool)$s->fetchColumn();
    }

    /** Fija el descuento máximo de un rol directamente en la base. */
    protected function fijarDescuentoMaximo(int $rolId, float $valor): void {
        self::$db->prepare('UPDATE roles SET descuento_maximo = :v WHERE id_rol = :id')->execute(['v' => $valor, 'id' => $rolId]);
    }

    /** Crea un usuario activo (contraseña Admin123*). Devuelve id_usuario. */
    protected function crearUsuario(string $usuario, int $rolId, ?string $nombre = null): int {
        self::$db->prepare('INSERT INTO usuarios (nombre, usuario, password, rol_id) VALUES (:n, :u, :p, :r)')
            ->execute(['n' => $nombre ?? ucfirst($usuario), 'u' => $usuario, 'p' => self::HASH_CLAVE, 'r' => $rolId]);
        return (int)self::$db->lastInsertId();
    }

    /** id del Cliente General que crea database.sql. */
    protected function clienteGeneral(): int {
        $s = self::$db->prepare('SELECT id_cliente FROM clientes WHERE documento = :d');
        $s->execute(['d' => CLIENTE_GENERAL_DOCUMENTO]);
        return (int)$s->fetchColumn();
    }

    /** Crea un producto activo con precio y stock. Devuelve id_producto. */
    protected function crearProducto(float $precioVenta, float $stock = 100, string $nombre = 'Producto de prueba'): int {
        $categoria = (int)self::$db->query("SELECT id_categoria FROM categorias WHERE nombre = 'Pruebas'")->fetchColumn();
        if (!$categoria) {
            self::$db->exec("INSERT INTO categorias (nombre) VALUES ('Pruebas')");
            $categoria = (int)self::$db->lastInsertId();
        }
        self::$db->prepare('INSERT INTO productos (id_categoria, codigo, nombre, precio_compra, precio_venta, stock) VALUES (:c, :cod, :n, :pc, :pv, :s)')
            ->execute(['c' => $categoria, 'cod' => 'P-' . bin2hex(random_bytes(4)), 'n' => $nombre,
                'pc' => round($precioVenta * 0.6, 2), 'pv' => $precioVenta, 's' => $stock]);
        return (int)self::$db->lastInsertId();
    }

    /** Crea el carrito activo del usuario con los ítems dados: [[id_producto, cantidad], ...]. Devuelve id_carrito. */
    protected function llenarCarrito(int $usuarioId, array $items): int {
        self::$db->prepare('INSERT INTO carritos (id_usuario) VALUES (:u)')->execute(['u' => $usuarioId]);
        $carrito = (int)self::$db->lastInsertId();
        $precio = self::$db->prepare('SELECT precio_venta FROM productos WHERE id_producto = :p');
        $det = self::$db->prepare('INSERT INTO carrito_detalle (id_carrito, id_producto, cantidad, precio_unitario, subtotal) VALUES (:c, :p, :q, :pu, :s)');
        foreach ($items as [$producto, $cantidad]) {
            $precio->execute(['p' => $producto]);
            $pu = (float)$precio->fetchColumn();
            $det->execute(['c' => $carrito, 'p' => $producto, 'q' => $cantidad, 'pu' => $pu, 's' => round($pu * $cantidad, 2)]);
        }
        return $carrito;
    }

    /** Abre la Caja Principal para el usuario. Devuelve id_apertura. */
    protected function abrirCaja(int $usuarioId, float $montoInicial = 0): int {
        self::$db->prepare("INSERT INTO aperturas_caja (id_caja, id_usuario, monto_inicial) VALUES (1, :u, :m)")
            ->execute(['u' => $usuarioId, 'm' => $montoInicial]);
        return (int)self::$db->lastInsertId();
    }

    protected function metodoPago(string $nombre): int {
        $s = self::$db->prepare('SELECT id_metodo_pago FROM metodos_pago WHERE nombre = :n');
        $s->execute(['n' => $nombre]);
        $id = (int)$s->fetchColumn();
        if (!$id) throw new InvalidArgumentException("Método de pago inexistente: {$nombre}");
        return $id;
    }

    /** Llena $_SESSION como lo hace helpers/auth.php al iniciar sesión. */
    protected function iniciarSesionComo(int $usuarioId): void {
        $s = self::$db->prepare('SELECT u.id_usuario, u.nombre, u.usuario, u.rol_id, r.nombre rol FROM usuarios u
            INNER JOIN roles r ON r.id_rol = u.rol_id WHERE u.id_usuario = :id');
        $s->execute(['id' => $usuarioId]);
        $u = $s->fetch(PDO::FETCH_ASSOC);
        if (!$u) throw new InvalidArgumentException("Usuario inexistente: {$usuarioId}");
        $p = self::$db->prepare('SELECT p.nombre FROM rol_permisos rp INNER JOIN permisos p ON p.id_permiso = rp.id_permiso WHERE rp.id_rol = :r');
        $p->execute(['r' => $u['rol_id']]);
        $version = self::$db->query("SELECT valor FROM configuracion WHERE clave = 'permisos_version'")->fetchColumn();
        $_SESSION = [
            'id_usuario' => (int)$u['id_usuario'],
            'nombre' => $u['nombre'],
            'usuario' => $u['usuario'],
            'rol_id' => (int)$u['rol_id'],
            'rol' => $u['rol'],
            'permisos' => $p->fetchAll(PDO::FETCH_COLUMN),
            'permisos_version' => (string)($version ?: '0'),
        ];
    }

    /** Cuenta filas de una tabla existente con un filtro opcional parametrizado. */
    protected function contar(string $tabla, string $where = '1=1', array $params = []): int {
        if (!in_array($tabla, self::$tablas, true)) throw new InvalidArgumentException("Tabla desconocida: {$tabla}");
        $s = self::$db->prepare("SELECT COUNT(*) FROM `{$tabla}` WHERE {$where}");
        $s->execute($params);
        return (int)$s->fetchColumn();
    }
}
