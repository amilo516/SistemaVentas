<?php
// Formulario compartido por crear.php y editar.php. Espera: $datos, $roles, $errores, $esEdicion, $bloquearRol.
?>
<?php if ($errores): ?><div class="alert error"><ul><?php foreach ($errores as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<form method="post" class="form-card">
  <?= csrfCampo() ?>
  <label>Nombre completo<input type="text" name="nombre" value="<?= e($datos['nombre'] ?? '') ?>" maxlength="100" required autofocus></label>
  <label>Usuario (para iniciar sesión)<input type="text" name="usuario" value="<?= e($datos['usuario'] ?? '') ?>" maxlength="50" pattern="[A-Za-z0-9._\-]{3,50}" required></label>
  <label>Rol
    <select name="rol_id" required <?= $bloquearRol ? 'disabled' : '' ?>>
      <option value="">Selecciona un rol</option>
      <?php foreach ($roles as $rol): ?>
        <option value="<?= (int)$rol['id_rol'] ?>" <?= (int)($datos['rol_id'] ?? 0)===(int)$rol['id_rol'] ? 'selected' : '' ?>><?= e($rol['nombre']) ?> — <?= e($rol['descripcion']) ?></option>
      <?php endforeach; ?>
    </select>
    <?php if ($bloquearRol): ?><small>No puedes cambiar tu propio rol.</small><?php endif; ?>
  </label>
  <label>Contraseña<input type="password" name="password" minlength="8" autocomplete="new-password" <?= $esEdicion ? '' : 'required' ?>>
    <?php if ($esEdicion): ?><small>Déjala vacía para no cambiarla.</small><?php else: ?><small>Mínimo 8 caracteres.</small><?php endif; ?></label>
  <label>Confirmar contraseña<input type="password" name="password_confirmar" minlength="8" autocomplete="new-password" <?= $esEdicion ? '' : 'required' ?>></label>
  <div class="form-actions">
    <a class="btn btn-light" href="index.php">Cancelar</a>
    <button type="submit" class="btn"><?= $esEdicion ? 'Guardar cambios' : 'Crear usuario' ?></button>
  </div>
</form>
