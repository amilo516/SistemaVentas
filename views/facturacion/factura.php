<?php
// La factura se consulta desde el detalle de la venta.
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../helpers/permisos.php';
requirePermission('facturas.ver');
header('Location: ../ventas/detalle.php?id=' . (int)($_GET['id'] ?? 0));
exit;
