<?php
declare(strict_types=1);

define('APP_NAME', 'Sistema de Ventas');
define('APP_URL', '/sistema_ventas');
define('BASE_URL', APP_URL . '/public');
date_default_timezone_set('America/Bogota');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
