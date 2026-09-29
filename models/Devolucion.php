<?php
class Devolucion {
    public function __construct(private PDO $db) {}

    // Filtros: desde, hasta (Y-m-d), q (factura, cliente o documento), id_venta.
    public function listar(array $f): array {
        $where=['d.fecha_devolucion >= :desde','d.fecha_devolucion < DATE_ADD(:hasta, INTERVAL 1 DAY)'];
        $p=['desde'=>$f['desde'],'hasta'=>$f['hasta']];
        if (($f['q'] ?? '')!=='') { $where[]='(v.numero_factura LIKE :q1 OR c.nombre LIKE :q2 OR c.documento LIKE :q3)'; $p['q1']=$p['q2']=$p['q3']='%'.$f['q'].'%'; }
        $s=$this->db->prepare("SELECT d.*,v.numero_factura,c.nombre cliente,u.nombre usuario,
                (SELECT COUNT(*) FROM detalle_devolucion dd WHERE dd.id_devolucion=d.id_devolucion) productos
            FROM devoluciones d INNER JOIN ventas v ON v.id_venta=d.id_venta
            LEFT JOIN clientes c ON c.id_cliente=v.id_cliente INNER JOIN usuarios u ON u.id_usuario=d.id_usuario
            WHERE ".implode(' AND ',$where)." ORDER BY d.fecha_devolucion DESC, d.id_devolucion DESC LIMIT 300");
        $s->execute($p);
        return $s->fetchAll();
    }
    public function deVenta(int $ventaId): array {
        $s=$this->db->prepare("SELECT d.*,u.nombre usuario FROM devoluciones d INNER JOIN usuarios u ON u.id_usuario=d.id_usuario
            WHERE d.id_venta=:v ORDER BY d.fecha_devolucion");
        $s->execute(['v'=>$ventaId]); return $s->fetchAll();
    }
    public function buscar(int $id): ?array {
        $s=$this->db->prepare("SELECT d.*,v.numero_factura,v.fecha_venta,c.nombre cliente,c.documento cliente_documento,u.nombre usuario,ca.nombre caja
            FROM devoluciones d INNER JOIN ventas v ON v.id_venta=d.id_venta
            LEFT JOIN clientes c ON c.id_cliente=v.id_cliente INNER JOIN usuarios u ON u.id_usuario=d.id_usuario
            LEFT JOIN aperturas_caja a ON a.id_apertura=d.id_apertura LEFT JOIN cajas ca ON ca.id_caja=a.id_caja
            WHERE d.id_devolucion=:id");
        $s->execute(['id'=>$id]); return $s->fetch() ?: null;
    }
    public function detalle(int $id): array {
        $s=$this->db->prepare("SELECT dd.*,p.nombre,p.codigo FROM detalle_devolucion dd
            INNER JOIN productos p ON p.id_producto=dd.id_producto WHERE dd.id_devolucion=:id ORDER BY dd.id_detalle_devolucion");
        $s->execute(['id'=>$id]); return $s->fetchAll();
    }
    // Productos de una venta con lo vendido, lo ya devuelto y lo que aún se puede devolver.
    public function disponibles(int $ventaId): array {
        $s=$this->db->prepare("SELECT dv.id_producto,p.nombre,p.codigo,p.unidad_medida,SUM(dv.cantidad) vendido,MAX(dv.precio_unitario) precio_unitario,
                COALESCE((SELECT SUM(dd.cantidad) FROM detalle_devolucion dd INNER JOIN devoluciones d ON d.id_devolucion=dd.id_devolucion
                    WHERE d.id_venta=dv.id_venta AND d.estado<>'anulada' AND dd.id_producto=dv.id_producto),0) devuelto
            FROM detalle_venta dv INNER JOIN productos p ON p.id_producto=dv.id_producto
            WHERE dv.id_venta=:v GROUP BY dv.id_producto,p.nombre,p.codigo,p.unidad_medida ORDER BY MIN(dv.id_detalle)");
        $s->execute(['v'=>$ventaId]);
        return array_map(fn($r) => $r+['disponible'=>round((float)$r['vendido']-(float)$r['devuelto'],3)], $s->fetchAll());
    }
    public function totalDevuelto(int $ventaId): float {
        $s=$this->db->prepare("SELECT COALESCE(SUM(total),0) FROM devoluciones WHERE id_venta=:v AND estado<>'anulada'");
        $s->execute(['v'=>$ventaId]); return (float)$s->fetchColumn();
    }
    public function crear(int $ventaId, int $usuarioId, ?int $aperturaId, string $motivo, float $total, ?string $observaciones): int {
        $this->db->prepare("INSERT INTO devoluciones (id_venta,id_usuario,id_apertura,motivo,total,estado,observaciones)
            VALUES (:v,:u,:a,:m,:t,'procesada',:o)")
            ->execute(['v'=>$ventaId,'u'=>$usuarioId,'a'=>$aperturaId,'m'=>$motivo,'t'=>$total,'o'=>$observaciones]);
        return (int)$this->db->lastInsertId();
    }
    public function agregarDetalle(int $devolucionId, int $productoId, float $cantidad, float $precio, float $subtotal): void {
        $this->db->prepare("INSERT INTO detalle_devolucion (id_devolucion,id_producto,cantidad,precio_unitario,subtotal) VALUES (:d,:p,:c,:pu,:s)")
            ->execute(['d'=>$devolucionId,'p'=>$productoId,'c'=>$cantidad,'pu'=>$precio,'s'=>$subtotal]);
    }
}
