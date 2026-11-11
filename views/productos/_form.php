<?php
// Formulario compartido por crear.php y editar.php. Espera: $datos, $categorias, $errores, $esEdicion.
$unidades = unidadesMedida();
$puedeAjustar = $esEdicion && tienePermiso('inventario.ajuste');
?>
<?php if ($errores): ?><div class="alert error"><ul><?php foreach ($errores as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<?php if (!$categorias): ?>
  <div class="alert error">No hay categorías activas. <?php if (tienePermiso('categorias.gestionar')): ?><a href="../categorias/index.php">Crea una categoría</a> antes de registrar productos.<?php endif; ?></div>
<?php endif; ?>
<form method="post" enctype="multipart/form-data" class="form-card form-wide">
  <?= csrfCampo() ?>
  <div class="form-grid">
    <label class="span-2">Nombre<input type="text" name="nombre" value="<?= e($datos['nombre'] ?? '') ?>" maxlength="150" required autofocus></label>
    <label>Código interno<input type="text" name="codigo" value="<?= e($datos['codigo'] ?? '') ?>" maxlength="50" pattern="[A-Za-z0-9._\-]{1,50}" placeholder="Ej. BEB-001" required></label>
    <label><span>Código de barras <small>(opcional)</small></span><input type="text" name="codigo_barras" value="<?= e($datos['codigo_barras'] ?? '') ?>" maxlength="100" placeholder="Escanéalo aquí"></label>
    <label>Categoría
      <select name="id_categoria" required>
        <option value="">Selecciona…</option>
        <?php foreach ($categorias as $c): ?><option value="<?= (int)$c['id_categoria'] ?>" <?= (int)($datos['id_categoria'] ?? 0) === (int)$c['id_categoria'] ? 'selected' : '' ?>><?= e($c['nombre']) ?></option><?php endforeach; ?>
      </select>
    </label>
    <label>Unidad de medida
      <select name="unidad_medida" required>
        <?php foreach ($unidades as $k => [$etiqueta]): ?><option value="<?= $k ?>" <?= ($datos['unidad_medida'] ?? 'unidad') === $k ? 'selected' : '' ?>><?= e($etiqueta) ?></option><?php endforeach; ?>
      </select>
    </label>
    <label>Precio de compra<input type="number" name="precio_compra" value="<?= e((string)(float)($datos['precio_compra'] ?? 0)) ?>" min="0" step="any" required></label>
    <label>Precio de venta<input type="number" name="precio_venta" id="precio_venta" value="<?= e(isset($datos['precio_venta']) ? (string)(float)$datos['precio_venta'] : '') ?>" min="0.01" step="any" required>
      <small id="margen"></small></label>
    <?php if (!$esEdicion): ?>
      <label>Stock inicial<input type="number" name="stock" value="<?= e((string)(float)($datos['stock'] ?? 0)) ?>" min="0" step="any"></label>
    <?php elseif ($puedeAjustar): ?>
      <label>Stock actual<input type="number" name="stock" id="stock" value="<?= e((string)(float)$datos['stock']) ?>" data-original="<?= e((string)(float)($datos['stock_original'] ?? $datos['stock'])) ?>" min="0" step="any">
        <small>Cambiarlo registra un ajuste de inventario.</small></label>
    <?php else: ?>
      <label>Stock actual<input type="text" value="<?= e(cantidad($datos['stock'])) ?>" disabled><small>Se modifica desde Inventario.</small></label>
    <?php endif; ?>
    <label><span>Stock mínimo <small>(alerta de stock bajo)</small></span><input type="number" name="stock_minimo" value="<?= e((string)(float)($datos['stock_minimo'] ?? 0)) ?>" min="0" step="any"></label>
    <?php if ($puedeAjustar): ?>
      <label class="span-2" id="campo-motivo" hidden>Motivo del ajuste de stock<input type="text" name="motivo_ajuste" value="<?= e($datos['motivo_ajuste'] ?? '') ?>" maxlength="255" placeholder="Ej. conteo físico, producto dañado, vencido"></label>
    <?php endif; ?>
    <label class="span-2"><span>Descripción <small>(opcional)</small></span><textarea name="descripcion" rows="2"><?= e($datos['descripcion'] ?? '') ?></textarea></label>
    <div class="span-2 imagen-campo">
      <?php if ($esEdicion && ($img = urlImagenProducto($datos['imagen'] ?? null))): ?>
        <img class="thumb thumb-lg" src="<?= e($img) ?>" alt="">
        <label class="check"><input type="checkbox" name="quitar_imagen" value="1"> Quitar imagen</label>
      <?php endif; ?>
      <label><span>Imagen <small>(opcional · JPG, PNG o WEBP · máx. 2 MB)</small></span><input type="file" name="imagen" accept="image/jpeg,image/png,image/webp"></label>
    </div>
  </div>
  <div class="form-actions">
    <a class="btn btn-light" href="index.php">Cancelar</a>
    <button type="submit" class="btn"><?= $esEdicion ? 'Guardar cambios' : 'Crear producto' ?></button>
  </div>
</form>
<script>
(() => {
  const compra = document.querySelector('[name=precio_compra]'), venta = document.getElementById('precio_venta'), margen = document.getElementById('margen');
  const stock = document.getElementById('stock'), motivo = document.getElementById('campo-motivo');
  const pintar = () => {
    const c = parseFloat(compra.value) || 0, v = parseFloat(venta.value) || 0;
    margen.textContent = v > 0 && c > 0 ? `Ganancia: $${Math.round(v - c).toLocaleString('es-CO')} (${((v - c) / v * 100).toFixed(1)}%)` : '';
    margen.style.color = v > 0 && v < c ? '#b91c1c' : '';
    if (stock && motivo) {
      const cambia = stock.value !== '' && parseFloat(stock.value) !== parseFloat(stock.dataset.original);
      motivo.hidden = !cambia; motivo.querySelector('input').required = cambia;
    }
  };
  [compra, venta, stock].forEach(el => el && el.addEventListener('input', pintar));
  pintar();
})();
</script>
