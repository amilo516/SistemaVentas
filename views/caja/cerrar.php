<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../helpers/permisos.php';
require_once __DIR__ . '/../../controllers/CajaController.php';
requirePermission('caja.cerrar');
$controller = new CajaController();
$id = (int)($_GET['id'] ?? 0);
$errores = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarCsrf();
    $errores = $controller->cerrar($id, $_POST);
    if (!$errores) { flash('success', 'Caja cerrada.'); redirect('movimientos.php?id=' . $id); }
}
$turno = $controller->ver($id);
if (!$turno || !$controller->puedeOperar($turno)) { flash('error', 'Esta caja no está abierta o no te pertenece.'); redirect('index.php'); }
$titulo = 'Cerrar caja';
require __DIR__ . '/../layouts/header.php';
?>
<h1>Cerrar <?= e($turno['caja']) ?></h1>
<p class="muted">Turno de <?= e($turno['usuario']) ?> desde el <?= date('d/m/Y H:i', strtotime($turno['fecha_apertura'])) ?></p>
<div class="detalle-grid">
  <?php require __DIR__ . '/_resumen.php'; ?>
  <form method="post" class="form-card" id="form-cierre" data-esperado="<?= e((string)$turno['esperado']) ?>">
    <?php if ($errores): ?><div class="alert error"><ul><?php foreach ($errores as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <?= csrfCampo() ?>
    <label><span>Efectivo contado en la caja <small>(cuenta billetes y monedas)</small></span>
      <input type="number" name="monto_final" id="monto_final" value="<?= e($_POST['monto_final'] ?? '') ?>" min="0" step="any" required autofocus></label>
    <p class="cuadre-vivo" id="cuadre"></p>
    <label><span>Observaciones <small id="obs-ayuda">(opcional)</small></span><textarea name="observaciones" id="observaciones" rows="3" maxlength="1000"><?= e($_POST['observaciones'] ?? '') ?></textarea></label>
    <div class="form-actions">
      <a class="btn btn-light" href="movimientos.php?id=<?= (int)$turno['id_apertura'] ?>">Cancelar</a>
      <button class="btn" onclick="return confirm('¿Cerrar la caja? Después no podrás registrar más movimientos en este turno.')">Cerrar caja</button>
    </div>
  </form>
</div>
<script>
(() => {
  const form = document.getElementById('form-cierre'), esperado = parseFloat(form.dataset.esperado);
  const monto = document.getElementById('monto_final'), cuadre = document.getElementById('cuadre');
  const obs = document.getElementById('observaciones'), ayuda = document.getElementById('obs-ayuda');
  const dinero = v => '$' + Math.round(v).toLocaleString('es-CO');
  const pintar = () => {
    if (monto.value === '') { cuadre.textContent = ''; cuadre.className = 'cuadre-vivo'; obs.required = false; ayuda.textContent = '(opcional)'; return; }
    const dif = Math.round((parseFloat(monto.value) - esperado) * 100) / 100;
    cuadre.className = 'cuadre-vivo ' + (dif === 0 ? 'ok' : dif > 0 ? 'sobra' : 'falta');
    cuadre.textContent = dif === 0 ? '✓ La caja cuadra.' : (dif > 0 ? 'Sobrante de ' : 'Faltante de ') + dinero(Math.abs(dif));
    obs.required = dif !== 0; ayuda.textContent = dif !== 0 ? '(obligatorio: explica la diferencia)' : '(opcional)';
  };
  monto.addEventListener('input', pintar); pintar();
})();
</script>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
