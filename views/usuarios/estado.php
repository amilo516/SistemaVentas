<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../helpers/permisos.php';
require_once __DIR__ . '/../../controllers/UsuarioController.php';
requirePermission('usuarios.eliminar');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('index.php');
verificarCsrf();
$controller = new UsuarioController();
$eliminar = ($_POST['accion'] ?? '') === 'eliminar';
$error = $eliminar ? $controller->eliminar((int)($_POST['id'] ?? 0)) : $controller->cambiarEstado((int)($_POST['id'] ?? 0));
$error ? flash('error', $error) : flash('success', $eliminar ? 'Usuario eliminado.' : 'Estado del usuario actualizado.');
redirect('index.php');
