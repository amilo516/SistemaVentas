<?php
declare(strict_types=1);

// Arranque de PHPUnit. No incluye config/config.php porque ese archivo calcula rutas web y envía
// cabeceras; las pruebas cargan solo lo que necesitan.
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/constantes.php';

// Sesión simulada: los controladores leen $_SESSION, pero en CLI no se llama a session_start().
$_SESSION = [];
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

// BaseDatosTestCase (base de las pruebas de integración) se carga con el autoload-dev de composer.json.
