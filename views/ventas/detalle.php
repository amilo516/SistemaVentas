<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../helpers/permisos.php';
require_once __DIR__ . '/../../controllers/VentaController.php';
require_once __DIR__ . '/../../controllers/DevolucionController.php';
requirePermission('ventas.ver');
$venta = (new VentaController())->ver((int)($_GET['id'] ?? 0));
if (!$venta) { flash('error', 'La venta no existe.'); redirect('index.php'); }
$badge = ['pagada' => 'badge-ok', 'pendiente' => 'badge-warn', 'anulada' => 'badge-danger'];
$devoluciones = tienePermiso('devoluciones.ver') || tienePermiso('devoluciones.crear') ? (new DevolucionController())->deVenta((int)$venta['id_venta']) : [];
$devuelto = array_sum(array_map(fn($d) => $d['estado'] !== 'anulada' ? (float)$d['total'] : 0, $devoluciones));
$titulo = 'Venta ' . $venta['numero_factura'];
require __DIR__ . '/../layouts/header.php';
?>
<div class="page-header">
  <h1>Venta <?= e($venta['numero_factura']) ?> <span class="badge <?= $badge[$venta['estado']] ?>"><?= ucfirst($venta['estado']) ?></span></h1>
  <div class="acciones">
    <?php if (tienePermiso('facturas.imprimir')): ?><a class="btn" href="../facturacion/imprimir.php?id=<?= (int)$venta['id_venta'] ?>" target="_blank" id="imprimir">Imprimir factura</a><?php endif; ?>
    <?php if (tienePermiso('devoluciones.crear') && $venta['estado'] === 'pagada' && $devuelto < (float)$venta['total'] - 0.009): ?><a class="btn btn-light" href="../devoluciones/crear.php?venta=<?= (int)$venta['id_venta'] ?>">Devolución</a><?php endif; ?>
    <?php if (tienePermiso('ventas.anular') && $venta['estado'] !== 'anulada'): ?><a class="btn btn-danger" href="anulada.php?id=<?= (int)$venta['id_venta'] ?>">Anular</a><?php endif; ?>
    <?php if (tienePermiso('ventas.crear')): ?><a class="btn btn-light" href="crear.php">Nueva venta</a><?php endif; ?>
  </div>
</div>
<div class="detalle-grid">
  <div class="card info">
    <dl>
      <dt>Fecha</dt><dd><?= date('d/m/Y H:i', strtotime($venta['fecha_venta'])) ?></dd>
      <dt>Cliente</dt><dd><?= e($venta['cliente'] ?? '—') ?><?= $venta['cliente_documento'] ? ' · ' . e($venta['cliente_documento']) : '' ?></dd>
      <dt>Vendedor</dt><dd><?= e($venta['usuario']) ?></dd>
      <?php foreach ($venta['pagos'] as $p): ?>
        <dt>Pago</dt><dd><?= e($p['metodo']) ?> · <?= dinero($p['monto']) ?><?= $p['referencia'] ? '<br><small>' . e($p['referencia']) . '</small>' : '' ?></dd>
      <?php endforeach; ?>
      <?php if ($venta['observaciones']): ?><dt>Observaciones</dt><dd class="pre"><?= e($venta['observaciones']) ?></dd><?php endif; ?>
    </dl>
  </div>
  <div class="card">
    <div class="table-wrap">
    <table class="table">
      <thead><tr><th>Producto</th><th class="num">Cantidad</th><th class="num">Precio</th><th class="num">Subtotal</th></tr></thead>
      <tbody>
      <?php foreach ($venta['items'] as $i): ?>
        <tr><td><?= e($i['nombre']) ?><br><small><?= e($i['codigo']) ?></small></td>
          <td class="num"><?= cantidad($i['cantidad']) ?></td><td class="num"><?= dinero($i['precio_unitario']) ?></td><td class="num"><?= dinero($i['subtotal']) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
    <dl class="totales">
      <dt>Subtotal</dt><dd><?= dinero($venta['subtotal']) ?></dd>
      <?php if ((float)$venta['descuento'] > 0): ?><dt>Descuento</dt><dd>−<?= dinero($venta['descuento']) ?></dd><?php endif; ?>
      <?php if ((float)$venta['impuesto'] > 0): ?><dt>Impuesto</dt><dd><?= dinero($venta['impuesto']) ?></dd><?php endif; ?>
      <dt class="total">Total</dt><dd class="total"><?= dinero($venta['total']) ?></dd>
      <?php if ($devuelto > 0): ?>
        <dt>Devuelto</dt><dd class="negativo">−<?= dinero($devuelto) ?></dd><dt>Neto</dt><dd><strong><?= dinero((float)$venta['total'] - $devuelto) ?></strong></dd>
      <?php endif; ?>
    </dl>
    <?php if ($devoluciones): ?>
      <h3 class="card-title dev-titulo">Devoluciones de esta venta</h3>
      <ul class="lista-dev"><?php foreach ($devoluciones as $d): ?>
        <li><a href="../devoluciones/detalle.php?id=<?= (int)$d['id_devolucion'] ?>">DEV-<?= str_pad((string)$d['id_devolucion'], 5, '0', STR_PAD_LEFT) ?></a>
          · <?= date('d/m/Y H:i', strtotime($d['fecha_devolucion'])) ?> · <?= e($d['motivo']) ?> · <strong><?= dinero($d['total']) ?></strong></li>
      <?php endforeach; ?></ul>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
