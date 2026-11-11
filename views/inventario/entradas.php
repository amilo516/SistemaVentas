<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../helpers/permisos.php';
require_once __DIR__ . '/../../controllers/InventarioController.php';
$tipo = 'entrada';
requirePermission(InventarioController::TIPOS[$tipo]['permiso']);
require __DIR__ . '/_procesar.php';
