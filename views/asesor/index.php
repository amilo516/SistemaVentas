<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../helpers/permisos.php';
require_once __DIR__ . '/../../helpers/funciones.php';
require_once __DIR__ . '/../../controllers/AsesorController.php';
requirePermission('asesor.ver');
$asesor = new AsesorController();
$resultado = null;
$pregunta = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarCsrf();
    set_time_limit(180);
    $pregunta = (string)($_POST['pregunta'] ?? '');
    $resultado = $asesor->analizar($pregunta);
} elseif (isset($_GET['ver'])) {
    $resultado = $asesor->buscar((int)$_GET['ver']);
    $pregunta = $resultado['pregunta'] ?? '';
}
$historial = $asesor->historial();
$modoIA = $asesor->modoIA();
$tipos = ['reabastecer' => 'Reabastecer', 'promocion' => 'Promoción', 'precio' => 'Precio', 'devoluciones' => 'Devoluciones', 'ventas' => 'Ventas'];
$herramientas = ['consultar_inventario_critico' => 'Inventario crítico', 'consultar_mas_vendidos' => 'Más vendidos', 'consultar_sin_movimiento' => 'Sin movimiento',
    'consultar_ventas' => 'Ventas por día', 'consultar_devoluciones' => 'Devoluciones', 'consultar_margenes' => 'Márgenes'];
$titulo = 'Asesor IA';
require __DIR__ . '/../layouts/header.php';
?>
<div class="page-header"><h1>Asesor IA</h1>
  <span class="badge <?= $modoIA ? 'badge-ok' : 'badge-off' ?>" title="<?= $modoIA ? 'Conectado al servicio de IA' : 'Sin clave de IA: se usan reglas de negocio' ?>"><?= $modoIA ? 'Modo IA' : 'Modo local (reglas)' ?></span></div>
<p class="subtitle">Analiza ventas, inventario, márgenes y devoluciones, y te recomienda qué hacer.</p>

<form method="post" class="card asesor-form" data-asesor>
  <?= csrfCampo() ?>
  <label for="pregunta">Pregúntale al asesor <small class="muted">(opcional<?= $modoIA ? '' : ', requiere modo IA' ?>)</small></label>
  <div class="asesor-fila">
    <input type="text" id="pregunta" name="pregunta" maxlength="500" value="<?= e($pregunta) ?>"
      placeholder="Ej.: ¿Qué productos debo pedir esta semana?" <?= $modoIA ? '' : 'disabled' ?>>
    <button class="btn"><?= icono('asesor') ?> Generar recomendaciones</button>
  </div>
  <?php if ($modoIA): ?><div class="atajos"><?php foreach (['¿Qué productos debo pedir esta semana?', '¿Qué productos debería poner en promoción?', '¿Cómo van las ventas frente a la semana pasada?'] as $s): ?>
    <a href="#" data-sugerencia="<?= e($s) ?>"><?= e($s) ?></a><?php endforeach; ?></div><?php endif; ?>
  <p class="asesor-cargando" hidden>El asesor está consultando los datos y analizando… puede tardar unos segundos.</p>
</form>

<?php if ($resultado): ?>
<section class="card asesor-resultado">
  <?php if ($resultado['aviso']): ?><div class="alert warn"><?= e($resultado['aviso']) ?></div><?php endif; ?>
  <div class="asesor-resumen"><?= icono('asesor') ?><p><?= nl2br(e($resultado['resumen'])) ?></p></div>
  <p class="nota">
    <?= $resultado['modo'] === 'ia' ? 'Generado con IA' : 'Generado por reglas' ?>
    <?= isset($resultado['fecha']) ? ' el ' . date('d/m/Y H:i', strtotime($resultado['fecha'])) : '' ?>
    <?= $resultado['segundos'] !== null ? ' en ' . $resultado['segundos'] . ' s' : '' ?>
    <?php if ($resultado['herramientas']): ?> · Consultó: <?= e(implode(', ', array_map(fn($h) => $herramientas[$h] ?? $h, $resultado['herramientas']))) ?><?php endif; ?>
  </p>
  <?php if ($resultado['recomendaciones']): ?>
  <ol class="recomendaciones">
    <?php foreach ($resultado['recomendaciones'] as $r): ?>
    <li class="rec rec-<?= e($r['prioridad']) ?>">
      <div class="rec-cabecera"><span class="badge rec-prioridad">Prioridad <?= e($r['prioridad']) ?></span><span class="rec-tipo"><?= e($tipos[$r['tipo']] ?? $r['tipo']) ?></span></div>
      <strong><?= e($r['titulo']) ?></strong>
      <p><?= e($r['detalle']) ?></p>
      <p class="rec-accion">→ <?= e($r['accion']) ?></p>
    </li>
    <?php endforeach; ?>
  </ol>
  <?php endif; ?>
</section>
<?php endif; ?>

<h2 class="section-title">Consultas anteriores</h2>
<div class="table-card">
<table class="table">
  <thead><tr><th>Fecha</th><th>Usuario</th><th>Modo</th><th>Pregunta</th><th class="num">Recomendaciones</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($historial as $h): ?>
    <tr><td><?= date('d/m/Y H:i', strtotime($h['fecha'])) ?></td><td><?= e($h['usuario']) ?></td>
      <td><span class="badge <?= $h['modo'] === 'ia' ? 'badge-ok' : 'badge-off' ?>"><?= $h['modo'] === 'ia' ? 'IA' : 'Local' ?></span></td>
      <td><?= e($h['pregunta'] ?? 'Análisis general') ?></td><td class="num"><?= (int)$h['total_recomendaciones'] ?></td>
      <td><a href="?ver=<?= (int)$h['id_recomendacion'] ?>">Ver</a></td></tr>
  <?php endforeach; ?>
  <?php if (!$historial): ?><tr><td colspan="6" class="empty">Aún no hay consultas. Pulsa “Generar recomendaciones”.</td></tr><?php endif; ?>
  </tbody>
</table>
</div>
<script>
document.querySelectorAll('[data-sugerencia]').forEach(a => a.addEventListener('click', ev => {
  ev.preventDefault(); document.getElementById('pregunta').value = a.dataset.sugerencia;
}));
document.querySelector('[data-asesor]').addEventListener('submit', ev => {
  ev.target.querySelector('.asesor-cargando').hidden = false;
  ev.target.querySelector('button').disabled = true;
});
</script>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
