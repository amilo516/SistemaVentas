<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constantes.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/permisos.php';
require_once __DIR__ . '/../models/Rol.php';
require_once __DIR__ . '/../models/Permiso.php';
require_once __DIR__ . '/../models/Auditoria.php';

class RolController {
    // Nombre visible de cada grupo de permisos (prefijo antes del punto).
    public const MODULOS = [
        'dashboard' => 'Dashboard', 'ventas' => 'Ventas', 'facturas' => 'Facturas', 'caja' => 'Caja', 'devoluciones' => 'Devoluciones',
        'productos' => 'Productos', 'categorias' => 'Categorías', 'inventario' => 'Inventario', 'clientes' => 'Clientes',
        'reportes' => 'Reportes', 'usuarios' => 'Usuarios', 'roles' => 'Roles', 'configuracion' => 'Configuración', 'auditoria' => 'Auditoría',
    ];
    private PDO $db;
    private Rol $roles;
    private Permiso $permisos;
    public function __construct() {
        $this->db=(new Database())->getConnection();
        $this->roles=new Rol($this->db);
        $this->permisos=new Permiso($this->db);
    }

    public function listar(): array { return $this->roles->listar(); }
    public function buscar(int $id): ?array { return $this->roles->buscar($id); }
    public function usuarios(int $id): array { return $this->roles->usuarios($id); }
    public function permisosDeRol(int $id): array { return $this->permisos->deRol($id); }
    public function esAdministrador(array $rol): bool { return (int)$rol['id_rol']===ROL_ADMINISTRADOR; }

    // Permisos agrupados por módulo: ['Ventas' => [permiso, ...], ...]
    public function permisosAgrupados(): array {
        $grupos=[];
        foreach ($this->permisos->todos() as $p) {
            $mod=explode('.',$p['nombre'])[0];
            $grupos[self::MODULOS[$mod] ?? ucfirst($mod)][]=$p;
        }
        return $grupos;
    }

    // Crea ($id=0) o actualiza nombre/descripción. Devuelve [id, errores].
    public function guardar(int $id, array $post): array {
        $nombre=trim($post['nombre'] ?? '');
        $descripcion=mb_substr(trim($post['descripcion'] ?? ''),0,255) ?: null;
        $actual=$id ? $this->roles->buscar($id) : null;
        if ($id && !$actual) return [0,['El rol no existe.']];
        if ($actual && $this->esAdministrador($actual)) $nombre=$actual['nombre']; // el nombre del administrador no cambia
        $e=[];
        if ($nombre==='' || mb_strlen($nombre)>50) $e[]='El nombre del rol es obligatorio (máximo 50 caracteres).';
        elseif ($this->roles->existeNombre($nombre,$id)) $e[]="Ya existe un rol llamado \"{$nombre}\".";
        if ($e) return [0,$e];
        if ($id) $this->roles->actualizar($id,$nombre,$descripcion); else $id=$this->roles->crear($nombre,$descripcion);
        (new Auditoria($this->db))->registrar('roles',$actual ? 'editar' : 'crear','roles',$id,"Rol {$nombre}");
        marcarCambioPermisos($this->db);
        return [$id,[]];
    }

    // Guarda los permisos marcados. Marcar cualquier acción de un módulo incluye su permiso de consulta (.ver).
    public function guardarPermisos(int $id, array $marcados): string {
        $rol=$this->roles->buscar($id);
        if (!$rol) return 'El rol no existe.';
        if ($this->esAdministrador($rol)) return 'El Administrador siempre tiene todos los permisos.';
        $validos=array_column($this->permisos->todos(),'nombre');
        $sel=array_values(array_intersect($validos,array_map('strval',$marcados)));
        foreach ($sel as $p) {
            $ver=explode('.',$p)[0].'.ver';
            if (in_array($ver,$validos,true) && !in_array($ver,$sel,true)) $sel[]=$ver;
        }
        $antes=$this->permisos->deRol($id);
        $this->db->beginTransaction();
        $this->permisos->asignar($id,$sel);
        $agregados=array_diff($sel,$antes); $quitados=array_diff($antes,$sel);
        if ($agregados || $quitados) {
            (new Auditoria($this->db))->registrar('roles','permisos','rol_permisos',$id,"Rol {$rol['nombre']}."
                .($agregados ? ' Agregó: '.implode(', ',$agregados).'.' : '').($quitados ? ' Quitó: '.implode(', ',$quitados).'.' : ''));
        }
        marcarCambioPermisos($this->db);
        $this->db->commit();
        return '';
    }

    public function cambiarEstado(int $id): string {
        $rol=$this->roles->buscar($id);
        if (!$rol) return 'El rol no existe.';
        if ($this->esAdministrador($rol)) return 'El rol Administrador no se puede desactivar.';
        if ($rol['estado'] && (int)$rol['usuarios']>0) return "No puedes desactivar \"{$rol['nombre']}\": tiene {$rol['usuarios']} usuario(s) activo(s). Cámbialos de rol primero.";
        $this->roles->cambiarEstado($id,$rol['estado'] ? 0 : 1);
        (new Auditoria($this->db))->registrar('roles',$rol['estado'] ? 'desactivar' : 'activar','roles',$id,"Rol {$rol['nombre']}");
        marcarCambioPermisos($this->db);
        return '';
    }
    public function eliminar(int $id): string {
        $rol=$this->roles->buscar($id);
        if (!$rol) return 'El rol no existe.';
        if ($id<=ROL_SUPERVISOR) return "El rol \"{$rol['nombre']}\" es parte del sistema y no se puede eliminar. Puedes desactivarlo si no tiene usuarios.";
        $n=$this->roles->contarUsuarios($id);
        if ($n) return "No se puede eliminar \"{$rol['nombre']}\": tiene {$n} usuario(s) asignado(s). Cámbialos de rol primero.";
        $this->roles->eliminar($id);
        (new Auditoria($this->db))->registrar('roles','eliminar','roles',0,"Rol {$rol['nombre']} eliminado");
        marcarCambioPermisos($this->db);
        return '';
    }
}
