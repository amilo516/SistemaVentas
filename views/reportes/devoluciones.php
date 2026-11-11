<?php
require __DIR__ . '/_inicio.php';
if ($exportar) {
    exportarCsv('devoluciones_' . $sufijo, ['N.º', 'Fecha', 'Factura', 'Cliente', 'Registró', 'Motivo', 'Total', 'Reembolso'],
        array_map(fn($d) => ['DEV-' . str_pad((string)$d['id_devolucion'], 5, '0', STR_PAD_LEFT), $d['fecha_devolucion'], $d['numero_factura'], $d['cliente'], $d['usuario'], $d['motivo'], numeroCsv($d['total']), $d['observaciones']], $rep->listadoDevoluciones()));
}
$motivos = $rep->devolucionesPorMotivo();
$productos = $rep->devolucionesPorProducto();
$total = array_sum(array_column($motivos, 'total'));
$ventas = $rep->resumenVentas();
$titulo = 'Reporte de devoluciones';
require __DIR__ . '/../layouts/header.php';
cabeceraReporte($rep, 'devoluciones', $pestanas);
?>
<div class="stats">
  <div class="stat"><span class="stat-label">Total devuelto</span><strong class="stat-value"><?= dinero($total) ?></strong><span class="stat-detail"><?= (int)array_sum(array_column($motivos, 'devoluciones')) ?> devoluciones</span></div>
  <div class="stat"><span class="stat-label">% sobre lo vendido</span><strong class="stat-value"><?= (float)$ventas['total'] > 0 ? number_format($total / (float)$ventas['total'] * 100, 1, ',', '.') : '0' ?>%</strong><span class="stat-detail">Ventas del periodo: <?= dinero($ventas['total']) ?></span></div>
</div>
<div class="reporte-2col">
  <div class="card reporte-card">
    <h2 class="card-title">Por motivo</h2>
    <?= barrasHorizontales(array_map(fn($m) => ['etiqueta' => $m['motivo'], 'valor' => $m['total'], 'texto' => dinero($m['total']) . ' · ' . (int)$m['devoluciones'] . ((int)$m['devoluciones'] === 1 ? ' devolución' : ' devoluciones')], $motivos)) ?>
  </div>
  <div class="card reporte-card">
    <h2 class="card-title">Productos más devueltos</h2>
    <table class="table compacta"><thead><tr><th>Producto</th><th class="num">Unidades</th><th class="num">Total</th></tr></thead><tbody>
      <?php foreach (array_slice($productos, 0, 10) as $p): ?><tr><td><?= e($p['nombre']) ?></td><td class="num"><?= cantidad($p['unidades']) ?></td><td class="num"><?= dinero($p['total']) ?></td></tr><?php endforeach; ?>
      <?php if (!$productos): ?><tr><td colspan="3" class="empty">Sin devoluciones en este periodo.</td></tr><?php endif; ?>
    </tbody></table>
  </div>
</div>
<?php require __DIR__ . '/_fin.php'; ?>
