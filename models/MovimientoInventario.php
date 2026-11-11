<?php
class MovimientoInventario {
    public function __construct(private PDO $db) {}

    // Filtros: desde, hasta (Y-m-d), tipo, q (producto: nombre o código), id_producto.
    public function listar(array $f): array {
        [$where,$p]=$this->filtros($f);
        $s=$this->db->prepare("SELECT m.*,p.codigo,p.nombre producto,p.unidad_medida,u.nombre usuario,v.numero_factura
            FROM movimientos_inventario m
            INNER JOIN productos p ON p.id_producto=m.id_producto
            INNER JOIN usuarios u ON u.id_usuario=m.id_usuario
            LEFT JOIN ventas v ON v.id_venta=m.id_venta
            WHERE {$where} ORDER BY m.fecha_movimiento DESC, m.id_movimiento DESC LIMIT 500");
        $s->execute($p);
        return $s->fetchAll();
    }
    // Cantidad de movimientos por tipo con los mismos filtros.
    public function conteoPorTipo(array $f): array {
        [$where,$p]=$this->filtros($f);
        $s=$this->db->prepare("SELECT m.tipo,COUNT(*) FROM movimientos_inventario m
            INNER JOIN productos p ON p.id_producto=m.id_producto WHERE {$where} GROUP BY m.tipo");
        $s->execute($p);
        return $s->fetchAll(PDO::FETCH_KEY_PAIR)+['entrada'=>0,'salida'=>0,'ajuste'=>0,'devolucion'=>0];
    }
    private function filtros(array $f): array {
        $where=['m.fecha_movimiento >= :desde','m.fecha_movimiento < DATE_ADD(:hasta, INTERVAL 1 DAY)'];
        $p=['desde'=>$f['desde'],'hasta'=>$f['hasta']];
        if (!empty($f['tipo'])) { $where[]='m.tipo=:tipo'; $p['tipo']=$f['tipo']; }
        if (!empty($f['id_producto'])) { $where[]='m.id_producto=:prod'; $p['prod']=(int)$f['id_producto']; }
        if (($f['q'] ?? '')!=='') { $where[]='(p.nombre LIKE :q1 OR p.codigo LIKE :q2)'; $p['q1']=$p['q2']='%'.$f['q'].'%'; }
        return [implode(' AND ',$where),$p];
    }
}
