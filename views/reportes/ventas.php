<?php
require __DIR__ . '/_inicio.php';
if ($exportar) {
    exportarCsv('ventas_' . $sufijo, ['Factura', 'Fecha', 'Cliente', 'Vendedor', 'Método de pago', 'Subtotal', 'Descuento', 'Impuesto', 'Total', 'Estado'],
        array_map(fn($v) => [$v['numero_factura'], $v['fecha_venta'], $v['cliente'], $v['vendedor'], $v['metodo'], numeroCsv($v['subtotal']), numeroCsv($v['descuento']), numeroCsv($v['impuesto']), numeroCsv($v['total']), $v['estado']], $rep->listadoVentas()));
}
$r = $rep->resumenVentas();
$serie = $rep->serieVentas();
$metodos = $rep->ventasPorMetodo();
$vendedores = $rep->ventasPorVendedor();
$titulo = 'Reporte de ventas';
require __DIR__ . '/../layouts/header.php';
cabeceraReporte($rep, 'ventas', $pestanas);
?>
<div class="stats">
  <div class="stat"><span class="stat-label">Total vendido</span><strong class="stat-value"><?= dinero($r['total']) ?></strong><span class="stat-detail"><?= (int)$r['ventas'] ?> ventas · <?= (int)$r['anuladas'] ?> anuladas</span></div>
  <div class="stat"><span class="stat-label">Ticket promedio</span><strong class="stat-value"><?= dinero($r['promedio']) ?></strong><span class="stat-detail">Descuentos: <?= dinero($r['descuentos']) ?></span></div>
  <div class="stat"><span class="stat-label">Devoluciones</span><strong class="stat-value"><?= dinero($r['devoluciones']) ?></strong><span class="stat-detail">Registradas en el periodo</span></div>
  <div class="stat"><span class="stat-label">Venta neta</span><strong class="stat-value"><?= dinero((float)$r['total'] - $r['devoluciones']) ?></strong><span class="stat-detail">Impuestos cobrados: <?= dinero($r['impuestos']) ?></span></div>
</div>
<div class="card reporte-card">
  <h2 class="card-title">Ventas por <?= $serie['mensual'] ? 'mes' : 'día' ?></h2>
  <?= graficoBarras($serie['puntos'], 'Ventas por ' . ($serie['mensual'] ? 'mes' : 'día')) ?>
</div>
<div class="reporte-2col">
  <div class="card reporte-card">
    <h2 class="card-title">Por método de pago</h2>
    <?php $totalM = array_sum(array_column($metodos, 'total')) ?: 1; ?>
    <?= barrasHorizontales(array_map(fn($m) => ['etiqueta' => $m['nombre'], 'valor' => $m['total'], 'texto' => dinero($m['total']) . ' · ' . round($m['total'] / $totalM * 100) . '%'], $metodos)) ?>
  </div>
  <div class="card reporte-card">
    <h2 class="card-title">Por vendedor</h2>
    <table class="table compacta"><thead><tr><th>Vendedor</th><th class="num">Ventas</th><th class="num">Promedio</th><th class="num">Total</th></tr></thead><tbody>
      <?php foreach ($vendedores as $v): ?><tr><td><?= e($v['nombre']) ?></td><td class="num"><?= (int)$v['ventas'] ?></td><td class="num"><?= dinero($v['promedio']) ?></td><td class="num"><strong><?= dinero($v['total']) ?></strong></td></tr><?php endforeach; ?>
      <?php if (!$vendedores): ?><tr><td colspan="4" class="empty">Sin ventas en este periodo.</td></tr><?php endif; ?>
    </tbody></table>
  </div>
</div>
<?php require __DIR__ . '/_fin.php'; ?>
