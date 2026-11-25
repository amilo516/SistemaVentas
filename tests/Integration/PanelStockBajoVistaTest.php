<?php
declare(strict_types=1);

/**
 * Panel "Stock bajo" en la vista real public/dashboard.php (specs/002-panel-stock-bajo: spec.md US1–US3,
 * FR-001..FR-010; contracts/panel-dashboard.md). Ejecuta la vista en procesos aparte con
 * tests/Integration/fixtures/ejecutar_vista.php y la sesión guardada en un archivo, como en el navegador.
 */
final class PanelStockBajoVistaTest extends BaseDatosTestCase {
    private const VISTA = 'public/dashboard.php';
    private string $rutaSesiones;
    private string $sesionId;

    protected function setUp(): void {
        parent::setUp();
        $this->rutaSesiones = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sv_sesiones_pruebas';
        if (!is_dir($this->rutaSesiones)) mkdir($this->rutaSesiones);
        $this->sesionId = 'panel' . bin2hex(random_bytes(8));
    }

    protected function tearDown(): void {
        @unlink($this->rutaSesiones . DIRECTORY_SEPARATOR . 'sess_' . $this->sesionId);
        parent::tearDown();
    }

    // ---------- Apoyo ----------

    private function producto(string $nombre, float $stock, float $minimo, float $precio = 12345): int {
        $id = $this->crearProducto($precio, $stock, $nombre);
        self::$db->prepare('UPDATE productos SET stock_minimo = :m WHERE id_producto = :id')->execute(['m' => $minimo, 'id' => $id]);
        return $id;
    }

