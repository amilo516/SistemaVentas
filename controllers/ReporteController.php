<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/permisos.php';
require_once __DIR__ . '/../helpers/funciones.php';
require_once __DIR__ . '/../helpers/factura.php';
require_once __DIR__ . '/../models/Reporte.php';

class ReporteController {
    private Reporte $reporte;
    public string $desde;
    public string $hasta;
    public function __construct(array $get) {
        $this->reporte=new Reporte((new Database())->getConnection());
        $fecha=fn($v,$def) => preg_match('/^\d{4}-\d{2}-\d{2}$/',(string)$v) && strtotime((string)$v) ? (string)$v : $def;
        $this->desde=$fecha($get['desde'] ?? '', date('Y-m-01'));
        $this->hasta=$fecha($get['hasta'] ?? '', date('Y-m-d'));
        if ($this->desde>$this->hasta) [$this->desde,$this->hasta]=[$this->hasta,$this->desde];
    }
    public function __call(string $metodo, array $args): mixed {
        return $this->reporte->$metodo($this->desde,$this->hasta,...$args);
    }
    public function valorInventario(): array { return $this->reporte->valorInventario(); }

    // Serie de ventas por día (o por mes si el rango supera 62 días), con los periodos sin ventas en cero.
    public function serieVentas(): array {
        $mensual=(strtotime($this->hasta)-strtotime($this->desde))/86400>62;
        $datos=array_column($this->reporte->ventasPorPeriodo($this->desde,$this->hasta,$mensual ? '%Y-%m' : '%Y-%m-%d'),null,'periodo');
        $meses=['ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'];
        $serie=[];
        $t=strtotime($mensual ? date('Y-m-01',strtotime($this->desde)) : $this->desde);
        while ($t<=strtotime($this->hasta)) {
            $k=date($mensual ? 'Y-m' : 'Y-m-d',$t);
            $etq=$mensual ? $meses[(int)date('n',$t)-1].' '.date('Y',$t) : date('d',$t).' '.$meses[(int)date('n',$t)-1];
            $serie[]=['etiqueta'=>$etq,'valor'=>(float)($datos[$k]['total'] ?? 0),'detalle'=>(int)($datos[$k]['ventas'] ?? 0).' ventas'];
            $t=strtotime($mensual ? '+1 month' : '+1 day',$t);
        }
        return ['mensual'=>$mensual,'puntos'=>$serie];
    }
}
