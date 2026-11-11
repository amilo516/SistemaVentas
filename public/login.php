<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/empresa.php';
if (isLoggedIn()) { header('Location: dashboard.php'); exit; }
$error = $_SESSION['login_error'] ?? null;
unset($_SESSION['login_error']);
?>
<!doctype html>
<html lang="es"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Iniciar sesión - <?= htmlspecialchars(nombreEmpresa()) ?></title>
<link rel="stylesheet" href="../assets/css/style.css">
</head><body class="login-page"><div class="login-card">
<?php if ($logo = urlLogoEmpresa()): ?><img class="login-logo" src="<?= htmlspecialchars($logo) ?>" alt=""><?php endif; ?>
<h1><?= htmlspecialchars(nombreEmpresa()) ?></h1><p>Iniciar sesión</p>
<?php if ($error): ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<form method="post" action="procesar_login.php">
<label>Usuario</label><input type="text" name="usuario" required autofocus>
<label>Contraseña</label><input type="password" name="password" required>
<button type="submit">Ingresar</button>
</form>
<p class="login-ayuda"><a href="../views/auth/recuperar.php">¿Olvidaste tu contraseña?</a></p></div></body></html>
