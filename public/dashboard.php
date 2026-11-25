<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../helpers/permisos.php';
require_once __DIR__ . '/../helpers/funciones.php';
require_once __DIR__ . '/../helpers/factura.php';
require_once __DIR__ . '/../helpers/reportes.php';
require_once __DIR__ . '/../controllers/DashboardController.php';
requirePermission('dashboard.ver');
$titulo = 'Dashboard';
$ultimas = $topMes = [];
$stockBajo = null; // null = sin datos (el panel lo indica)
try {
    $dash = new DashboardController();
    $resumen = $dash->resumen();
    if (tienePermiso('ventas.ver')) {
        $ultimas = $dash->ultimasVentas();
        $topMes = $dash->masVendidosMes();
    }
    if (tienePermiso('productos.ver')) $stockBajo = $dash->stockBajo();
} catch (PDOException $e) {
    error_log('Dashboard: ' . $e->getMessage());
    $resumen = ['ventas' => null, 'cajas' => null, 'devoluciones' => null];
}
$meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
['ventas' => $ventas, 'cajas' => $cajas, 'devoluciones' => $devoluciones] = $resumen;
require __DIR__ . '/../views/layouts/header.php';
?>
<h1>Dashboard</h1><p class="subtitle">Resumen de hoy, <?= date('d/m/Y') ?></p>
<div class="stats">
  <?php if (tienePermiso('ventas.ver')): ?>
  <a class="stat" href="<?= APP_URL ?>/views/ventas/index.php">
    <span class="stat-label"><?= icono('ventas') ?>Total ventas hoy</span>
    <?php if ($ventas): ?>
      <strong class="stat-value"><?= dinero($ventas['total_hoy']) ?></strong>
      <span class="stat-detail"><?= (int)$ventas['cantidad_hoy'] ?> <?= (int)$ventas['cantidad_hoy'] === 1 ? 'venta' : 'ventas' ?> hoy · <?= dinero($ventas['total_mes']) ?> en el mes</span>
    <?php else: ?><strong class="stat-value">—</strong><span class="stat-detail">Sin datos disponibles</span><?php endif; ?>
  </a>
  <?php endif; ?>
  <?php if (tienePermiso('caja.ver')): ?>
  <a class="stat" href="<?= APP_URL ?>/views/caja/index.php">
    <span class="stat-label"><?= icono('caja') ?>Cajas abiertas</span>
    <?php if ($cajas): ?>
      <strong class="stat-value"><?= (int)$cajas['abiertas'] ?> <small>de <?= (int)$cajas['total'] ?></small></strong>
      <span class="stat-detail">Base inicial: <?= dinero($cajas['monto_inicial']) ?></span>
    <?php else: ?><strong class="stat-value">—</strong><span class="stat-detail">Sin datos disponibles</span><?php endif; ?>
  </a>
  <?php endif; ?>
  <?php if (tienePermiso('devoluciones.ver')): ?>
  <a class="stat" href="<?= APP_URL ?>/views/devoluciones/index.php">
    <span class="stat-label"><?= icono('devoluciones') ?>Total devoluciones hoy</span>
    <?php if ($devoluciones): ?>
      <strong class="stat-value"><?= dinero($devoluciones['total_hoy']) ?></strong>
      <span class="stat-detail"><?= (int)$devoluciones['cantidad_hoy'] ?> <?= (int)$devoluciones['cantidad_hoy'] === 1 ? 'devolución' : 'devoluciones' ?> hoy · <?= dinero($devoluciones['total_mes']) ?> en el mes</span>
    <?php else: ?><strong class="stat-value">—</strong><span class="stat-detail">Sin datos disponibles</span><?php endif; ?>
  </a>
  <?php endif; ?>
</div>

