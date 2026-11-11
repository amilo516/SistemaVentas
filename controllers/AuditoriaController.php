<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/permisos.php';
require_once __DIR__ . '/../models/Auditoria.php';

class AuditoriaController {
    public const POR_PAGINA = 50;
    private Auditoria $auditoria;
    public array $filtros;
    public function __construct(array $get) {
        $this->auditoria=new Auditoria((new Database())->getConnection());
        $fecha=fn($v,$def) => preg_match('/^\d{4}-\d{2}-\d{2}$/',(string)$v) ? (string)$v : $def;
        $this->filtros=[
            'desde'=>$fecha($get['desde'] ?? '', date('Y-m-d',strtotime('-7 days'))),
            'hasta'=>$fecha($get['hasta'] ?? '', date('Y-m-d')),
            'id_usuario'=>(int)($get['usuario'] ?? 0),
            'modulo'=>mb_substr((string)($get['modulo'] ?? ''),0,100),
            'accion'=>mb_substr((string)($get['accion'] ?? ''),0,100),
            'q'=>mb_substr(trim((string)($get['q'] ?? '')),0,100),
        ];
    }
    public function pagina(int $n): array {
        $total=$this->auditoria->contar($this->filtros);
        $paginas=max(1,(int)ceil($total/self::POR_PAGINA));
        $n=min(max($n,1),$paginas);
        return ['registros'=>$this->auditoria->listar($this->filtros,self::POR_PAGINA,($n-1)*self::POR_PAGINA),'total'=>$total,'pagina'=>$n,'paginas'=>$paginas];
    }
    public function todos(): array { return $this->auditoria->listar($this->filtros,20000); }
    public function resumen(): array { return $this->auditoria->resumen($this->filtros); }
    public function opciones(): array {
        return ['modulos'=>$this->auditoria->modulos(),'acciones'=>$this->auditoria->acciones(),'usuarios'=>$this->auditoria->usuarios()];
    }
    // Enlace al registro afectado, si existe una pantalla para verlo.
    public static function enlace(array $r): ?string {
        $id=(int)$r['id_registro'];
        if (!$id || $r['modulo']==='sesion') return null;
        $mapa=[
            'ventas'=>['ventas.ver','ventas/detalle.php?id='], 'devoluciones'=>['devoluciones.ver','devoluciones/detalle.php?id='],
            'productos'=>['productos.editar','productos/editar.php?id='], 'clientes'=>['clientes.ver','clientes/editar.php?id='],
            'usuarios'=>['usuarios.editar','usuarios/editar.php?id='], 'roles'=>['roles.gestionar','roles/editar.php?id='],
            'aperturas_caja'=>['caja.ver','caja/movimientos.php?id='], 'rol_permisos'=>['roles.gestionar','roles/editar.php?id='],
        ];
        [$permiso,$ruta]=$mapa[$r['tabla_afectada']] ?? [null,null];
        return $ruta && tienePermiso($permiso) ? APP_URL.'/views/'.$ruta.$id : null;
    }
}
