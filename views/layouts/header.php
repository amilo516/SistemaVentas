<?php
// Uso: definir $titulo antes de incluir este archivo, y cerrar con layouts/footer.php.
require_once __DIR__ . '/../../helpers/menu.php';
require_once __DIR__ . '/../../helpers/funciones.php';
require_once __DIR__ . '/../../helpers/empresa.php';
$titulo = $titulo ?? APP_NAME;
$activo = moduloActual();
?><!doctype html><html lang="es"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($titulo) ?> - <?= htmlspecialchars(nombreEmpresa()) ?></title>
<script>try{if(localStorage.getItem('sidebar')==='oculto')document.documentElement.classList.add('sidebar-collapsed')}catch(e){}</script>
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css?v=<?= filemtime(__DIR__ . '/../../assets/css/style.css') ?>">
<script src="<?= APP_URL ?>/assets/js/app.js?v=<?= filemtime(__DIR__ . '/../../assets/js/app.js') ?>" defer></script></head><body class="app">
<header class="topbar">
  <a class="brand" href="<?= BASE_URL ?>/dashboard.php"><?php if ($logoEmpresa = urlLogoEmpresa()): ?><img src="<?= e($logoEmpresa) ?>" alt=""><?php endif; ?><?= htmlspecialchars(nombreEmpresa()) ?></a>
  <div class="topbar-right">
    <span class="user"><?= htmlspecialchars($_SESSION['nombre'] ?? '') ?>
      <?php if (!empty($_SESSION['rol'])): ?><small><?= htmlspecialchars($_SESSION['rol']) ?></small><?php endif; ?></span>
    <button type="button" class="sidebar-toggle" aria-controls="sidebar" aria-expanded="true" aria-label="Mostrar u ocultar menú"><?= icono('menu') ?></button>
  </div>
</header>
<aside class="sidebar" id="sidebar">
  <nav>
  <?php foreach (menuModulos() as $grupo => $items): ?>
    <p class="sidebar-group"><?= htmlspecialchars($grupo) ?></p>
    <?php foreach ($items as $item): ?>
      <a href="<?= $item['url'] ?>" class="<?= $item['carpeta'] === $activo ? 'active' : '' ?>"><?= icono($item['icono']) ?><span><?= htmlspecialchars($item['nombre']) ?></span></a>
    <?php endforeach; ?>
  <?php endforeach; ?>
  </nav>
  <a class="sidebar-logout" href="<?= BASE_URL ?>/logout.php"><?= icono('salir') ?><span>Cerrar sesión</span></a>
</aside>
<div class="sidebar-backdrop"></div>
<main class="app-main"><div class="container">
<?php if ($flash = tomarFlash()): ?><div class="alert <?= e($flash['tipo']) ?>"><?= e($flash['mensaje']) ?></div><?php endif; ?>
