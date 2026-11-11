<?php
// Lógica común de entradas.php, salidas.php y ajustes.php. Espera $tipo.
$controller = new InventarioController();
$errores = [];
$inicial = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarCsrf();
    [$afectados, $errores] = $controller->registrar($tipo, $_POST);
    if (!$errores) {
        flash('success', $afectados ? "Movimiento registrado: {$afectados} producto(s) actualizado(s)." : 'El conteo coincide con el stock: no hubo cambios.');
        redirect('index.php');
    }
    // Si hubo error, se vuelven a mostrar las líneas que el usuario había agregado.
    foreach ((array)($_POST['items'] ?? []) as $id => $l) {
        if ($p = $controller->producto((int)$id)) $inicial[] = $p + ['decimal' => permiteDecimales($p['unidad_medida']), 'cantidad' => $l['cantidad'] ?? '', 'costo' => $l['costo'] ?? ''];
    }
} elseif ($id = (int)($_GET['producto'] ?? 0)) {
    if ($p = $controller->producto($id)) $inicial[] = $p + ['decimal' => permiteDecimales($p['unidad_medida'])];
}
$titulo = InventarioController::TIPOS[$tipo]['titulo'];
require __DIR__ . '/../layouts/header.php';
require __DIR__ . '/_formulario.php';
require __DIR__ . '/../layouts/footer.php';
