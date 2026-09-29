<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/permisos.php';
require_once __DIR__ . '/../helpers/funciones.php';
require_once __DIR__ . '/../helpers/inventario.php';
require_once __DIR__ . '/../helpers/factura.php';
require_once __DIR__ . '/../helpers/productos.php';
require_once __DIR__ . '/../models/Devolucion.php';
require_once __DIR__ . '/../models/Venta.php';
require_once __DIR__ . '/../models/AperturaCaja.php';
require_once __DIR__ . '/../models/MovimientoCaja.php';
require_once __DIR__ . '/../models/Auditoria.php';

// Devoluciones de ventas: reintegran stock (opcional) y registran el reembolso.
class DevolucionController {
    private PDO $db;
    private Devolucion $devoluciones;
    private Venta $ventas;
    public function __construct() {
        $this->db=(new Database())->getConnection();
        $this->devoluciones=new Devolucion($this->db);
        $this->ventas=new Venta($this->db);
    }

    public function listar(array $filtros): array { return $this->devoluciones->listar($filtros); }
    public function deVenta(int $ventaId): array { return $this->devoluciones->deVenta($ventaId); }
    public function totalDevuelto(int $ventaId): float { return $this->devoluciones->totalDevuelto($ventaId); }
    public function ver(int $id): ?array {
        $d=$this->devoluciones->buscar($id);
        if ($d) $d['items']=$this->devoluciones->detalle($id);
        return $d;
    }
    public function buscarVentaPorFactura(string $numero): ?array {
        $s=$this->db->prepare("SELECT id_venta FROM ventas WHERE numero_factura=:n LIMIT 1");
        $s->execute(['n'=>trim($numero)]);
        $id=$s->fetchColumn();
        return $id ? $this->ventas->buscar((int)$id) : null;
    }
    public function venta(int $id): ?array { return $this->ventas->buscar($id); }
    public function miCajaAbierta(): ?array { return (new AperturaCaja($this->db))->abiertaDeUsuario((int)$_SESSION['id_usuario']); }

    // Productos de la venta con lo disponible para devolver y el precio real pagado (con descuento/impuesto prorrateado).
    public function productosDevolvibles(array $venta): array {
        $factor=$this->factor($venta);
        return array_map(fn($p) => $p+['precio_pagado'=>round((float)$p['precio_unitario']*$factor,2),'decimal'=>permiteDecimales($p['unidad_medida'])],
            $this->devoluciones->disponibles((int)$venta['id_venta']));
    }

