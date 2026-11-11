<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../helpers/permisos.php';
require_once __DIR__ . '/../../controllers/InventarioController.php';
requirePermission('inventario.ver');
$controller = new InventarioController();
$fecha = fn($v, $def) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$v) ? $v : $def;
$filtros = [
    'desde' => $fecha($_GET['desde'] ?? '', date('Y-m-d', strtotime('-30 days'))),
    'hasta' => $fecha($_GET['hasta'] ?? '', date('Y-m-d')),
    'tipo' => in_array($_GET['tipo'] ?? '', ['entrada', 'salida', 'ajuste', 'devolucion'], true) ? $_GET['tipo'] : '',
    'q' => mb_substr(trim($_GET['q'] ?? ''), 0, 100),
    'id_producto' => (int)($_GET['producto'] ?? 0),
];
$producto = $filtros['id_producto'] ? $controller->producto($filtros['id_producto']) : null;
$movimientos = $controller->listar($filtros);
$conteo = $controller->conteoPorTipo($filtros);
$etiquetas = ['entrada' => ['Entrada', 'badge-ok'], 'salida' => ['Salida', 'badge-danger'], 'ajuste' => ['Ajuste', 'badge-warn'], 'devolucion' => ['Devolución', 'badge']];
$titulo = 'Inventario';
require __DIR__ . '/../layouts/header.php';
?>
<div class="page-header">
  <h1>Inventario<?= $producto ? ': ' . e($producto['nombre']) : '' ?></h1>
  <div class="acciones">
    <?php $q = $producto ? '?producto=' . (int)$producto['id_producto'] : ''; ?>
    <?php if (tienePermiso('inventario.entrada')): ?><a class="btn" href="entradas.php<?= $q ?>">+ Entrada</a><?php endif; ?>
    <?php if (tienePermiso('inventario.salida')): ?><a class="btn btn-light" href="salidas.php<?= $q ?>">− Salida</a><?php endif; ?>
    <?php if (tienePermiso('inventario.ajuste')): ?><a class="btn btn-light" href="ajustes.php<?= $q ?>">Ajuste por conteo</a><?php endif; ?>
  </div>
</div>
<?php if ($producto): ?>
  <p class="muted">Kardex de <?= e($producto['codigo']) ?> · Stock actual: <strong><?= cantidad($producto['stock']) ?> <?= e($producto['unidad_medida']) ?></strong> · <a href="index.php">Ver todos los productos</a></p>
<?php endif; ?>
<form class="filtros card" method="get">
  <?php if ($producto): ?><input type="hidden" name="producto" value="<?= (int)$producto['id_producto'] ?>"><?php else: ?>
  <label class="grow">Producto<input type="search" name="q" value="<?= e($filtros['q']) ?>" placeholder="Nombre o código"></label><?php endif; ?>
  <label>Tipo
    <select name="tipo"><option value="">Todos</option>
      <?php foreach ($etiquetas as $k => [$v]): ?><option value="<?= $k ?>" <?= $filtros['tipo'] === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?>
    </select>
  </label>
  <label>Desde<input type="date" name="desde" value="<?= e($filtros['desde']) ?>"></label>
  <label>Hasta<input type="date" name="hasta" value="<?= e($filtros['hasta']) ?>"></label>
  <button class="btn">Filtrar</button>
</form>
<p class="resumen-filtro"><?= count($movimientos) ?> movimientos ·
  Entradas: <strong><?= (int)$conteo['entrada'] ?></strong> · Salidas: <strong><?= (int)$conteo['salida'] ?></strong> ·
  Ajustes: <strong><?= (int)$conteo['ajuste'] ?></strong> · Devoluciones: <strong><?= (int)$conteo['devolucion'] ?></strong></p>
<div class="table-card">
<table class="table">
  <thead><tr><th>Fecha</th><?php if (!$producto): ?><th>Producto</th><?php endif; ?><th>Tipo</th><th class="num">Cambio</th><th class="num">Stock</th><th>Motivo</th><th>Referencia</th><th>Usuario</th></tr></thead>
  <tbody>
  <?php foreach ($movimientos as $m): [$etq, $cls] = $etiquetas[$m['tipo']]; $cambio = (float)$m['stock_nuevo'] - (float)$m['stock_anterior']; ?>
    <tr>
      <td><?= date('d/m/Y H:i', strtotime($m['fecha_movimiento'])) ?></td>
      <?php if (!$producto): ?><td><a href="?producto=<?= (int)$m['id_producto'] ?>&desde=<?= e($filtros['desde']) ?>&hasta=<?= e($filtros['hasta']) ?>" title="Ver kardex"><?= e($m['producto']) ?></a><br><small class="muted"><?= e($m['codigo']) ?></small></td><?php endif; ?>
      <td><span class="badge <?= $cls ?>"><?= $etq ?></span></td>
      <td class="num <?= $cambio > 0 ? 'positivo' : ($cambio < 0 ? 'negativo' : '') ?>"><?= $cambio > 0 ? '+' : ($cambio < 0 ? '−' : '') ?><?= cantidad(abs($cambio)) ?></td>
      <td class="num"><small class="muted"><?= cantidad($m['stock_anterior']) ?> →</small> <strong><?= cantidad($m['stock_nuevo']) ?></strong></td>
      <td class="wrap"><?= e($m['motivo'] ?? '') ?></td>
      <td><?php if ($m['id_venta'] && tienePermiso('ventas.ver')): ?><a href="../ventas/detalle.php?id=<?= (int)$m['id_venta'] ?>"><?= e($m['numero_factura'] ?? $m['referencia']) ?></a><?php else: ?><?= e($m['referencia'] ?? '') ?><?php endif; ?></td>
      <td><?= e($m['usuario']) ?></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$movimientos): ?><tr><td colspan="8" class="empty">No hay movimientos con estos filtros.</td></tr><?php endif; ?>
  </tbody>
</table>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
