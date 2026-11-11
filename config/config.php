<?php
declare(strict_types=1);

// El sistema usa funciones de PHP 8.1. Con una versión anterior se muestra un aviso claro en vez de un error.
if (PHP_VERSION_ID < 80100) {
    http_response_code(500);
    exit('<h1>Versión de PHP no compatible</h1><p>El Sistema de Ventas necesita PHP 8.1 o superior y este servidor tiene PHP '
        . PHP_VERSION . '. Instala una versión reciente de XAMPP.</p>');
}

// Ruta web del proyecto (ej. "/sistema_ventas" o "/Sistema-Ventas"), detectada a partir de la carpeta real,
// para que el sistema funcione con cualquier nombre de carpeta dentro de htdocs.
function detectarAppUrl(): string {
    $raiz = str_replace('\\', '/', (string)realpath(dirname(__DIR__)));
    $script = str_replace('\\', '/', (string)realpath($_SERVER['SCRIPT_FILENAME'] ?? ''));
    $nombre = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    if ($raiz !== '' && $script !== '' && stripos($script, $raiz . '/') === 0) {
        $relativo = substr($script, strlen($raiz));   // ej. "/public/login.php"
        if (strcasecmp(substr($nombre, -strlen($relativo)), $relativo) === 0) {
            return rtrim(substr($nombre, 0, -strlen($relativo)), '/');
        }
    }
    return '/sistema_ventas';
}

define('APP_NAME', 'Sistema de Ventas');
define('APP_URL', detectarAppUrl());
define('BASE_URL', APP_URL . '/public');
date_default_timezone_set('America/Bogota');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
