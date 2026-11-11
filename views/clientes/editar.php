<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../helpers/permisos.php';
require_once __DIR__ . '/../../helpers/funciones.php';
require_once __DIR__ . '/../../controllers/ClienteController.php';
requirePermission('clientes.ver');
$controller = new ClienteController();
$id = (int)($_GET['id'] ?? 0);
$datos = $controller->buscar($id);
if (!$datos) { flash('error', 'El cliente no existe.'); redirect('index.php'); }
$soloLectura = !tienePermiso('clientes.editar');
$errores = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requirePermission('clientes.editar');
    verificarCsrf();
    $errores = $controller->actualizar($id, $_POST);
    if (!$errores) { flash('success', 'Cliente actualizado.'); redirect('index.php'); }
    $datos = array_merge($datos, array_diff_key($_POST, ['csrf' => 1]));
}
$historial = tienePermiso('ventas.ver') ? $controller->historial($id) : null;
$esEdicion = true;
$volver = '';
$titulo = $datos['nombre'];
require __DIR__ . '/../layouts/header.php';
?>
<div class="page-header">
  <h1><?= e($datos['nombre']) ?> <?php if (!$datos['estado']): ?><span class="badge badge-off">Inactivo</span><?php endif; ?></h1>
  <a class="btn btn-light" href="index.php">Volver a clientes</a>
</div>
<div class="<?= $historial ? 'cliente-grid' : '' ?>">
  <div><?php require __DIR__ . '/_form.php'; ?></div>
  <?php if ($historial): $r = $historial['resumen']; ?>
  <div class="stack">
    <div class="card">
      <h3 class="card-title">Compras<?= $historial['solo_propias'] ? ' <small class="muted">(atendidas por ti)</small>' : '' ?></h3>
      <dl class="totales">
        <dt>Número de compras</dt><dd><?= (int)$r['compras'] ?></dd>
        <dt>Promedio por compra</dt><dd><?= dinero($r['promedio']) ?></dd>
        <dt>Última compra</dt><dd><?= $r['ultima'] ? date('d/m/Y', strtotime($r['ultima'])) : '—' ?></dd>
        <dt class="total">Total comprado</dt><dd class="total"><?= dinero($r['total']) ?></dd>
      </dl>
    </div>
    <div class="table-card">
      <table class="table">
        <thead><tr><th>Factura</th><th>Fecha</th><th class="num">Total</th></tr></thead>
        <tbody>
        <?php foreach ($historial['ventas'] as $v): ?>
          <tr class="<?= $v['estado'] === 'anulada' ? 'inactivo' : '' ?>">
            <td><a href="../ventas/detalle.php?id=<?= (int)$v['id_venta'] ?>"><?= e($v['numero_factura']) ?></a><?= $v['estado'] === 'anulada' ? ' <span class="badge badge-danger">Anulada</span>' : '' ?></td>
            <td><?= date('d/m/Y', strtotime($v['fecha_venta'])) ?></td>
            <td class="num"><?= dinero($v['total']) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$historial['ventas']): ?><tr><td colspan="3" class="empty">Sin compras registradas.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
