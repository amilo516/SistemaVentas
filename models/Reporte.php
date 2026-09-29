<?php
// Consultas de los reportes. Todas reciben un rango de fechas Y-m-d (inclusive).
class Reporte {
    public function __construct(private PDO $db) {}

    private function consultar(string $sql, array $p): array {
        $s=$this->db->prepare($sql); $s->execute($p); return $s->fetchAll();
    }
    private function rango(string $d, string $h): array { return ['d'=>$d,'h'=>$h]; }
    private const EN_RANGO = ">= :d AND %s < DATE_ADD(:h, INTERVAL 1 DAY)";
    private function entre(string $campo): string { return $campo.' '.sprintf(self::EN_RANGO,$campo); }

    // ---------- Ventas ----------
    public function resumenVentas(string $d, string $h): array {
        $r=$this->consultar("SELECT COUNT(*) ventas,COALESCE(SUM(total),0) total,COALESCE(AVG(total),0) promedio,
                COALESCE(SUM(descuento),0) descuentos,COALESCE(SUM(impuesto),0) impuestos
            FROM ventas WHERE estado='pagada' AND ".$this->entre('fecha_venta'),$this->rango($d,$h))[0];
        $r['anuladas']=(int)$this->consultar("SELECT COUNT(*) n FROM ventas WHERE estado='anulada' AND ".$this->entre('fecha_venta'),$this->rango($d,$h))[0]['n'];
        $r['devoluciones']=(float)$this->consultar("SELECT COALESCE(SUM(total),0) t FROM devoluciones WHERE estado<>'anulada' AND ".$this->entre('fecha_devolucion'),$this->rango($d,$h))[0]['t'];
        return $r;
    }
    // Agrupa por día o por mes ('%Y-%m-%d' o '%Y-%m').
    public function ventasPorPeriodo(string $d, string $h, string $formato): array {
        return $this->consultar("SELECT DATE_FORMAT(fecha_venta,'{$formato}') periodo,COUNT(*) ventas,SUM(total) total
            FROM ventas WHERE estado='pagada' AND ".$this->entre('fecha_venta')." GROUP BY periodo ORDER BY periodo",$this->rango($d,$h));
    }
    public function ventasPorMetodo(string $d, string $h): array {
        return $this->consultar("SELECT mp.nombre,COUNT(DISTINCT v.id_venta) ventas,SUM(vp.monto) total
            FROM venta_pagos vp INNER JOIN ventas v ON v.id_venta=vp.id_venta INNER JOIN metodos_pago mp ON mp.id_metodo_pago=vp.id_metodo_pago
            WHERE v.estado='pagada' AND ".$this->entre('v.fecha_venta')." GROUP BY mp.id_metodo_pago,mp.nombre ORDER BY total DESC",$this->rango($d,$h));
    }
    public function ventasPorVendedor(string $d, string $h): array {
        return $this->consultar("SELECT u.nombre,COUNT(*) ventas,SUM(v.total) total,AVG(v.total) promedio
            FROM ventas v INNER JOIN usuarios u ON u.id_usuario=v.id_usuario
            WHERE v.estado='pagada' AND ".$this->entre('v.fecha_venta')." GROUP BY u.id_usuario,u.nombre ORDER BY total DESC",$this->rango($d,$h));
    }
    public function listadoVentas(string $d, string $h): array {
        return $this->consultar("SELECT v.numero_factura,v.fecha_venta,c.nombre cliente,u.nombre vendedor,
                (SELECT GROUP_CONCAT(mp.nombre SEPARATOR ', ') FROM venta_pagos vp INNER JOIN metodos_pago mp ON mp.id_metodo_pago=vp.id_metodo_pago WHERE vp.id_venta=v.id_venta) metodo,
                v.subtotal,v.descuento,v.impuesto,v.total,v.estado
            FROM ventas v LEFT JOIN clientes c ON c.id_cliente=v.id_cliente INNER JOIN usuarios u ON u.id_usuario=v.id_usuario
            WHERE ".$this->entre('v.fecha_venta')." ORDER BY v.fecha_venta",$this->rango($d,$h));
    }

    // ---------- Ganancias y productos ----------
    // Por producto: unidades e ingreso sin impuesto (descuento prorrateado), menos lo devuelto de esas ventas.
    public function rendimientoProductos(string $d, string $h): array {
        $p=$this->rango($d,$h);
        $vendidos=$this->consultar("SELECT p.id_producto,p.codigo,p.nombre,p.unidad_medida,p.precio_compra,c.nombre categoria,
                SUM(dv.cantidad) unidades,SUM(dv.subtotal*(v.subtotal-v.descuento)/NULLIF(v.subtotal,0)) ingreso
            FROM detalle_venta dv INNER JOIN ventas v ON v.id_venta=dv.id_venta
            INNER JOIN productos p ON p.id_producto=dv.id_producto INNER JOIN categorias c ON c.id_categoria=p.id_categoria
            WHERE v.estado='pagada' AND ".$this->entre('v.fecha_venta')." GROUP BY p.id_producto,p.codigo,p.nombre,p.unidad_medida,p.precio_compra,c.nombre",$p);
        $devueltos=$this->consultar("SELECT dd.id_producto,SUM(dd.cantidad) unidades,SUM(dd.subtotal*(v.subtotal-v.descuento)/NULLIF(v.total,0)) ingreso
            FROM detalle_devolucion dd INNER JOIN devoluciones de ON de.id_devolucion=dd.id_devolucion INNER JOIN ventas v ON v.id_venta=de.id_venta
            WHERE de.estado<>'anulada' AND v.estado='pagada' AND ".$this->entre('v.fecha_venta')." GROUP BY dd.id_producto",$p);
        $dev=array_column($devueltos,null,'id_producto');
        $filas=[];
        foreach ($vendidos as $r) {
            $x=$dev[$r['id_producto']] ?? ['unidades'=>0,'ingreso'=>0];
            $unidades=(float)$r['unidades']-(float)$x['unidades'];
            $ingreso=round((float)$r['ingreso']-(float)$x['ingreso'],2);
            $costo=round($unidades*(float)$r['precio_compra'],2);
            $filas[]=array_merge($r,['unidades'=>$unidades,'ingreso'=>$ingreso,'devueltas'=>(float)$x['unidades'],'costo'=>$costo,
                'ganancia'=>round($ingreso-$costo,2),'margen'=>$ingreso>0 ? ($ingreso-$costo)/$ingreso*100 : 0]);
        }
        return $filas;
    }
    public function productosSinVentas(string $d, string $h): array {
        return $this->consultar("SELECT p.codigo,p.nombre,c.nombre categoria,p.stock,p.unidad_medida,p.precio_venta
            FROM productos p INNER JOIN categorias c ON c.id_categoria=p.id_categoria
            WHERE p.estado=1 AND NOT EXISTS (SELECT 1 FROM detalle_venta dv INNER JOIN ventas v ON v.id_venta=dv.id_venta
                WHERE dv.id_producto=p.id_producto AND v.estado='pagada' AND ".$this->entre('v.fecha_venta').")
            ORDER BY p.stock*p.precio_compra DESC LIMIT 100",$this->rango($d,$h));
    }

    // ---------- Caja ----------
    public function turnosCaja(string $d, string $h): array {
        return $this->consultar("SELECT a.*,c.nombre caja,u.nombre usuario,
                (SELECT COALESCE(SUM(m.monto),0) FROM movimientos_caja m WHERE m.id_apertura=a.id_apertura AND m.tipo='venta') ventas_efectivo
            FROM aperturas_caja a INNER JOIN cajas c ON c.id_caja=a.id_caja INNER JOIN usuarios u ON u.id_usuario=a.id_usuario
            WHERE ".$this->entre('a.fecha_apertura')." ORDER BY a.fecha_apertura DESC",$this->rango($d,$h));
    }

    // ---------- Inventario ----------
    public function valorInventario(): array {
        return $this->db->query("SELECT c.nombre categoria,COUNT(p.id_producto) productos,COALESCE(SUM(p.stock*p.precio_compra),0) costo,
                COALESCE(SUM(p.stock*p.precio_venta),0) venta,COALESCE(SUM(p.stock<=p.stock_minimo),0) bajos
            FROM categorias c INNER JOIN productos p ON p.id_categoria=c.id_categoria AND p.estado=1
            GROUP BY c.id_categoria,c.nombre ORDER BY costo DESC")->fetchAll();
    }
    public function movimientosPorTipo(string $d, string $h): array {
        return $this->consultar("SELECT tipo,COUNT(*) movimientos,COUNT(DISTINCT id_producto) productos
            FROM movimientos_inventario WHERE ".$this->entre('fecha_movimiento')." GROUP BY tipo",$this->rango($d,$h));
    }
    public function movimientosDetalle(string $d, string $h): array {
        return $this->consultar("SELECT m.fecha_movimiento,p.codigo,p.nombre,m.tipo,m.stock_anterior,m.stock_nuevo,m.motivo,m.referencia,u.nombre usuario
            FROM movimientos_inventario m INNER JOIN productos p ON p.id_producto=m.id_producto INNER JOIN usuarios u ON u.id_usuario=m.id_usuario
            WHERE ".$this->entre('m.fecha_movimiento')." ORDER BY m.fecha_movimiento",$this->rango($d,$h));
    }

    // ---------- Devoluciones ----------
    public function devolucionesPorMotivo(string $d, string $h): array {
        return $this->consultar("SELECT motivo,COUNT(*) devoluciones,SUM(total) total FROM devoluciones
            WHERE estado<>'anulada' AND ".$this->entre('fecha_devolucion')." GROUP BY motivo ORDER BY total DESC",$this->rango($d,$h));
    }
    public function devolucionesPorProducto(string $d, string $h): array {
        return $this->consultar("SELECT p.codigo,p.nombre,SUM(dd.cantidad) unidades,SUM(dd.subtotal) total
            FROM detalle_devolucion dd INNER JOIN devoluciones de ON de.id_devolucion=dd.id_devolucion INNER JOIN productos p ON p.id_producto=dd.id_producto
            WHERE de.estado<>'anulada' AND ".$this->entre('de.fecha_devolucion')." GROUP BY p.id_producto,p.codigo,p.nombre ORDER BY total DESC LIMIT 50",$this->rango($d,$h));
    }
    public function listadoDevoluciones(string $d, string $h): array {
        return $this->consultar("SELECT de.id_devolucion,de.fecha_devolucion,v.numero_factura,c.nombre cliente,u.nombre usuario,de.motivo,de.total,de.observaciones
            FROM devoluciones de INNER JOIN ventas v ON v.id_venta=de.id_venta LEFT JOIN clientes c ON c.id_cliente=v.id_cliente
            INNER JOIN usuarios u ON u.id_usuario=de.id_usuario
            WHERE de.estado<>'anulada' AND ".$this->entre('de.fecha_devolucion')." ORDER BY de.fecha_devolucion",$this->rango($d,$h));
    }
}
