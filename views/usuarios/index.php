<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../helpers/permisos.php';
require_once __DIR__ . '/../../controllers/UsuarioController.php';
requirePermission('usuarios.ver');
$usuarios = (new UsuarioController())->listar();
$titulo = 'Usuarios';
require __DIR__ . '/../layouts/header.php';
?>
<div class="page-header">
  <h1>Usuarios</h1>
  <?php if (tienePermiso('usuarios.crear')): ?><a class="btn" href="crear.php">+ Nuevo usuario</a><?php endif; ?>
</div>
<div class="table-card">
<table class="table">
  <thead><tr><th>Nombre</th><th>Usuario</th><th>Rol</th><th>Estado</th><th>Último acceso</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($usuarios as $u): $esYo = (int)$u['id_usuario'] === (int)$_SESSION['id_usuario']; ?>
    <tr class="<?= $u['estado'] ? '' : 'inactivo' ?>">
      <td><?= e($u['nombre']) ?><?= $esYo ? ' <small>(tú)</small>' : '' ?></td>
      <td><?= e($u['usuario']) ?></td>
      <td><span class="badge"><?= e($u['rol']) ?></span></td>
      <td><span class="badge <?= $u['estado'] ? 'badge-ok' : 'badge-off' ?>"><?= $u['estado'] ? 'Activo' : 'Inactivo' ?></span></td>
      <td><?= $u['ultimo_acceso'] ? date('d/m/Y H:i', strtotime($u['ultimo_acceso'])) : 'Nunca' ?></td>
      <td class="acciones">
        <?php if (tienePermiso('usuarios.editar')): ?><a class="btn btn-light btn-sm" href="editar.php?id=<?= (int)$u['id_usuario'] ?>">Editar</a><?php endif; ?>
        <?php if (tienePermiso('usuarios.eliminar') && !$esYo): ?>
          <form method="post" action="estado.php" onsubmit="return confirm('¿<?= $u['estado'] ? 'Desactivar' : 'Activar' ?> a <?= e($u['usuario']) ?>?')">
            <?= csrfCampo() ?><input type="hidden" name="id" value="<?= (int)$u['id_usuario'] ?>">
            <button class="btn btn-sm <?= $u['estado'] ? 'btn-danger' : 'btn-light' ?>"><?= $u['estado'] ? 'Desactivar' : 'Activar' ?></button>
          </form>
          <?= botonEliminar('estado.php', (int)$u['id_usuario'], $u['usuario']) ?>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
