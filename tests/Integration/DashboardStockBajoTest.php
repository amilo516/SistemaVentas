<?php
declare(strict_types=1);

require_once __DIR__ . '/../../controllers/DashboardController.php';
require_once __DIR__ . '/../../models/Producto.php';

/**
 * DashboardController::stockBajo() (specs/002-panel-stock-bajo: spec.md US1, FR-002, FR-004, FR-006, FR-010;
 * data-model.md "Resultado del panel"; research.md R2 y R4).
 */
final class DashboardStockBajoTest extends BaseDatosTestCase {
    /** Crea un producto con stock y mínimo dados. Devuelve su id. */
    private function producto(string $nombre, float $stock, float $minimo, bool $activo = true): int {
        $id = $this->crearProducto(1000, $stock, $nombre);
        self::$db->prepare('UPDATE productos SET stock_minimo = :m, estado = :e WHERE id_producto = :id')
            ->execute(['m' => $minimo, 'e' => $activo ? 1 : 0, 'id' => $id]);
        return $id;
    }

    private function panel(): ?array { return (new DashboardController())->stockBajo(); }

    private function nombres(): array { return array_column($this->panel()['productos'], 'nombre'); }

    public function testStockIgualAlMinimoEntra(): void {
        $this->producto('Igual', 5, 5);
        $this->assertSame(['Igual'], $this->nombres());
    }

    public function testPorEncimaDelMinimoEInactivosNoEntran(): void {
        $this->producto('Por encima', 6, 5);
        $this->producto('Inactivo', 0, 5, false);
        $this->assertSame(['productos' => [], 'total' => 0], $this->panel());
    }

    public function testProductoActivoDeCategoriaInactivaEntra(): void {
        $this->producto('De categoría inactiva', 1, 5);
        self::$db->exec("UPDATE categorias SET estado = 0 WHERE nombre = 'Pruebas'");
        $this->assertSame(['De categoría inactiva'], $this->nombres());
    }

    public function testOrdenaDelMasCriticoAlMenosCritico(): void {
        $this->producto('Alfa 40 de 50', 40, 50);
        $this->producto('Beta 1 de 10', 1, 10);
        $this->producto('Zeta agotado', 0, 5);
        $this->assertSame(['Zeta agotado', 'Beta 1 de 10', 'Alfa 40 de 50'], $this->nombres());
    }

    public function testDevuelveComoMaximoDiezYElTotalReal(): void {
        for ($i = 1; $i <= 12; $i++) $this->producto(sprintf('P %02d', $i), 1, 10);
        $panel = $this->panel();
        $this->assertCount(10, $panel['productos']);
        $this->assertSame(12, $panel['total']);
    }

    public function testConMasDe500EnAlertaIncluyeAlMasCritico(): void {
        for ($i = 1; $i <= 501; $i++) $this->producto(sprintf('A %04d', $i), 4, 5); // 80 %
        $this->producto('Zzz agotado', 0, 5); // último por nombre: listar() lo dejaría fuera con su corte de 500
        $panel = $this->panel();
        $this->assertSame(502, $panel['total']);
        $this->assertSame('Zzz agotado', $panel['productos'][0]['nombre']);
    }

    public function testElTotalCoincideConLaVistaCompleta(): void {
        $this->producto('Uno', 0, 5);
        $this->producto('Dos', 2, 5);
        $this->producto('Fuera', 9, 5);
        $modelo = new Producto(self::$db);
        $vistaCompleta = $modelo->listar(['estado' => '1', 'stock_bajo' => true]);
        $this->assertSame(count($vistaCompleta), $this->panel()['total']);
        $this->assertSame(2, $this->panel()['total']);
    }

    public function testDevuelveNullSiLaConsultaFalla(): void {
        $this->producto('Uno', 0, 5);
        $log = tempnam(sys_get_temp_dir(), 'dash');
        $anterior = ini_set('error_log', $log);
        self::$db->exec('RENAME TABLE productos TO productos_fuera');
        try {
            $panel = $this->panel();
        } finally {
            self::$db->exec('RENAME TABLE productos_fuera TO productos');
            ini_set('error_log', (string)$anterior);
        }
        $this->assertNull($panel);
        $this->assertStringContainsString('Dashboard:', (string)file_get_contents($log));
        @unlink($log);
    }
}
