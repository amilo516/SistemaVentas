<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constantes.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/permisos.php';
require_once __DIR__ . '/../helpers/funciones.php';
require_once __DIR__ . '/../models/Caja.php';
require_once __DIR__ . '/../models/AperturaCaja.php';
require_once __DIR__ . '/../models/MovimientoCaja.php';
require_once __DIR__ . '/../models/Auditoria.php';

// Caja: turnos (aperturas), movimientos de efectivo y cierre con cuadre.
class CajaController {
    private PDO $db;
    private Caja $cajas;
    private AperturaCaja $aperturas;
    private MovimientoCaja $movimientos;
    public function __construct() {
        $this->db=(new Database())->getConnection();
        $this->cajas=new Caja($this->db);
        $this->aperturas=new AperturaCaja($this->db);
        $this->movimientos=new MovimientoCaja($this->db);
    }

    private function usuarioId(): int { return (int)$_SESSION['id_usuario']; }
    private function esAdmin(): bool { return (int)$_SESSION['rol_id']===ROL_ADMINISTRADOR; }
    // Quien puede ver todos los turnos (no solo los suyos).
    public function veTodo(): bool { return $this->esAdmin() || (int)$_SESSION['rol_id']===ROL_SUPERVISOR; }
    // Solo el dueño del turno (o un administrador) registra movimientos o cierra.
    public function puedeOperar(array $apertura): bool {
        return $apertura['estado']==='abierta' && ((int)$apertura['id_usuario']===$this->usuarioId() || $this->esAdmin());
    }

    public function miTurno(): ?array {
        $a=$this->aperturas->abiertaDeUsuario($this->usuarioId());
        return $a ? $this->conResumen($a) : null;
    }
    public function cajas(): array { return $this->cajas->listarConEstado(); }
    public function cajasDisponibles(): array { return $this->cajas->disponibles(); }
    public function historial(): array { return $this->aperturas->historial(30, $this->veTodo() ? null : $this->usuarioId()); }

    // Turno con movimientos y resumen, o null si no existe o el usuario no puede verlo.
    public function ver(int $aperturaId): ?array {
        $a=$this->aperturas->buscar($aperturaId);
        if (!$a || (!$this->veTodo() && (int)$a['id_usuario']!==$this->usuarioId())) return null;
        $a=$this->conResumen($a);
        $a['movimientos']=$this->movimientos->listar($aperturaId);
        $a['ventas_por_metodo']=$this->movimientos->ventasPorMetodo($aperturaId);
        return $a;
    }

    // Devuelve [id de la apertura, errores].
    public function abrir(array $post): array {
        $cajaId=(int)($post['id_caja'] ?? 0);
        $monto=$this->numero($post['monto_inicial'] ?? '');
        $obs=mb_substr(trim($post['observaciones'] ?? ''),0,1000) ?: null;
        $errores=[];
        if ($monto<0 || ($post['monto_inicial'] ?? '')==='') $errores[]='Escribe el dinero con el que abres la caja (puede ser 0).';
        if ($this->aperturas->abiertaDeUsuario($this->usuarioId())) $errores[]='Ya tienes una caja abierta. Ciérrala antes de abrir otra.';
        if ($errores) return [0,$errores];
        $this->db->beginTransaction();
        try {
            $caja=$this->cajas->bloquearActiva($cajaId);
            if (!$caja) throw new RuntimeException('Selecciona una caja válida.');
            if ($this->aperturas->hayAbiertaEnCaja($cajaId)) throw new RuntimeException("La caja \"{$caja['nombre']}\" ya está abierta por otro usuario.");
            $id=$this->aperturas->abrir($cajaId,$this->usuarioId(),round($monto,2),$obs);
            (new Auditoria($this->db))->registrar('caja','abrir','aperturas_caja',$id,"Apertura de {$caja['nombre']} con ".dinero($monto));
            $this->db->commit();
            return [$id,[]];
        } catch (RuntimeException $e) {
            $this->db->rollBack();
            return [0,[$e->getMessage()]];
        }
    }

