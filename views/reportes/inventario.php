<?php
require __DIR__ . '/_inicio.php';
if ($exportar) {
    exportarCsv('movimientos_inventario_' . $sufijo, ['Fecha', 'Código', 'Producto', 'Tipo', 'Stock anterior', 'Stock nuevo', 'Motivo', 'Referencia', 'Usuario'],
        array_map(fn($m) => [$m['fecha_movimiento'], $m['codigo'], $m['nombre'], $m['tipo'], numeroCsv($m['stock_anterior']), numeroCsv($m['stock_nuevo']), $m['motivo'], $m['referencia'], $m['usuario']], $rep->movimientosDetalle()));
}
$valor = $rep->valorInventario();
$movs = array_column($rep->movimientosPorTipo(), null, 'tipo');
$costo = array_sum(array_column($valor, 'costo'));
$venta = array_sum(array_column($valor, 'venta'));
$titulo = 'Reporte de inventario';
require __DIR__ . '/../layouts/header.php';
cabeceraReporte($rep, 'inventario', $pestanas);
?>
<div class="stats">
  <div class="stat"><span class="stat-label">Inventario al costo</span><strong class="stat-value"><?= dinero($costo) ?></strong><span class="stat-detail">Hoy, productos activos</span></div>
  <div class="stat"><span class="stat-label">Inventario a precio de venta</span><strong class="stat-value"><?= dinero($venta) ?></strong><span class="stat-detail">Ganancia potencial: <?= dinero($venta - $costo) ?></span></div>
  <div class="stat"><span class="stat-label">Productos con stock bajo</span><strong class="stat-value"><?= (int)array_sum(array_column($valor, 'bajos')) ?></strong><span class="stat-detail"><a href="../productos/stock.php">Ver detalle</a></span></div>
</div>
<div class="reporte-2col">
  <div class="card reporte-card">
    <h2 class="card-title">Valor al costo por categoría</h2>
    <?= barrasHorizontales(array_map(fn($c) => ['etiqueta' => $c['categoria'], 'valor' => $c['costo'], 'texto' => dinero($c['costo'])], $valor)) ?>
  </div>
  <div class="card reporte-card">
    <h2 class="card-title">Movimientos del periodo</h2>
    <table class="table compacta"><thead><tr><th>Tipo</th><th class="num">Movimientos</th><th class="num">Productos</th></tr></thead><tbody>
      <?php foreach (['entrada' => 'Entradas', 'salida' => 'Salidas (ventas y bajas)', 'ajuste' => 'Ajustes', 'devolucion' => 'Devoluciones'] as $k => $v): ?>
        <tr><td><?= $v ?></td><td class="num"><?= (int)($movs[$k]['movimientos'] ?? 0) ?></td><td class="num"><?= (int)($movs[$k]['productos'] ?? 0) ?></td></tr>
      <?php endforeach; ?>
    </tbody></table>
    <?php if (tienePermiso('inventario.ver')): ?><p class="nota"><a href="../inventario/index.php?desde=<?= e($rep->desde) ?>&hasta=<?= e($rep->hasta) ?>">Ver el kardex del periodo</a></p><?php endif; ?>
  </div>
</div>
<div class="table-card">
<table class="table">
  <thead><tr><th>Categoría</th><th class="num">Productos</th><th class="num">Stock bajo</th><th class="num">Valor al costo</th><th class="num">Valor de venta</th></tr></thead>
  <tbody>
  <?php foreach ($valor as $c): ?><tr><td><strong><?= e($c['categoria']) ?></strong></td><td class="num"><?= (int)$c['productos'] ?></td><td class="num"><?= (int)$c['bajos'] ?></td><td class="num"><?= dinero($c['costo']) ?></td><td class="num"><?= dinero($c['venta']) ?></td></tr><?php endforeach; ?>
  <?php if (!$valor): ?><tr><td colspan="5" class="empty">No hay productos activos.</td></tr><?php endif; ?>
  </tbody>
</table>
</div>
<?php require __DIR__ . '/_fin.php'; ?>
