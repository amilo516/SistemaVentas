<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../helpers/descuentos.php';

/**
 * Validación del porcentaje máximo configurable (data-model.md Rol.descuento_maximo; contracts/regla-descuento.md;
 * spec.md FR-001, FR-003). Sin base de datos.
 */
final class NormalizarDescuentoMaximoTest extends TestCase {
    public static function validos(): array {
        return [
            'entero'                => ['5', 5.0],
            'coma decimal'          => ['7,5', 7.5],
            'punto decimal'         => ['7.5', 7.5],
            'dos decimales'         => ['10,25', 10.25],
            'cero'                  => ['0', 0.0],
            'cien'                  => ['100', 100.0],
            'cien con decimales 0'  => ['100,00', 100.0],
            'espacios alrededor'    => [' 10 ', 10.0],
            'número entero de PHP'  => [15, 15.0],
            'número float de PHP'   => [7.5, 7.5],
        ];
    }

    #[DataProvider('validos')]
    public function testAceptaValoresValidos(mixed $entrada, float $esperado): void {
        $this->assertSame($esperado, normalizarDescuentoMaximo($entrada));
    }

    public static function invalidos(): array {
        return [
            'negativo'                    => ['-1'],
            'mayor que 100'               => ['100.01'],
            'más de dos decimales'        => ['7,555'],
            'no numérico'                 => ['abc'],
            'vacío'                       => [''],
            'solo espacios'               => ['   '],
            'con signo de porcentaje'     => ['10 %'],
            'separador de miles'          => ['1.000,5'],
            'nulo'                        => [null],
            'arreglo'                     => [['10']],
            'booleano'                    => [true],
            'notación científica'         => ['1e1'],
        ];
    }

    #[DataProvider('invalidos')]
    public function testRechazaValoresInvalidosSinRedondear(mixed $entrada): void {
        $this->assertNull(normalizarDescuentoMaximo($entrada));
    }
}
