<?php
declare(strict_types=1);

/**
 * US1 escenario 4 (spec.md) y contracts/formularios.md: tras una venta rechazada por descuento, al volver a
 * la pantalla de venta el carrito, el cliente y el método de pago siguen como estaban (T035).
 * Ejecuta la vista real views/ventas/crear.php en procesos aparte: POST rechazado y luego GET con la misma sesión.
 */
final class VentaFormularioRechazoTest extends BaseDatosTestCase {
    private const VISTA = 'views/ventas/crear.php';
    private string $rutaSesiones;
    private string $sesionId;
    private array $sesion;
    private int $cliente;
    private int $tarjeta;

    protected function setUp(): void {
        parent::setUp();
        $cajero = $this->crearUsuario('cajero1', ROL_CAJERO, 'Cajero Uno'); // 10 %
        $producto = $this->crearProducto(50000, 10, 'Producto conservado');
        $this->llenarCarrito($cajero, [[$producto, 2]]); // subtotal $100.000
        self::$db->exec("INSERT INTO clientes (documento, nombre) VALUES ('900111', 'Cliente Elegido')");
        $this->cliente = (int)self::$db->lastInsertId();
        $this->tarjeta = $this->metodoPago('Tarjeta');
        $this->iniciarSesionComo($cajero);
        $this->sesion = $_SESSION + ['csrf' => 'token-prueba'];

        $this->rutaSesiones = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sv_sesiones_pruebas';
        if (!is_dir($this->rutaSesiones)) mkdir($this->rutaSesiones);
        $this->sesionId = 'prueba' . bin2hex(random_bytes(8));
    }

    protected function tearDown(): void {
        @unlink($this->rutaSesiones . DIRECTORY_SEPARATOR . 'sess_' . $this->sesionId);
        parent::tearDown();
    }

    /** Ejecuta la vista en un proceso aparte y devuelve su HTML. */
    private function vista(string $metodo, array $extra = []): string {
        $datos = ['vista' => self::VISTA, 'metodo' => $metodo, 'sesion_id' => $this->sesionId, 'ruta_sesiones' => $this->rutaSesiones] + $extra;
        $cmd = [PHP_BINARY, __DIR__ . '/fixtures/ejecutar_vista.php', base64_encode(json_encode($datos, JSON_THROW_ON_ERROR))];
        $entorno = array_merge(getenv(), ['DB_NAME' => self::BASE_PRUEBAS]);
        $proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $tubos, null, $entorno);
        $this->assertIsResource($proc);
        $html = stream_get_contents($tubos[1]);
        $errores = stream_get_contents($tubos[2]);
        fclose($tubos[1]); fclose($tubos[2]);
        $this->assertSame(0, proc_close($proc), "La vista terminó con error: {$errores}");
        $this->assertSame('', $errores, 'La vista no debe emitir avisos ni errores.');
        return (string)$html;
    }

    private function postRechazado(): void {
        $this->vista('POST', ['sesion' => $this->sesion, 'post' => ['csrf' => 'token-prueba',
            'id_cliente' => (string)$this->cliente, 'id_metodo_pago' => (string)$this->tarjeta, 'descuento' => '15000']]);
        $this->assertSame(0, $this->contar('ventas'), 'La venta debía rechazarse.');
    }

    private function seleccionado(string $html, string $campo, int $valor): bool {
        if (!preg_match('#<select name="' . $campo . '"[^>]*>(.*?)</select>#s', $html, $m)) $this->fail("No se encontró el selector {$campo}.");
        return (bool)preg_match('#<option value="' . $valor . '"[^>]*\bselected\b#', $m[1]);
    }

    public function testTrasUnRechazoSeConservanElClienteYElMetodoDePago(): void {
        $this->postRechazado();
        $html = $this->vista('GET');

        $this->assertTrue($this->seleccionado($html, 'id_cliente', $this->cliente), 'El cliente elegido sigue seleccionado.');
        $this->assertTrue($this->seleccionado($html, 'id_metodo_pago', $this->tarjeta), 'El método de pago elegido sigue seleccionado.');
        $this->assertStringContainsString('El descuento (15 %) supera el máximo permitido para tu rol (10 %).', $html);
        $this->assertStringContainsString('Producto conservado', $html, 'El carrito sigue con sus productos.');
    }

    public function testLaSeleccionConservadaSeUsaUnaSolaVez(): void {
        $this->postRechazado();
        $this->vista('GET');
        $html = $this->vista('GET'); // recarga posterior: vuelve a la pantalla normal

        $this->assertFalse($this->seleccionado($html, 'id_cliente', $this->cliente));
        $this->assertFalse($this->seleccionado($html, 'id_metodo_pago', $this->tarjeta));
    }

    public function testSinRechazoPrevioNoSeFuerzaNingunaSeleccion(): void {
        $html = $this->vista('GET', ['sesion' => $this->sesion]);
        $this->assertFalse($this->seleccionado($html, 'id_cliente', $this->cliente));
        $this->assertFalse($this->seleccionado($html, 'id_metodo_pago', $this->tarjeta));
    }

    public function testElClienteDeLaUrlSigueFuncionando(): void {
        $html = $this->vista('GET', ['sesion' => $this->sesion, 'get' => ['cliente' => (string)$this->cliente]]);
        $this->assertTrue($this->seleccionado($html, 'id_cliente', $this->cliente), '?cliente= (al volver de crear un cliente) se mantiene.');
    }
}
