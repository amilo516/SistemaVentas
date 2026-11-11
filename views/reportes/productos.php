<?php
require __DIR__ . '/_inicio.php';
$productos = $rep->rendimientoProductos();
usort($productos, fn($a, $b) => $b['ingreso'] <=> $a['ingreso']);
$sinVentas = $rep->productosSinVentas();
if ($exportar) {
    exportarCsv('productos_' . $sufijo, ['Código', 'Producto', 'Categoría', 'Unidades vendidas', 'Unidad', 'Ingreso sin impuesto'],
        array_map(fn($p) => [$p['codigo'], $p['nombre'], $p['categoria'], numeroCsv($p['unidades']), $p['unidad_medida'], numeroCsv($p['ingreso'])], $productos));
}
$titulo = 'Reporte de productos';
require __DIR__ . '/../layouts/header.php';
cabeceraReporte($rep, 'productos', $pestanas);
?>
<div class="reporte-2col">
  <div class="card reporte-card">
    <h2 class="card-title">Más vendidos por ingreso</h2>
    <?= barrasHorizontales(array_map(fn($p) => ['etiqueta' => $p['nombre'], 'valor' => $p['ingreso'], 'texto' => dinero($p['ingreso'])], array_slice($productos, 0, 10))) ?>
  </div>
  <div class="card reporte-card">
    <h2 class="card-title">Más vendidos por unidades</h2>
    <?php $porUnidades = $productos; usort($porUnidades, fn($a, $b) => $b['unidades'] <=> $a['unidades']); ?>
    <?= barrasHorizontales(array_map(fn($p) => ['etiqueta' => $p['nombre'], 'valor' => $p['unidades'], 'texto' => cantidad($p['unidades']) . ' ' . $p['unidad_medida']], array_slice($porUnidades, 0, 10))) ?>
  </div>
</div>
<h2 class="section-title">Productos activos sin ventas en el periodo <small class="muted">(<?= count($sinVentas) ?>)</small></h2>
<div class="table-card">
<table class="table">
  <thead><tr><th>Código</th><th>Producto</th><th>Categoría</th><th class="num">Stock</th><th class="num">Precio</th></tr></thead>
  <tbody>
  <?php foreach ($sinVentas as $p): ?><tr><td><?= e($p['codigo']) ?></td><td><?= e($p['nombre']) ?></td><td><?= e($p['categoria']) ?></td><td class="num"><?= cantidad($p['stock']) ?> <?= e($p['unidad_medida']) ?></td><td class="num"><?= dinero($p['precio_venta']) ?></td></tr><?php endforeach; ?>
  <?php if (!$sinVentas): ?><tr><td colspan="5" class="empty">Todos los productos activos tuvieron ventas.</td></tr><?php endif; ?>
  </tbody>
</table>
</div>
<?php require __DIR__ . '/_fin.php'; ?>