    /** Ejecuta el dashboard en un proceso aparte. Con $sesionDe inicia la sesión de ese usuario; sin él, reutiliza la guardada. */
    private function dashboard(?int $sesionDe = null, bool $permitirErrores = false): array {
        $datos = ['vista' => self::VISTA, 'metodo' => 'GET', 'sesion_id' => $this->sesionId, 'ruta_sesiones' => $this->rutaSesiones];
        if ($sesionDe !== null) { $this->iniciarSesionComo($sesionDe); $datos['sesion'] = $_SESSION; }
        $cmd = [PHP_BINARY, __DIR__ . '/fixtures/ejecutar_vista.php', base64_encode(json_encode($datos, JSON_THROW_ON_ERROR))];
        $proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $tubos, null, array_merge(getenv(), ['DB_NAME' => self::BASE_PRUEBAS]));
        $this->assertIsResource($proc);
        $html = (string)stream_get_contents($tubos[1]);
        $errores = (string)stream_get_contents($tubos[2]);
        fclose($tubos[1]); fclose($tubos[2]);
        $this->assertSame(0, proc_close($proc), "El dashboard terminó con error: {$errores}");
        if (!$permitirErrores) $this->assertSame('', $errores, 'El dashboard no debe emitir avisos ni errores.');
        return [$html, $errores];
    }

    /** HTML de la sección del panel, o null si no está. */
    private function panel(string $html): ?string {
        return preg_match('#<section[^>]*id="panel-stock-bajo".*?</section>#s', $html, $m) ? $m[0] : null;
    }

    private function cajero(): int { return $this->crearUsuario('cajero1', ROL_CAJERO, 'Cajero Uno'); }

    // ---------- US1: visibilidad (FR-001, US1/AC6, AC8, SC-004) ----------

    public function testUnCajeroVeElPanel(): void {
        $this->producto('Arroz', 2, 5);
        [$html] = $this->dashboard($this->cajero());
        $this->assertNotNull($this->panel($html));
    }

    public function testSinPermisoDeProductosNoHayPanelNiDatosDeStock(): void {
        $rol = $this->crearRol('Solo dashboard', 10, ['dashboard.ver']);
        $this->producto('Arroz', 2, 5);
        [$html] = $this->dashboard($this->crearUsuario('mirón', $rol));
        $this->assertNull($this->panel($html));
        $this->assertStringNotContainsString('Arroz', $html);
        $this->assertStringNotContainsString('Mínimo:', $html);
    }

    public function testConProductosPeroSinVentasTambienHayPanel(): void {
        $rol = $this->crearRol('Bodega', 10, ['dashboard.ver', 'productos.ver']);
        $this->producto('Arroz', 2, 5);
        [$html] = $this->dashboard($this->crearUsuario('bodega1', $rol));
        $this->assertNotNull($this->panel($html));
        $this->assertStringNotContainsString('dash-paneles', $html, 'Este rol no ve los paneles de ventas.');
    }

    public function testElPanelVaDespuesDeLosPanelesExistentes(): void {
        $this->producto('Arroz', 2, 5);
        [$html] = $this->dashboard($this->cajero());
        $this->assertNotFalse(strpos($html, 'class="dash-paneles"'));
        $this->assertGreaterThan(strpos($html, 'class="dash-paneles"'), strpos($html, 'id="panel-stock-bajo"'));
    }

    // ---------- US1: contenido (FR-003, US1/AC1, AC7, caso límite de nombres) ----------

    public function testMuestraNombreStockYMinimoSinPrecioNiCodigo(): void {
        $id = $this->producto('Arroz', 2, 5, 98765);
        $codigo = (string)self::$db->query("SELECT codigo FROM productos WHERE id_producto = {$id}")->fetchColumn();
        $panel = $this->panel($this->dashboard($this->cajero())[0]);
        $this->assertStringContainsString('Arroz', $panel);
        $this->assertMatchesRegularExpression('#Stock:\s*2\s+unidad#u', $panel);
        $this->assertMatchesRegularExpression('#Mínimo:\s*5\s+unidad#u', $panel);
        $this->assertStringNotContainsString('98.765', $panel, 'No se muestra el precio.');
        $this->assertStringNotContainsString($codigo, $panel, 'No se muestra el código.');
    }

    public function testUnAgotadoLlevaElTextoAgotado(): void {
        $this->producto('Azúcar', 0, 5);
        $this->producto('Sal', 2, 5);
        $panel = $this->panel($this->dashboard($this->cajero())[0]);
        $this->assertSame(1, substr_count($panel, 'Agotado'));
        $this->assertLessThan(strpos($panel, 'Sal'), strpos($panel, 'Agotado'), 'El agotado va primero y lleva el texto.');
    }

    public function testLosNombresSeMuestranEscapados(): void {
        $this->producto('<b>Negrita</b>', 1, 5);
        $panel = $this->panel($this->dashboard($this->cajero())[0]);
        $this->assertStringContainsString('&lt;b&gt;Negrita&lt;/b&gt;', $panel);
        $this->assertStringNotContainsString('<b>Negrita</b>', $panel);
    }

    // ---------- US1: límite de 10 (FR-006) ----------

    public function testConDiezEnAlertaNoHayEnlaceVerTodos(): void {
        for ($i = 1; $i <= 10; $i++) $this->producto(sprintf('P %02d', $i), 1, 10);
        $panel = $this->panel($this->dashboard($this->cajero())[0]);
        $this->assertStringNotContainsString('Ver todos', $panel);
        $this->assertSame(10, substr_count($panel, 'Mínimo:'));
    }

    public function testConOnceEnAlertaMuestraDiezYVerTodos(): void {
        for ($i = 1; $i <= 11; $i++) $this->producto(sprintf('P %02d', $i), 1, 10);
        $panel = $this->panel($this->dashboard($this->cajero())[0]);
        $this->assertSame(10, substr_count($panel, 'Mínimo:'));
        $this->assertMatchesRegularExpression('#<a[^>]*href="[^"]*/views/productos/stock\.php"[^>]*>\s*Ver todos \(11\) →\s*</a>#u', $panel);
    }

    // ---------- US1: fallo de datos (FR-010) ----------

    public function testSiFallanLosDatosElPanelLoIndicaYElRestoSigue(): void {
        $this->producto('Arroz', 2, 5);
        $cajero = $this->cajero();
        self::$db->exec('RENAME TABLE productos TO productos_fuera');
        try {
            [$html, $errores] = $this->dashboard($cajero, true);
        } finally {
            self::$db->exec('RENAME TABLE productos_fuera TO productos');
        }
        $this->assertStringContainsString('Sin datos disponibles', (string)$this->panel($html));
        $this->assertStringContainsString('<h1>Dashboard</h1>', $html, 'El resto del dashboard se muestra.');
        $this->assertStringContainsString('Dashboard:', $errores, 'El fallo queda en el log.');
    }

    // ---------- US2: sin alertas (FR-005, FR-008, US2/AC1–AC2) ----------

    public function testSinAlertasMuestraElMensaje(): void {
        $this->producto('Arroz', 9, 5); // por encima de su mínimo
        $panel = $this->panel($this->dashboard($this->cajero())[0]);
        $this->assertNotNull($panel, 'El panel se muestra aunque no haya alertas.');
        $this->assertStringContainsString('No hay productos con stock bajo.', $panel);
        $this->assertStringNotContainsString('Ver todos', $panel);
        $this->assertStringNotContainsString('Mínimo:', $panel);
    }

    public function testTrasBajarAlMinimoLaSiguienteCargaLoMuestra(): void {
        $id = $this->producto('Arroz', 9, 5);
        $panel = $this->panel($this->dashboard($this->cajero())[0]);
        $this->assertStringContainsString('No hay productos con stock bajo.', $panel);

        self::$db->exec("UPDATE productos SET stock = 5 WHERE id_producto = {$id}");
        $panel = $this->panel($this->dashboard()[0]); // misma sesión
        $this->assertStringContainsString('Arroz', $panel);
        $this->assertStringNotContainsString('No hay productos con stock bajo.', $panel);
    }

    // ---------- T024: cambio de permisos con la sesión abierta (FR-008) ----------

    public function testQuitarElPermisoOcultaElPanelEnLaSiguienteCarga(): void {
        $this->producto('Arroz', 2, 5);
        [$html] = $this->dashboard($this->cajero());
        $this->assertNotNull($this->panel($html), 'Primera carga: el cajero ve el panel.');

        self::$db->exec("DELETE rp FROM rol_permisos rp JOIN permisos p ON p.id_permiso = rp.id_permiso
            WHERE rp.id_rol = " . ROL_CAJERO . " AND p.nombre = 'productos.ver'");
        $this->renovarVersionDePermisos();

        [$html] = $this->dashboard(); // misma sesión, sin volver a iniciarla
        $this->assertNull($this->panel($html));
        $this->assertStringNotContainsString('Arroz', $html);
    }

    public function testDarElPermisoDeEdicionMuestraLosEnlacesEnLaSiguienteCarga(): void {
        $id = $this->producto('Arroz', 2, 5);
        [$html] = $this->dashboard($this->cajero());
        $this->assertStringNotContainsString('views/productos/editar.php', (string)$this->panel($html));

        $this->asignarPermisos(ROL_CAJERO, ['productos.editar']);
        $this->renovarVersionDePermisos();

        $panel = (string)$this->panel($this->dashboard()[0]); // misma sesión, sin volver a iniciarla
        $this->assertStringContainsString("views/productos/editar.php?id={$id}", $panel);
    }

    // ---------- US3: enlace a la edición (FR-007, US3/AC1–AC2) ----------

    public function testElSupervisorVeCadaNombreEnlazadoASuEdicion(): void {
        $a = $this->producto('Arroz', 0, 5);
        $b = $this->producto('Sal', 2, 5);
        $panel = (string)$this->panel($this->dashboard($this->crearUsuario('super1', ROL_SUPERVISOR))[0]);
        $this->assertMatchesRegularExpression("#<a[^>]*href=\"[^\"]*/views/productos/editar\\.php\\?id={$a}\"[^>]*>\\s*Arroz\\s*</a>#u", $panel);
        $this->assertMatchesRegularExpression("#<a[^>]*href=\"[^\"]*/views/productos/editar\\.php\\?id={$b}\"[^>]*>\\s*Sal\\s*</a>#u", $panel);
    }

    public function testElAdministradorTambienVeLosEnlaces(): void {
        $id = $this->producto('Arroz', 2, 5);
        $panel = (string)$this->panel($this->dashboard(1)[0]);
        $this->assertStringContainsString("views/productos/editar.php?id={$id}", $panel);
    }

    public function testElCajeroVeLosNombresSinNingunEnlaceALaEdicion(): void {
        $this->producto('Arroz', 0, 5);
        $this->producto('Sal', 2, 5);
        $panel = (string)$this->panel($this->dashboard($this->cajero())[0]);
        $this->assertStringContainsString('Arroz', $panel);
        $this->assertStringContainsString('Sal', $panel);
        $this->assertStringNotContainsString('views/productos/editar.php', $panel);
    }

    /** Lo mismo que hace marcarCambioPermisos() al cambiar roles o permisos. */
    private function renovarVersionDePermisos(): void {
        self::$db->prepare("INSERT INTO configuracion (clave, valor, descripcion) VALUES ('permisos_version', :v, 'Control interno de cambios de permisos')
            ON DUPLICATE KEY UPDATE valor = VALUES(valor)")->execute(['v' => bin2hex(random_bytes(8))]);
    }
}
