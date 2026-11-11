<?php
// Formulario de varias líneas para entradas, salidas y ajustes. Espera: $tipo, $errores, $inicial (líneas a precargar).
$cfg = InventarioController::TIPOS[$tipo];
$ayuda = [
    'entrada' => 'Mercancía que llega (compra a proveedor, traslado, donación). Suma al stock.',
    'salida' => 'Mercancía que sale sin venderse (dañada, vencida, consumo interno, pérdida). Resta del stock.',
    'ajuste' => 'Escribe la cantidad que contaste físicamente. El sistema calcula la diferencia y deja el stock igual al conteo.',
][$tipo];
$motivos = [
    'entrada' => ['Compra a proveedor', 'Traslado desde otra sede', 'Devolución a proveedor anulada', 'Donación'],
    'salida' => ['Producto dañado', 'Producto vencido', 'Consumo interno', 'Pérdida o robo', 'Devolución a proveedor'],
    'ajuste' => ['Conteo físico', 'Corrección de error de digitación', 'Inventario inicial'],
][$tipo];
?>
<div class="page-header"><h1><?= e($cfg['titulo']) ?></h1><a class="btn btn-light" href="index.php">Ver movimientos</a></div>
<p class="muted"><?= e($ayuda) ?></p>
<?php if ($errores): ?><div class="alert error"><ul><?php foreach ($errores as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<form method="post" id="form-inv" data-tipo="<?= e($tipo) ?>" data-buscar="buscar.php">
  <?= csrfCampo() ?>
  <div class="card">
    <label class="pos-search"><?= icono('productos') ?><input type="search" id="buscar" placeholder="Buscar producto por nombre o código, o escanear código de barras…" autocomplete="off" autofocus></label>
    <div id="resultados" class="resultados" hidden></div>
    <div class="table-wrap">
    <table class="table inv-lineas">
      <thead><tr>
        <th>Producto</th><th class="num">Stock actual</th>
        <th class="num"><?= $tipo === 'ajuste' ? 'Cantidad contada' : 'Cantidad' ?></th>
        <?php if ($tipo === 'entrada'): ?><th class="num">Costo unitario</th><?php endif; ?>
        <th class="num"><?= $tipo === 'ajuste' ? 'Diferencia' : 'Stock final' ?></th><th></th>
      </tr></thead>
      <tbody id="lineas"></tbody>
    </table>
    </div>
    <p id="sin-lineas" class="empty">Busca y agrega los productos.</p>
  </div>
  <div class="card form-inline inv-datos">
    <div class="fila">
      <label class="grow"><span>Motivo<?= $tipo === 'entrada' ? ' <small>(opcional)</small>' : '' ?></span>
        <input type="text" name="motivo" list="motivos" value="<?= e($_POST['motivo'] ?? '') ?>" maxlength="255" <?= $tipo === 'entrada' ? '' : 'required' ?> placeholder="Elige o escribe el motivo">
        <datalist id="motivos"><?php foreach ($motivos as $m): ?><option value="<?= e($m) ?>"><?php endforeach; ?></datalist>
      </label>
      <label class="grow"><span>Referencia <small>(opcional)</small></span><input type="text" name="referencia" value="<?= e($_POST['referencia'] ?? '') ?>" maxlength="100" placeholder="<?= $tipo === 'entrada' ? 'Ej. Factura proveedor 1234' : 'Ej. Acta 05' ?>"></label>
      <button class="btn" id="guardar"><?= e($cfg['verbo']) ?></button>
    </div>
  </div>
</form>
<script>window.INV_LINEAS = <?= json_encode(array_values($inicial), JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>;</script>
<script src="<?= APP_URL ?>/assets/js/inventario.js?v=<?= filemtime(__DIR__ . '/../../assets/js/inventario.js') ?>" defer></script>
