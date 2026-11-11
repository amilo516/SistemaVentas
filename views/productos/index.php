<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../helpers/permisos.php';
require_once __DIR__ . '/../../helpers/funciones.php';
require_once __DIR__ . '/../../controllers/ProductoController.php';
requirePermission('productos.ver');
$controller = new ProductoController();
$filtros = [
    'q' => mb_substr(trim($_GET['q'] ?? ''), 0, 100),
    'categoria' => (int)($_GET['categoria'] ?? 0),
    'estado' => in_array($_GET['estado'] ?? '1', ['1', '0', ''], true) ? ($_GET['estado'] ?? '1') : '1',
    'stock_bajo' => !empty($_GET['stock_bajo']),
];
$productos = $controller->listar($filtros);
$categorias = $controller->categorias();
$verCostos = tienePermiso('productos.editar');
$unidades = unidadesMedida();
$titulo = 'Productos';
require __DIR__ . '/../layouts/header.php';
?>
<div class="page-header">
  <h1>Productos</h1>
  <div class="acciones">
    <a class="btn btn-light" href="stock.php">Stock bajo</a>
    <?php if (tienePermiso('categorias.ver')): ?><a class="btn btn-light" href="../categorias/index.php">Categorías</a><?php endif; ?>
    <?php if (tienePermiso('productos.crear')): ?><a class="btn" href="crear.php">+ Nuevo producto</a><?php endif; ?>
  </div>
</div>
<form class="filtros card" method="get">
  <label class="grow">Buscar<input type="search" name="q" value="<?= e($filtros['q']) ?>" placeholder="Nombre, código o código de barras"></label>
  <label>Categoría
    <select name="categoria">
      <option value="">Todas</option>
      <?php foreach ($categorias as $c): ?><option value="<?= (int)$c['id_categoria'] ?>" <?= $filtros['categoria'] === (int)$c['id_categoria'] ? 'selected' : '' ?>><?= e($c['nombre']) ?></option><?php endforeach; ?>
    </select>
  </label>
  <label>Estado
    <select name="estado">
      <?php foreach (['1' => 'Activos', '0' => 'Inactivos', '' => 'Todos'] as $k => $v): ?><option value="<?= $k ?>" <?= $filtros['estado'] === (string)$k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?>
    </select>
  </label>
  <label class="check"><input type="checkbox" name="stock_bajo" value="1" <?= $filtros['stock_bajo'] ? 'checked' : '' ?>> Solo stock bajo</label>
  <button class="btn">Filtrar</button>
</form>
<p class="resumen-filtro"><?= count($productos) ?> productos</p>
<div class="table-card">
<table class="table">
  <thead><tr><th></th><th>Código</th><th>Producto</th><th>Categoría</th>
    <?php if ($verCostos): ?><th class="num">Costo</th><?php endif; ?><th class="num">Precio</th><th class="num">Stock</th><th>Estado</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($productos as $p): $bajo = (float)$p['stock'] <= (float)$p['stock_minimo']; ?>
    <tr class="<?= $p['estado'] ? '' : 'inactivo' ?>">
      <td class="thumb-cell"><?php if ($img = urlImagenProducto($p['imagen'])): ?><img class="thumb" src="<?= e($img) ?>" alt=""><?php else: ?><span class="thumb thumb-vacio"><?= icono('productos') ?></span><?php endif; ?></td>
      <td><?= e($p['codigo']) ?><?= $p['codigo_barras'] ? '<br><small class="muted">' . e($p['codigo_barras']) . '</small>' : '' ?></td>
      <td><strong><?= e($p['nombre']) ?></strong></td>
      <td><?= e($p['categoria']) ?></td>
      <?php if ($verCostos): ?><td class="num"><?= dinero($p['precio_compra']) ?></td><?php endif; ?>
      <td class="num"><?= dinero($p['precio_venta']) ?></td>
      <td class="num"><?php $badgeStock = '<span class="badge ' . ((float)$p['stock'] <= 0 ? 'badge-danger' : ($bajo ? 'badge-warn' : 'badge-ok')) . '">' . cantidad($p['stock']) . ' ' . e($p['unidad_medida']) . '</span>'; ?>
        <?php if (tienePermiso('inventario.ver')): ?><a class="stock-kardex" href="../inventario/index.php?producto=<?= (int)$p['id_producto'] ?>&desde=2000-01-01" title="Ver kardex (movimientos de inventario)"><?= $badgeStock ?></a><?php else: ?><?= $badgeStock ?><?php endif; ?></td>
      <td><span class="badge <?= $p['estado'] ? 'badge-ok' : 'badge-off' ?>"><?= $p['estado'] ? 'Activo' : 'Inactivo' ?></span></td>
      <td class="acciones">
        <?php if (tienePermiso('productos.editar')): ?><a class="btn btn-light btn-sm" href="editar.php?id=<?= (int)$p['id_producto'] ?>">Editar</a><?php endif; ?>
        <?php if (tienePermiso('productos.eliminar')): ?>
          <form method="post" action="estado.php" onsubmit="return confirm('¿<?= $p['estado'] ? 'Desactivar' : 'Activar' ?> <?= e(addslashes($p['nombre'])) ?>?')">
            <?= csrfCampo() ?><input type="hidden" name="id" value="<?= (int)$p['id_producto'] ?>">
            <button class="btn btn-sm <?= $p['estado'] ? 'btn-danger' : 'btn-light' ?>"><?= $p['estado'] ? 'Desactivar' : 'Activar' ?></button>
          </form>
          <?= botonEliminar('estado.php', (int)$p['id_producto'], $p['nombre']) ?>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$productos): ?><tr><td colspan="9" class="empty">No hay productos con estos filtros.<?= !$categorias && tienePermiso('categorias.gestionar') ? ' Empieza creando una <a href="../categorias/index.php">categoría</a>.' : '' ?></td></tr><?php endif; ?>
  </tbody>
</table>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
