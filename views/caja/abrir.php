<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../helpers/permisos.php';
require_once __DIR__ . '/../../controllers/CajaController.php';
requirePermission('caja.abrir');
$controller = new CajaController();
if ($turno = $controller->miTurno()) { flash('error', 'Ya tienes abierta la caja "' . $turno['caja'] . '".'); redirect('index.php'); }
$errores = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarCsrf();
    [$id, $errores] = $controller->abrir($_POST);
    if (!$errores) { flash('success', 'Caja abierta. ¡Buen turno!'); redirect('index.php'); }
}
$disponibles = $controller->cajasDisponibles();
$titulo = 'Abrir caja';
require __DIR__ . '/../layouts/header.php';
?>
<h1>Abrir caja</h1>
<?php if ($errores): ?><div class="alert error"><ul><?php foreach ($errores as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<?php if (!$disponibles): ?>
  <div class="alert error">No hay cajas disponibles: todas están abiertas o inactivas.</div>
  <a class="btn btn-light" href="index.php">Volver</a>
<?php else: ?>
<form method="post" class="form-card">
  <?= csrfCampo() ?>
  <label>Caja
    <select name="id_caja" required>
      <?php foreach ($disponibles as $c): ?><option value="<?= (int)$c['id_caja'] ?>" <?= (int)($_POST['id_caja'] ?? 0) === (int)$c['id_caja'] ? 'selected' : '' ?>><?= e($c['nombre']) ?></option><?php endforeach; ?>
    </select>
  </label>
  <label><span>Base inicial <small>(efectivo con el que empiezas, para dar cambio)</small></span>
    <input type="number" name="monto_inicial" value="<?= e($_POST['monto_inicial'] ?? '') ?>" min="0" step="any" required autofocus></label>
  <label><span>Observaciones <small>(opcional)</small></span><textarea name="observaciones" rows="2" maxlength="1000"><?= e($_POST['observaciones'] ?? '') ?></textarea></label>
  <div class="form-actions"><a class="btn btn-light" href="index.php">Cancelar</a><button class="btn">Abrir caja</button></div>
</form>
<?php endif; ?>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
