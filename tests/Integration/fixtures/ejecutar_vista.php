<?php
// Apoyo para pruebas: ejecuta una vista real en un proceso aparte y escribe su HTML en la salida.
// La sesión se guarda en un archivo con id fijo, así dos ejecuciones seguidas (POST y luego GET)
// comparten la misma sesión, como en el navegador.
// Uso: php ejecutar_vista.php <json en base64> con {vista, metodo, sesion_id, ruta_sesiones, sesion?, post?, get?}
declare(strict_types=1);

if (getenv('DB_NAME') !== 'sistema_ventas_test') {
    fwrite(STDERR, "ejecutar_vista.php solo funciona con DB_NAME=sistema_ventas_test.\n");
    exit(2);
}
$p = json_decode((string)base64_decode($argv[1] ?? '', true), true, 512, JSON_THROW_ON_ERROR);
$raiz = dirname(__DIR__, 2) . '/..';
$vista = (string)$p['vista'];
if (!preg_match('#^(views/[a-z_]+/[a-z_]+\.php|public/dashboard\.php)$#', $vista)) {
    fwrite(STDERR, "Vista no permitida: {$vista}\n");
    exit(2);
}

ini_set('session.save_path', (string)$p['ruta_sesiones']);
ini_set('session.use_cookies', '0');
session_id((string)$p['sesion_id']);
session_start();
if (isset($p['sesion'])) $_SESSION = $p['sesion'];

$_SERVER['REQUEST_METHOD'] = (string)$p['metodo'];
$_SERVER['SCRIPT_FILENAME'] = realpath($raiz) . '/' . $vista;
$_SERVER['SCRIPT_NAME'] = '/Sistema-Ventas-main/' . $vista;
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_POST = $p['post'] ?? [];
$_GET = $p['get'] ?? [];

require realpath($raiz) . '/' . $vista; // redirect() hace exit: la sesión se guarda al terminar el proceso
