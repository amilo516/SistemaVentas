<?php
// API JSON del carrito del punto de venta (la usa assets/js/ventas.js).
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../helpers/permisos.php';
require_once __DIR__ . '/../../helpers/funciones.php';
require_once __DIR__ . '/../../helpers/respuesta.php';
require_once __DIR__ . '/../../controllers/CarritoController.php';
if (!isLoggedIn()) { http_response_code(401); jsonResponse(false, 'Tu sesión expiró. Vuelve a iniciar sesión.'); }
if (!tienePermiso('ventas.crear')) { http_response_code(403); jsonResponse(false, 'No tienes permiso para vender.'); }

$carrito = new CarritoController();
$accion = $_REQUEST['accion'] ?? 'estado';
try {
    if ($accion === 'buscar') jsonResponse(true, '', ['productos' => $carrito->buscarProductos((string)($_GET['q'] ?? ''))]);
    if ($accion !== 'estado') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !hash_equals(csrfToken(), (string)($_POST['csrf'] ?? ''))) {
            http_response_code(400); jsonResponse(false, 'Solicitud inválida. Recarga la página.');
        }
        $productoId = (int)($_POST['id_producto'] ?? 0);
        $cant = (float)str_replace(',', '.', (string)($_POST['cantidad'] ?? '1'));
        match ($accion) {
            'agregar' => $carrito->agregar($productoId, $cant),
            'actualizar' => $carrito->actualizar($productoId, $cant),
            'quitar' => $carrito->quitar($productoId),
            'vaciar' => $carrito->vaciar(),
            default => throw new RuntimeException('Acción no válida.'),
        };
    }
    jsonResponse(true, '', $carrito->estado());
} catch (RuntimeException $e) {
    jsonResponse(false, $e->getMessage(), $carrito->estado());
}
