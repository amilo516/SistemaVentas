<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../helpers/permisos.php';
require_once __DIR__ . '/../../controllers/DevolucionController.php';
requirePermission('devoluciones.ver');
$dev = (new DevolucionController())->ver((int)($_GET['id'] ?? 0));
if (!$dev) { flash('error', 'La devolución no existe.'); redirect('index.php'); }
$numero = 'DEV-' . str_pad((string)$dev['id_devolucion'], 5, '0', STR_PAD_LEFT);
$titulo = 'Devolución ' . $numero;
require __DIR__ . '/../layouts/header.php';
?>
<div class="page-header">
  <h1>Devolución <?= e($numero) ?> <span class="badge <?= $dev['estado'] === 'anulada' ? 'badge-danger' : 'badge-ok' ?>"><?= ucfirst($dev['estado']) ?></span></h1>
  <div class="acciones">
    <?php if (tienePermiso('ventas.ver')): ?><a class="btn btn-light" href="../ventas/detalle.php?id=<?= (int)$dev['id_venta'] ?>">Ver venta <?= e($dev['numero_factura']) ?></a><?php endif; ?>
    <a class="btn btn-light" href="index.php">Volver</a>
  </div>
</div>
<div class="detalle-grid">
  <div class="card info">
    <dl>
      <dt>Fecha</dt><dd><?= date('d/m/Y H:i', strtotime($dev['fecha_devolucion'])) ?></dd>
      <dt>Factura</dt><dd><?= e($dev['numero_factura']) ?> <small class="muted">(<?= date('d/m/Y', strtotime($dev['fecha_venta'])) ?>)</small></dd>
      <dt>Cliente</dt><dd><?= e($dev['cliente'] ?? '—') ?><?= $dev['cliente_documento'] ? ' · ' . e($dev['cliente_documento']) : '' ?></dd>
      <dt>Registró</dt><dd><?= e($dev['usuario']) ?></dd>
      <dt>Motivo</dt><dd><?= e($dev['motivo']) ?></dd>
      <?php if ($dev['observaciones']): ?><dt>Reembolso</dt><dd class="pre"><?= e($dev['observaciones']) ?></dd><?php endif; ?>
    </dl>
  </div>
  <div class="card">
    <div class="table-wrap">
    <table class="table">
      <thead><tr><th>Producto</th><th class="num">Cantidad</th><th class="num">Precio</th><th class="num">Subtotal</th></tr></thead>
      <tbody>
      <?php foreach ($dev['items'] as $i): ?>
        <tr><td><?= e($i['nombre']) ?><br><small class="muted"><?= e($i['codigo']) ?></small></td>
          <td class="num"><?= cantidad($i['cantidad']) ?></td><td class="num"><?= dinero($i['precio_unitario']) ?></td><td class="num"><?= dinero($i['subtotal']) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
    <dl class="totales"><dt class="total">Total devuelto</dt><dd class="total"><?= dinero($dev['total']) ?></dd></dl>
  </div>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
