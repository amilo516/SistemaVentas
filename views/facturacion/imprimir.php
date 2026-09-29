<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../helpers/permisos.php';
require_once __DIR__ . '/../../controllers/VentaController.php';
require_once __DIR__ . '/../../helpers/empresa.php';
requirePermission('facturas.imprimir');
$venta = (new VentaController())->ver((int)($_GET['id'] ?? 0));
if (!$venta) { http_response_code(404); exit('La venta no existe.'); }
$empresa = datosEmpresa();
?><!doctype html><html lang="es"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Factura <?= e($venta['numero_factura']) ?></title>
<style>
  *{box-sizing:border-box}body{margin:0;background:#e5e7eb;font-family:"Courier New",monospace;font-size:13px;color:#000}
  .ticket{width:80mm;max-width:100%;margin:20px auto;background:#fff;padding:14px 12px;position:relative}
  h1{font-size:16px;margin:0}.c{text-align:center}.r{text-align:right}p{margin:2px 0}
  hr{border:0;border-top:1px dashed #000;margin:8px 0}table{width:100%;border-collapse:collapse}td{vertical-align:top;padding:2px 0}
  .total td{font-size:16px;font-weight:bold}.anulada{position:absolute;top:40%;left:0;right:0;text-align:center;font-size:36px;color:rgba(220,38,38,.35);transform:rotate(-20deg);font-weight:bold}
  .acciones{text-align:center;margin:12px}.logo{max-width:60%;max-height:70px;margin-bottom:6px}.acciones button{font-size:14px;padding:8px 16px;cursor:pointer}
  @media print{body{background:#fff}.ticket{margin:0;width:auto}.acciones{display:none}@page{margin:4mm}}
</style></head><body>
<div class="acciones"><button onclick="window.print()">Imprimir</button> <button onclick="window.close()">Cerrar</button></div>
<div class="ticket">
  <?php if ($venta['estado'] === 'anulada'): ?><div class="anulada">ANULADA</div><?php endif; ?>
  <div class="c">
    <?php if ($logo = urlLogoEmpresa()): ?><img class="logo" src="<?= e($logo) ?>" alt=""><?php endif; ?>
    <h1><?= e(nombreEmpresa()) ?></h1>
    <?php if (!empty($empresa['nit'])): ?><p>NIT: <?= e($empresa['nit']) ?></p><?php endif; ?>
    <?php if (!empty($empresa['direccion'])): ?><p><?= e($empresa['direccion']) ?></p><?php endif; ?>
    <?php if (!empty($empresa['telefono'])): ?><p>Tel: <?= e($empresa['telefono']) ?></p><?php endif; ?>
    <?php if (!empty($empresa['email'])): ?><p><?= e($empresa['email']) ?></p><?php endif; ?>
  </div>
  <hr>
  <p><strong>Factura: <?= e($venta['numero_factura']) ?></strong></p>
  <p>Fecha: <?= date('d/m/Y H:i', strtotime($venta['fecha_venta'])) ?></p>
  <p>Cliente: <?= e($venta['cliente'] ?? '—') ?><?= $venta['cliente_documento'] ? ' (' . e($venta['cliente_documento']) . ')' : '' ?></p>
  <p>Atendió: <?= e($venta['usuario']) ?></p>
  <hr>
  <table>
    <?php foreach ($venta['items'] as $i): ?>
      <tr><td colspan="2"><?= e($i['nombre']) ?></td></tr>
      <tr><td><?= cantidad($i['cantidad']) ?> x <?= dinero($i['precio_unitario']) ?></td><td class="r"><?= dinero($i['subtotal']) ?></td></tr>
    <?php endforeach; ?>
  </table>
  <hr>
  <table>
    <tr><td>Subtotal</td><td class="r"><?= dinero($venta['subtotal']) ?></td></tr>
    <?php if ((float)$venta['descuento'] > 0): ?><tr><td>Descuento</td><td class="r">-<?= dinero($venta['descuento']) ?></td></tr><?php endif; ?>
    <?php if ((float)$venta['impuesto'] > 0): ?><tr><td>Impuesto</td><td class="r"><?= dinero($venta['impuesto']) ?></td></tr><?php endif; ?>
    <tr class="total"><td>TOTAL</td><td class="r"><?= dinero($venta['total']) ?></td></tr>
  </table>
  <hr>
  <?php foreach ($venta['pagos'] as $p): ?>
    <p><?= e($p['metodo']) ?>: <?= dinero($p['monto']) ?></p>
    <?php if ($p['referencia']): ?><p><?= e($p['referencia']) ?></p><?php endif; ?>
  <?php endforeach; ?>
  <hr>
  <p class="c"><?= e(trim($empresa['pie_factura'] ?? '') ?: '¡Gracias por su compra!') ?></p>
</div>
<script>if (new URLSearchParams(location.search).has('auto')) window.addEventListener('load', () => window.print());</script>
</body></html>
