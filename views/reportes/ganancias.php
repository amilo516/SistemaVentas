<?php
require __DIR__ . '/_inicio.php';
$productos = $rep->rendimientoProductos();
usort($productos, fn($a, $b) => $b['ganancia'] <=> $a['ganancia']);
if ($exportar) {
    exportarCsv('ganancias_' . $sufijo, ['Código', 'Producto', 'Categoría', 'Unidades netas', 'Unidades devueltas', 'Ingreso sin impuesto', 'Costo', 'Ganancia', 'Margen %'],
        array_map(fn($p) => [$p['codigo'], $p['nombre'], $p['categoria'], numeroCsv($p['unidades']), numeroCsv($p['devueltas']), numeroCsv($p['ingreso']), numeroCsv($p['costo']), numeroCsv($p['ganancia']), numeroCsv($p['margen'])], $productos));
}
$ingreso = array_sum(array_column($productos, 'ingreso'));
$costo = array_sum(array_column($productos, 'costo'));
$ganancia = $ingreso - $costo;
$categorias = [];
foreach ($productos as $p) {
    $categorias[$p['categoria']]['ingreso'] = ($categorias[$p['categoria']]['ingreso'] ?? 0) + $p['ingreso'];
    $categorias[$p['categoria']]['ganancia'] = ($categorias[$p['categoria']]['ganancia'] ?? 0) + $p['ganancia'];
}
uasort($categorias, fn($a, $b) => $b['ganancia'] <=> $a['ganancia']);
$titulo = 'Reporte de ganancias';
require __DIR__ . '/../layouts/header.php';
cabeceraReporte($rep, 'ganancias', $pestanas);
?>
<div class="stats">
  <div class="stat"><span class="stat-label">Ingresos</span><strong class="stat-value"><?= dinero($ingreso) ?></strong><span class="stat-detail">Sin impuesto, con descuentos y devoluciones</span></div>
  <div class="stat"><span class="stat-label">Costo de lo vendido</span><strong class="stat-value"><?= dinero($costo) ?></strong></div>
  <div class="stat"><span class="stat-label">Ganancia bruta</span><strong class="stat-value <?= $ganancia < 0 ? 'negativo' : '' ?>"><?= dinero($ganancia) ?></strong><span class="stat-detail">Margen: <?= $ingreso > 0 ? number_format($ganancia / $ingreso * 100, 1, ',', '.') : '0' ?>%</span></div>
</div>
<p class="nota">El costo se calcula con el <strong>precio de compra actual</strong> de cada producto.</p>
<div class="reporte-2col">
  <div class="card reporte-card">
    <h2 class="card-title">Ganancia por categoría</h2>
    <?= barrasHorizontales(array_map(fn($k, $c) => ['etiqueta' => $k, 'valor' => $c['ganancia'], 'texto' => dinero($c['ganancia']) . ($c['ingreso'] > 0 ? ' · ' . round($c['ganancia'] / $c['ingreso'] * 100) . '%' : '')], array_keys($categorias), $categorias)) ?>
  </div>
  <div class="card reporte-card">
    <h2 class="card-title">Productos más rentables</h2>
    <?= barrasHorizontales(array_map(fn($p) => ['etiqueta' => $p['nombre'], 'valor' => $p['ganancia'], 'texto' => dinero($p['ganancia'])], array_slice($productos, 0, 8))) ?>
  </div>
</div>
<div class="table-card">
<table class="table">
  <thead><tr><th>Producto</th><th>Categoría</th><th class="num">Unidades</th><th class="num">Ingreso</th><th class="num">Costo</th><th class="num">Ganancia</th><th class="num">Margen</th></tr></thead>
  <tbody>
  <?php foreach ($productos as $p): ?>
    <tr><td><strong><?= e($p['nombre']) ?></strong><br><small class="muted"><?= e($p['codigo']) ?></small></td><td><?= e($p['categoria']) ?></td>
      <td class="num"><?= cantidad($p['unidades']) ?><?= $p['devueltas'] > 0 ? '<br><small class="muted">' . cantidad($p['devueltas']) . ' devueltas</small>' : '' ?></td>
      <td class="num"><?= dinero($p['ingreso']) ?></td><td class="num"><?= dinero($p['costo']) ?></td>
      <td class="num <?= $p['ganancia'] < 0 ? 'negativo' : '' ?>"><strong><?= dinero($p['ganancia']) ?></strong></td>
      <td class="num"><?= number_format($p['margen'], 1, ',', '.') ?>%</td></tr>
  <?php endforeach; ?>
  <?php if (!$productos): ?><tr><td colspan="7" class="empty">Sin ventas en este periodo.</td></tr><?php endif; ?>
  </tbody>
</table>
</div>
<?php require __DIR__ . '/_fin.php'; ?>