    // Devuelve [id de la devolución, errores].
    public function registrar(int $ventaId, array $post): array {
        $cantidades=array_filter(array_map(fn($v) => round((float)str_replace(',','.',(string)$v),3), (array)($post['cantidad'] ?? [])), fn($c) => $c!=0.0);
        $motivo=mb_substr(trim($post['motivo'] ?? ''),0,255);
        $reintegrar=!empty($post['reintegrar']);
        $reembolso=$post['reembolso'] ?? '';
        $referencia=mb_substr(trim($post['referencia'] ?? ''),0,100);
        $errores=[];
        if (!$cantidades) $errores[]='Indica la cantidad a devolver de al menos un producto.';
        if ($motivo==='') $errores[]='Escribe el motivo de la devolución.';
        if (!in_array($reembolso,['efectivo','otro'],true)) $errores[]='Selecciona cómo se reembolsa el dinero.';
        if ($errores) return [0,$errores];
        $usuarioId=(int)$_SESSION['id_usuario'];
        $this->db->beginTransaction();
        try {
            $venta=$this->ventas->buscar($ventaId,true);
            if (!$venta) throw new RuntimeException('La venta no existe.');
            if ($venta['estado']!=='pagada') throw new RuntimeException('Solo se pueden devolver productos de ventas pagadas (esta venta está '.$venta['estado'].').');
            $disponibles=array_column($this->productosDevolvibles($venta),null,'id_producto');
            $lineas=[]; $total=0.0;
            foreach ($cantidades as $productoId=>$cant) {
                $p=$disponibles[$productoId] ?? null;
                if (!$p) throw new RuntimeException('Uno de los productos no pertenece a esta venta.');
                if ($cant<0) throw new RuntimeException("La cantidad de \"{$p['nombre']}\" no puede ser negativa.");
                if (!$p['decimal'] && floor($cant)!=$cant) throw new RuntimeException("La cantidad de \"{$p['nombre']}\" debe ser un número entero.");
                if ($cant>$p['disponible']+0.0005) throw new RuntimeException("Solo se pueden devolver ".cantidad($p['disponible'])." de \"{$p['nombre']}\".");
                $subtotal=round($cant*$p['precio_pagado'],2);
                $lineas[]=[$productoId,$cant,$p['precio_pagado'],$subtotal,$p];
                $total+=$subtotal;
            }
            $total=round($total,2);
            // Nunca se devuelve más de lo que se cobró en la venta; si el redondeo lo excede, se ajusta la última línea.
            $maximo=round((float)$venta['total']-$this->devoluciones->totalDevuelto($ventaId),2);
            if ($total>$maximo) {
                $ultima=count($lineas)-1;
                $lineas[$ultima][3]=round($lineas[$ultima][3]-($total-$maximo),2);
                $total=$maximo;
            }

            $aperturaId=null; $notaReembolso='';
            if ($reembolso==='efectivo') {
                $caja=$this->miCajaAbierta();
                if (!$caja) throw new RuntimeException('Para reembolsar en efectivo debes tener tu caja abierta.');
                $t=(new MovimientoCaja($this->db))->totalesPorTipo((int)$caja['id_apertura']);
                $efectivo=(float)$caja['monto_inicial']+$t['venta']+$t['ingreso']-$t['egreso']-$t['devolucion'];
                if ($total>$efectivo+0.001) throw new RuntimeException('No hay suficiente efectivo en tu caja. Disponible: '.dinero($efectivo).'.');
                $aperturaId=(int)$caja['id_apertura'];
                $notaReembolso="Reembolso en efectivo desde {$caja['caja']}.";
            } else {
                $notaReembolso='Reembolso por otro medio'.($referencia!=='' ? " (ref. {$referencia})" : '').'.';
            }
            $obs=trim($notaReembolso.($reintegrar ? '' : ' Productos NO reintegrados al inventario (dañados o no aptos para la venta).'));

            $devId=$this->devoluciones->crear($ventaId,$usuarioId,$aperturaId,$motivo,$total,$obs);
            foreach ($lineas as [$productoId,$cant,$precio,$subtotal]) {
                $this->devoluciones->agregarDetalle($devId,(int)$productoId,$cant,$precio,$subtotal);
                if ($reintegrar) actualizarStock($this->db,(int)$productoId,$cant,'devolucion',$usuarioId,$ventaId,'Devolución: '.$motivo,$venta['numero_factura']);
            }
            if ($aperturaId) {
                $this->db->prepare("INSERT INTO movimientos_caja (id_apertura,id_usuario,id_venta,tipo,concepto,monto) VALUES (:a,:u,:v,'devolucion',:c,:m)")
                    ->execute(['a'=>$aperturaId,'u'=>$usuarioId,'v'=>$ventaId,'c'=>'Devolución venta '.$venta['numero_factura'],'m'=>$total]);
            }
            (new Auditoria($this->db))->registrar('devoluciones','crear','devoluciones',$devId,
                "Devolución de ".dinero($total)." sobre {$venta['numero_factura']}: {$motivo}");
            $this->db->commit();
            return [$devId,[]];
        } catch (RuntimeException $e) {
            $this->db->rollBack();
            return [0,[$e->getMessage()]];
        }
    }

    // Proporción entre lo cobrado y el subtotal: reparte descuento e impuesto de la venta en cada producto.
    private function factor(array $venta): float {
        $sub=(float)$venta['subtotal'];
        return $sub>0 ? (float)$venta['total']/$sub : 1.0;
    }
}
