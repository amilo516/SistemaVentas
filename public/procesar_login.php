<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../helpers/auth.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: login.php'); exit; }
if (!login(trim($_POST['usuario'] ?? ''), $_POST['password'] ?? '')) {
    $_SESSION['login_error'] = 'Usuario o contraseña incorrectos.';
    header('Location: login.php'); exit;
}
header('Location: dashboard.php'); exit;
