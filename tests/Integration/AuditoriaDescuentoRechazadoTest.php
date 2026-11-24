<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;

require_once __DIR__ . '/../../controllers/VentaController.php';

/**
 * US3: cada intento rechazado por exceder el descuento máximo queda en auditoría, aunque la venta se deshaga
 * (spec.md US3, FR-011; data-model.md, tabla de auditoría; research.md R1 y R8).
 * Venta base: 2 × $50.000 = subtotal $100.000, rol Cajero con 10 %.
 */
final class AuditoriaDescuentoRechazadoTest extends BaseDatosTestCase {
    private const ACCION = 'descuento rechazado';
    private int $cajero;
    private int $producto;

    protected function setUp(): void {
        parent::setUp();
        $this->cajero = $this->crearUsuario('cajero1', ROL_CAJERO, 'Cajero Uno');
        $this->producto = $this->crearProducto(50000, 10);
        $this->llenarCarrito($this->cajero, [[$this->producto, 2]]);
        $this->iniciarSesionComo($this->cajero);
    }

    private function finalizar(float $descuento): ?RuntimeException {
        try {
            (new VentaController())->finalizar(['id_cliente' => $this->clienteGeneral(),
                'id_metodo_pago' => $this->metodoPago('Tarjeta'), 'descuento' => $descuento]);
            return null;
        } catch (RuntimeException $e) {
            return $e;
        }
    }

    private function registros(): array {
        $s = self::$db->prepare('SELECT * FROM auditoria WHERE modulo = :m AND accion = :a ORDER BY id_auditoria');
        $s->execute(['m' => 'ventas', 'a' => self::ACCION]);
        return $s->fetchAll(PDO::FETCH_ASSOC);
    }

    // Escenarios 1 y 2: registro completo y conservado aunque la venta se deshaga
    public function testRegistraElIntentoRechazadoAunqueLaVentaSeDeshaga(): void {
        $this->assertInstanceOf(DescuentoExcedidoException::class, $this->finalizar(15000));

        $this->assertSame(0, $this->contar('ventas'), 'La venta no debe existir.');
        $r = $this->registros();
        $this->assertCount(1, $r);
        $this->assertSame($this->cajero, (int)$r[0]['id_usuario']);
        $this->assertSame('ventas', $r[0]['tabla_afectada']);
        $this->assertNull($r[0]['id_registro']);
        $this->assertSame('Descuento rechazado: $15.000 (15 %) sobre subtotal $100.000. Máximo del rol Cajero: 10 %', $r[0]['descripcion']);
        $this->assertNotEmpty($r[0]['fecha']);
    }

    public function testCadaIntentoGeneraSuPropioRegistro(): void {
        $this->finalizar(15000);
        $this->finalizar(12000);
        $r = $this->registros();
        $this->assertCount(2, $r);
        $this->assertStringContainsString('$12.000 (12 %)', $r[1]['descripcion']);
    }

    // Escenario 3: otros rechazos no se registran como descuento excesivo
    public static function otrosRechazos(): array {
        return [
            'descuento negativo'      => [-1],
            'mayor que el subtotal'   => [150000],
        ];
    }

    #[DataProvider('otrosRechazos')]
    public function testOtrosRechazosDeDescuentoNoSeRegistran(float $descuento): void {
        $e = $this->finalizar($descuento);
        $this->assertNotNull($e);
        $this->assertNotInstanceOf(DescuentoExcedidoException::class, $e);
        $this->assertCount(0, $this->registros());
    }

    public function testRechazoPorStockNoSeRegistraComoDescuento(): void {
        self::$db->exec("UPDATE productos SET stock = 1 WHERE id_producto = {$this->producto}");
        $e = $this->finalizar(50000); // también excede el 10 %, pero el stock se valida antes
        $this->assertNotNull($e);
        $this->assertStringContainsString('Stock insuficiente', $e->getMessage());
        $this->assertCount(0, $this->registros());
    }

    public function testVentaAceptadaNoGeneraRegistroDeRechazo(): void {
        $this->assertNull($this->finalizar(10000));
        $this->assertSame(1, $this->contar('ventas'));
        $this->assertCount(0, $this->registros());
    }

    // FR-011: si la auditoría falla, la venta igual no se guarda, el usuario ve el mensaje y el fallo va al log
    public function testFalloDeAuditoriaNoOcultaElMensajeDelLimite(): void {
        $log = tempnam(sys_get_temp_dir(), 'audit');
        $logAnterior = ini_set('error_log', $log);
        self::$db->exec('RENAME TABLE auditoria TO auditoria_fuera');
        try {
            $e = $this->finalizar(15000);
        } finally {
            self::$db->exec('RENAME TABLE auditoria_fuera TO auditoria');
            ini_set('error_log', (string)$logAnterior);
        }
        $this->assertInstanceOf(DescuentoExcedidoException::class, $e);
        $this->assertSame('El descuento (15 %) supera el máximo permitido para tu rol (10 %).', $e->getMessage());
        $this->assertSame(0, $this->contar('ventas'));
        $this->assertStringContainsString('descuento rechazado', (string)file_get_contents($log));
        @unlink($log);
    }

    // T029: el resumen de auditoría cuenta el rechazo como acción sensible
    public function testElResumenCuentaElRechazoComoAccionSensible(): void {
        $this->finalizar(15000);
        $hoy = (string)self::$db->query('SELECT CURDATE()')->fetchColumn(); // misma zona horaria que auditoria.fecha
        $resumen = (new Auditoria(self::$db))->resumen(['desde' => $hoy, 'hasta' => $hoy]);
        $this->assertSame(1, (int)$resumen['sensibles']);
    }
}
