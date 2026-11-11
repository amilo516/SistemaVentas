<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../helpers/permisos.php';
require_once __DIR__ . '/../../helpers/funciones.php';
require_once __DIR__ . '/../../controllers/ProductoController.php';
requirePermission('productos.ver');
$controller = new ProductoController();
$resumen = $controller->resumenStock();
$productos = $controller->listar(['estado' => '1', 'stock_bajo' => true]);
$titulo = 'Stock bajo';
require __DIR__ . '/../layouts/header.php';
?>
<div class="page-header"><h1>Stock bajo</h1><a class="btn btn-light" href="index.php">Ver todos los productos</a></div>
<div class="stats">
  <div class="stat"><span class="stat-label"><?= icono('productos') ?>Productos activos</span><strong class="stat-value"><?= (int)$resumen['activos'] ?></strong></div>
  <div class="stat"><span class="stat-label"><?= icono('inventario') ?>Con stock bajo</span><strong class="stat-value"><?= (int)$resumen['bajos'] ?></strong><span class="stat-detail">En o por debajo del stock mínimo</span></div>
  <div class="stat"><span class="stat-label"><?= icono('devoluciones') ?>Agotados</span><strong class="stat-value"><?= (int)$resumen['agotados'] ?></strong></div>
  <?php if (tienePermiso('productos.editar')): ?><div class="stat"><span class="stat-label"><?= icono('caja') ?>Inventario al costo</span><strong class="stat-value"><?= dinero($resumen['valor_costo']) ?></strong></div><?php endif; ?>
</div>
<h2 class="section-title">Productos por reabastecer</h2>
<div class="table-card">
<table class="table">
  <thead><tr><th>Código</th><th>Producto</th><th>Categoría</th><th class="num">Stock</th><th class="num">Mínimo</th><th class="num">Faltan</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($productos as $p): ?>
    <tr>
      <td><?= e($p['codigo']) ?></td><td><strong><?= e($p['nombre']) ?></strong></td><td><?= e($p['categoria']) ?></td>
      <td class="num"><span class="badge <?= (float)$p['stock'] <= 0 ? 'badge-danger' : 'badge-warn' ?>"><?= cantidad($p['stock']) ?> <?= e($p['unidad_medida']) ?></span></td>
      <td class="num"><?= cantidad($p['stock_minimo']) ?></td>
      <td class="num"><?= cantidad(max((float)$p['stock_minimo'] - (float)$p['stock'], 0)) ?></td>
      <td class="acciones"><?php if (tienePermiso('productos.editar')): ?><a class="btn btn-light btn-sm" href="editar.php?id=<?= (int)$p['id_producto'] ?>">Editar</a><?php endif; ?></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$productos): ?><tr><td colspan="7" class="empty">Todos los productos activos tienen stock suficiente.</td></tr><?php endif; ?>
  </tbody>
</table>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
