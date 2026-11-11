<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../helpers/permisos.php';
require_once __DIR__ . '/../../controllers/UsuarioController.php';
requirePermission('usuarios.editar');
$controller = new UsuarioController();
$id = (int)($_GET['id'] ?? 0);
$datos = $controller->buscar($id);
if (!$datos) { flash('error', 'El usuario no existe.'); redirect('index.php'); }
$errores = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarCsrf();
    $errores = $controller->actualizar($id, $_POST);
    if (!$errores) { flash('success', 'Usuario actualizado.'); redirect('index.php'); }
    $datos = array_merge($datos, array_intersect_key($_POST, array_flip(['nombre', 'usuario', 'rol_id'])));
}
$roles = $controller->roles();
$esEdicion = true;
$bloquearRol = $id === (int)$_SESSION['id_usuario'];
$titulo = 'Editar usuario';
require __DIR__ . '/../layouts/header.php';
?>
<h1>Editar usuario</h1>
<?php require __DIR__ . '/_form.php'; ?>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
