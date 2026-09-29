<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../helpers/permisos.php';
require_once __DIR__ . '/../../helpers/funciones.php';
require_once __DIR__ . '/../../controllers/ProductoController.php';
requirePermission('productos.crear');
$controller = new ProductoController();
$errores = [];
$datos = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarCsrf();
    $datos = $_POST;
    [$id, $errores] = $controller->crear($_POST, $_FILES['imagen'] ?? []);
    if (!$errores) { flash('success', 'Producto "' . trim($_POST['nombre']) . '" creado.'); redirect('index.php'); }
}
$categorias = $controller->categorias();
$esEdicion = false;
$titulo = 'Nuevo producto';
require __DIR__ . '/../layouts/header.php';
?>
<h1>Nuevo producto</h1>
<?php require __DIR__ . '/_form.php'; ?>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
