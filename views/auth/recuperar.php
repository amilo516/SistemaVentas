<?php
// Página pública: el sistema no envía correos, así que el restablecimiento lo hace un administrador desde Usuarios.
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../helpers/empresa.php';
$empresa = datosEmpresa();
?><!doctype html><html lang="es"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Recuperar contraseña - <?= htmlspecialchars(nombreEmpresa()) ?></title>
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css?v=<?= filemtime(__DIR__ . '/../../assets/css/style.css') ?>">
</head><body class="login-page"><div class="login-card">
<?php if ($logo = urlLogoEmpresa()): ?><img class="login-logo" src="<?= htmlspecialchars($logo) ?>" alt=""><?php endif; ?>
<h1>¿Olvidaste tu contraseña?</h1>
<p>Por seguridad, las contraseñas las restablece un administrador del sistema.</p>
<ol class="pasos">
  <li>Comunícate con el administrador<?= !empty($empresa['telefono']) ? ' al <strong>' . htmlspecialchars($empresa['telefono']) . '</strong>' : '' ?><?= !empty($empresa['email']) ? ' o escribe a <strong>' . htmlspecialchars($empresa['email']) . '</strong>' : '' ?>.</li>
  <li>Indícale tu nombre de usuario.</li>
  <li>El administrador te asignará una contraseña nueva desde <em>Usuarios → Editar</em>.</li>
</ol>
<a class="btn btn-lg" href="<?= BASE_URL ?>/login.php">Volver a iniciar sesión</a>
</div></body></html>
