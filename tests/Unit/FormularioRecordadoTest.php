<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../helpers/funciones.php';

/**
 * recordarFormulario() / tomarFormulario(): datos de un formulario que se vuelven a mostrar una sola vez
 * después de redirigir, igual que flash() (T035). Solo usan $_SESSION.
 */
final class FormularioRecordadoTest extends TestCase {
    protected function setUp(): void { $_SESSION = []; }

    public function testDevuelveLoRecordadoUnaSolaVez(): void {
        recordarFormulario('venta', ['id_cliente' => 5, 'id_metodo_pago' => 2]);
        $this->assertSame(['id_cliente' => 5, 'id_metodo_pago' => 2], tomarFormulario('venta'));
        $this->assertSame([], tomarFormulario('venta'));
    }

    public function testSinNadaRecordadoDevuelveVacio(): void {
        $this->assertSame([], tomarFormulario('venta'));
    }

    public function testCadaFormularioTieneSuPropiaClave(): void {
        recordarFormulario('venta', ['id_cliente' => 5]);
        recordarFormulario('otro', ['x' => 1]);
        $this->assertSame(['x' => 1], tomarFormulario('otro'));
        $this->assertSame(['id_cliente' => 5], tomarFormulario('venta'));
    }

    public function testIgnoraDatosCorruptosEnLaSesion(): void {
        $_SESSION['formulario'] = ['venta' => 'no es un arreglo'];
        $this->assertSame([], tomarFormulario('venta'));
    }
}
