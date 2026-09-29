<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../helpers/permisos.php';
require_once __DIR__ . '/../../controllers/VentaController.php';
requirePermission('ventas.ver');
$controller = new VentaController();
$fecha = fn($v, $def) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$v) ? $v : $def;
$filtros = [
    'desde' => $fecha($_GET['desde'] ?? '', date('Y-m-01')),
    'hasta' => $fecha($_GET['hasta'] ?? '', date('Y-m-d')),
    'estado' => in_array($_GET['estado'] ?? '', ['pagada', 'pendiente', 'anulada'], true) ? $_GET['estado'] : '',
    'q' => mb_substr(trim($_GET['q'] ?? ''), 0, 100),
];
$ventas = $controller->listar($filtros);
$validas = array_filter($ventas, fn($v) => $v['estado'] !== 'anulada');
$totalValidas = array_sum(array_column($validas, 'total'));
$badge = ['pagada' => 'badge-ok', 'pendiente' => 'badge-warn', 'anulada' => 'badge-danger'];
$titulo = 'Ventas';
require __DIR__ . '/../layouts/header.php';
?>
<div class="page-header">
  <h1>Ventas<?= $controller->soloPropias() ? ' <small class="muted">(mis ventas)</small>' : '' ?></h1>
  <?php if (tienePermiso('ventas.crear')): ?><a class="btn" href="crear.php">+ Nueva venta</a><?php endif; ?>
</div>
<form class="filtros card" method="get">
  <label>Desde<input type="date" name="desde" value="<?= e($filtros['desde']) ?>"></label>
  <label>Hasta<input type="date" name="hasta" value="<?= e($filtros['hasta']) ?>"></label>
  <label>Estado
    <select name="estado">
      <option value="">Todos</option>
      <?php foreach (['pagada' => 'Pagada', 'pendiente' => 'Pendiente', 'anulada' => 'Anulada'] as $k => $v): ?>
        <option value="<?= $k ?>" <?= $filtros['estado'] === $k ? 'selected' : '' ?>><?= $v ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label class="grow">Buscar<input type="search" name="q" value="<?= e($filtros['q']) ?>" placeholder="N.º de factura, cliente o documento"></label>
  <button class="btn">Filtrar</button>
</form>
<p class="resumen-filtro"><?= count($ventas) ?> ventas · Total (sin anuladas): <strong><?= dinero($totalValidas) ?></strong></p>
<div class="table-card">
<table class="table">
  <thead><tr><th>Factura</th><th>Fecha</th><th>Cliente</th><th>Vendedor</th><th>Pago</th><th class="num">Total</th><th>Estado</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($ventas as $v): ?>
    <tr class="<?= $v['estado'] === 'anulada' ? 'inactivo' : '' ?>">
      <td><a href="detalle.php?id=<?= (int)$v['id_venta'] ?>"><?= e($v['numero_factura']) ?></a></td>
      <td><?= date('d/m/Y H:i', strtotime($v['fecha_venta'])) ?></td>
      <td><?= e($v['cliente'] ?? '—') ?></td>
      <td><?= e($v['usuario']) ?></td>
      <td><?= e($v['metodos'] ?? '—') ?></td>
      <td class="num"><?= dinero($v['total']) ?></td>
      <td><span class="badge <?= $badge[$v['estado']] ?>"><?= ucfirst($v['estado']) ?></span></td>
      <td class="acciones"><a class="btn btn-light btn-sm" href="detalle.php?id=<?= (int)$v['id_venta'] ?>">Ver</a></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$ventas): ?><tr><td colspan="8" class="empty">No hay ventas con estos filtros.</td></tr><?php endif; ?>
  </tbody>
</table>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
