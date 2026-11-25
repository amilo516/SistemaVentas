<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../helpers/productos.php';

/**
 * ordenarPorCriticidad() (specs/002-panel-stock-bajo: contracts/orden-criticidad.md, spec.md FR-004).
 * Sin base de datos. Las filas imitan lo que devuelve PDO: números como texto.
 */
final class OrdenCriticidadTest extends TestCase {
    private static function p(int $id, string $nombre, string $stock, string $minimo): array {
        return ['id_producto' => $id, 'nombre' => $nombre, 'stock' => $stock, 'stock_minimo' => $minimo];
    }

    private static function nombres(array $filas): array { return array_column($filas, 'nombre'); }

    /** Filas de "Casos mínimos de prueba" del contrato. */
    public static function casosDelContrato(): array {
        return [
            'proporción menor primero'      => [[self::p(1, 'A', '40', '50'), self::p(2, 'B', '1', '10')], ['B', 'A']],
            'agotado antes que el resto'    => [[self::p(1, 'A', '5', '5'), self::p(2, 'B', '0', '5')], ['B', 'A']],
            'negativo antes que 0'          => [[self::p(1, 'A', '0', '5'), self::p(2, 'B', '-2', '5')], ['B', 'A']],
            'agotado con mínimo 0'          => [[self::p(1, 'B', '1', '10'), self::p(2, 'A', '0', '0')], ['A', 'B']],
            'misma proporción: por nombre'  => [[self::p(1, 'B', '2', '20'), self::p(2, 'A', '1', '10')], ['A', 'B']],
            'nombre sin mayúsculas'         => [[self::p(1, 'Beta', '1', '10'), self::p(2, 'alfa', '1', '10')], ['alfa', 'Beta']],
            'decimales'                     => [[self::p(1, 'B', '1.000', '2.000'), self::p(2, 'A', '0.500', '2.000')], ['A', 'B']],
        ];
    }

    #[DataProvider('casosDelContrato')]
    public function testCasosDelContrato(array $entrada, array $esperado): void {
        $this->assertSame($esperado, self::nombres(ordenarPorCriticidad($entrada)));
    }

    public function testListaVacia(): void {
        $this->assertSame([], ordenarPorCriticidad([]));
    }

    public function testReindexaLaSalida(): void {
        $salida = ordenarPorCriticidad([5 => self::p(1, 'B', '4', '5'), 9 => self::p(2, 'A', '0', '5')]);
        $this->assertSame([0, 1], array_keys($salida));
        $this->assertSame(['A', 'B'], self::nombres($salida));
    }

    public function testNoModificaLaEntrada(): void {
        $entrada = [self::p(1, 'B', '4', '5'), self::p(2, 'A', '0', '5')];
        $copia = $entrada;
        ordenarPorCriticidad($entrada);
        $this->assertSame($copia, $entrada);
    }

    public function testEntreAgotadosSoloCuentaElStock(): void {
        // 0 de 100 y 0 de 1 empatan (FR-004, intencional): se ordenan por nombre.
        $salida = ordenarPorCriticidad([self::p(1, 'Bolsas', '0', '100'), self::p(2, 'Agujas', '0', '1')]);
        $this->assertSame(['Agujas', 'Bolsas'], self::nombres($salida));
    }

    public function testLasTildesSeComparanComoTexto(): void {
        // FR-004: sin distinguir mayúsculas; las tildes no se ignoran ("Ácido" no se iguala a "acido").
        $salida = ordenarPorCriticidad([self::p(1, 'Ácido', '1', '10'), self::p(2, 'acido', '1', '10')]);
        $this->assertSame(['acido', 'Ácido'], self::nombres($salida));
    }

    public function testDesempateFinalPorOrdenDeAlta(): void {
        $salida = ordenarPorCriticidad([self::p(7, 'Igual', '1', '10'), self::p(3, 'igual', '1', '10')]);
        $this->assertSame([3, 7], array_column($salida, 'id_producto'));
    }

    public function testOrdenCompletoDelEscenarioIndependiente(): void {
        // spec.md US1, prueba independiente: 0/5 → 1/10 → 40/50.
        $salida = ordenarPorCriticidad([self::p(1, 'C', '40', '50'), self::p(2, 'B', '1', '10'), self::p(3, 'A', '0', '5')]);
        $this->assertSame(['A', 'B', 'C'], self::nombres($salida));
    }
}
