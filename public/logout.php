<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Auditoria.php';
if (isset($_SESSION['id_usuario'])) {
    try { (new Auditoria((new Database())->getConnection()))->registrar('sesion', 'logout', 'usuarios', (int)$_SESSION['id_usuario'], 'Cierre de sesión'); }
    catch (Throwable $e) { error_log('Auditoría logout: ' . $e->getMessage()); }
}
$_SESSION = [];
session_destroy();
header('Location: login.php');
exit;
