<?php
declare(strict_types=1);

require_once __DIR__ . '/EjecutaVistas.php';

/**
 * T041 (specs/001-descuento-maximo-rol, caso límite "Rol desactivado o permiso retirado con una venta en curso"):
 * con la vista real views/ventas/crear.php, al confirmar la venta se vuelven a comprobar los permisos.
 */
final class VentaPermisoRetiradoTest extends BaseDatosTestCase {
    use EjecutaVistas;

    private int $cajero;

    protected function setUp(): void {
        parent::setUp();
        $this->prepararSesiones();
        $this->cajero = $this->crearUsuario('cajero1', ROL_CAJERO, 'Cajero Uno');
        $this->llenarCarrito($this->cajero, [[$this->crearProducto(50000, 10), 2]]); // subtotal $100.000
        $this->iniciarSesionComo($this->cajero);
        // Primera carga con la sesión del cajero, como si tuviera la pantalla de venta abierta.
        $this->ejecutarVista('views/ventas/crear.php', 'GET', ['sesion' => $_SESSION + ['csrf' => 'token-prueba']]);
    }

    protected function tearDown(): void { $this->borrarSesion(); parent::tearDown(); }

    /** Confirma una venta válida (descuento dentro del límite) con la sesión guardada. */
    private function confirmarVenta(): string {
        return $this->ejecutarVista('views/ventas/crear.php', 'POST', ['post' => ['csrf' => 'token-prueba',
            'id_cliente' => (string)$this->clienteGeneral(), 'id_metodo_pago' => (string)$this->metodoPago('Tarjeta'), 'descuento' => '5000']]);
    }

    public function testSinElPermisoDeVentasRecibeAccesoDenegadoYNoSeRegistra(): void {
        self::$db->exec("DELETE rp FROM rol_permisos rp JOIN permisos p ON p.id_permiso = rp.id_permiso
            WHERE rp.id_rol = " . ROL_CAJERO . " AND p.nombre = 'ventas.crear'");
        $this->renovarVersionDePermisos();

        $html = $this->confirmarVenta();
        $this->assertStringContainsString('Acceso denegado', $html);
        $this->assertSame(0, $this->contar('ventas'));
    }

    public function testConElUsuarioDesactivadoSeCierraLaSesionYNoSeRegistra(): void {
        self::$db->exec("UPDATE usuarios SET estado = 0 WHERE id_usuario = {$this->cajero}");
        $this->renovarVersionDePermisos(); // como hace UsuarioController al desactivar

        $antes = time();
        $this->confirmarVenta();
        $this->assertSame(0, $this->contar('ventas'));

        // sincronizarSesion() vacía la sesión y la regenera (session_regenerate_id(true) borra el archivo antiguo).
        $antigua = is_file($this->archivoSesion()) ? (string)file_get_contents($this->archivoSesion()) : '';
        $this->assertStringNotContainsString('id_usuario', $antigua, 'La sesión antigua ya no tiene usuario.');
        $nuevas = array_filter(glob($this->rutaSesiones . DIRECTORY_SEPARATOR . 'sess_*') ?: [],
            fn($f) => filemtime($f) >= $antes - 1 && str_contains((string)file_get_contents($f), 'Tu usuario o tu rol fue desactivado'));
        $this->assertCount(1, $nuevas, 'La sesión regenerada lleva el aviso de usuario desactivado.');
        foreach ($nuevas as $f) {
            $this->assertStringNotContainsString('id_usuario', (string)file_get_contents($f));
            @unlink($f);
        }
    }

    public function testConPermisoYUsuarioActivoLaVentaSeRegistra(): void {
        // Control: la misma venta, sin cambios de permisos, sí se registra (las anteriores no fallan por otra causa).
        $this->confirmarVenta();
        $this->assertSame(1, $this->contar('ventas'));
    }
}
