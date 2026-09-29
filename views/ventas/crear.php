<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../helpers/permisos.php';
require_once __DIR__ . '/../../controllers/VentaController.php';
require_once __DIR__ . '/../../controllers/CarritoController.php';
requirePermission('ventas.crear');
$ventas = new VentaController();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarCsrf();
    try {
        $id = $ventas->finalizar($_POST);
        flash('success', 'Venta registrada correctamente.');
        redirect('detalle.php?id=' . $id . '&nueva=1');
    } catch (RuntimeException $e) {
        flash('error', $e->getMessage());
        redirect('crear.php');
    }
}
$form = $ventas->datosFormulario();
$estado = (new CarritoController())->estado();
$titulo = 'Nueva venta';
require __DIR__ . '/../layouts/header.php';
?>
<div class="page-header"><h1>Nueva venta</h1><a class="btn btn-light" href="index.php">Ver ventas</a></div>
<?php if (!$form['hay_caja_abierta']): ?>
  <div class="alert warn">No hay ninguna caja abierta: las ventas en efectivo no quedarán registradas en caja.<?php if (tienePermiso('caja.abrir')): ?> <a href="../caja/abrir.php">Abrir caja</a><?php endif; ?></div>
<?php endif; ?>
<div class="pos" id="pos" data-url="carrito.php" data-csrf="<?= e(csrfToken()) ?>" data-impuesto="<?= e((string)$form['impuesto_pct']) ?>">
  <section class="pos-main">
    <div class="card">
      <label class="pos-search">
        <?= icono('productos') ?>
        <input type="search" id="buscar" placeholder="Buscar producto por nombre o código, o escanear código de barras…" autocomplete="off" autofocus>
      </label>
      <div id="resultados" class="resultados" hidden></div>
    </div>
    <div class="card">
      <div class="card-header"><h2>Carrito</h2><button type="button" class="btn btn-light btn-sm" id="vaciar">Vaciar</button></div>
      <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Producto</th><th class="num">Precio</th><th class="num">Cantidad</th><th class="num">Subtotal</th><th></th></tr></thead>
        <tbody id="items"></tbody>
      </table>
      </div>
      <p id="vacio" class="empty">El carrito está vacío. Busca un producto para empezar.</p>
    </div>
  </section>

  <form method="post" class="card pos-resumen" id="form-venta">
    <?= csrfCampo() ?>
    <label><span class="label-fila">Cliente<?php if (tienePermiso('clientes.crear')): ?><a href="../clientes/crear.php?volver=pos">+ Nuevo cliente</a><?php endif; ?></span>
      <select name="id_cliente" required>
        <?php $clienteSel = (int)($_GET['cliente'] ?? 0); foreach ($form['clientes'] as $c): ?>
          <option value="<?= (int)$c['id_cliente'] ?>" <?= $clienteSel === (int)$c['id_cliente'] ? 'selected' : '' ?>><?= e($c['nombre']) ?><?= $c['documento'] ? ' · ' . e($c['documento']) : '' ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <dl class="totales">
      <dt>Subtotal</dt><dd id="t-subtotal">$0</dd>
      <dt><label for="descuento">Descuento</label></dt><dd><input type="number" name="descuento" id="descuento" min="0" step="any" value="0"></dd>
      <?php if ($form['impuesto_pct'] > 0): ?><dt>Impuesto (<?= e(cantidad($form['impuesto_pct'])) ?>%)</dt><dd id="t-impuesto">$0</dd><?php endif; ?>
      <dt class="total">Total</dt><dd class="total" id="t-total">$0</dd>
    </dl>
    <label>Método de pago
      <select name="id_metodo_pago" id="metodo" required>
        <?php foreach ($form['metodos'] as $m): ?>
          <option value="<?= (int)$m['id_metodo_pago'] ?>" data-efectivo="<?= mb_strtolower($m['nombre']) === 'efectivo' ? '1' : '0' ?>"><?= e($m['nombre']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <div id="campo-efectivo">
      <label>Dinero recibido<input type="number" name="recibido" id="recibido" min="0" step="any"></label>
      <p class="cambio">Cambio: <strong id="cambio">$0</strong></p>
    </div>
    <label id="campo-referencia" hidden>Referencia / N.º de transacción<input type="text" name="referencia" maxlength="100"></label>
    <label>Observaciones<textarea name="observaciones" rows="2" maxlength="1000"></textarea></label>
    <button type="submit" class="btn btn-lg" id="finalizar">Finalizar venta</button>
  </form>
</div>
<script>window.CARRITO = <?= json_encode($estado, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>;</script>
<script src="<?= APP_URL ?>/assets/js/ventas.js?v=<?= filemtime(__DIR__ . '/../../assets/js/ventas.js') ?>" defer></script>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