    public function registrarMovimiento(int $aperturaId, array $post): array {
        $tipo=$post['tipo'] ?? '';
        $concepto=mb_substr(trim($post['concepto'] ?? ''),0,255);
        $monto=round($this->numero($post['monto'] ?? 0),2);
        $errores=[];
        if (!in_array($tipo,['ingreso','egreso'],true)) $errores[]='Selecciona si es un ingreso o un egreso.';
        if ($concepto==='') $errores[]='Escribe el concepto del movimiento.';
        if ($monto<=0) $errores[]='El monto debe ser mayor que cero.';
        if ($errores) return $errores;
        $this->db->beginTransaction();
        try {
            $a=$this->aperturas->buscar($aperturaId,true);
            if (!$a || !$this->puedeOperar($a)) throw new RuntimeException('Esta caja no está abierta o no te pertenece.');
            $disponible=$this->conResumen($a)['esperado'];
            if ($tipo==='egreso' && $monto>$disponible) throw new RuntimeException('No hay suficiente efectivo en caja. Disponible: '.dinero($disponible).'.');
            $id=$this->movimientos->crear($aperturaId,$this->usuarioId(),$tipo,$concepto,$monto);
            (new Auditoria($this->db))->registrar('caja',$tipo,'movimientos_caja',$id,ucfirst($tipo)." de ".dinero($monto)." en {$a['caja']}: {$concepto}");
            $this->db->commit();
            return [];
        } catch (RuntimeException $e) {
            $this->db->rollBack();
            return [$e->getMessage()];
        }
    }

    public function cerrar(int $aperturaId, array $post): array {
        if (($post['monto_final'] ?? '')==='' || $this->numero($post['monto_final'])<0) return ['Escribe el efectivo que contaste en la caja.'];
        $montoFinal=round($this->numero($post['monto_final']),2);
        $obs=mb_substr(trim($post['observaciones'] ?? ''),0,1000) ?: null;
        $this->db->beginTransaction();
        try {
            $a=$this->aperturas->buscar($aperturaId,true);
            if (!$a || !$this->puedeOperar($a)) throw new RuntimeException('Esta caja no está abierta o no te pertenece.');
            $esperado=$this->conResumen($a)['esperado'];
            $diferencia=round($montoFinal-$esperado,2);
            if (abs($diferencia)>=0.01 && !$obs) throw new RuntimeException('Hay un '.($diferencia>0 ? 'sobrante' : 'faltante').' de '.dinero(abs($diferencia)).'. Explica el motivo en observaciones.');
            $nota='Cierre por '.$_SESSION['nombre'].($obs ? ': '.$obs : '');
            $this->aperturas->cerrar($aperturaId,$montoFinal,$esperado,$nota);
            (new Auditoria($this->db))->registrar('caja','cerrar','aperturas_caja',$aperturaId,
                "Cierre de {$a['caja']}. Esperado ".dinero($esperado).", contado ".dinero($montoFinal).", diferencia ".($diferencia<0 ? '-' : '').dinero(abs($diferencia)));
            $this->db->commit();
            return [];
        } catch (RuntimeException $e) {
            $this->db->rollBack();
            return [$e->getMessage()];
        }
    }

    public function crearCaja(string $nombre): string {
        $nombre=trim($nombre);
        if ($nombre==='' || mb_strlen($nombre)>100) return 'El nombre de la caja es obligatorio (máximo 100 caracteres).';
        if ($this->cajas->existeNombre($nombre)) return "Ya existe una caja llamada \"{$nombre}\".";
        $id=$this->cajas->crear($nombre);
        (new Auditoria($this->db))->registrar('caja','crear caja','cajas',$id,"Caja {$nombre}");
        return '';
    }
    public function cambiarEstadoCaja(int $id): string {
        $c=$this->cajas->buscar($id);
        if (!$c) return 'La caja no existe.';
        if ($c['estado'] && $this->aperturas->hayAbiertaEnCaja($id)) return 'No puedes desactivar una caja que está abierta.';
        $this->cajas->cambiarEstado($id,$c['estado'] ? 0 : 1);
        return '';
    }

    public function eliminarCaja(int $id): string {
        $c=$this->cajas->buscar($id);
        if (!$c) return 'La caja no existe.';
        $n=$this->cajas->contarAperturas($id);
        if ($n) return "No se puede eliminar \"{$c['nombre']}\": tiene {$n} turno(s) registrados. Puedes desactivarla.";
        $this->cajas->eliminar($id);
        (new Auditoria($this->db))->registrar('caja','eliminar caja','cajas',0,"Caja {$c['nombre']} eliminada");
        return '';
    }

    // Efectivo esperado = base + ventas en efectivo + ingresos − egresos − devoluciones.
    private function conResumen(array $a): array {
        $t=$this->movimientos->totalesPorTipo((int)$a['id_apertura']);
        $a['totales']=array_map('floatval',$t);
        $a['esperado']=round((float)$a['monto_inicial']+$t['venta']+$t['ingreso']-$t['egreso']-$t['devolucion'],2);
        return $a;
    }
    private function numero(mixed $v): float { return (float)str_replace(',','.',(string)$v); }
}
