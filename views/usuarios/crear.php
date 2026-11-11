<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../helpers/permisos.php';
require_once __DIR__ . '/../../controllers/UsuarioController.php';
requirePermission('usuarios.crear');
$controller = new UsuarioController();
$errores = [];
$datos = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarCsrf();
    $datos = $_POST;
    $errores = $controller->crear($datos);
    if (!$errores) { flash('success', 'Usuario "' . trim($datos['usuario']) . '" creado. Ya puede iniciar sesión.'); redirect('index.php'); }
}
$roles = $controller->roles();
$esEdicion = false;
$bloquearRol = false;
$titulo = 'Nuevo usuario';
require __DIR__ . '/../layouts/header.php';
?>
<h1>Nuevo usuario</h1>
<?php require __DIR__ . '/_form.php'; ?>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
