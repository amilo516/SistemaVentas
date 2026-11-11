<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constantes.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/permisos.php';
require_once __DIR__ . '/../models/Cliente.php';
require_once __DIR__ . '/../models/Venta.php';
require_once __DIR__ . '/../models/Auditoria.php';

class ClienteController {
    private PDO $db;
    private Cliente $clientes;
    public function __construct() {
        $this->db=(new Database())->getConnection();
        $this->clientes=new Cliente($this->db);
    }

    public function listar(array $filtros): array { return $this->clientes->listar($filtros); }
    public function buscar(int $id): ?array { return $this->clientes->buscar($id); }
    public function esGeneral(array $c): bool { return $c['documento']===CLIENTE_GENERAL_DOCUMENTO; }

    // Resumen y últimas compras. El vendedor solo ve las ventas que él hizo.
    public function historial(int $id): array {
        $propias=(int)$_SESSION['rol_id']===ROL_VENDEDOR ? (int)$_SESSION['id_usuario'] : null;
        $ventas=(new Venta($this->db))->listar(['desde'=>'2000-01-01','hasta'=>date('Y-m-d'),'id_cliente'=>$id,'id_usuario'=>$propias]);
        return ['resumen'=>$this->clientes->resumenCompras($id,$propias),'ventas'=>array_slice($ventas,0,10),'solo_propias'=>(bool)$propias];
    }

    // Devuelve [id, errores].
    public function crear(array $post): array {
        [$d,$errores]=$this->validar($post,0);
        if ($errores) return [0,$errores];
        $id=$this->clientes->crear($d);
        (new Auditoria($this->db))->registrar('clientes','crear','clientes',$id,"Cliente {$d['nombre']}".($d['documento'] ? " ({$d['documento']})" : ''));
        return [$id,[]];
    }
    public function actualizar(int $id, array $post): array {
        $actual=$this->clientes->buscar($id);
        if (!$actual) return ['El cliente no existe.'];
        [$d,$errores]=$this->validar($post,$id);
        if ($this->esGeneral($actual) && $d['documento']!==CLIENTE_GENERAL_DOCUMENTO) $errores[]='No se puede cambiar el documento del Cliente General.';
        if ($errores) return $errores;
        $this->clientes->actualizar($id,$d);
        (new Auditoria($this->db))->registrar('clientes','editar','clientes',$id,"Cliente {$d['nombre']}");
        return [];
    }
    public function cambiarEstado(int $id): string {
        $c=$this->clientes->buscar($id);
        if (!$c) return 'El cliente no existe.';
        if ($this->esGeneral($c)) return 'El Cliente General no se puede desactivar: se usa en las ventas sin cliente identificado.';
        $this->clientes->cambiarEstado($id,$c['estado'] ? 0 : 1);
        (new Auditoria($this->db))->registrar('clientes',$c['estado'] ? 'desactivar' : 'activar','clientes',$id,"Cliente {$c['nombre']}");
        return '';
    }

    public function eliminar(int $id): string {
        $c=$this->clientes->buscar($id);
        if (!$c) return 'El cliente no existe.';
        if ($this->esGeneral($c)) return 'El Cliente General no se puede eliminar.';
        $n=$this->clientes->contarVentas($id);
        if ($n) return "No se puede eliminar \"{$c['nombre']}\": tiene {$n} compra(s) registradas. Puedes desactivarlo.";
        $this->clientes->eliminar($id);
        (new Auditoria($this->db))->registrar('clientes','eliminar','clientes',0,"Cliente {$c['nombre']}".($c['documento'] ? " ({$c['documento']})" : '').' eliminado');
        return '';
    }

    private function validar(array $post, int $id): array {
        $limpiar=fn($k,$max) => mb_substr(trim((string)($post[$k] ?? '')),0,$max) ?: null;
        $d=[
            'documento'=>$limpiar('documento',30),
            'nombre'=>trim((string)($post['nombre'] ?? '')),
            'telefono'=>$limpiar('telefono',30),
            'email'=>$limpiar('email',100),
            'direccion'=>$limpiar('direccion',200),
            'ciudad'=>$limpiar('ciudad',100),
        ];
        $e=[];
        if ($d['nombre']==='' || mb_strlen($d['nombre'])>100) $e[]='El nombre es obligatorio (máximo 100 caracteres).';
        if ($d['documento']!==null) {
            if (!preg_match('/^[A-Za-z0-9.\-]{3,30}$/',$d['documento'])) $e[]='El documento solo puede tener letras, números, puntos y guiones (mínimo 3).';
            elseif ($this->clientes->existeDocumento($d['documento'],$id)) $e[]="Ya existe un cliente con el documento {$d['documento']}.";
        }
        if ($d['telefono']!==null && !preg_match('/^[0-9+()\s\-]{7,30}$/',$d['telefono'])) $e[]='El teléfono no es válido.';
        if ($d['email']!==null && !filter_var($d['email'],FILTER_VALIDATE_EMAIL)) $e[]='El correo electrónico no es válido.';
        return [$d,$e];
    }
}
