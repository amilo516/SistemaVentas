<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../helpers/permisos.php';
require_once __DIR__ . '/../../helpers/funciones.php';
require_once __DIR__ . '/../../controllers/RolController.php';
requirePermission('roles.gestionar');
$controller = new RolController();
$id = (int)($_GET['id'] ?? 0);
$rol = $controller->buscar($id);
if (!$rol) { flash('error', 'El rol no existe.'); redirect('index.php'); }
$admin = $controller->esAdministrador($rol);
$errores = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarCsrf();
    [, $errores] = $controller->guardar($id, $_POST);
    if (!$errores && !$admin) {
        $error = $controller->guardarPermisos($id, (array)($_POST['permisos'] ?? []));
        if ($error) $errores[] = $error;
    }
    if (!$errores) { flash('success', 'Rol guardado. Los usuarios con este rol ya tienen los nuevos permisos.'); redirect('editar.php?id=' . $id); }
}
$grupos = $controller->permisosAgrupados();
$actuales = $admin ? array_merge(...array_map(fn($g) => array_column($g, 'nombre'), array_values($grupos))) : $controller->permisosDeRol($id);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$admin) $actuales = (array)($_POST['permisos'] ?? []);
$usuarios = $controller->usuarios($id);
$titulo = 'Rol ' . $rol['nombre'];
require __DIR__ . '/../layouts/header.php';
?>
<div class="page-header"><h1>Rol: <?= e($rol['nombre']) ?></h1><a class="btn btn-light" href="index.php">Volver a roles</a></div>
<?php if ($errores): ?><div class="alert error"><ul><?php foreach ($errores as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<?php if ($admin): ?><div class="alert warn">El Administrador siempre tiene acceso completo; sus permisos no se pueden quitar.</div><?php endif; ?>
<form method="post" id="form-rol">
  <?= csrfCampo() ?>
  <div class="rol-grid">
    <div class="stack">
      <div class="form-card">
        <label>Nombre<input type="text" name="nombre" value="<?= e($_POST['nombre'] ?? $rol['nombre']) ?>" maxlength="50" required <?= $admin ? 'readonly' : '' ?>></label>
        <label><span>Descripción <small>(opcional)</small></span><input type="text" name="descripcion" value="<?= e($_POST['descripcion'] ?? ($rol['descripcion'] ?? '')) ?>" maxlength="255"></label>
      </div>
      <div class="card">
        <h3 class="card-title">Usuarios con este rol (<?= count($usuarios) ?>)</h3>
        <?php if ($usuarios): ?><ul class="lista-simple"><?php foreach ($usuarios as $u): ?><li><?= e($u['nombre']) ?> <small class="muted">@<?= e($u['usuario']) ?></small></li><?php endforeach; ?></ul>
        <?php else: ?><p class="empty">Ningún usuario activo.</p><?php endif; ?>
        <?php if (tienePermiso('usuarios.ver')): ?><p class="nota"><a href="../usuarios/index.php">Gestionar usuarios</a></p><?php endif; ?>
      </div>
    </div>
    <div class="card">
      <div class="card-header"><h2>Permisos</h2>
        <?php if (!$admin): ?><span class="acciones"><button type="button" class="btn btn-light btn-sm" data-todos="1">Marcar todos</button><button type="button" class="btn btn-light btn-sm" data-todos="0">Quitar todos</button></span><?php endif; ?></div>
      <p class="muted nota">Al marcar una acción de un módulo (crear, editar…) se marca también su permiso de consulta.</p>
      <div class="permisos-grid">
        <?php foreach ($grupos as $modulo => $lista): ?>
          <fieldset class="permiso-grupo">
            <legend><label class="check"><input type="checkbox" class="marcar-grupo" <?= $admin ? 'disabled' : '' ?>> <?= e($modulo) ?></label></legend>
            <?php foreach ($lista as $p): ?>
              <label class="check permiso"><input type="checkbox" name="permisos[]" value="<?= e($p['nombre']) ?>" <?= in_array($p['nombre'], $actuales, true) ? 'checked' : '' ?> <?= $admin ? 'disabled' : '' ?> data-ver="<?= str_ends_with($p['nombre'], '.ver') ? '1' : '0' ?>">
                <span><?= e($p['descripcion'] ?: $p['nombre']) ?><small><?= e($p['nombre']) ?></small></span></label>
            <?php endforeach; ?>
          </fieldset>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <div class="barra-guardar"><span class="muted" id="conteo"></span><button class="btn">Guardar rol</button></div>
</form>
<script>
(() => {
  const form = document.getElementById('form-rol');
  const cajas = [...form.querySelectorAll('input[name="permisos[]"]')];
  const pintar = () => {
    form.querySelectorAll('.permiso-grupo').forEach(g => {
      const items = [...g.querySelectorAll('input[name="permisos[]"]')], n = items.filter(i => i.checked).length;
      const m = g.querySelector('.marcar-grupo'); m.checked = n === items.length; m.indeterminate = n > 0 && n < items.length;
    });
    document.getElementById('conteo').textContent = cajas.filter(c => c.checked).length + ' de ' + cajas.length + ' permisos marcados';
  };
  form.addEventListener('change', e => {
    const t = e.target, grupo = t.closest('.permiso-grupo');
    if (t.classList.contains('marcar-grupo')) grupo.querySelectorAll('input[name="permisos[]"]').forEach(i => i.checked = t.checked);
    else if (t.name === 'permisos[]' && grupo) {
      const ver = grupo.querySelector('input[data-ver="1"]');
      if (t.checked && ver) ver.checked = true;                       // una acción requiere poder consultar
      if (!t.checked && t === ver) grupo.querySelectorAll('input[name="permisos[]"]').forEach(i => i.checked = false); // sin consulta, sin acciones
    }
    pintar();
  });
  form.querySelectorAll('[data-todos]').forEach(b => b.addEventListener('click', () => { cajas.forEach(c => c.checked = b.dataset.todos === '1'); pintar(); }));
  pintar();
})();
</script>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
