<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../helpers/permisos.php';
require_once __DIR__ . '/../../helpers/funciones.php';
require_once __DIR__ . '/../../controllers/ClienteController.php';
requirePermission('clientes.crear');
$controller = new ClienteController();
// 'pos': se abrió desde Nueva venta; al guardar vuelve a la venta con el cliente seleccionado.
$volver = ($_REQUEST['volver'] ?? '') === 'pos' ? 'pos' : '';
$errores = [];
$datos = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarCsrf();
    $datos = $_POST;
    [$id, $errores] = $controller->crear($_POST);
    if (!$errores) {
        flash('success', 'Cliente "' . trim($_POST['nombre']) . '" creado.');
        redirect($volver === 'pos' ? '../ventas/crear.php?cliente=' . $id : 'index.php');
    }
}
$esEdicion = false;
$soloLectura = false;
$titulo = 'Nuevo cliente';
require __DIR__ . '/../layouts/header.php';
?>
<h1>Nuevo cliente</h1>
<?php require __DIR__ . '/_form.php'; ?>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
