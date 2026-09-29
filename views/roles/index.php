<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../helpers/permisos.php';
require_once __DIR__ . '/../../helpers/funciones.php';
require_once __DIR__ . '/../../controllers/RolController.php';
requirePermission('roles.gestionar');
$controller = new RolController();
$errores = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarCsrf();
    if (in_array($_POST['accion'] ?? '', ['estado', 'eliminar'], true)) {
        $eliminar = $_POST['accion'] === 'eliminar';
        $error = $eliminar ? $controller->eliminar((int)($_POST['id'] ?? 0)) : $controller->cambiarEstado((int)($_POST['id'] ?? 0));
        $error ? flash('error', $error) : flash('success', $eliminar ? 'Rol eliminado.' : 'Estado del rol actualizado.');
        redirect('index.php');
    }
    [$id, $errores] = $controller->guardar(0, $_POST);
    if (!$errores) { flash('success', 'Rol creado. Ahora marca sus permisos.'); redirect('editar.php?id=' . $id); }
}
$roles = $controller->listar();
$totalPermisos = array_sum(array_map('count', $controller->permisosAgrupados()));
$titulo = 'Roles y permisos';
require __DIR__ . '/../layouts/header.php';
?>
<h1>Roles y permisos</h1>
<p class="muted">Cada usuario tiene un rol, y el rol define qué puede ver y hacer en el sistema. Los cambios se aplican de inmediato.</p>
<form method="post" class="card form-inline nuevo-rol">
  <?php if ($errores): ?><div class="alert error"><ul><?php foreach ($errores as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
  <?= csrfCampo() ?>
  <div class="fila">
    <label>Nuevo rol<input type="text" name="nombre" value="<?= e($_POST['nombre'] ?? '') ?>" maxlength="50" placeholder="Ej. Bodeguero" required></label>
    <label class="grow"><span>Descripción <small class="muted">(opcional)</small></span><input type="text" name="descripcion" value="<?= e($_POST['descripcion'] ?? '') ?>" maxlength="255" placeholder="Qué hace este rol"></label>
    <button class="btn">Crear y asignar permisos</button>
  </div>
</form>
<div class="table-card">
  <table class="table">
    <thead><tr><th>Rol</th><th class="num">Usuarios activos</th><th>Permisos</th><th>Estado</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($roles as $r): $admin = $controller->esAdministrador($r); ?>
      <tr class="<?= $r['estado'] ? '' : 'inactivo' ?>">
        <td><a href="editar.php?id=<?= (int)$r['id_rol'] ?>"><?= e($r['nombre']) ?></a><br><small class="muted"><?= e($r['descripcion'] ?? '') ?></small></td>
        <td class="num"><?= (int)$r['usuarios'] ?></td>
        <td><?php $n = $admin ? $totalPermisos : (int)$r['permisos']; ?>
          <div class="permiso-medidor" title="<?= $n ?> de <?= $totalPermisos ?>"><span style="width:<?= $totalPermisos ? round($n / $totalPermisos * 100) : 0 ?>%"></span></div>
          <small class="muted"><?= $admin ? 'Todos' : $n . ' de ' . $totalPermisos ?></small></td>
        <td><span class="badge <?= $r['estado'] ? 'badge-ok' : 'badge-off' ?>"><?= $r['estado'] ? 'Activo' : 'Inactivo' ?></span></td>
        <td class="acciones">
          <a class="btn btn-light btn-sm" href="editar.php?id=<?= (int)$r['id_rol'] ?>"><?= $admin ? 'Ver' : 'Permisos' ?></a>
          <?php if (!$admin): ?>
          <form method="post" onsubmit="return confirm('¿<?= $r['estado'] ? 'Desactivar' : 'Activar' ?> este rol?')">
            <?= csrfCampo() ?><input type="hidden" name="accion" value="estado"><input type="hidden" name="id" value="<?= (int)$r['id_rol'] ?>">
            <button class="btn btn-sm <?= $r['estado'] ? 'btn-danger' : 'btn-light' ?>"><?= $r['estado'] ? 'Desactivar' : 'Activar' ?></button>
          </form>
          <?= (int)$r['id_rol'] > ROL_SUPERVISOR ? botonEliminar('index.php', (int)$r['id_rol'], $r['nombre']) : '' ?>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
