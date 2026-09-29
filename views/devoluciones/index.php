<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../helpers/permisos.php';
require_once __DIR__ . '/../../controllers/DevolucionController.php';
requirePermission('devoluciones.ver');
$fecha = fn($v, $def) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$v) ? $v : $def;
$filtros = [
    'desde' => $fecha($_GET['desde'] ?? '', date('Y-m-01')),
    'hasta' => $fecha($_GET['hasta'] ?? '', date('Y-m-d')),
    'q' => mb_substr(trim($_GET['q'] ?? ''), 0, 100),
];
$devoluciones = (new DevolucionController())->listar($filtros);
$total = array_sum(array_map(fn($d) => $d['estado'] !== 'anulada' ? (float)$d['total'] : 0, $devoluciones));
$titulo = 'Devoluciones';
require __DIR__ . '/../layouts/header.php';
?>
<div class="page-header">
  <h1>Devoluciones</h1>
  <?php if (tienePermiso('devoluciones.crear')): ?><a class="btn" href="crear.php">+ Nueva devolución</a><?php endif; ?>
</div>
<form class="filtros card" method="get">
  <label class="grow">Buscar<input type="search" name="q" value="<?= e($filtros['q']) ?>" placeholder="N.º de factura, cliente o documento"></label>
  <label>Desde<input type="date" name="desde" value="<?= e($filtros['desde']) ?>"></label>
  <label>Hasta<input type="date" name="hasta" value="<?= e($filtros['hasta']) ?>"></label>
  <button class="btn">Filtrar</button>
</form>
<p class="resumen-filtro"><?= count($devoluciones) ?> devoluciones · Total devuelto: <strong><?= dinero($total) ?></strong></p>
<div class="table-card">
<table class="table">
  <thead><tr><th>N.º</th><th>Fecha</th><th>Factura</th><th>Cliente</th><th>Motivo</th><th>Registró</th><th class="num">Total</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($devoluciones as $d): ?>
    <tr class="<?= $d['estado'] === 'anulada' ? 'inactivo' : '' ?>">
      <td><a href="detalle.php?id=<?= (int)$d['id_devolucion'] ?>">DEV-<?= str_pad((string)$d['id_devolucion'], 5, '0', STR_PAD_LEFT) ?></a></td>
      <td><?= date('d/m/Y H:i', strtotime($d['fecha_devolucion'])) ?></td>
      <td><?php if (tienePermiso('ventas.ver')): ?><a href="../ventas/detalle.php?id=<?= (int)$d['id_venta'] ?>"><?= e($d['numero_factura']) ?></a><?php else: ?><?= e($d['numero_factura']) ?><?php endif; ?></td>
      <td><?= e($d['cliente'] ?? '—') ?></td>
      <td class="wrap"><?= e($d['motivo']) ?></td>
      <td><?= e($d['usuario']) ?></td>
      <td class="num"><?= dinero($d['total']) ?></td>
      <td class="acciones"><a class="btn btn-light btn-sm" href="detalle.php?id=<?= (int)$d['id_devolucion'] ?>">Ver</a></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$devoluciones): ?><tr><td colspan="8" class="empty">No hay devoluciones en este periodo.</td></tr><?php endif; ?>
  </tbody>
</table>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
