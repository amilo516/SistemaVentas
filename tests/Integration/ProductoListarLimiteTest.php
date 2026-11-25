<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;

require_once __DIR__ . '/../../models/Producto.php';

/**
 * Filtro opcional 'limite' de Producto::listar() (specs/002-panel-stock-bajo: plan.md Complexity Tracking,
 * research.md R2). Sin 'limite', listar() debe comportarse exactamente como antes (máximo 500 por nombre).
 */
final class ProductoListarLimiteTest extends BaseDatosTestCase {
    private function modelo(): Producto { return new Producto(self::$db); }

    private function crearVarios(int $n, string $prefijo = 'P'): void {
        for ($i = 1; $i <= $n; $i++) $this->crearProducto(1000, 10, sprintf('%s %04d', $prefijo, $i));
    }

    public function testSinLimiteSigueDevolviendoComoMaximo500PorNombre(): void {
        $this->crearVarios(501);
        $filas = $this->modelo()->listar([]);
        $this->assertCount(500, $filas);
        $this->assertSame('P 0001', $filas[0]['nombre']);
        $this->assertSame('P 0500', $filas[499]['nombre']);
    }

    public function testRespetaElLimiteIndicado(): void {
        $this->crearVarios(5);
        $filas = $this->modelo()->listar(['limite' => 3]);
        $this->assertSame(['P 0001', 'P 0002', 'P 0003'], array_column($filas, 'nombre'));
    }

    public function testPermiteMasDe500ConLimiteMayor(): void {
        $this->crearVarios(501);
        $this->assertCount(501, $this->modelo()->listar(['limite' => 600]));
    }

    public static function limitesInvalidos(): array {
        return ['cero' => [0], 'negativo' => [-5], 'no numérico' => ['abc'], 'vacío' => ['']];
    }

    #[DataProvider('limitesInvalidos')]
    public function testUnLimiteInvalidoSeTrataComoUno(mixed $limite): void {
        $this->crearVarios(3);
        $this->assertCount(1, $this->modelo()->listar(['limite' => $limite]));
    }

    public function testFuncionaJuntoConEstadoYStockBajo(): void {
        $this->crearVarios(4);
        self::$db->exec("UPDATE productos SET stock_minimo = 20 WHERE nombre IN ('P 0001','P 0002','P 0003')"); // 3 en alerta
        self::$db->exec("UPDATE productos SET estado = 0 WHERE nombre = 'P 0002'");                           // uno inactivo
        $filas = $this->modelo()->listar(['estado' => '1', 'stock_bajo' => true, 'limite' => 10]);
        $this->assertSame(['P 0001', 'P 0003'], array_column($filas, 'nombre'));
        $this->assertCount(1, $this->modelo()->listar(['estado' => '1', 'stock_bajo' => true, 'limite' => 1]));
    }
}