<?php if (tienePermiso('ventas.ver')): ?>
<div class="dash-paneles">
  <section class="card">
    <div class="card-header">
      <h2><?= (int)($_SESSION['rol_id'] ?? 0) === ROL_VENDEDOR ? 'Mis últimas ventas' : 'Últimas ventas' ?></h2>
      <a class="enlace-sec" href="<?= APP_URL ?>/views/ventas/index.php">Ver todas →</a>
    </div>
    <?php if ($ultimas): ?>
      <ul class="lista-ventas">
        <?php foreach ($ultimas as $v): $hoy = date('Y-m-d', strtotime($v['fecha_venta'])) === date('Y-m-d'); ?>
          <li class="<?= $v['estado'] === 'anulada' ? 'anulada' : '' ?>">
            <a href="<?= APP_URL ?>/views/ventas/detalle.php?id=<?= (int)$v['id_venta'] ?>">
              <span class="lv-info">
                <strong><?= e($v['numero_factura']) ?></strong>
                <small><?= $hoy ? 'Hoy ' . date('H:i', strtotime($v['fecha_venta'])) : date('d/m H:i', strtotime($v['fecha_venta'])) ?> · <?= e($v['cliente'] ?? 'Sin cliente') ?><?= $v['metodos'] ? ' · ' . e($v['metodos']) : '' ?></small>
              </span>
              <span class="lv-total"><?= $v['estado'] === 'anulada' ? '<span class="badge badge-danger">Anulada</span> ' : '' ?><?= dinero($v['total']) ?></span>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <div class="vacio-panel"><?= icono('ventas') ?><p>Aún no hay ventas registradas.</p>
        <?php if (tienePermiso('ventas.crear')): ?><a class="btn btn-sm" href="<?= APP_URL ?>/views/ventas/crear.php">Registrar la primera venta</a><?php endif; ?></div>
    <?php endif; ?>
  </section>

  <section class="card">
    <div class="card-header">
      <h2>Más vendidos de <?= $meses[(int)date('n') - 1] ?></h2>
      <?php if (tienePermiso('reportes.ver')): ?><a class="enlace-sec" href="<?= APP_URL ?>/views/reportes/productos.php">Ver reporte →</a><?php endif; ?>
    </div>
    <?php if ($topMes): ?>
      <?= barrasHorizontales(array_map(fn($p) => ['etiqueta' => $p['nombre'], 'valor' => $p['total'],
          'texto' => dinero($p['total']) . ' · ' . cantidad($p['unidades']) . ' ' . $p['unidad_medida']], $topMes)) ?>
    <?php else: ?>
      <div class="vacio-panel"><?= icono('productos') ?><p>Todavía no hay productos vendidos este mes.</p></div>
    <?php endif; ?>
  </section>
</div>
<script src="<?= APP_URL ?>/assets/js/reportes.js?v=<?= filemtime(__DIR__ . '/../assets/js/reportes.js') ?>" defer></script>
<?php endif; ?>

<?php if (tienePermiso('productos.ver')): // Panel "Stock bajo": después de los paneles existentes (FR-001) ?>
<section class="card panel-stock-bajo" id="panel-stock-bajo">
  <div class="card-header">
    <h2>Stock bajo</h2>
    <?php if ($stockBajo && $stockBajo['total'] > 10): ?><a class="enlace-sec" href="<?= APP_URL ?>/views/productos/stock.php">Ver todos (<?= (int)$stockBajo['total'] ?>) →</a><?php endif; ?>
  </div>
  <?php if ($stockBajo === null): ?>
    <div class="vacio-panel"><?= icono('productos') ?><p>Sin datos disponibles</p></div>
  <?php elseif (!$stockBajo['productos']): ?>
    <div class="vacio-panel"><?= icono('productos') ?><p>No hay productos con stock bajo.</p></div>
  <?php else: ?>
    <?php $puedeEditar = tienePermiso('productos.editar'); // el enlace solo para quien puede editar (FR-007) ?>
    <ul class="lista-stock">
      <?php foreach ($stockBajo['productos'] as $p): $agotado = (float)$p['stock'] <= 0; ?>
        <li class="<?= $agotado ? 'agotado' : '' ?>">
          <span class="ls-nombre"><?php if ($puedeEditar): ?><a href="<?= APP_URL ?>/views/productos/editar.php?id=<?= (int)$p['id_producto'] ?>"><?= e($p['nombre']) ?></a><?php else: ?><?= e($p['nombre']) ?><?php endif; ?><?php if ($agotado): ?> <span class="badge badge-danger">Agotado</span><?php endif; ?></span>
          <span class="ls-cantidades">Stock: <?= e(cantidad($p['stock']) . ' ' . $p['unidad_medida']) ?> · Mínimo: <?= e(cantidad($p['stock_minimo']) . ' ' . $p['unidad_medida']) ?></span>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>
<?php endif; ?>
<?php require __DIR__ . '/../views/layouts/footer.php'; ?>
