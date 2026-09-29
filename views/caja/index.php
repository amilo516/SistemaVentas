<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../helpers/permisos.php';
require_once __DIR__ . '/../../controllers/CajaController.php';
requirePermission('caja.ver');
$controller = new CajaController();
$gestionaCajas = tienePermiso('configuracion.gestionar');
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $gestionaCajas) {
    verificarCsrf();
    $error = match ($_POST['accion'] ?? '') {
        'estado' => $controller->cambiarEstadoCaja((int)($_POST['id'] ?? 0)),
        'eliminar' => $controller->eliminarCaja((int)($_POST['id'] ?? 0)),
        default => $controller->crearCaja((string)($_POST['nombre'] ?? '')),
    };
    $error ? flash('error', $error) : flash('success', ($_POST['accion'] ?? '') === 'eliminar' ? 'Caja eliminada.' : 'Cajas actualizadas.');
    redirect('index.php');
}
$turno = $controller->miTurno();
$cajas = $controller->cajas();
$historial = $controller->historial();
$titulo = 'Caja';
require __DIR__ . '/../layouts/header.php';
?>
<div class="page-header">
  <h1>Caja</h1>
  <?php if (!$turno && tienePermiso('caja.abrir')): ?><a class="btn" href="abrir.php">Abrir caja</a><?php endif; ?>
</div>

<?php if ($turno): ?>
  <div class="turno-actual">
    <div>
      <h2 class="section-title">Mi turno: <?= e($turno['caja']) ?> <span class="badge badge-ok">Abierta</span></h2>
      <p class="muted">Abierta el <?= date('d/m/Y \a \l\a\s H:i', strtotime($turno['fecha_apertura'])) ?></p>
      <p class="acciones">
        <a class="btn btn-light" href="movimientos.php?id=<?= (int)$turno['id_apertura'] ?>">Ver movimientos</a>
        <?php if (tienePermiso('caja.movimientos')): ?><a class="btn btn-light" href="movimientos.php?id=<?= (int)$turno['id_apertura'] ?>#nuevo">+ Ingreso / egreso</a><?php endif; ?>
        <?php if (tienePermiso('caja.cerrar')): ?><a class="btn" href="cerrar.php?id=<?= (int)$turno['id_apertura'] ?>">Cerrar caja</a><?php endif; ?>
      </p>
    </div>
    <?php require __DIR__ . '/_resumen.php'; ?>
  </div>
<?php elseif (tienePermiso('caja.abrir')): ?>
  <div class="card aviso-caja"><?= icono('caja') ?><div><strong>No tienes una caja abierta.</strong><br><span class="muted">Ábrela al iniciar tu turno para que el efectivo de tus ventas quede registrado.</span></div></div>
<?php endif; ?>

<h2 class="section-title">Cajas</h2>
<div class="<?= $gestionaCajas ? 'split split-right' : '' ?>">
  <div class="table-card">
  <table class="table">
    <thead><tr><th>Caja</th><th>Estado</th><th>Abierta por</th><th>Desde</th><?php if ($gestionaCajas): ?><th></th><?php endif; ?></tr></thead>
    <tbody>
    <?php foreach ($cajas as $c): ?>
      <tr class="<?= $c['estado'] ? '' : 'inactivo' ?>">
        <td><strong><?= e($c['nombre']) ?></strong></td>
        <td><?php if (!$c['estado']): ?><span class="badge badge-off">Inactiva</span><?php elseif ($c['id_apertura']): ?><span class="badge badge-ok">Abierta</span><?php else: ?><span class="badge">Cerrada</span><?php endif; ?></td>
        <td><?= e($c['usuario'] ?? '—') ?></td>
        <td><?= $c['fecha_apertura'] ? date('d/m/Y H:i', strtotime($c['fecha_apertura'])) : '—' ?></td>
        <?php if ($gestionaCajas): ?>
        <td class="acciones">
          <?php if ($c['id_apertura']): ?><a class="btn btn-light btn-sm" href="movimientos.php?id=<?= (int)$c['id_apertura'] ?>">Ver turno</a><?php endif; ?>
          <form method="post" onsubmit="return confirm('¿<?= $c['estado'] ? 'Desactivar' : 'Activar' ?> esta caja?')">
            <?= csrfCampo() ?><input type="hidden" name="accion" value="estado"><input type="hidden" name="id" value="<?= (int)$c['id_caja'] ?>">
            <button class="btn btn-sm <?= $c['estado'] ? 'btn-danger' : 'btn-light' ?>"><?= $c['estado'] ? 'Desactivar' : 'Activar' ?></button>
          </form>
          <?= botonEliminar('index.php', (int)$c['id_caja'], $c['nombre']) ?>
        </td>
        <?php endif; ?>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php if ($gestionaCajas): ?>
  <form method="post" class="form-card">
    <h2>Nueva caja</h2>
    <?= csrfCampo() ?>
    <label>Nombre<input type="text" name="nombre" maxlength="100" placeholder="Ej. Caja 2" required></label>
    <div class="form-actions"><button class="btn">Crear caja</button></div>
  </form>
  <?php endif; ?>
</div>

<h2 class="section-title"><?= $controller->veTodo() ? 'Turnos recientes' : 'Mis turnos recientes' ?></h2>
<div class="table-card">
<table class="table">
  <thead><tr><th>Caja</th><th>Usuario</th><th>Apertura</th><th>Cierre</th><th class="num">Base</th><th class="num">Esperado</th><th class="num">Contado</th><th>Diferencia</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($historial as $h): $dif = (float)$h['diferencia']; ?>
    <tr>
      <td><?= e($h['caja']) ?></td><td><?= e($h['usuario']) ?></td>
      <td><?= date('d/m/Y H:i', strtotime($h['fecha_apertura'])) ?></td>
      <td><?= $h['fecha_cierre'] ? date('d/m/Y H:i', strtotime($h['fecha_cierre'])) : '<span class="badge badge-ok">Abierta</span>' ?></td>
      <td class="num"><?= dinero($h['monto_inicial']) ?></td>
      <td class="num"><?= $h['monto_esperado'] !== null ? dinero($h['monto_esperado']) : '—' ?></td>
      <td class="num"><?= $h['monto_final'] !== null ? dinero($h['monto_final']) : '—' ?></td>
      <td><?php if ($h['estado'] === 'cerrada'): ?><span class="badge <?= abs($dif) < 0.01 ? 'badge-ok' : ($dif > 0 ? 'badge-warn' : 'badge-danger') ?>"><?= abs($dif) < 0.01 ? 'Cuadrada' : ($dif > 0 ? '+' : '−') . dinero(abs($dif)) ?></span><?php endif; ?></td>
      <td class="acciones"><a class="btn btn-light btn-sm" href="movimientos.php?id=<?= (int)$h['id_apertura'] ?>">Ver</a></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$historial): ?><tr><td colspan="9" class="empty">Aún no hay turnos de caja.</td></tr><?php endif; ?>
  </tbody>
</table>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
