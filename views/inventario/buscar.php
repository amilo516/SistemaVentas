<?php
// API JSON: búsqueda de productos para los formularios de inventario (assets/js/inventario.js).
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../helpers/permisos.php';
require_once __DIR__ . '/../../helpers/respuesta.php';
require_once __DIR__ . '/../../controllers/InventarioController.php';
if (!isLoggedIn()) { http_response_code(401); jsonResponse(false, 'Tu sesión expiró. Vuelve a iniciar sesión.'); }
if (!tienePermiso('inventario.entrada') && !tienePermiso('inventario.salida') && !tienePermiso('inventario.ajuste')) {
    http_response_code(403); jsonResponse(false, 'No tienes permiso para mover inventario.');
}
jsonResponse(true, '', ['productos' => (new InventarioController())->buscarProductos((string)($_GET['q'] ?? ''))]);
