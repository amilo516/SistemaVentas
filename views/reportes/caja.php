<?php
require __DIR__ . '/_inicio.php';
$turnos = $rep->turnosCaja();
if ($exportar) {
    exportarCsv('caja_' . $sufijo, ['Caja', 'Usuario', 'Apertura', 'Cierre', 'Base', 'Ventas en efectivo', 'Esperado', 'Contado', 'Diferencia', 'Estado'],
        array_map(fn($t) => [$t['caja'], $t['usuario'], $t['fecha_apertura'], $t['fecha_cierre'], numeroCsv($t['monto_inicial']), numeroCsv($t['ventas_efectivo']), numeroCsv($t['monto_esperado']), numeroCsv($t['monto_final']), numeroCsv($t['diferencia']), $t['estado']], $turnos));
}
$cerrados = array_filter($turnos, fn($t) => $t['estado'] === 'cerrada');
$faltantes = array_sum(array_map(fn($t) => min((float)$t['diferencia'], 0), $cerrados));
$sobrantes = array_sum(array_map(fn($t) => max((float)$t['diferencia'], 0), $cerrados));
$titulo = 'Reporte de caja';
require __DIR__ . '/../layouts/header.php';
cabeceraReporte($rep, 'caja', $pestanas);
?>
<div class="stats">
  <div class="stat"><span class="stat-label">Turnos</span><strong class="stat-value"><?= count($turnos) ?></strong><span class="stat-detail"><?= count($cerrados) ?> cerrados · <?= count($turnos) - count($cerrados) ?> abiertos</span></div>
  <div class="stat"><span class="stat-label">Ventas en efectivo</span><strong class="stat-value"><?= dinero(array_sum(array_column($turnos, 'ventas_efectivo'))) ?></strong></div>
  <div class="stat"><span class="stat-label">Faltantes</span><strong class="stat-value <?= $faltantes < 0 ? 'negativo' : '' ?>"><?= dinero(abs($faltantes)) ?></strong><span class="stat-detail"><?= count(array_filter($cerrados, fn($t) => (float)$t['diferencia'] < 0)) ?> turnos con faltante</span></div>
  <div class="stat"><span class="stat-label">Sobrantes</span><strong class="stat-value"><?= dinero($sobrantes) ?></strong><span class="stat-detail"><?= count(array_filter($cerrados, fn($t) => (float)$t['diferencia'] > 0)) ?> turnos con sobrante</span></div>
</div>
<div class="table-card">
<table class="table">
  <thead><tr><th>Caja</th><th>Usuario</th><th>Apertura</th><th>Cierre</th><th class="num">Base</th><th class="num">Ventas efectivo</th><th class="num">Esperado</th><th class="num">Contado</th><th>Diferencia</th></tr></thead>
  <tbody>
  <?php foreach ($turnos as $t): $dif = (float)$t['diferencia']; ?>
    <tr><td><?php if (tienePermiso('caja.ver')): ?><a href="../caja/movimientos.php?id=<?= (int)$t['id_apertura'] ?>"><?= e($t['caja']) ?></a><?php else: ?><?= e($t['caja']) ?><?php endif; ?></td>
      <td><?= e($t['usuario']) ?></td><td><?= date('d/m/Y H:i', strtotime($t['fecha_apertura'])) ?></td>
      <td><?= $t['fecha_cierre'] ? date('d/m/Y H:i', strtotime($t['fecha_cierre'])) : '<span class="badge badge-ok">Abierta</span>' ?></td>
      <td class="num"><?= dinero($t['monto_inicial']) ?></td><td class="num"><?= dinero($t['ventas_efectivo']) ?></td>
      <td class="num"><?= $t['monto_esperado'] !== null ? dinero($t['monto_esperado']) : '—' ?></td><td class="num"><?= $t['monto_final'] !== null ? dinero($t['monto_final']) : '—' ?></td>
      <td><?php if ($t['estado'] === 'cerrada'): ?><span class="badge <?= abs($dif) < 0.01 ? 'badge-ok' : ($dif > 0 ? 'badge-warn' : 'badge-danger') ?>"><?= abs($dif) < 0.01 ? 'Cuadrada' : ($dif > 0 ? 'Sobrante ' : 'Faltante ') . dinero(abs($dif)) ?></span><?php endif; ?></td></tr>
  <?php endforeach; ?>
  <?php if (!$turnos): ?><tr><td colspan="9" class="empty">No hubo turnos de caja en este periodo.</td></tr><?php endif; ?>
  </tbody>
</table>
</div>
<?php require __DIR__ . '/_fin.php'; ?>
