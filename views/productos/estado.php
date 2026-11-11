<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../helpers/permisos.php';
require_once __DIR__ . '/../../helpers/funciones.php';
require_once __DIR__ . '/../../controllers/ProductoController.php';
requirePermission('productos.eliminar');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('index.php');
verificarCsrf();
$controller = new ProductoController();
$eliminar = ($_POST['accion'] ?? '') === 'eliminar';
$error = $eliminar ? $controller->eliminar((int)($_POST['id'] ?? 0)) : $controller->cambiarEstado((int)($_POST['id'] ?? 0));
$error ? flash('error', $error) : flash('success', $eliminar ? 'Producto eliminado.' : 'Estado del producto actualizado.');
redirect('index.php');
