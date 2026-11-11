<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../helpers/permisos.php';
require_once __DIR__ . '/../../helpers/funciones.php';
require_once __DIR__ . '/../../helpers/empresa.php';
require_once __DIR__ . '/../../controllers/ConfiguracionController.php';
requirePermission('configuracion.gestionar');
$controller = new ConfiguracionController();
$errores = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarCsrf();
    $accion = $_POST['accion'] ?? '';
    if (in_array($accion, ['metodo_nuevo', 'metodo_estado', 'metodo_eliminar'], true)) {
        $error = match ($accion) {
            'metodo_nuevo' => $controller->crearMetodo((string)($_POST['nombre'] ?? '')),
            'metodo_estado' => $controller->cambiarEstadoMetodo((int)($_POST['id'] ?? 0)),
            default => $controller->eliminarMetodo((int)($_POST['id'] ?? 0)),
        };
        $error ? flash('error', $error) : flash('success', $accion === 'metodo_eliminar' ? 'Método de pago eliminado.' : 'Métodos de pago actualizados.');
        redirect('index.php#metodos');
    }
    $errores = $controller->guardar($_POST, $_FILES['logo'] ?? []);
    if (!$errores) { flash('success', 'Configuración guardada.'); redirect('index.php'); }
}
$c = $_SERVER['REQUEST_METHOD'] === 'POST' ? array_merge($controller->valores(), $_POST) : $controller->valores();
$metodos = $controller->metodosPago();
$logo = urlLogoEmpresa();
$ejemplo = strtoupper(trim((string)($c['prefijo_factura'] ?? 'FAC'))) . '-000001';
$titulo = 'Configuración';
require __DIR__ . '/../layouts/header.php';
?>
<h1>Configuración</h1>
<?php if ($errores): ?><div class="alert error"><ul><?php foreach ($errores as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<form method="post" enctype="multipart/form-data" class="config-grid">
  <?= csrfCampo() ?>
  <section class="form-card">
    <h2>Datos de la empresa</h2>
    <p class="muted">Aparecen en la factura y en la barra superior del sistema.</p>
    <div class="form-grid">
      <label class="span-2">Nombre o razón social<input type="text" name="nombre_empresa" value="<?= e($c['nombre_empresa'] ?? '') ?>" maxlength="150" required></label>
      <label>NIT<input type="text" name="nit" value="<?= e($c['nit'] ?? '') ?>" maxlength="30" placeholder="900.123.456-7"></label>
      <label>Teléfono<input type="text" name="telefono" value="<?= e($c['telefono'] ?? '') ?>" maxlength="50"></label>
      <label class="span-2">Dirección<input type="text" name="direccion" value="<?= e($c['direccion'] ?? '') ?>" maxlength="200"></label>
      <label class="span-2">Correo electrónico<input type="email" name="email" value="<?= e($c['email'] ?? '') ?>" maxlength="100"></label>
      <div class="span-2 imagen-campo">
        <?php if ($logo): ?><img class="logo-preview" src="<?= e($logo) ?>" alt="Logo"><label class="check"><input type="checkbox" name="quitar_logo" value="1"> Quitar logo</label><?php endif; ?>
        <label><span>Logo <small>(opcional · JPG, PNG o WEBP · máx. 1 MB)</small></span><input type="file" name="logo" accept="image/jpeg,image/png,image/webp"></label>
      </div>
    </div>
  </section>
  <section class="form-card">
    <h2>Facturación</h2>
    <div class="form-grid">
      <label><span>Prefijo de factura <small>(ej. FAC)</small></span><input type="text" name="prefijo_factura" id="prefijo" value="<?= e($c['prefijo_factura'] ?? 'FAC') ?>" maxlength="10" pattern="[A-Za-z0-9]{1,10}" required>
        <small>Próximas facturas: <strong id="ejemplo"><?= e($ejemplo) ?></strong>. Las ya emitidas no cambian.</small></label>
      <label><span>Impuesto (%) <small>(0 si no aplica)</small></span><input type="number" name="impuesto" value="<?= e($c['impuesto'] ?? '0') ?>" min="0" max="100" step="any" required>
        <small>Se suma al total de cada venta nueva.</small></label>
      <label class="span-2"><span>Mensaje al pie de la factura <small>(opcional)</small></span><input type="text" name="pie_factura" value="<?= e($c['pie_factura'] ?? '') ?>" maxlength="300" placeholder="¡Gracias por su compra!"></label>
    </div>
    <div class="form-actions"><button class="btn">Guardar configuración</button></div>
  </section>
</form>

<h2 class="section-title" id="metodos">Métodos de pago</h2>
<div class="split split-right">
  <div class="table-card">
  <table class="table">
    <thead><tr><th>Método</th><th class="num">Pagos registrados</th><th>Estado</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($metodos as $m): ?>
      <tr class="<?= $m['estado'] ? '' : 'inactivo' ?>">
        <td><strong><?= e($m['nombre']) ?></strong><?= mb_strtolower($m['nombre']) === 'efectivo' ? ' <small class="muted">(entra a la caja)</small>' : '' ?></td>
        <td class="num"><?= (int)$m['usos'] ?></td>
        <td><span class="badge <?= $m['estado'] ? 'badge-ok' : 'badge-off' ?>"><?= $m['estado'] ? 'Activo' : 'Inactivo' ?></span></td>
        <td class="acciones">
          <form method="post"><?= csrfCampo() ?><input type="hidden" name="accion" value="metodo_estado"><input type="hidden" name="id" value="<?= (int)$m['id_metodo_pago'] ?>">
            <button class="btn btn-sm <?= $m['estado'] ? 'btn-danger' : 'btn-light' ?>"><?= $m['estado'] ? 'Desactivar' : 'Activar' ?></button></form>
          <?= mb_strtolower($m['nombre']) !== 'efectivo' ? botonEliminar('index.php', (int)$m['id_metodo_pago'], $m['nombre'], 'metodo_eliminar') : '' ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <form method="post" class="form-card">
    <h2>Nuevo método</h2>
    <?= csrfCampo() ?><input type="hidden" name="accion" value="metodo_nuevo">
    <label>Nombre<input type="text" name="nombre" maxlength="50" placeholder="Ej. Bancolombia QR" required></label>
    <div class="form-actions"><button class="btn">Agregar</button></div>
  </form>
</div>
<script>
  document.getElementById('prefijo').addEventListener('input', e => {
    document.getElementById('ejemplo').textContent = (e.target.value.trim().toUpperCase() || 'FAC') + '-000001';
  });
</script>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
