<?php
// Formulario compartido por crear.php y editar.php. Espera: $datos, $errores, $esEdicion, $soloLectura, $volver.
$bloquearDoc = $esEdicion && ($datos['documento'] ?? '') === CLIENTE_GENERAL_DOCUMENTO;
$ro = $soloLectura ? 'disabled' : '';
?>
<?php if ($errores): ?><div class="alert error"><ul><?php foreach ($errores as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<form method="post" class="form-card form-wide">
  <?= csrfCampo() ?>
  <?php if ($volver): ?><input type="hidden" name="volver" value="<?= e($volver) ?>"><?php endif; ?>
  <div class="form-grid">
    <label class="span-2">Nombre completo o razón social<input type="text" name="nombre" value="<?= e($datos['nombre'] ?? '') ?>" maxlength="100" required autofocus <?= $ro ?>></label>
    <label><span>Documento <small>(cédula o NIT)</small></span><input type="text" name="documento" value="<?= e($datos['documento'] ?? '') ?>" maxlength="30" pattern="[A-Za-z0-9.\-]{3,30}" <?= $bloquearDoc ? 'readonly' : '' ?> <?= $ro ?>></label>
    <label>Teléfono<input type="tel" name="telefono" value="<?= e($datos['telefono'] ?? '') ?>" maxlength="30" <?= $ro ?>></label>
    <label>Correo electrónico<input type="email" name="email" value="<?= e($datos['email'] ?? '') ?>" maxlength="100" <?= $ro ?>></label>
    <label>Ciudad<input type="text" name="ciudad" value="<?= e($datos['ciudad'] ?? '') ?>" maxlength="100" <?= $ro ?>></label>
    <label class="span-2">Dirección<input type="text" name="direccion" value="<?= e($datos['direccion'] ?? '') ?>" maxlength="200" <?= $ro ?>></label>
  </div>
  <?php if (!$soloLectura): ?>
  <div class="form-actions">
    <a class="btn btn-light" href="<?= $volver === 'pos' ? '../ventas/crear.php' : 'index.php' ?>">Cancelar</a>
    <button type="submit" class="btn"><?= $esEdicion ? 'Guardar cambios' : ($volver === 'pos' ? 'Crear y volver a la venta' : 'Crear cliente') ?></button>
  </div>
  <?php endif; ?>
</form>
