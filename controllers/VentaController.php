<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constantes.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/permisos.php';
require_once __DIR__ . '/../helpers/factura.php';
require_once __DIR__ . '/../helpers/inventario.php';
require_once __DIR__ . '/../helpers/funciones.php';
require_once __DIR__ . '/../models/Venta.php';
require_once __DIR__ . '/../models/Carrito.php';
require_once __DIR__ . '/../models/Cliente.php';
require_once __DIR__ . '/../models/Configuracion.php';
require_once __DIR__ . '/../models/Auditoria.php';
require_once __DIR__ . '/../models/AperturaCaja.php';

class VentaController {
    private PDO $db;
    private Venta $ventas;
    public function __construct() {
        $this->db=(new Database())->getConnection();
        $this->ventas=new Venta($this->db);
    }

    // El vendedor solo ve sus propias ventas; los demás roles ven todas.
    public function soloPropias(): bool { return (int)$_SESSION['rol_id']===ROL_VENDEDOR; }

    public function listar(array $filtros): array {
        if ($this->soloPropias()) $filtros['id_usuario']=(int)$_SESSION['id_usuario'];
        return $this->ventas->listar($filtros);
    }
    // Devuelve la venta con su detalle y pagos, o null si no existe o el usuario no puede verla.
    public function ver(int $id): ?array {
        $v=$this->ventas->buscar($id);
        if (!$v || ($this->soloPropias() && (int)$v['id_usuario']!==(int)$_SESSION['id_usuario'])) return null;
        $v['items']=$this->ventas->detalle($id);
        $v['pagos']=$this->ventas->pagos($id);
        return $v;
    }
    public function datosFormulario(): array {
        return [
            'clientes'=>(new Cliente($this->db))->listarActivos(),
            'metodos'=>$this->ventas->metodosPago(),
            'impuesto_pct'=>(float)(new Configuracion($this->db))->obtener('impuesto','0'),
            'hay_caja_abierta'=>(new AperturaCaja($this->db))->hayAlgunaAbierta(),
        ];
    }

