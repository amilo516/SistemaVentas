<?php
class Venta {
    public function __construct(private PDO $db) {}

    // Filtros: desde, hasta (Y-m-d), estado, q (factura o cliente), id_usuario (solo sus ventas).
    public function listar(array $f=[]): array {
        $where=['v.fecha_venta >= :desde','v.fecha_venta < DATE_ADD(:hasta, INTERVAL 1 DAY)'];
        $p=['desde'=>$f['desde'],'hasta'=>$f['hasta']];
        if (!empty($f['estado'])) { $where[]='v.estado=:estado'; $p['estado']=$f['estado']; }
        if (!empty($f['q'])) { $where[]='(v.numero_factura LIKE :q1 OR c.nombre LIKE :q2 OR c.documento LIKE :q3)'; $p['q1']=$p['q2']=$p['q3']='%'.$f['q'].'%'; }
        if (!empty($f['id_usuario'])) { $where[]='v.id_usuario=:u'; $p['u']=$f['id_usuario']; }
        if (!empty($f['id_cliente'])) { $where[]='v.id_cliente=:cli'; $p['cli']=(int)$f['id_cliente']; }
        $s=$this->db->prepare("SELECT v.id_venta,v.numero_factura,v.fecha_venta,v.total,v.estado,c.nombre cliente,u.nombre usuario,
                (SELECT GROUP_CONCAT(DISTINCT mp.nombre SEPARATOR ', ') FROM venta_pagos vp
                    INNER JOIN metodos_pago mp ON mp.id_metodo_pago=vp.id_metodo_pago WHERE vp.id_venta=v.id_venta) metodos
            FROM ventas v LEFT JOIN clientes c ON c.id_cliente=v.id_cliente
            INNER JOIN usuarios u ON u.id_usuario=v.id_usuario
            WHERE ".implode(' AND ',$where)."
            ORDER BY v.fecha_venta DESC, v.id_venta DESC LIMIT 300");
        $s->execute($p);
        return $s->fetchAll();
    }
    public function buscar(int $id, bool $bloquear=false): ?array {
        $s=$this->db->prepare("SELECT v.*,c.nombre cliente,c.documento cliente_documento,u.nombre usuario
            FROM ventas v LEFT JOIN clientes c ON c.id_cliente=v.id_cliente
            INNER JOIN usuarios u ON u.id_usuario=v.id_usuario
            WHERE v.id_venta=:id".($bloquear ? " FOR UPDATE" : ""));
        $s->execute(['id'=>$id]);
        return $s->fetch() ?: null;
    }
    public function detalle(int $id): array {
        $s=$this->db->prepare("SELECT d.*,p.nombre,p.codigo,p.unidad_medida FROM detalle_venta d
            INNER JOIN productos p ON p.id_producto=d.id_producto WHERE d.id_venta=:id ORDER BY d.id_detalle");
        $s->execute(['id'=>$id]);
        return $s->fetchAll();
    }
    public function pagos(int $id): array {
        $s=$this->db->prepare("SELECT vp.*,mp.nombre metodo FROM venta_pagos vp
            INNER JOIN metodos_pago mp ON mp.id_metodo_pago=vp.id_metodo_pago WHERE vp.id_venta=:id");
        $s->execute(['id'=>$id]);
        return $s->fetchAll();
    }
    public function tieneDevoluciones(int $id): bool {
        $s=$this->db->prepare("SELECT 1 FROM devoluciones WHERE id_venta=:id AND estado<>'anulada' LIMIT 1");
        $s->execute(['id'=>$id]);
        return (bool)$s->fetchColumn();
    }
    public function metodosPago(): array {
        return $this->db->query("SELECT id_metodo_pago,nombre FROM metodos_pago WHERE estado=1 ORDER BY id_metodo_pago")->fetchAll();
    }
}
