<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constantes.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/permisos.php';
require_once __DIR__ . '/../helpers/descuentos.php';
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

    // Crea ($id=0) o actualiza nombre, descripción y descuento máximo. Devuelve [id, errores].
    // Si hay cualquier error no se guarda nada del formulario.
    public function guardar(int $id, array $post): array {
        $nombre=trim($post['nombre'] ?? '');
        $descripcion=mb_substr(trim($post['descripcion'] ?? ''),0,255) ?: null;
        $actual=$id ? $this->roles->buscar($id) : null;
        if ($id && !$actual) return [0,['El rol no existe.']];
        $esAdmin=$actual && $this->esAdministrador($actual);
        if ($esAdmin) $nombre=$actual['nombre']; // el nombre del administrador no cambia
        $e=[];
        if ($nombre==='' || mb_strlen($nombre)>50) $e[]='El nombre del rol es obligatorio (máximo 50 caracteres).';
        elseif ($this->roles->existeNombre($nombre,$id)) $e[]="Ya existe un rol llamado \"{$nombre}\".";

        // Descuento máximo (FR-001, FR-003, FR-005, FR-014): el Administrador siempre 100; al crear, 10 si se omite.
        $enviado=$post['descuento_maximo'] ?? null;
        if ($esAdmin) $descuento=100.00;
        elseif (!$id && trim((string)($enviado ?? ''))==='') $descuento=10.00;
        else $descuento=normalizarDescuentoMaximo($enviado);
        if ($descuento===null) $e[]='El descuento máximo debe ser un número entre 0 y 100, con hasta dos decimales.';
        $anterior=$actual ? (float)$actual['descuento_maximo'] : null;
        $cambia=$actual && $descuento!==null && abs($descuento-$anterior)>=0.005;

        // FR-002a: nadie salvo el Administrador cambia el límite de su propio rol (el rol del Administrador es fijo arriba).
        if ($cambia && !$esAdmin && $id===(int)($_SESSION['rol_id'] ?? 0)) {
            (new Auditoria($this->db))->registrar('roles','descuento maximo denegado','roles',$id,
                "Intento de cambiar el descuento máximo del propio rol {$actual['nombre']}: ".formatearPorcentaje($anterior).' % → '.formatearPorcentaje($descuento).' %');
            $e[]='Solo el Administrador puede cambiar el descuento máximo de tu propio rol.';
        }
        if ($e) return [0,$e];

        $this->db->beginTransaction();
        try {
            if ($id) {
                $this->roles->actualizar($id,$nombre,$descripcion);
                if ($cambia) $this->roles->actualizarDescuentoMaximo($id,$descuento);
            } else {
                $id=$this->roles->crear($nombre,$descripcion,$descuento);
            }
            $auditoria=new Auditoria($this->db);
            $auditoria->registrar('roles',$actual ? 'editar' : 'crear','roles',$id,"Rol {$nombre}"
                .($actual ? '' : ' con descuento máximo '.formatearPorcentaje($descuento).' %'));
            if ($cambia) $auditoria->registrar('roles','descuento maximo','roles',$id,
                "Rol {$nombre}: descuento máximo ".formatearPorcentaje($anterior).' % → '.formatearPorcentaje($descuento).' %');
            marcarCambioPermisos($this->db);
            $this->db->commit();
        } catch (Throwable $ex) {
            $this->db->rollBack();
            throw $ex;
        }
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