    // Registra la venta del carrito activo. Devuelve el id de la venta.
    public function finalizar(array $post): int {
        $usuarioId=(int)$_SESSION['id_usuario'];
        $carrito=new Carrito($this->db);
        $carritoId=$carrito->activo($usuarioId);
        $this->db->beginTransaction();
        try {
            $items=$carrito->items($carritoId, true);
            if (!$items) throw new RuntimeException('El carrito está vacío.');
            foreach ($items as $i) {
                if (!(int)$i['estado']) throw new RuntimeException("\"{$i['nombre']}\" ya no está disponible. Quítalo del carrito.");
                if ((float)$i['cantidad']>(float)$i['stock']) throw new RuntimeException("Stock insuficiente para \"{$i['nombre']}\". Disponible: ".cantidad($i['stock']).'.');
            }
            $clienteId=(int)($post['id_cliente'] ?? 0);
            if (!(new Cliente($this->db))->existeActivo($clienteId)) throw new RuntimeException('Selecciona un cliente válido.');

            $pct=(float)(new Configuracion($this->db))->obtener('impuesto','0');
            $descuento=(float)str_replace(',','.',(string)($post['descuento'] ?? 0));
            if ($descuento<0) throw new RuntimeException('El descuento no puede ser negativo.');
            $t=calcularTotales($items, $descuento, $pct);
            if ($descuento>$t['subtotal']) throw new RuntimeException('El descuento no puede ser mayor que el subtotal.');

            $metodo=$this->metodoPago((int)($post['id_metodo_pago'] ?? 0));
            $esEfectivo=mb_strtolower($metodo['nombre'])==='efectivo';
            if ($esEfectivo) {
                $recibido=(float)str_replace(',','.',(string)($post['recibido'] ?? 0));
                if ($recibido<$t['total']) throw new RuntimeException('El dinero recibido es menor que el total a pagar.');
                $referencia='Recibido '.dinero($recibido).' · Cambio '.dinero($recibido-$t['total']);
            } else {
                $referencia=mb_substr(trim((string)($post['referencia'] ?? '')),0,100) ?: null;
            }
            $observaciones=mb_substr(trim((string)($post['observaciones'] ?? '')),0,1000) ?: null;
            $aperturaId=$this->aperturaAbierta($usuarioId);

            $this->db->prepare("INSERT INTO ventas (numero_factura,id_cliente,id_usuario,id_apertura,subtotal,descuento,impuesto,total,estado,observaciones)
                VALUES (:n,:c,:u,:a,:s,:d,:i,:t,'pagada',:o)")
                ->execute(['n'=>'TMP-'.bin2hex(random_bytes(8)),'c'=>$clienteId,'u'=>$usuarioId,'a'=>$aperturaId,
                    's'=>$t['subtotal'],'d'=>$t['descuento'],'i'=>$t['impuesto'],'t'=>$t['total'],'o'=>$observaciones]);
            $ventaId=(int)$this->db->lastInsertId();
            $numero=generarNumeroFactura($this->db,$ventaId);
            $this->db->prepare("UPDATE ventas SET numero_factura=:n WHERE id_venta=:id")->execute(['n'=>$numero,'id'=>$ventaId]);

            $det=$this->db->prepare("INSERT INTO detalle_venta (id_venta,id_producto,cantidad,precio_unitario,subtotal) VALUES (:v,:p,:c,:pu,:s)");
            foreach ($items as $i) {
                $det->execute(['v'=>$ventaId,'p'=>$i['id_producto'],'c'=>$i['cantidad'],'pu'=>$i['precio_unitario'],
                    's'=>round((float)$i['cantidad']*(float)$i['precio_unitario'],2)]);
                actualizarStock($this->db,(int)$i['id_producto'],(float)$i['cantidad'],'salida',$usuarioId,$ventaId,'Venta',$numero);
            }
            $this->db->prepare("INSERT INTO venta_pagos (id_venta,id_metodo_pago,monto,referencia) VALUES (:v,:m,:monto,:r)")
                ->execute(['v'=>$ventaId,'m'=>$metodo['id_metodo_pago'],'monto'=>$t['total'],'r'=>$referencia]);
            // Solo el efectivo entra físicamente a la caja.
            if ($esEfectivo && $aperturaId) {
                $this->db->prepare("INSERT INTO movimientos_caja (id_apertura,id_usuario,id_venta,tipo,concepto,monto) VALUES (:a,:u,:v,'venta',:c,:m)")
                    ->execute(['a'=>$aperturaId,'u'=>$usuarioId,'v'=>$ventaId,'c'=>'Venta '.$numero,'m'=>$t['total']]);
            }
            $carrito->cambiarEstado($carritoId,'convertido');
            (new Auditoria($this->db))->registrar('ventas','crear','ventas',$ventaId,"Venta {$numero} por ".dinero($t['total']));
            $this->db->commit();
            return $ventaId;
        } catch (Throwable $e) {
            $this->db->rollBack();
            if ($e instanceof RuntimeException) throw $e;
            error_log('Venta: '.$e->getMessage());
            throw new RuntimeException('No se pudo registrar la venta. Inténtalo de nuevo.');
        }
    }

    // Anula la venta: devuelve el stock y, si fue en efectivo con la caja aún abierta, registra el egreso.
    public function anular(int $id, string $motivo): void {
        $motivo=mb_substr(trim($motivo),0,255);
        if ($motivo==='') throw new RuntimeException('Escribe el motivo de la anulación.');
        $this->db->beginTransaction();
        try {
            $v=$this->ventas->buscar($id, true);
            if (!$v) throw new RuntimeException('La venta no existe.');
            if ($v['estado']==='anulada') throw new RuntimeException('La venta ya estaba anulada.');
            if ($this->ventas->tieneDevoluciones($id)) throw new RuntimeException('La venta tiene devoluciones registradas; no se puede anular.');
            $usuarioId=(int)$_SESSION['id_usuario'];
            $this->db->prepare("UPDATE ventas SET estado='anulada',observaciones=CONCAT_WS('\n',observaciones,:o) WHERE id_venta=:id")
                ->execute(['o'=>'Anulada por '.$_SESSION['nombre'].' el '.date('d/m/Y H:i').': '.$motivo,'id'=>$id]);
            foreach ($this->ventas->detalle($id) as $d) {
                actualizarStock($this->db,(int)$d['id_producto'],(float)$d['cantidad'],'entrada',$usuarioId,$id,'Anulación de venta',$v['numero_factura']);
            }
            if ($v['id_apertura']) {
                $s=$this->db->prepare("SELECT COALESCE(SUM(monto),0) FROM movimientos_caja mc
                    INNER JOIN aperturas_caja a ON a.id_apertura=mc.id_apertura
                    WHERE mc.id_venta=:v AND mc.tipo='venta' AND a.estado='abierta'");
                $s->execute(['v'=>$id]);
                $efectivo=(float)$s->fetchColumn();
                if ($efectivo>0) {
                    $this->db->prepare("INSERT INTO movimientos_caja (id_apertura,id_usuario,id_venta,tipo,concepto,monto) VALUES (:a,:u,:v,'egreso',:c,:m)")
                        ->execute(['a'=>$v['id_apertura'],'u'=>$usuarioId,'v'=>$id,'c'=>'Anulación venta '.$v['numero_factura'],'m'=>$efectivo]);
                }
            }
            (new Auditoria($this->db))->registrar('ventas','anular','ventas',$id,"Venta {$v['numero_factura']} anulada: {$motivo}");
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            if ($e instanceof RuntimeException) throw $e;
            error_log('Anular venta: '.$e->getMessage());
            throw new RuntimeException('No se pudo anular la venta. Inténtalo de nuevo.');
        }
    }

    private function metodoPago(int $id): array {
        $s=$this->db->prepare("SELECT id_metodo_pago,nombre FROM metodos_pago WHERE id_metodo_pago=:id AND estado=1");
        $s->execute(['id'=>$id]);
        $m=$s->fetch();
        if (!$m) throw new RuntimeException('Selecciona un método de pago válido.');
        return $m;
    }
    // Caja abierta del usuario; si no tiene, la última caja abierta (ej. el vendedor vende y el cajero cobra).
    private function aperturaAbierta(int $usuarioId): ?int {
        $s=$this->db->prepare("SELECT id_apertura FROM aperturas_caja WHERE estado='abierta' ORDER BY id_usuario=:u DESC, fecha_apertura DESC LIMIT 1");
        $s->execute(['u'=>$usuarioId]);
        $id=$s->fetchColumn();
        return $id ? (int)$id : null;
    }
}
