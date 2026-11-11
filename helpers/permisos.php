<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constantes.php';
require_once __DIR__ . '/auth.php';

function cargarPermisos(int $rolId): array {
    $db=(new Database())->getConnection();
    $s=$db->prepare("SELECT p.nombre FROM rol_permisos rp
        INNER JOIN permisos p ON p.id_permiso=rp.id_permiso
        WHERE rp.id_rol=:rol");
    $s->execute(['rol'=>$rolId]);
    return $s->fetchAll(PDO::FETCH_COLUMN);
}

// Versión de roles y permisos. Cambia cada vez que se edita un rol, sus permisos o el rol/estado de un usuario.
function versionPermisos(): string {
    static $version=null;
    if ($version===null) {
        $s=(new Database())->getConnection()->prepare("SELECT valor FROM configuracion WHERE clave='permisos_version' LIMIT 1");
        $s->execute();
        $version=(string)($s->fetchColumn() ?: '0');
    }
    return $version;
}
function marcarCambioPermisos(PDO $db): void {
    $db->prepare("INSERT INTO configuracion (clave,valor,descripcion) VALUES ('permisos_version',:v,'Control interno de cambios de permisos')
        ON DUPLICATE KEY UPDATE valor=VALUES(valor)")->execute(['v'=>bin2hex(random_bytes(8))]);
}
// Si hubo cambios de permisos desde que el usuario inició sesión, recarga su rol y permisos.
// Un usuario desactivado (o con rol inactivo) pierde la sesión.
function sincronizarSesion(): void {
    static $hecho=false;
    if ($hecho || !isset($_SESSION['id_usuario'])) return;
    $hecho=true;
    if (($_SESSION['permisos_version'] ?? null)===versionPermisos() && isset($_SESSION['permisos'])) return;
    $s=(new Database())->getConnection()->prepare("SELECT u.estado,u.rol_id,r.nombre rol,r.estado rol_estado FROM usuarios u
        INNER JOIN roles r ON r.id_rol=u.rol_id WHERE u.id_usuario=:id");
    $s->execute(['id'=>$_SESSION['id_usuario']]);
    $u=$s->fetch();
    if (!$u || !(int)$u['estado'] || !(int)$u['rol_estado']) {
        $_SESSION=[];
        session_regenerate_id(true);
        $_SESSION['login_error']='Tu usuario o tu rol fue desactivado. Consulta con el administrador.';
        header('Location: '.BASE_URL.'/login.php');
        exit;
    }
    $_SESSION['rol_id']=(int)$u['rol_id'];
    $_SESSION['rol']=$u['rol'];
    $_SESSION['permisos']=cargarPermisos((int)$u['rol_id']);
    $_SESSION['permisos_version']=versionPermisos();
}

function tienePermiso(string $permiso): bool {
    if (!isset($_SESSION['rol_id'])) return false;
    sincronizarSesion();
    if ((int)$_SESSION['rol_id'] === ROL_ADMINISTRADOR) return true;
    return in_array($permiso, $_SESSION['permisos'] ?? [], true);
}
function requirePermission(string $permiso): void {
    requireLogin();
    if (tienePermiso($permiso)) return;
    http_response_code(403);
    $titulo = 'Acceso denegado';
    require __DIR__ . '/../views/layouts/header.php';
    echo '<h1>Acceso denegado</h1><p>Tu rol no tiene permiso para ver esta página.</p>'
        . '<p><a class="btn" href="' . BASE_URL . '/dashboard.php">Volver al dashboard</a></p>';
    require __DIR__ . '/../views/layouts/footer.php';
    exit;
}
