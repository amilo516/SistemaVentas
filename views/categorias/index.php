<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../helpers/permisos.php';
require_once __DIR__ . '/../../helpers/funciones.php';
require_once __DIR__ . '/../../controllers/CategoriaController.php';
requirePermission('categorias.ver');
$controller = new CategoriaController();
$puedeGestionar = tienePermiso('categorias.gestionar');
$errores = [];
$editar = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requirePermission('categorias.gestionar');
    verificarCsrf();
    $id = (int)($_POST['id'] ?? 0);
    if (in_array($_POST['accion'] ?? '', ['estado', 'eliminar'], true)) {
        $eliminar = $_POST['accion'] === 'eliminar';
        $error = $eliminar ? $controller->eliminar($id) : $controller->cambiarEstado($id);
        $error ? flash('error', $error) : flash('success', $eliminar ? 'Categoría eliminada.' : 'Estado de la categoría actualizado.');
        redirect('index.php');
    }
    $errores = $controller->guardar($id, $_POST);
    if (!$errores) { flash('success', $id ? 'Categoría actualizada.' : 'Categoría creada.'); redirect('index.php'); }
    $editar = ['id_categoria' => $id, 'nombre' => $_POST['nombre'] ?? '', 'descripcion' => $_POST['descripcion'] ?? ''];
} elseif ($puedeGestionar && isset($_GET['editar'])) {
    $editar = $controller->buscar((int)$_GET['editar']);
}
$categorias = $controller->listar();
$titulo = 'Categorías';
require __DIR__ . '/../layouts/header.php';
?>
<div class="page-header"><h1>Categorías</h1><?php if (tienePermiso('productos.ver')): ?><a class="btn btn-light" href="../productos/index.php">Ver productos</a><?php endif; ?></div>
<div class="<?= $puedeGestionar ? 'split' : '' ?>">
  <?php if ($puedeGestionar): ?>
  <form method="post" class="form-card">
    <h2><?= !empty($editar['id_categoria']) ? 'Editar categoría' : 'Nueva categoría' ?></h2>
    <?php if ($errores): ?><div class="alert error"><ul><?php foreach ($errores as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <?= csrfCampo() ?>
    <input type="hidden" name="id" value="<?= (int)($editar['id_categoria'] ?? 0) ?>">
    <label>Nombre<input type="text" name="nombre" value="<?= e($editar['nombre'] ?? '') ?>" maxlength="100" required></label>
    <label>Descripción <small>(opcional)</small><input type="text" name="descripcion" value="<?= e($editar['descripcion'] ?? '') ?>" maxlength="255"></label>
    <div class="form-actions">
      <?php if (!empty($editar['id_categoria'])): ?><a class="btn btn-light" href="index.php">Cancelar</a><?php endif; ?>
      <button class="btn"><?= !empty($editar['id_categoria']) ? 'Guardar cambios' : 'Crear categoría' ?></button>
    </div>
  </form>
  <?php endif; ?>
  <div class="table-card">
  <table class="table">
    <thead><tr><th>Nombre</th><th>Descripción</th><th class="num">Productos</th><th>Estado</th><?php if ($puedeGestionar): ?><th></th><?php endif; ?></tr></thead>
    <tbody>
    <?php foreach ($categorias as $c): ?>
      <tr class="<?= $c['estado'] ? '' : 'inactivo' ?>">
        <td><strong><?= e($c['nombre']) ?></strong></td>
        <td class="wrap"><?= e($c['descripcion'] ?? '') ?></td>
        <td class="num"><a href="../productos/index.php?categoria=<?= (int)$c['id_categoria'] ?>&estado="><?= (int)$c['productos'] ?></a></td>
        <td><span class="badge <?= $c['estado'] ? 'badge-ok' : 'badge-off' ?>"><?= $c['estado'] ? 'Activa' : 'Inactiva' ?></span></td>
        <?php if ($puedeGestionar): ?>
        <td class="acciones">
          <a class="btn btn-light btn-sm" href="?editar=<?= (int)$c['id_categoria'] ?>">Editar</a>
          <form method="post" onsubmit="return confirm('¿<?= $c['estado'] ? 'Desactivar' : 'Activar' ?> esta categoría?')">
            <?= csrfCampo() ?><input type="hidden" name="accion" value="estado"><input type="hidden" name="id" value="<?= (int)$c['id_categoria'] ?>">
            <button class="btn btn-sm <?= $c['estado'] ? 'btn-danger' : 'btn-light' ?>"><?= $c['estado'] ? 'Desactivar' : 'Activar' ?></button>
          </form>
          <?= botonEliminar('index.php', (int)$c['id_categoria'], $c['nombre']) ?>
        </td>
        <?php endif; ?>
      </tr>
    <?php endforeach; ?>
    <?php if (!$categorias): ?><tr><td colspan="5" class="empty">Aún no hay categorías.</td></tr><?php endif; ?>
    </tbody>
  </table>
  </div>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
