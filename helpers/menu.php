<?php
declare(strict_types=1);
require_once __DIR__ . '/permisos.php';

// Módulos del menú lateral. 'carpeta' marca el enlace activo y 'permiso' decide si el usuario lo ve.
function menuModulos(): array {
    $menu = [
        'Principal' => [
            ['nombre' => 'Dashboard', 'url' => BASE_URL . '/dashboard.php', 'carpeta' => 'dashboard', 'icono' => 'dashboard', 'permiso' => 'dashboard.ver'],
        ],
        'Operación' => [
            ['nombre' => 'Ventas', 'url' => APP_URL . '/views/ventas/index.php', 'carpeta' => 'ventas', 'icono' => 'ventas', 'permiso' => 'ventas.ver'],
            ['nombre' => 'Caja', 'url' => APP_URL . '/views/caja/index.php', 'carpeta' => 'caja', 'icono' => 'caja', 'permiso' => 'caja.ver'],
            ['nombre' => 'Devoluciones', 'url' => APP_URL . '/views/devoluciones/index.php', 'carpeta' => 'devoluciones', 'icono' => 'devoluciones', 'permiso' => 'devoluciones.ver'],
        ],
        'Catálogo' => [
            ['nombre' => 'Productos', 'url' => APP_URL . '/views/productos/index.php', 'carpeta' => 'productos', 'icono' => 'productos', 'permiso' => 'productos.ver'],
            ['nombre' => 'Categorías', 'url' => APP_URL . '/views/categorias/index.php', 'carpeta' => 'categorias', 'icono' => 'categorias', 'permiso' => 'categorias.ver'],
            ['nombre' => 'Inventario', 'url' => APP_URL . '/views/inventario/index.php', 'carpeta' => 'inventario', 'icono' => 'inventario', 'permiso' => 'inventario.ver'],
            ['nombre' => 'Clientes', 'url' => APP_URL . '/views/clientes/index.php', 'carpeta' => 'clientes', 'icono' => 'clientes', 'permiso' => 'clientes.ver'],
        ],
        'Administración' => [
            ['nombre' => 'Reportes', 'url' => APP_URL . '/views/reportes/ventas.php', 'carpeta' => 'reportes', 'icono' => 'reportes', 'permiso' => 'reportes.ver'],
            ['nombre' => 'Usuarios', 'url' => APP_URL . '/views/usuarios/index.php', 'carpeta' => 'usuarios', 'icono' => 'usuarios', 'permiso' => 'usuarios.ver'],
            ['nombre' => 'Roles', 'url' => APP_URL . '/views/roles/index.php', 'carpeta' => 'roles', 'icono' => 'roles', 'permiso' => 'roles.gestionar'],
            ['nombre' => 'Configuración', 'url' => APP_URL . '/views/configuracion/index.php', 'carpeta' => 'configuracion', 'icono' => 'configuracion', 'permiso' => 'configuracion.gestionar'],
            ['nombre' => 'Auditoría', 'url' => APP_URL . '/views/auditoria/index.php', 'carpeta' => 'auditoria', 'icono' => 'auditoria', 'permiso' => 'auditoria.ver'],
        ],
    ];
    foreach ($menu as $grupo => $items) {
        $menu[$grupo] = array_values(array_filter($items, fn($i) => tienePermiso($i['permiso'])));
        if (!$menu[$grupo]) unset($menu[$grupo]);
    }
    return $menu;
}

// Carpeta del módulo que se está viendo (ej. "productos"), o "dashboard".
function moduloActual(): string {
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    if (preg_match('#/views/([^/]+)/#', $script, $m)) return $m[1];
    return str_ends_with($script, '/dashboard.php') ? 'dashboard' : '';
}

function icono(string $nombre): string {
    $iconos = [
        'dashboard' => '<rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/>',
        'ventas' => '<circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.7 13.4a2 2 0 0 0 2 1.6h9.7a2 2 0 0 0 2-1.6L23 6H6"/>',
        'caja' => '<rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2"/><path d="M6 12h.01M18 12h.01"/>',
        'devoluciones' => '<path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5"/>',
        'productos' => '<path d="M21 8l-9-5-9 5 9 5 9-5z"/><path d="M3 8v8l9 5 9-5V8"/><path d="M12 13v8"/>',
        'categorias' => '<path d="M20.6 13.4l-7.2 7.2a2 2 0 0 1-2.8 0L2 12V2h10l8.6 8.6a2 2 0 0 1 0 2.8z"/><circle cx="7" cy="7" r="1.5"/>',
        'inventario' => '<rect x="8" y="2" width="8" height="4" rx="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="M9 12h6M9 16h6"/>',
        'clientes' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.9M16 3.1a4 4 0 0 1 0 7.8"/>',
        'reportes' => '<path d="M3 3v18h18"/><path d="M8 17v-7M13 17V6M18 17v-4"/>',
        'usuarios' => '<circle cx="12" cy="8" r="4"/><path d="M4 21v-1a6 6 0 0 1 6-6h4a6 6 0 0 1 6 6v1"/>',
        'roles' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
        'configuracion' => '<path d="M4 21v-7M4 10V3M12 21v-9M12 8V3M20 21v-5M20 12V3M1 14h6M9 8h6M17 16h6"/>',
        'auditoria' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M8 13h8M8 17h5"/>',
        'menu' => '<path d="M3 6h18M3 12h18M3 18h18"/>',
        'salir' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>',
    ];
    return '<svg class="icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
        . ($iconos[$nombre] ?? '') . '</svg>';
}
