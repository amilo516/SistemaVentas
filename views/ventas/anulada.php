<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../helpers/permisos.php';
require_once __DIR__ . '/../../controllers/VentaController.php';
requirePermission('ventas.anular');
$controller = new VentaController();
$id = (int)($_GET['id'] ?? 0);
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarCsrf();
    try {
        $controller->anular($id, (string)($_POST['motivo'] ?? ''));
        flash('success', 'Venta anulada. El stock de los productos fue devuelto al inventario.');
        redirect('detalle.php?id=' . $id);
    } catch (RuntimeException $e) {
        $error = $e->getMessage();
    }
}
$venta = $controller->ver($id);
if (!$venta) { flash('error', 'La venta no existe.'); redirect('index.php'); }
if ($venta['estado'] === 'anulada') { flash('error', 'La venta ya está anulada.'); redirect('detalle.php?id=' . $id); }
$titulo = 'Anular venta';
require __DIR__ . '/../layouts/header.php';
?>
<h1>Anular venta <?= e($venta['numero_factura']) ?></h1>
<?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
<form method="post" class="form-card">
  <?= csrfCampo() ?>
  <p>Total: <strong><?= dinero($venta['total']) ?></strong> · Cliente: <?= e($venta['cliente'] ?? '—') ?> · <?= date('d/m/Y H:i', strtotime($venta['fecha_venta'])) ?></p>
  <p class="muted">Al anular, los productos vuelven al inventario y, si se pagó en efectivo con la caja aún abierta, se registra la salida del dinero. Esta acción no se puede deshacer.</p>
  <label>Motivo de la anulación<textarea name="motivo" rows="3" maxlength="255" required autofocus></textarea></label>
  <div class="form-actions">
    <a class="btn btn-light" href="detalle.php?id=<?= (int)$venta['id_venta'] ?>">Cancelar</a>
    <button class="btn btn-danger-solid" onclick="return confirm('¿Seguro que deseas anular esta venta?')">Anular venta</button>
  </div>
</form>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
