<?php
// Resumen de efectivo de un turno. Espera $turno (con 'totales' y 'esperado').
$cerrada = $turno['estado'] === 'cerrada';
$esperado = $cerrada ? (float)$turno['monto_esperado'] : $turno['esperado'];
?>
<div class="card">
  <dl class="totales cuadre">
    <dt>Base inicial</dt><dd><?= dinero($turno['monto_inicial']) ?></dd>
    <dt>+ Ventas en efectivo</dt><dd><?= dinero($turno['totales']['venta']) ?></dd>
    <dt>+ Ingresos</dt><dd><?= dinero($turno['totales']['ingreso']) ?></dd>
    <dt>− Egresos</dt><dd><?= dinero($turno['totales']['egreso']) ?></dd>
    <?php if ($turno['totales']['devolucion'] > 0): ?><dt>− Devoluciones</dt><dd><?= dinero($turno['totales']['devolucion']) ?></dd><?php endif; ?>
    <dt class="total">Efectivo esperado</dt><dd class="total"><?= dinero($esperado) ?></dd>
    <?php if ($cerrada): $dif = (float)$turno['diferencia']; ?>
      <dt>Efectivo contado</dt><dd><?= dinero($turno['monto_final']) ?></dd>
      <dt>Diferencia</dt><dd><span class="badge <?= abs($dif) < 0.01 ? 'badge-ok' : ($dif > 0 ? 'badge-warn' : 'badge-danger') ?>"><?= abs($dif) < 0.01 ? 'Cuadrada' : ($dif > 0 ? 'Sobrante ' : 'Faltante ') . dinero(abs($dif)) ?></span></dd>
    <?php endif; ?>
  </dl>
</div>
