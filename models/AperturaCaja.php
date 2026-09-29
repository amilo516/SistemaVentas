<?php
class AperturaCaja {
    public function __construct(private PDO $db) {}

    public function buscar(int $id, bool $bloquear=false): ?array {
        $s=$this->db->prepare("SELECT a.*,c.nombre caja,u.nombre usuario FROM aperturas_caja a
            INNER JOIN cajas c ON c.id_caja=a.id_caja INNER JOIN usuarios u ON u.id_usuario=a.id_usuario
            WHERE a.id_apertura=:id".($bloquear ? " FOR UPDATE" : ""));
        $s->execute(['id'=>$id]); return $s->fetch() ?: null;
    }
    public function abiertaDeUsuario(int $usuarioId): ?array {
        $s=$this->db->prepare("SELECT a.*,c.nombre caja FROM aperturas_caja a INNER JOIN cajas c ON c.id_caja=a.id_caja
            WHERE a.id_usuario=:u AND a.estado='abierta' LIMIT 1");
        $s->execute(['u'=>$usuarioId]); return $s->fetch() ?: null;
    }
    public function hayAbiertaEnCaja(int $cajaId): bool {
        $s=$this->db->prepare("SELECT 1 FROM aperturas_caja WHERE id_caja=:c AND estado='abierta' LIMIT 1");
        $s->execute(['c'=>$cajaId]); return (bool)$s->fetchColumn();
    }
    public function hayAlgunaAbierta(): bool {
        return (bool)$this->db->query("SELECT 1 FROM aperturas_caja WHERE estado='abierta' LIMIT 1")->fetchColumn();
    }
    // Historial de turnos. $usuarioId limita a los turnos de ese usuario.
    public function historial(int $limite=30, ?int $usuarioId=null): array {
        $s=$this->db->prepare("SELECT a.*,c.nombre caja,u.nombre usuario FROM aperturas_caja a
            INNER JOIN cajas c ON c.id_caja=a.id_caja INNER JOIN usuarios u ON u.id_usuario=a.id_usuario
            ".($usuarioId ? "WHERE a.id_usuario=:u " : "")."ORDER BY a.fecha_apertura DESC LIMIT {$limite}");
        $s->execute($usuarioId ? ['u'=>$usuarioId] : []); return $s->fetchAll();
    }
    public function abrir(int $cajaId, int $usuarioId, float $montoInicial, ?string $observaciones): int {
        $this->db->prepare("INSERT INTO aperturas_caja (id_caja,id_usuario,monto_inicial,observaciones) VALUES (:c,:u,:m,:o)")
            ->execute(['c'=>$cajaId,'u'=>$usuarioId,'m'=>$montoInicial,'o'=>$observaciones]);
        return (int)$this->db->lastInsertId();
    }
    public function cerrar(int $id, float $montoFinal, float $esperado, ?string $observaciones): void {
        $this->db->prepare("UPDATE aperturas_caja SET estado='cerrada',fecha_cierre=NOW(),monto_final=:f,monto_esperado=:e,
            diferencia=:d,observaciones=CONCAT_WS('\n',observaciones,:o) WHERE id_apertura=:id")
            ->execute(['f'=>$montoFinal,'e'=>$esperado,'d'=>round($montoFinal-$esperado,2),'o'=>$observaciones,'id'=>$id]);
    }
}
