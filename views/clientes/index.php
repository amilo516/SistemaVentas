<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../helpers/permisos.php';
require_once __DIR__ . '/../../helpers/funciones.php';
require_once __DIR__ . '/../../controllers/ClienteController.php';
requirePermission('clientes.ver');
$controller = new ClienteController();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requirePermission('clientes.eliminar');
    verificarCsrf();
    $eliminar = ($_POST['accion'] ?? '') === 'eliminar';
    $error = $eliminar ? $controller->eliminar((int)($_POST['id'] ?? 0)) : $controller->cambiarEstado((int)($_POST['id'] ?? 0));
    $error ? flash('error', $error) : flash('success', $eliminar ? 'Cliente eliminado.' : 'Estado del cliente actualizado.');
    redirect('index.php');
}
$filtros = [
    'q' => mb_substr(trim($_GET['q'] ?? ''), 0, 100),
    'estado' => in_array($_GET['estado'] ?? '1', ['1', '0', ''], true) ? ($_GET['estado'] ?? '1') : '1',
];
$clientes = $controller->listar($filtros);
$titulo = 'Clientes';
require __DIR__ . '/../layouts/header.php';
?>
<div class="page-header">
  <h1>Clientes</h1>
  <?php if (tienePermiso('clientes.crear')): ?><a class="btn" href="crear.php">+ Nuevo cliente</a><?php endif; ?>
</div>
<form class="filtros card" method="get">
  <label class="grow">Buscar<input type="search" name="q" value="<?= e($filtros['q']) ?>" placeholder="Nombre, documento, teléfono o correo" autofocus></label>
  <label>Estado
    <select name="estado"><?php foreach (['1' => 'Activos', '0' => 'Inactivos', '' => 'Todos'] as $k => $v): ?><option value="<?= $k ?>" <?= $filtros['estado'] === (string)$k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?></select>
  </label>
  <button class="btn">Filtrar</button>
</form>
<p class="resumen-filtro"><?= count($clientes) ?> clientes</p>
<div class="table-card">
<table class="table">
  <thead><tr><th>Cliente</th><th>Documento</th><th>Contacto</th><th>Ciudad</th><th class="num">Compras</th><th>Última compra</th><th>Estado</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($clientes as $c): $general = $controller->esGeneral($c); ?>
    <tr class="<?= $c['estado'] ? '' : 'inactivo' ?>">
      <td><a href="editar.php?id=<?= (int)$c['id_cliente'] ?>"><?= e($c['nombre']) ?></a><?= $general ? ' <span class="badge">General</span>' : '' ?></td>
      <td><?= e($c['documento'] ?? '—') ?></td>
      <td><?= e($c['telefono'] ?? '') ?><?= $c['email'] ? '<br><small class="muted">' . e($c['email']) . '</small>' : '' ?><?= !$c['telefono'] && !$c['email'] ? '—' : '' ?></td>
      <td><?= e($c['ciudad'] ?? '—') ?></td>
      <td class="num"><?= (int)$c['compras'] ?><?= $c['compras'] ? '<br><small class="muted">' . dinero($c['total_compras']) . '</small>' : '' ?></td>
      <td><?= $c['ultima_compra'] ? date('d/m/Y', strtotime($c['ultima_compra'])) : '—' ?></td>
      <td><span class="badge <?= $c['estado'] ? 'badge-ok' : 'badge-off' ?>"><?= $c['estado'] ? 'Activo' : 'Inactivo' ?></span></td>
      <td class="acciones">
        <a class="btn btn-light btn-sm" href="editar.php?id=<?= (int)$c['id_cliente'] ?>"><?= tienePermiso('clientes.editar') ? 'Editar' : 'Ver' ?></a>
        <?php if (tienePermiso('clientes.eliminar') && !$general): ?>
          <form method="post" onsubmit="return confirm('¿<?= $c['estado'] ? 'Desactivar' : 'Activar' ?> este cliente?')">
            <?= csrfCampo() ?><input type="hidden" name="id" value="<?= (int)$c['id_cliente'] ?>">
            <button class="btn btn-sm <?= $c['estado'] ? 'btn-danger' : 'btn-light' ?>"><?= $c['estado'] ? 'Desactivar' : 'Activar' ?></button>
          </form>
          <?= botonEliminar('index.php', (int)$c['id_cliente'], $c['nombre']) ?>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$clientes): ?><tr><td colspan="8" class="empty">No hay clientes con estos filtros.</td></tr><?php endif; ?>
  </tbody>
</table>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
