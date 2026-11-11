<?php
// Endpoint del panel flotante del Asesor IA: recibe la pregunta por POST y devuelve las recomendaciones en JSON.
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../helpers/permisos.php';
require_once __DIR__ . '/../../helpers/funciones.php';
require_once __DIR__ . '/../../helpers/respuesta.php';
require_once __DIR__ . '/../../controllers/AsesorController.php';
requirePermission('asesor.ver');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(false, 'Método no permitido.');
verificarCsrf();
set_time_limit(180);
$resultado = (new AsesorController())->analizar((string)($_POST['pregunta'] ?? ''));
jsonResponse(true, '', $resultado);
