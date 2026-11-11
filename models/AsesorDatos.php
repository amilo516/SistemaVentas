<?php
declare(strict_types=1);
// Consultas de solo lectura que usa el Asesor IA. Son las "herramientas" del agente:
// el modelo decide cuáles llamar, pero solo puede ejecutar estas consultas fijas (nunca SQL libre).
class AsesorDatos {
    public function __construct(private PDO $db) {}

    private function filas(string $sql, array $params = []): array {
        $s = $this->db->prepare($sql);
        $s->execute($params);
        return $s->fetchAll();
    }
    private static function dias(mixed $d, int $porDefecto = 30): int { return max(1, min(365, (int)($d ?: $porDefecto))); }

    // Productos activos con su venta promedio diaria y los días que alcanza el stock.
    public function inventarioCritico(int $dias = 30, float $diasCobertura = 7): array {
        $dias = self::dias($dias);
        $filas = $this->filas("SELECT p.codigo,p.nombre,p.unidad_medida,p.stock,p.stock_minimo,p.precio_compra,
                COALESCE(v.unidades,0) unidades_vendidas
            FROM productos p LEFT JOIN (
                SELECT dv.id_producto,SUM(dv.cantidad) unidades FROM detalle_venta dv
                INNER JOIN ventas v ON v.id_venta=dv.id_venta
                WHERE v.estado<>'anulada' AND v.fecha_venta >= CURDATE() - INTERVAL :d DAY GROUP BY dv.id_producto
            ) v ON v.id_producto=p.id_producto
            WHERE p.estado=1", ['d' => $dias]);
        $criticos = [];
        foreach ($filas as $f) {
            $promedio = (float)$f['unidades_vendidas'] / $dias;
            $cobertura = $promedio > 0 ? (float)$f['stock'] / $promedio : null;
            if ((float)$f['stock'] <= (float)$f['stock_minimo'] || ($cobertura !== null && $cobertura < $diasCobertura)) {
                $criticos[] = $f + ['promedio_diario' => round($promedio, 2), 'dias_cobertura' => $cobertura === null ? null : round($cobertura, 1)];
            }
        }
        usort($criticos, fn($a, $b) => ($a['dias_cobertura'] ?? 9999) <=> ($b['dias_cobertura'] ?? 9999) ?: (float)$a['stock'] <=> (float)$b['stock']);
        return $criticos;
    }

    public function masVendidos(int $dias = 30, int $limite = 10): array {
        return $this->filas("SELECT p.codigo,p.nombre,p.unidad_medida,SUM(dv.cantidad) unidades,SUM(dv.subtotal) ingreso,
                SUM(dv.cantidad*p.precio_compra) costo
            FROM detalle_venta dv INNER JOIN ventas v ON v.id_venta=dv.id_venta INNER JOIN productos p ON p.id_producto=dv.id_producto
            WHERE v.estado<>'anulada' AND v.fecha_venta >= CURDATE() - INTERVAL :d DAY
            GROUP BY p.id_producto,p.codigo,p.nombre,p.unidad_medida ORDER BY ingreso DESC LIMIT " . max(1, min(30, $limite)),
            ['d' => self::dias($dias)]);
    }

    // Productos con stock que no se venden: capital inmovilizado (stock x costo).
    public function sinMovimiento(int $dias = 30): array {
        return $this->filas("SELECT p.codigo,p.nombre,p.unidad_medida,p.stock,p.precio_venta,p.precio_compra,
                ROUND(p.stock*p.precio_compra,2) capital_inmovilizado,
                (SELECT MAX(v.fecha_venta) FROM detalle_venta dv INNER JOIN ventas v ON v.id_venta=dv.id_venta
                    WHERE dv.id_producto=p.id_producto AND v.estado<>'anulada') ultima_venta
            FROM productos p WHERE p.estado=1 AND p.stock>0 AND NOT EXISTS (
                SELECT 1 FROM detalle_venta dv INNER JOIN ventas v ON v.id_venta=dv.id_venta
                WHERE dv.id_producto=p.id_producto AND v.estado<>'anulada' AND v.fecha_venta >= CURDATE() - INTERVAL :d DAY)
            ORDER BY capital_inmovilizado DESC LIMIT 20", ['d' => self::dias($dias)]);
    }

    public function ventasPorDia(int $dias = 14): array {
        return $this->filas("SELECT DATE(fecha_venta) fecha,COUNT(*) ventas,SUM(total) total
            FROM ventas WHERE estado<>'anulada' AND fecha_venta >= CURDATE() - INTERVAL :d DAY
            GROUP BY DATE(fecha_venta) ORDER BY fecha", ['d' => self::dias($dias, 14) - 1]);
    }

    // Total de los últimos N días frente a los N días anteriores.
    public function comparativo(int $dias = 7): array {
        $dias = self::dias($dias, 7);
        $f = $this->filas("SELECT
                COALESCE(SUM(CASE WHEN fecha_venta >= CURDATE() - INTERVAL :a DAY THEN total END),0) actual,
                COALESCE(SUM(CASE WHEN fecha_venta < CURDATE() - INTERVAL :b DAY THEN total END),0) anterior
            FROM ventas WHERE estado<>'anulada' AND fecha_venta >= CURDATE() - INTERVAL :c DAY",
            ['a' => $dias - 1, 'b' => $dias - 1, 'c' => $dias * 2 - 1])[0];
        $f['dias'] = $dias;
        $f['variacion_pct'] = (float)$f['anterior'] > 0 ? round(((float)$f['actual'] - (float)$f['anterior']) / (float)$f['anterior'] * 100, 1) : null;
        return $f;
    }

    // Productos devueltos frente a lo vendido en el mismo periodo, con los motivos.
    public function devoluciones(int $dias = 30): array {
        return $this->filas("SELECT p.codigo,p.nombre,p.unidad_medida,SUM(dd.cantidad) unidades_devueltas,SUM(dd.subtotal) valor_devuelto,
                (SELECT COALESCE(SUM(dv.cantidad),0) FROM detalle_venta dv INNER JOIN ventas v ON v.id_venta=dv.id_venta
                    WHERE dv.id_producto=p.id_producto AND v.estado<>'anulada' AND v.fecha_venta >= CURDATE() - INTERVAL :a DAY) unidades_vendidas,
                GROUP_CONCAT(DISTINCT d.motivo SEPARATOR ' | ') motivos
            FROM detalle_devolucion dd INNER JOIN devoluciones d ON d.id_devolucion=dd.id_devolucion
            INNER JOIN productos p ON p.id_producto=dd.id_producto
            WHERE d.estado<>'anulada' AND d.fecha_devolucion >= CURDATE() - INTERVAL :b DAY
            GROUP BY p.id_producto,p.codigo,p.nombre,p.unidad_medida ORDER BY unidades_devueltas DESC LIMIT 15",
            ['a' => self::dias($dias), 'b' => self::dias($dias)]);
    }

    // Productos activos cuyo margen sobre el precio de venta es menor al mínimo indicado.
    public function margenBajo(float $minimo = 15): array {
        return $this->filas("SELECT codigo,nombre,precio_compra,precio_venta,
                ROUND((precio_venta-precio_compra)/precio_venta*100,1) margen_pct
            FROM productos WHERE estado=1 AND precio_venta>0 AND (precio_venta-precio_compra)/precio_venta*100 < :m
            ORDER BY margen_pct LIMIT 20", ['m' => max(0, min(90, $minimo))]);
    }
}
