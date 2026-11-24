<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;

require_once __DIR__ . '/../../controllers/VentaController.php';

/**
 * US1: rechazar ventas con descuento superior al máximo del rol (spec.md US1, FR-006..FR-010, FR-013, FR-015).
 * Venta base: 2 × $50.000 = subtotal $100.000, impuesto 0 %, efectivo, con caja abierta.
 */
final class VentaDescuentoMaximoTest extends BaseDatosTestCase {
    private int $cajero;
    private int $producto;
    private int $carrito;

    protected function setUp(): void {
        parent::setUp();
        $this->cajero = $this->crearUsuario('cajero1', ROL_CAJERO, 'Cajero Uno'); // Cajero: 10 % (datos iniciales)
        $this->producto = $this->crearProducto(50000, 10);
        $this->carrito = $this->llenarCarrito($this->cajero, [[$this->producto, 2]]);
        $this->abrirCaja($this->cajero);
        $this->iniciarSesionComo($this->cajero);
    }

    private function venta(float|string $descuento): array {
        return ['id_cliente' => $this->clienteGeneral(), 'id_metodo_pago' => $this->metodoPago('Efectivo'),
            'recibido' => 200000, 'descuento' => $descuento];
    }

    /** Ejecuta finalizar() esperando un rechazo por descuento; devuelve la excepción. */
    private function rechazo(float|string $descuento): DescuentoExcedidoException {
        try {
            (new VentaController())->finalizar($this->venta($descuento));
        } catch (DescuentoExcedidoException $e) {
            return $e;
        }
        $this->fail("La venta con descuento de {$descuento} debía rechazarse.");
    }

    private function assertNadaRegistrado(): void {
        foreach (['ventas', 'detalle_venta', 'venta_pagos', 'movimientos_caja', 'movimientos_inventario'] as $tabla) {
            $this->assertSame(0, $this->contar($tabla), "No debe haber filas nuevas en {$tabla}.");
        }
        $this->assertSame('10.000', (string)self::$db->query("SELECT stock FROM productos WHERE id_producto={$this->producto}")->fetchColumn());
        $this->assertSame('activo', self::$db->query("SELECT estado FROM carritos WHERE id_carrito={$this->carrito}")->fetchColumn());
        $this->assertSame(1, $this->contar('carrito_detalle', 'id_carrito = :c', ['c' => $this->carrito]));
    }

    // Escenario 1
    public function testRechazaDescuentoSuperiorAlMaximoSinRegistrarNada(): void {
        $e = $this->rechazo(15000);
        $this->assertSame('El descuento (15 %) supera el máximo permitido para tu rol (10 %).', $e->getMessage());
        $this->assertSame('Cajero', $e->rol);
        $this->assertNadaRegistrado(); // escenario 4: carrito, stock y caja intactos
    }

    // Escenario 2 (FR-009: límite inclusivo)
    public function testAceptaDescuentoIgualAlMaximo(): void {
        $id = (new VentaController())->finalizar($this->venta(10000));
        $this->assertSame('10000.00', self::$db->query("SELECT descuento FROM ventas WHERE id_venta={$id}")->fetchColumn());
        $this->assertSame('90000.00', self::$db->query("SELECT total FROM ventas WHERE id_venta={$id}")->fetchColumn());
    }

    // Escenario 3
    public function testAceptaVentaSinDescuento(): void {
        $id = (new VentaController())->finalizar($this->venta(0));
        $this->assertSame('100000.00', self::$db->query("SELECT total FROM ventas WHERE id_venta={$id}")->fetchColumn());
    }

    public function testAdministradorNoTieneLimite(): void {
        $carrito = $this->llenarCarrito(1, [[$this->producto, 2]]);
        $this->iniciarSesionComo(1);
        $id = (new VentaController())->finalizar($this->venta(90000));
        $this->assertSame('90000.00', self::$db->query("SELECT descuento FROM ventas WHERE id_venta={$id}")->fetchColumn());
        $this->assertSame('convertido', self::$db->query("SELECT estado FROM carritos WHERE id_carrito={$carrito}")->fetchColumn());
    }

    // FR-013: cambio del límite sin volver a iniciar sesión
    public function testCambioDelLimiteAplicaEnLaSiguienteVentaSinReiniciarSesion(): void {
        $sesion = $_SESSION;
        $this->fijarDescuentoMaximo(ROL_CAJERO, 5);
        $e = $this->rechazo(6000);
        $this->assertSame('El descuento (6 %) supera el máximo permitido para tu rol (5 %).', $e->getMessage());
        $this->assertSame($sesion, $_SESSION, 'La sesión no debe necesitar recargarse.');
    }

    // FR-013: cambio de rol del usuario sin volver a iniciar sesión
    public function testCambioDeRolDelUsuarioAplicaSinReiniciarSesion(): void {
        $aprendiz = $this->crearRol('Aprendiz', 5, ['ventas.crear', 'ventas.ver']);
        self::$db->prepare('UPDATE usuarios SET rol_id = :r WHERE id_usuario = :u')->execute(['r' => $aprendiz, 'u' => $this->cajero]);
        $this->assertSame(ROL_CAJERO, $_SESSION['rol_id'], 'La sesión sigue con el rol anterior.');
        $e = $this->rechazo(6000);
        $this->assertSame(5.0, $e->maximo);
        $this->assertSame('Aprendiz', $e->rol);
    }

    // FR-015: las reglas actuales no cambian ni se tratan como descuento excesivo
    public static function reglasActuales(): array {
        return [
            'negativo'                => [-1, 'El descuento no puede ser negativo.'],
            'mayor que el subtotal'   => [150000, 'El descuento no puede ser mayor que el subtotal.'],
        ];
    }

    #[DataProvider('reglasActuales')]
    public function testReglasActualesDeDescuentoSeMantienen(float $descuento, string $mensaje): void {
        try {
            (new VentaController())->finalizar($this->venta($descuento));
            $this->fail('La venta debía rechazarse.');
        } catch (RuntimeException $e) {
            $this->assertNotInstanceOf(DescuentoExcedidoException::class, $e);
            $this->assertSame($mensaje, $e->getMessage());
        }
        $this->assertNadaRegistrado();
    }

    public function testDescuentoConComaDecimalSeInterpretaComoAntes(): void {
        $e = $this->rechazo('10000,5'); // así llega desde el formulario
        $this->assertSame('El descuento (10,01 %) supera el máximo permitido para tu rol (10 %).', $e->getMessage());
    }

    // FR-010: la pantalla recibe el límite del usuario
    public function testDatosFormularioIncluyenElMaximoDelRol(): void {
        $this->assertSame(10.0, (new VentaController())->datosFormulario()['descuento_maximo']);
        $this->iniciarSesionComo(1);
        $this->assertNull((new VentaController())->datosFormulario()['descuento_maximo'], 'Administrador: sin límite.');
    }

    // Autorización: permitido y denegado (Constitución, Principio V)
    public function testSoloRolesConPermisoDeVentasPuedenVender(): void {
        $this->assertTrue(tienePermiso('ventas.crear'), 'Cajero tiene ventas.crear.');
        $this->iniciarSesionComo($this->crearUsuario('super1', ROL_SUPERVISOR));
        $this->assertFalse(tienePermiso('ventas.crear'), 'Supervisor no tiene ventas.crear.');
    }
}
