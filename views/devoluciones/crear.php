<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../helpers/permisos.php';
require_once __DIR__ . '/../../controllers/DevolucionController.php';
requirePermission('devoluciones.crear');
$controller = new DevolucionController();
$errores = [];
$venta = null;
if (isset($_GET['venta'])) $venta = $controller->venta((int)$_GET['venta']);
elseif (($_GET['factura'] ?? '') !== '') {
    $venta = $controller->buscarVentaPorFactura((string)$_GET['factura']);
    if (!$venta) $errores[] = 'No se encontró la factura "' . trim($_GET['factura']) . '".';
}
if ($venta && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarCsrf();
    [$id, $errores] = $controller->registrar((int)$venta['id_venta'], $_POST);
    if (!$errores) { flash('success', 'Devolución registrada.'); redirect('detalle.php?id=' . $id); }
    $venta = $controller->venta((int)$venta['id_venta']);
}
$productos = $venta ? $controller->productosDevolvibles($venta) : [];
$quedaAlgo = array_sum(array_column($productos, 'disponible')) > 0;
$caja = $controller->miCajaAbierta();
$titulo = 'Nueva devolución';
require __DIR__ . '/../layouts/header.php';
?>
<div class="page-header"><h1>Nueva devolución</h1><a class="btn btn-light" href="index.php">Ver devoluciones</a></div>
<form method="get" class="filtros card">
  <label class="grow">N.º de factura<input type="search" name="factura" value="<?= e($venta['numero_factura'] ?? ($_GET['factura'] ?? '')) ?>" placeholder="Ej. FAC-000012" required <?= $venta ? '' : 'autofocus' ?>></label>
  <button class="btn">Buscar venta</button>
</form>
<?php if ($errores): ?><div class="alert error"><ul><?php foreach ($errores as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

<?php if ($venta): ?>
  <p class="muted">Venta <strong><?= e($venta['numero_factura']) ?></strong> del <?= date('d/m/Y H:i', strtotime($venta['fecha_venta'])) ?> ·
    Cliente: <?= e($venta['cliente'] ?? '—') ?> · Total: <?= dinero($venta['total']) ?>
    <?= (float)$venta['descuento'] > 0 || (float)$venta['impuesto'] > 0 ? '· <em>El valor a devolver incluye el descuento e impuesto proporcional de la venta.</em>' : '' ?></p>
  <?php if ($venta['estado'] !== 'pagada'): ?>
    <div class="alert error">Esta venta está <?= e($venta['estado']) ?>: no admite devoluciones.</div>
  <?php elseif (!$quedaAlgo): ?>
    <div class="alert warn">Todos los productos de esta venta ya fueron devueltos.</div>
  <?php else: ?>
  <form method="post" id="form-dev">
    <?= csrfCampo() ?>
    <div class="card">
      <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Producto</th><th class="num">Vendido</th><th class="num">Ya devuelto</th><th class="num">Precio pagado</th><th class="num">A devolver</th><th class="num">Subtotal</th></tr></thead>
        <tbody>
        <?php foreach ($productos as $p): $disp = (float)$p['disponible']; ?>
          <tr class="<?= $disp <= 0 ? 'inactivo' : '' ?>">
            <td><strong><?= e($p['nombre']) ?></strong><br><small class="muted"><?= e($p['codigo']) ?></small></td>
            <td class="num"><?= cantidad($p['vendido']) ?></td>
            <td class="num"><?= (float)$p['devuelto'] > 0 ? cantidad($p['devuelto']) : '—' ?></td>
            <td class="num"><?= dinero($p['precio_pagado']) ?></td>
            <td class="num"><?php if ($disp > 0): ?>
              <input type="number" class="cant-dev" name="cantidad[<?= (int)$p['id_producto'] ?>]" value="<?= e((string)($_POST['cantidad'][$p['id_producto']] ?? '')) ?>"
                min="0" max="<?= $disp ?>" step="<?= $p['decimal'] ? 'any' : '1' ?>" data-precio="<?= e((string)$p['precio_pagado']) ?>" placeholder="0">
              <small class="muted">de <?= cantidad($disp) ?></small>
            <?php else: ?>—<?php endif; ?></td>
            <td class="num sub-dev">$0</td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      </div>
      <dl class="totales dev-total"><dt class="total">Total a devolver</dt><dd class="total" id="total-dev">$0</dd></dl>
    </div>
    <div class="card form-card-plain">
      <div class="form-grid">
        <label class="span-2">Motivo de la devolución<input type="text" name="motivo" list="motivos-dev" value="<?= e($_POST['motivo'] ?? '') ?>" maxlength="255" required placeholder="Elige o escribe el motivo">
          <datalist id="motivos-dev"><option value="Producto defectuoso"><option value="Producto vencido"><option value="Error en el pedido"><option value="El cliente cambió de opinión"><option value="Producto equivocado"></datalist></label>
        <label>Reembolso
          <select name="reembolso" id="reembolso" required>
            <option value="efectivo" <?= ($_POST['reembolso'] ?? ($caja ? 'efectivo' : 'otro')) === 'efectivo' ? 'selected' : '' ?> <?= $caja ? '' : 'disabled' ?>>Efectivo<?= $caja ? ' (sale de ' . e($caja['caja']) . ')' : ' (necesitas tu caja abierta)' ?></option>
            <option value="otro" <?= ($_POST['reembolso'] ?? ($caja ? 'efectivo' : 'otro')) === 'otro' ? 'selected' : '' ?>>Otro medio (transferencia, reverso de tarjeta…)</option>
          </select>
        </label>
        <label id="campo-ref"><span>Referencia <small>(opcional)</small></span><input type="text" name="referencia" value="<?= e($_POST['referencia'] ?? '') ?>" maxlength="100" placeholder="N.º de transferencia"></label>
        <label class="check span-2"><input type="checkbox" name="reintegrar" value="1" <?= $_SERVER['REQUEST_METHOD'] !== 'POST' || !empty($_POST['reintegrar']) ? 'checked' : '' ?>> Devolver los productos al inventario <small class="muted">(desmárcalo si vienen dañados o vencidos)</small></label>
      </div>
      <div class="form-actions"><a class="btn btn-light" href="index.php">Cancelar</a><button class="btn" id="guardar-dev">Registrar devolución</button></div>
    </div>
  </form>
  <script>
  (() => {
    const form = document.getElementById('form-dev'), total = document.getElementById('total-dev');
    const reembolso = document.getElementById('reembolso'), ref = document.getElementById('campo-ref');
    const dinero = v => '$' + Math.round(v).toLocaleString('es-CO');
    const calcular = () => {
      let t = 0;
      form.querySelectorAll('.cant-dev').forEach(i => {
        const s = (parseFloat(i.value) || 0) * parseFloat(i.dataset.precio);
        i.closest('tr').querySelector('.sub-dev').textContent = dinero(s); t += s;
      });
      total.textContent = dinero(t);
      document.getElementById('guardar-dev').disabled = t <= 0;
      return t;
    };
    const verRef = () => { ref.hidden = reembolso.value !== 'otro'; };
    form.addEventListener('input', calcular); reembolso.addEventListener('change', verRef);
    form.addEventListener('submit', e => { if (!confirm('¿Registrar la devolución por ' + dinero(calcular()) + '?')) e.preventDefault(); });
    calcular(); verRef();
  })();
  </script>
  <?php endif; ?>
<?php endif; ?>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
