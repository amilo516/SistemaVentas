<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../helpers/permisos.php';
require_once __DIR__ . '/../../controllers/CajaController.php';
requirePermission('caja.ver');
$controller = new CajaController();
$id = (int)($_GET['id'] ?? 0);
$errores = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requirePermission('caja.movimientos');
    verificarCsrf();
    $errores = $controller->registrarMovimiento($id, $_POST);
    if (!$errores) { flash('success', 'Movimiento registrado.'); redirect('movimientos.php?id=' . $id); }
}
$turno = $controller->ver($id);
if (!$turno) { flash('error', 'El turno de caja no existe.'); redirect('index.php'); }
$puedeOperar = $controller->puedeOperar($turno);
$etiquetas = ['venta' => ['Venta', 'badge-ok'], 'ingreso' => ['Ingreso', 'badge'], 'egreso' => ['Egreso', 'badge-danger'], 'devolucion' => ['Devolución', 'badge-warn']];
$titulo = 'Turno de caja';
require __DIR__ . '/../layouts/header.php';
?>
<div class="page-header">
  <h1><?= e($turno['caja']) ?> <span class="badge <?= $turno['estado'] === 'abierta' ? 'badge-ok' : 'badge-off' ?>"><?= ucfirst($turno['estado']) ?></span></h1>
  <div class="acciones">
    <a class="btn btn-light" href="index.php">Volver a caja</a>
    <?php if ($puedeOperar && tienePermiso('caja.cerrar')): ?><a class="btn" href="cerrar.php?id=<?= (int)$turno['id_apertura'] ?>">Cerrar caja</a><?php endif; ?>
  </div>
</div>
<p class="muted">Turno de <?= e($turno['usuario']) ?> · abierto el <?= date('d/m/Y H:i', strtotime($turno['fecha_apertura'])) ?><?= $turno['fecha_cierre'] ? ' · cerrado el ' . date('d/m/Y H:i', strtotime($turno['fecha_cierre'])) : '' ?></p>

<?php if ($errores): ?><div class="alert error"><ul><?php foreach ($errores as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<div class="detalle-grid">
  <div class="stack">
    <?php require __DIR__ . '/_resumen.php'; ?>
    <?php if ($turno['ventas_por_metodo']): ?>
    <div class="card">
      <h3 class="card-title">Ventas del turno por método</h3>
      <dl class="totales">
        <?php foreach ($turno['ventas_por_metodo'] as $m): ?><dt><?= e($m['metodo']) ?> <small class="muted">(<?= (int)$m['cantidad'] ?>)</small></dt><dd><?= dinero($m['total']) ?></dd><?php endforeach; ?>
        <dt class="total">Total vendido</dt><dd class="total"><?= dinero(array_sum(array_column($turno['ventas_por_metodo'], 'total'))) ?></dd>
      </dl>
    </div>
    <?php endif; ?>
    <?php if ($turno['observaciones']): ?><div class="card"><h3 class="card-title">Observaciones</h3><p class="pre"><?= e($turno['observaciones']) ?></p></div><?php endif; ?>
  </div>
  <div class="stack">
    <?php if ($puedeOperar && tienePermiso('caja.movimientos')): ?>
    <form method="post" class="card form-inline" id="nuevo">
      <h3 class="card-title">Registrar ingreso o egreso</h3>
      <?= csrfCampo() ?>
      <div class="fila">
        <label>Tipo<select name="tipo" required>
          <option value="egreso" <?= ($_POST['tipo'] ?? '') === 'egreso' ? 'selected' : '' ?>>Egreso (sale dinero)</option>
          <option value="ingreso" <?= ($_POST['tipo'] ?? '') === 'ingreso' ? 'selected' : '' ?>>Ingreso (entra dinero)</option>
        </select></label>
        <label class="grow">Concepto<input type="text" name="concepto" value="<?= e($_POST['concepto'] ?? '') ?>" maxlength="255" placeholder="Ej. pago de domicilio, compra de bolsas" required></label>
        <label>Monto<input type="number" name="monto" value="<?= e($_POST['monto'] ?? '') ?>" min="0.01" step="any" required></label>
        <button class="btn">Registrar</button>
      </div>
    </form>
    <?php endif; ?>
    <div class="table-card">
    <table class="table">
      <thead><tr><th>Fecha</th><th>Tipo</th><th>Concepto</th><th>Usuario</th><th class="num">Monto</th></tr></thead>
      <tbody>
      <?php foreach ($turno['movimientos'] as $m): [$etq, $cls] = $etiquetas[$m['tipo']]; $sale = in_array($m['tipo'], ['egreso', 'devolucion'], true); ?>
        <tr>
          <td><?= date('d/m H:i', strtotime($m['fecha'])) ?></td>
          <td><span class="badge <?= $cls ?>"><?= $etq ?></span></td>
          <td class="wrap"><?php if ($m['id_venta'] && tienePermiso('ventas.ver')): ?><a href="../ventas/detalle.php?id=<?= (int)$m['id_venta'] ?>"><?= e($m['concepto']) ?></a><?php else: ?><?= e($m['concepto']) ?><?php endif; ?></td>
          <td><?= e($m['usuario']) ?></td>
          <td class="num <?= $sale ? 'negativo' : 'positivo' ?>"><?= $sale ? '−' : '+' ?><?= dinero($m['monto']) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$turno['movimientos']): ?><tr><td colspan="5" class="empty">Todavía no hay movimientos en este turno.</td></tr><?php endif; ?>
      </tbody>
    </table>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
