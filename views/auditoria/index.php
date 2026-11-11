<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../helpers/permisos.php';
require_once __DIR__ . '/../../helpers/funciones.php';
require_once __DIR__ . '/../../helpers/reportes.php';
require_once __DIR__ . '/../../controllers/AuditoriaController.php';
requirePermission('auditoria.ver');
$aud = new AuditoriaController($_GET);
$f = $aud->filtros;
if (($_GET['exportar'] ?? '') === 'csv') {
    exportarCsv('auditoria_' . $f['desde'] . '_a_' . $f['hasta'] . '.csv', ['Fecha', 'Usuario', 'Módulo', 'Acción', 'Tabla', 'Registro', 'Descripción', 'IP'],
        array_map(fn($r) => [$r['fecha'], $r['usuario'] ?? '(sin usuario)', $r['modulo'], $r['accion'], $r['tabla_afectada'], $r['id_registro'], $r['descripcion'], $r['ip']], $aud->todos()));
}
$pag = $aud->pagina((int)($_GET['pagina'] ?? 1));
$resumen = $aud->resumen();
$op = $aud->opciones();
$query = fn(array $extra) => '?' . http_build_query(array_filter(array_merge(['desde' => $f['desde'], 'hasta' => $f['hasta'], 'usuario' => $f['id_usuario'] ?: null,
    'modulo' => $f['modulo'], 'accion' => $f['accion'], 'q' => $f['q']], $extra), fn($v) => $v !== null && $v !== ''));
$nombresModulo = ['sesion' => 'Sesión', 'categorias' => 'Categorías', 'configuracion' => 'Configuración', 'auditoria' => 'Auditoría'];
$modulo = fn(?string $m) => $nombresModulo[$m] ?? ucfirst((string)$m);
$colores = ['crear' => 'badge-ok', 'login' => 'badge', 'logout' => 'badge-off', 'login fallido' => 'badge-danger', 'anular' => 'badge-danger',
    'desactivar' => 'badge-danger', 'eliminar' => 'badge-danger', 'permisos' => 'badge-warn', 'ajuste' => 'badge-warn', 'salida' => 'badge-warn', 'cerrar' => 'badge-warn'];
$titulo = 'Auditoría';
require __DIR__ . '/../layouts/header.php';
?>
<div class="page-header">
  <h1>Auditoría</h1>
  <a class="btn btn-light" href="<?= e($query(['exportar' => 'csv'])) ?>"><?= icono('reportes') ?> Exportar a Excel (CSV)</a>
</div>
<p class="muted">Historial de acciones: quién hizo qué, cuándo y desde qué equipo. Los registros no se pueden editar ni borrar desde el sistema.</p>
<form class="filtros card" method="get">
  <label>Desde<input type="date" name="desde" value="<?= e($f['desde']) ?>"></label>
  <label>Hasta<input type="date" name="hasta" value="<?= e($f['hasta']) ?>"></label>
  <label>Usuario<select name="usuario"><option value="">Todos</option>
    <?php foreach ($op['usuarios'] as $u): ?><option value="<?= (int)$u['id_usuario'] ?>" <?= $f['id_usuario'] === (int)$u['id_usuario'] ? 'selected' : '' ?>><?= e($u['nombre']) ?></option><?php endforeach; ?></select></label>
  <label>Módulo<select name="modulo"><option value="">Todos</option>
    <?php foreach ($op['modulos'] as $m): ?><option value="<?= e($m) ?>" <?= $f['modulo'] === $m ? 'selected' : '' ?>><?= e($modulo($m)) ?></option><?php endforeach; ?></select></label>
  <label>Acción<select name="accion"><option value="">Todas</option>
    <?php foreach ($op['acciones'] as $a): ?><option value="<?= e($a) ?>" <?= $f['accion'] === $a ? 'selected' : '' ?>><?= e(ucfirst($a)) ?></option><?php endforeach; ?></select></label>
  <label class="grow">Buscar<input type="search" name="q" value="<?= e($f['q']) ?>" placeholder="Texto en la descripción (ej. FAC-000012)"></label>
  <button class="btn">Filtrar</button>
</form>
<div class="stats stats-compactas">
  <div class="stat"><span class="stat-label">Acciones</span><strong class="stat-value"><?= number_format((int)$resumen['total'], 0, ',', '.') ?></strong></div>
  <div class="stat"><span class="stat-label">Usuarios distintos</span><strong class="stat-value"><?= (int)$resumen['usuarios'] ?></strong></div>
  <div class="stat"><span class="stat-label">Acciones sensibles</span><strong class="stat-value"><?= (int)$resumen['sensibles'] ?></strong><span class="stat-detail">Anulaciones, desactivaciones, permisos, ajustes…</span></div>
  <a class="stat" href="<?= e($query(['accion' => 'login fallido'])) ?>"><span class="stat-label">Inicios de sesión fallidos</span><strong class="stat-value <?= (int)$resumen['fallidos'] > 0 ? 'negativo' : '' ?>"><?= (int)$resumen['fallidos'] ?></strong><span class="stat-detail">Ver intentos</span></a>
</div>
<div class="table-card">
<table class="table tabla-auditoria">
  <thead><tr><th>Fecha</th><th>Usuario</th><th>Módulo</th><th>Acción</th><th>Descripción</th><th>IP</th></tr></thead>
  <tbody>
  <?php foreach ($pag['registros'] as $r): $link = AuditoriaController::enlace($r); ?>
    <tr>
      <td><?= date('d/m/Y', strtotime($r['fecha'])) ?><br><small class="muted"><?= date('H:i:s', strtotime($r['fecha'])) ?></small></td>
      <td><?= $r['usuario'] ? e($r['usuario']) . '<br><small class="muted">@' . e($r['login']) . '</small>' : '<span class="muted">(sin sesión)</span>' ?></td>
      <td><?= e($modulo($r['modulo'])) ?></td>
      <td><span class="badge <?= $colores[$r['accion']] ?? 'badge' ?>"><?= e(ucfirst($r['accion'])) ?></span></td>
      <td class="wrap"><?= e($r['descripcion'] ?? '') ?><?php if ($link): ?> <a class="ver-registro" href="<?= e($link) ?>">Ver →</a><?php endif; ?></td>
      <td><small class="muted"><?= e($r['ip'] ?? '') ?></small></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$pag['registros']): ?><tr><td colspan="6" class="empty">No hay acciones con estos filtros.</td></tr><?php endif; ?>
  </tbody>
</table>
</div>
<?php if ($pag['paginas'] > 1): ?>
<nav class="paginacion">
  <span class="muted"><?= number_format($pag['total'], 0, ',', '.') ?> registros · página <?= $pag['pagina'] ?> de <?= $pag['paginas'] ?></span>
  <?php if ($pag['pagina'] > 1): ?><a class="btn btn-light btn-sm" href="<?= e($query(['pagina' => $pag['pagina'] - 1])) ?>">← Anterior</a><?php endif; ?>
  <?php if ($pag['pagina'] < $pag['paginas']): ?><a class="btn btn-light btn-sm" href="<?= e($query(['pagina' => $pag['pagina'] + 1])) ?>">Siguiente →</a><?php endif; ?>
</nav>
<?php endif; ?>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
