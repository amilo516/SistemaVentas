<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/permisos.php';
require_once __DIR__ . '/../helpers/funciones.php';
require_once __DIR__ . '/../models/Usuario.php';
require_once __DIR__ . '/../models/Rol.php';
require_once __DIR__ . '/../models/Auditoria.php';

class UsuarioController {
    private PDO $db;
    private Usuario $usuarios;
    private Rol $roles;
    public function __construct() {
        $this->db=(new Database())->getConnection();
        $this->usuarios=new Usuario($this->db);
        $this->roles=new Rol($this->db);
    }

    public function listar(): array { return $this->usuarios->listar(); }
    public function roles(): array { return $this->roles->listarActivos(); }
    public function buscar(int $id): ?array { return $this->usuarios->buscar($id); }

    // Devuelve la lista de errores; si está vacía, el usuario se creó.
    public function crear(array $datos): array {
        $errores=$this->validar($datos, 0, true);
        if (!$errores) {
            $id=$this->usuarios->crear(trim($datos['nombre']), trim($datos['usuario']), $datos['password'], (int)$datos['rol_id']);
            (new Auditoria($this->db))->registrar('usuarios','crear','usuarios',$id,'Usuario "'.trim($datos['usuario']).'" ('.trim($datos['nombre']).') con rol '.$this->nombreRol((int)$datos['rol_id']));
        }
        return $errores;
    }

    public function actualizar(int $id, array $datos): array {
        $actual=$this->usuarios->buscar($id);
        if (!$actual) return ['El usuario no existe.'];
        $esYoMismo=$id===(int)$_SESSION['id_usuario'];
        // Nadie puede quitarse a sí mismo el rol: evita quedarse sin acceso al sistema.
        if ($esYoMismo) $datos['rol_id']=$actual['rol_id'];
        $cambiaPassword=($datos['password'] ?? '')!=='';
        $errores=$this->validar($datos, $id, $cambiaPassword);
        if (!$errores) {
            $this->usuarios->actualizar($id, trim($datos['nombre']), trim($datos['usuario']), (int)$datos['rol_id'], $cambiaPassword ? $datos['password'] : null);
            if ((int)$datos['rol_id']!==(int)$actual['rol_id']) marcarCambioPermisos($this->db);
            $cambios=[];
            if (trim($datos['nombre'])!==$actual['nombre']) $cambios[]='nombre';
            if (trim($datos['usuario'])!==$actual['usuario']) $cambios[]='usuario de '.$actual['usuario'].' a '.trim($datos['usuario']);
            if ((int)$datos['rol_id']!==(int)$actual['rol_id']) $cambios[]='rol de '.$this->nombreRol((int)$actual['rol_id']).' a '.$this->nombreRol((int)$datos['rol_id']);
            if ($cambiaPassword) $cambios[]='contraseña';
            (new Auditoria($this->db))->registrar('usuarios','editar','usuarios',$id,'Usuario "'.trim($datos['usuario']).'"'.($cambios ? '. Cambió: '.implode(', ',$cambios) : ''));
            if ($esYoMismo) $_SESSION['nombre']=trim($datos['nombre']);
        }
        return $errores;
    }

    public function cambiarEstado(int $id): string {
        if ($id===(int)$_SESSION['id_usuario']) return 'No puedes desactivar tu propio usuario.';
        $u=$this->usuarios->buscar($id);
        if (!$u) return 'El usuario no existe.';
        $this->usuarios->cambiarEstado($id, $u['estado'] ? 0 : 1);
        marcarCambioPermisos($this->db);
        (new Auditoria($this->db))->registrar('usuarios',$u['estado'] ? 'desactivar' : 'activar','usuarios',$id,'Usuario "'.$u['usuario'].'"');
        return '';
    }

    public function eliminar(int $id): string {
        if ($id===(int)$_SESSION['id_usuario']) return 'No puedes eliminar tu propio usuario.';
        $u=$this->usuarios->buscar($id);
        if (!$u) return 'El usuario no existe.';
        if ((int)$u['rol_id']===ROL_ADMINISTRADOR && $u['estado'] && $this->usuarios->administradoresActivos()<=1) return 'No se puede eliminar el único administrador activo.';
        $n=$this->usuarios->operaciones($id);
        if ($n) return "No se puede eliminar a \"{$u['usuario']}\": tiene {$n} operación(es) registradas (ventas, cajas, inventario…). Puedes desactivarlo para que no inicie sesión.";
        $this->db->beginTransaction();
        $this->usuarios->eliminar($id);
        (new Auditoria($this->db))->registrar('usuarios','eliminar','usuarios',0,"Usuario \"{$u['usuario']}\" ({$u['nombre']}) eliminado");
        marcarCambioPermisos($this->db);
        $this->db->commit();
        return '';
    }

    private function nombreRol(int $id): string {
        foreach ($this->roles->listarActivos() as $r) if ((int)$r['id_rol']===$id) return $r['nombre'];
        return '#'.$id;
    }

    private function validar(array $d, int $id, bool $validarPassword): array {
        $errores=[];
        $nombre=trim($d['nombre'] ?? '');
        $usuario=trim($d['usuario'] ?? '');
        if ($nombre==='' || mb_strlen($nombre)>100) $errores[]='El nombre es obligatorio (máximo 100 caracteres).';
        if (!preg_match('/^[A-Za-z0-9._-]{3,50}$/', $usuario)) $errores[]='El usuario debe tener entre 3 y 50 caracteres: letras, números, punto, guion o guion bajo, sin espacios.';
        elseif ($this->usuarios->existeUsuario($usuario, $id)) $errores[]="El usuario \"{$usuario}\" ya existe.";
        if (!$this->roles->existeActivo((int)($d['rol_id'] ?? 0))) $errores[]='Selecciona un rol válido.';
        if ($validarPassword) {
            $p=$d['password'] ?? '';
            if (strlen($p)<8) $errores[]='La contraseña debe tener al menos 8 caracteres.';
            elseif ($p!==($d['password_confirmar'] ?? '')) $errores[]='Las contraseñas no coinciden.';
        }
        return $errores;
    }
}
