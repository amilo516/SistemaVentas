<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../helpers/permisos.php';
require_once __DIR__ . '/../../helpers/funciones.php';
require_once __DIR__ . '/../../controllers/ProductoController.php';
requirePermission('productos.editar');
$controller = new ProductoController();
$id = (int)($_GET['id'] ?? 0);
$datos = $controller->buscar($id);
if (!$datos) { flash('error', 'El producto no existe.'); redirect('index.php'); }
$errores = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarCsrf();
    $errores = $controller->actualizar($id, $_POST, $_FILES['imagen'] ?? []);
    if (!$errores) { flash('success', 'Producto actualizado.'); redirect('index.php'); }
    $datos = array_merge($datos, array_diff_key($_POST, ['csrf' => 1]), ['stock_original' => $datos['stock']]);
}
$categorias = $controller->categorias();
$esEdicion = true;
$titulo = 'Editar producto';
require __DIR__ . '/../layouts/header.php';
?>
<h1>Editar producto</h1>
<?php require __DIR__ . '/_form.php'; ?>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
