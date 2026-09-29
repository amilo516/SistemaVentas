<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/permisos.php';
require_once __DIR__ . '/../models/Auditoria.php';

function login(string $usuario, string $password): bool {
    $db = (new Database())->getConnection();
    $stmt = $db->prepare("SELECT u.*, r.nombre AS rol FROM usuarios u
        INNER JOIN roles r ON r.id_rol=u.rol_id
        WHERE u.usuario=:usuario AND u.estado=1 AND r.estado=1 LIMIT 1");
    $stmt->execute(['usuario'=>$usuario]);
    $user = $stmt->fetch();
    if (!$user || !password_verify($password, $user['password'])) {
        // Nunca se guarda la contraseña; solo el usuario que se intentó.
        (new Auditoria($db))->registrar('sesion','login fallido','usuarios',(int)($user['id_usuario'] ?? 0),
            'Intento fallido de inicio de sesión con el usuario "'.mb_substr($usuario,0,50).'"', $user ? (int)$user['id_usuario'] : null);
        return false;
    }
    session_regenerate_id(true);
    $_SESSION['id_usuario']=(int)$user['id_usuario'];
    $_SESSION['nombre']=$user['nombre'];
    $_SESSION['usuario']=$user['usuario'];
    $_SESSION['rol_id']=(int)$user['rol_id'];
    $_SESSION['rol']=$user['rol'];
    $_SESSION['permisos']=cargarPermisos((int)$user['rol_id']);
    $_SESSION['permisos_version']=versionPermisos();
    (new Auditoria($db))->registrar('sesion','login','usuarios',(int)$user['id_usuario'],'Inicio de sesión');
    $u=$db->prepare("UPDATE usuarios SET ultimo_acceso=NOW() WHERE id_usuario=:id");
    $u->execute(['id'=>$user['id_usuario']]);
    return true;
}
function isLoggedIn(): bool { return isset($_SESSION['id_usuario']); }
function requireLogin(): void {
    if (!isLoggedIn()) { header('Location: /sistema_ventas/public/login.php'); exit; }
}
