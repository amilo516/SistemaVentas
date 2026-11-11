</div></main>
<?php
// Botón flotante del Asesor IA (en todas las páginas menos en la del propio Asesor).
if (function_exists('tienePermiso') && tienePermiso('asesor.ver') && moduloActual() !== 'asesor'):
    require_once __DIR__ . '/../../helpers/agente_ia.php';
    $asesorModoIA = AgenteIA::disponible();
?>
<button type="button" class="asesor-fab" aria-controls="asesor-panel" aria-expanded="false" title="Asesor IA"><?= icono('asesor') ?><span>Asesor IA</span></button>
<section class="asesor-panel" id="asesor-panel" role="dialog" aria-label="Asesor IA" hidden
  data-url="<?= APP_URL ?>/views/asesor/api.php" data-csrf="<?= csrfToken() ?>">
  <header class="ap-cabecera">
    <strong><?= icono('asesor') ?> Asesor IA</strong>
    <span class="badge <?= $asesorModoIA ? 'badge-ok' : 'badge-off' ?>"><?= $asesorModoIA ? 'Modo IA' : 'Modo local' ?></span>
    <button type="button" class="ap-cerrar" aria-label="Cerrar">×</button>
  </header>
  <form class="ap-form">
    <?php if ($asesorModoIA): ?><input type="text" name="pregunta" maxlength="500" placeholder="Pregúntale algo (opcional)"><?php endif; ?>
    <button class="btn">Generar recomendaciones</button>
  </form>
  <div class="ap-cuerpo"><p class="ap-ayuda">Analizo ventas, inventario, márgenes y devoluciones y te digo qué hacer.</p></div>
  <a class="ap-enlace" href="<?= APP_URL ?>/views/asesor/index.php">Abrir el Asesor completo e historial →</a>
</section>
<script src="<?= APP_URL ?>/assets/js/asesor.js?v=<?= filemtime(__DIR__ . '/../../assets/js/asesor.js') ?>" defer></script>
<?php endif; ?>
</body></html>
