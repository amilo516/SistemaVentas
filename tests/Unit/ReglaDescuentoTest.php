<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../helpers/descuentos.php';

/**
 * Regla pura del descuento máximo (contracts/regla-descuento.md). Sin base de datos ni sesión.
 */
final class ReglaDescuentoTest extends TestCase {
    /** Filas de "Casos mínimos de prueba" del contrato: [subtotal, descuento, máximo, ¿lanza?]. */
    public static function casosDelContrato(): array {
        return [
            '15 % con límite 10 %'          => [100000, 15000, 10, true],
            'igual al límite (inclusivo)'   => [100000, 10000, 10, false],
            '10,01 % con límite 10 %'       => [100000, 10010, 10, true],
            'sin descuento y máximo 0 %'    => [100000, 0, 0, false],
            '1 peso con máximo 0 %'         => [100000, 1, 0, true],
            'Administrador (sin límite)'    => [100000, 99999, null, false],
            'subtotal 0'                    => [0, 0, 10, false],
            'subtotal 0 con descuento (lo decide FR-015)' => [0, 500, 10, false],
            '9,99999 % con límite 10 %'     => [33333.33, 3333.33, 10, false],
            '7,5 % exacto con límite 7,5 %' => [1000, 75, 7.5, false],
            '7,501 % con límite 7,5 %'      => [1000, 75.01, 7.5, true],
            '10,004 % con límite 10 %'      => [100000, 10004, 10, true],
            '15 % con límite 20 %'          => [100000, 15000, 20, false],
            // T043: rol normal con máximo 100 % (caso límite de spec.md)
            'máximo 100: igual al subtotal' => [1000, 1000, 100, false],
            'máximo 100: 0,01 más'          => [1000, 1000.01, 100, true],
        ];
    }

    #[DataProvider('casosDelContrato')]
    public function testCasosDelContrato(float $subtotal, float $descuento, ?float $maximo, bool $lanza): void {
        if ($lanza) $this->expectException(DescuentoExcedidoException::class);
        validarDescuentoMaximo($subtotal, $descuento, $maximo);
        $this->assertFalse($lanza, 'Debía lanzar DescuentoExcedidoException.');
    }

    public function testLaExcepcionLlevaLosDatosDelRechazo(): void {
        try {
            validarDescuentoMaximo(100000, 15000, 10, 'Cajero');
            $this->fail('Debía lanzar DescuentoExcedidoException.');
        } catch (DescuentoExcedidoException $e) {
            $this->assertInstanceOf(RuntimeException::class, $e);
            $this->assertSame(100000.0, $e->subtotal);
            $this->assertSame(15000.0, $e->descuento);
            $this->assertSame(15.0, $e->porcentaje);
            $this->assertSame(10.0, $e->maximo);
            $this->assertSame('Cajero', $e->rol);
            $this->assertSame('El descuento (15 %) supera el máximo permitido para tu rol (10 %).', $e->getMessage());
        }
    }

    public function testElMensajeNuncaMuestraUnRechazoIgualAlLimite(): void {
        try {
            validarDescuentoMaximo(100000, 10004, 10);
            $this->fail('Debía lanzar DescuentoExcedidoException.');
        } catch (DescuentoExcedidoException $e) {
            $this->assertSame(10.01, $e->porcentaje);
            $this->assertSame('El descuento (10,01 %) supera el máximo permitido para tu rol (10 %).', $e->getMessage());
        }
    }

    public function testElMensajeUsaComaDecimalEnElMaximo(): void {
        try {
            validarDescuentoMaximo(1000, 75.01, 7.5);
            $this->fail('Debía lanzar DescuentoExcedidoException.');
        } catch (DescuentoExcedidoException $e) {
            $this->assertSame('El descuento (7,51 %) supera el máximo permitido para tu rol (7,5 %).', $e->getMessage());
        }
    }

    /** [subtotal, descuento, porcentaje mostrado] — redondeo hacia arriba calculado en enteros. */
    public static function porcentajesMostrados(): array {
        return [
            '15 % exacto (sin error de float)' => [100000, 15000, 15.0],
            '10 % exacto'                      => [100000, 10000, 10.0],
            '10,004 % sube a 10,01'            => [100000, 10004, 10.01],
            '10,01 %'                          => [100000, 10010, 10.01],
            '7,5 %'                            => [1000, 75, 7.5],
            '1 peso sobre 100.000 = 0,01'      => [100000, 1, 0.01],
            '9,99999 % sube a 10'              => [33333.33, 3333.33, 10.0],
            'subtotal 0'                       => [0, 0, 0.0],
            'sin descuento'                    => [100000, 0, 0.0],
        ];
    }

    #[DataProvider('porcentajesMostrados')]
    public function testPorcentajeDescuento(float $subtotal, float $descuento, float $esperado): void {
        $this->assertSame($esperado, porcentajeDescuento($subtotal, $descuento));
    }

    public static function formatos(): array {
        return [
            'entero'          => [15.0, '15'],
            'un decimal'      => [7.5, '7,5'],
            'dos decimales'   => [10.01, '10,01'],
            'cien'            => [100.0, '100'],
            'cero'            => [0.0, '0'],
            'sin miles'       => [1234.5, '1234,5'],
        ];
    }

    #[DataProvider('formatos')]
    public function testFormatearPorcentaje(float $valor, string $esperado): void {
        $this->assertSame($esperado, formatearPorcentaje($valor));
    }
}
