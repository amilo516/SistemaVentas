<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constantes.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/permisos.php';

class DashboardController {
    private PDO $db;
    public function __construct() { $this->db=(new Database())->getConnection(); }

    // Totales del día y del mes (sin ventas ni devoluciones anuladas). Si una consulta falla, esa tarjeta queda en null.
    public function resumen(): array {
        return [
            'ventas' => $this->consultar("SELECT
                    COALESCE(SUM(CASE WHEN DATE(fecha_venta)=CURDATE() THEN total END),0) AS total_hoy,
                    COUNT(CASE WHEN DATE(fecha_venta)=CURDATE() THEN 1 END) AS cantidad_hoy,
                    COALESCE(SUM(total),0) AS total_mes,
                    COUNT(*) AS cantidad_mes
                FROM ventas
                WHERE fecha_venta >= DATE_FORMAT(CURDATE(),'%Y-%m-01') AND estado <> 'anulada'"),
            'cajas' => $this->consultar("SELECT
                    (SELECT COUNT(*) FROM aperturas_caja WHERE estado='abierta') AS abiertas,
                    (SELECT COUNT(*) FROM cajas WHERE estado=1) AS total,
                    (SELECT COALESCE(SUM(monto_inicial),0) FROM aperturas_caja WHERE estado='abierta') AS monto_inicial"),
            'devoluciones' => $this->consultar("SELECT
                    COALESCE(SUM(CASE WHEN DATE(fecha_devolucion)=CURDATE() THEN total END),0) AS total_hoy,
                    COUNT(CASE WHEN DATE(fecha_devolucion)=CURDATE() THEN 1 END) AS cantidad_hoy,
                    COALESCE(SUM(total),0) AS total_mes,
                    COUNT(*) AS cantidad_mes
                FROM devoluciones
                WHERE fecha_devolucion >= DATE_FORMAT(CURDATE(),'%Y-%m-01') AND estado <> 'anulada'"),
        ];
    }

    // Las 5 ventas más recientes. El vendedor solo ve las suyas.
    public function ultimasVentas(): array {
        $propias=(int)($_SESSION['rol_id'] ?? 0)===ROL_VENDEDOR;
        return $this->consultarVarias("SELECT v.id_venta,v.numero_factura,v.fecha_venta,v.total,v.estado,c.nombre cliente,
                (SELECT GROUP_CONCAT(DISTINCT mp.nombre SEPARATOR ', ') FROM venta_pagos vp
                    INNER JOIN metodos_pago mp ON mp.id_metodo_pago=vp.id_metodo_pago WHERE vp.id_venta=v.id_venta) metodos
            FROM ventas v LEFT JOIN clientes c ON c.id_cliente=v.id_cliente
            ".($propias ? "WHERE v.id_usuario=:u " : "")."ORDER BY v.fecha_venta DESC, v.id_venta DESC LIMIT 5",
            $propias ? ['u'=>(int)$_SESSION['id_usuario']] : []);
    }

    // Top 5 de productos del mes por dinero vendido (sin ventas anuladas).
    public function masVendidosMes(): array {
        return $this->consultarVarias("SELECT p.nombre,p.unidad_medida,SUM(dv.cantidad) unidades,SUM(dv.subtotal) total
            FROM detalle_venta dv INNER JOIN ventas v ON v.id_venta=dv.id_venta
            INNER JOIN productos p ON p.id_producto=dv.id_producto
            WHERE v.estado<>'anulada' AND v.fecha_venta >= DATE_FORMAT(CURDATE(),'%Y-%m-01')
            GROUP BY p.id_producto,p.nombre,p.unidad_medida ORDER BY total DESC LIMIT 5");
    }

    private function consultarVarias(string $sql, array $params=[]): array {
        try {
            $s=$this->db->prepare($sql);
            $s->execute($params);
            return $s->fetchAll();
        } catch (PDOException $e) {
            error_log('Dashboard: ' . $e->getMessage());
            return [];
        }
    }

    private function consultar(string $sql): ?array {
        try {
            return $this->db->query($sql)->fetch() ?: null;
        } catch (PDOException $e) {
            error_log('Dashboard: ' . $e->getMessage());
            return null;
        }
    }
}
