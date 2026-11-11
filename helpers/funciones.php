<?php
function e(?string $value): string { return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8'); }
function redirect(string $url): never { header("Location: {$url}"); exit; }
function dinero(float|int|string|null $valor): string { return '$' . number_format((float)$valor, 0, ',', '.'); }

// Mensajes que se muestran una sola vez después de redirigir (ej. "Usuario creado").
function flash(string $tipo, string $mensaje): void { $_SESSION['flash'] = ['tipo' => $tipo, 'mensaje' => $mensaje]; }
function tomarFlash(): ?array { $f = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $f; }

// Protección CSRF para formularios POST.
function csrfToken(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrfCampo(): string { return '<input type="hidden" name="csrf" value="' . csrfToken() . '">'; }
function verificarCsrf(): void {
    if (!hash_equals(csrfToken(), (string)($_POST['csrf'] ?? ''))) { http_response_code(400); exit('Solicitud inválida. Recarga la página e inténtalo de nuevo.'); }
}

// Botón "Eliminar" con confirmación. $accion: nombre del campo 'accion' que espera el formulario.
function botonEliminar(string $url, int $id, string $nombre, string $accion = 'eliminar'): string {
    $msg = e(json_encode('¿Eliminar "' . $nombre . '" definitivamente? Esta acción no se puede deshacer.', JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT));
    return '<form method="post" action="' . e($url) . '" onsubmit="return confirm(' . $msg . ')">' . csrfCampo()
        . '<input type="hidden" name="accion" value="' . e($accion) . '"><input type="hidden" name="id" value="' . $id . '">'
        . '<button class="btn btn-sm btn-eliminar" title="Eliminar definitivamente">Eliminar</button></form>';
}
