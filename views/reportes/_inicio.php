<?php
// Común a todos los reportes. Espera $reporteActual (clave de la pestaña) antes de incluirse.
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../helpers/permisos.php';
require_once __DIR__ . '/../../helpers/reportes.php';
require_once __DIR__ . '/../../controllers/ReporteController.php';
requirePermission('reportes.ver');
$rep = new ReporteController($_GET);
$exportar = ($_GET['exportar'] ?? '') === 'csv';
$sufijo = $rep->desde . '_a_' . $rep->hasta . '.csv';
$pestanas = ['ventas' => 'Ventas', 'ganancias' => 'Ganancias', 'productos' => 'Productos', 'caja' => 'Caja', 'inventario' => 'Inventario', 'devoluciones' => 'Devoluciones'];
// Imprime la cabecera: título, pestañas, filtro de fechas y botón de exportar.
function cabeceraReporte(ReporteController $rep, string $actual, array $pestanas, bool $conFechas = true): void {
    $q = http_build_query(['desde' => $rep->desde, 'hasta' => $rep->hasta]);
    $atajos = ['Hoy' => [date('Y-m-d'), date('Y-m-d')], 'Últimos 7 días' => [date('Y-m-d', strtotime('-6 days')), date('Y-m-d')],
        'Este mes' => [date('Y-m-01'), date('Y-m-d')], 'Mes anterior' => [date('Y-m-01', strtotime('first day of last month')), date('Y-m-t', strtotime('last month'))],
        'Este año' => [date('Y-01-01'), date('Y-m-d')]];
    ?>
    <div class="page-header"><h1>Reportes</h1><a class="btn btn-light" href="?<?= e($q) ?>&exportar=csv"><?= icono('reportes') ?> Exportar a Excel (CSV)</a></div>
    <nav class="tabs"><?php foreach ($pestanas as $k => $v): ?><a href="<?= $k ?>.php?<?= e($q) ?>" class="<?= $k === $actual ? 'active' : '' ?>"><?= $v ?></a><?php endforeach; ?></nav>
    <?php if ($conFechas): ?>
    <form class="filtros card" method="get">
      <label>Desde<input type="date" name="desde" value="<?= e($rep->desde) ?>"></label>
      <label>Hasta<input type="date" name="hasta" value="<?= e($rep->hasta) ?>"></label>
      <button class="btn">Ver</button>
      <div class="atajos"><?php foreach ($atajos as $etq => [$d, $h]): ?><a class="<?= $rep->desde === $d && $rep->hasta === $h ? 'active' : '' ?>" href="?desde=<?= $d ?>&hasta=<?= $h ?>"><?= $etq ?></a><?php endforeach; ?></div>
    </form>
    <?php endif;
}
