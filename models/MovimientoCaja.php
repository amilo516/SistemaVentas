<?php
class MovimientoCaja {
    public function __construct(private PDO $db) {}

    public function listar(int $aperturaId): array {
        $s=$this->db->prepare("SELECT m.*,u.nombre usuario,v.numero_factura FROM movimientos_caja m
            INNER JOIN usuarios u ON u.id_usuario=m.id_usuario
            LEFT JOIN ventas v ON v.id_venta=m.id_venta
            WHERE m.id_apertura=:a ORDER BY m.fecha DESC, m.id_movimiento_caja DESC");
        $s->execute(['a'=>$aperturaId]); return $s->fetchAll();
    }
    // Totales por tipo: ['venta'=>..., 'ingreso'=>..., 'egreso'=>..., 'devolucion'=>...]
    public function totalesPorTipo(int $aperturaId): array {
        $s=$this->db->prepare("SELECT tipo,COALESCE(SUM(monto),0) total FROM movimientos_caja WHERE id_apertura=:a GROUP BY tipo");
        $s->execute(['a'=>$aperturaId]);
        return $s->fetchAll(PDO::FETCH_KEY_PAIR)+['venta'=>0,'ingreso'=>0,'egreso'=>0,'devolucion'=>0];
    }
    public function crear(int $aperturaId, int $usuarioId, string $tipo, string $concepto, float $monto): int {
        $this->db->prepare("INSERT INTO movimientos_caja (id_apertura,id_usuario,tipo,concepto,monto) VALUES (:a,:u,:t,:c,:m)")
            ->execute(['a'=>$aperturaId,'u'=>$usuarioId,'t'=>$tipo,'c'=>$concepto,'m'=>$monto]);
        return (int)$this->db->lastInsertId();
    }
    // Ventas del turno agrupadas por método de pago (incluye tarjeta, Nequi, etc.).
    public function ventasPorMetodo(int $aperturaId): array {
        $s=$this->db->prepare("SELECT mp.nombre metodo,COUNT(DISTINCT v.id_venta) cantidad,SUM(vp.monto) total
            FROM ventas v INNER JOIN venta_pagos vp ON vp.id_venta=v.id_venta
            INNER JOIN metodos_pago mp ON mp.id_metodo_pago=vp.id_metodo_pago
            WHERE v.id_apertura=:a AND v.estado<>'anulada' GROUP BY mp.id_metodo_pago,mp.nombre ORDER BY total DESC");
        $s->execute(['a'=>$aperturaId]); return $s->fetchAll();
    }
}
