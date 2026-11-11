<?php
class Caja {
    public function __construct(private PDO $db) {}

    // Cajas con su turno abierto (si tiene) y quién lo abrió.
    public function listarConEstado(): array {
        return $this->db->query("SELECT c.id_caja,c.nombre,c.estado,a.id_apertura,a.fecha_apertura,u.nombre usuario
            FROM cajas c
            LEFT JOIN aperturas_caja a ON a.id_caja=c.id_caja AND a.estado='abierta'
            LEFT JOIN usuarios u ON u.id_usuario=a.id_usuario
            ORDER BY c.estado DESC, c.nombre")->fetchAll();
    }
    public function disponibles(): array {
        return $this->db->query("SELECT c.id_caja,c.nombre FROM cajas c
            WHERE c.estado=1 AND NOT EXISTS (SELECT 1 FROM aperturas_caja a WHERE a.id_caja=c.id_caja AND a.estado='abierta')
            ORDER BY c.nombre")->fetchAll();
    }
    public function bloquearActiva(int $id): ?array {
        $s=$this->db->prepare("SELECT * FROM cajas WHERE id_caja=:id AND estado=1 FOR UPDATE");
        $s->execute(['id'=>$id]); return $s->fetch() ?: null;
    }
    public function existeNombre(string $nombre): bool {
        $s=$this->db->prepare("SELECT 1 FROM cajas WHERE nombre=:n LIMIT 1");
        $s->execute(['n'=>$nombre]); return (bool)$s->fetchColumn();
    }
    public function crear(string $nombre): int {
        $this->db->prepare("INSERT INTO cajas (nombre) VALUES (:n)")->execute(['n'=>$nombre]);
        return (int)$this->db->lastInsertId();
    }
    public function cambiarEstado(int $id, int $estado): void {
        $this->db->prepare("UPDATE cajas SET estado=:e WHERE id_caja=:id")->execute(['e'=>$estado,'id'=>$id]);
    }
    public function buscar(int $id): ?array {
        $s=$this->db->prepare("SELECT * FROM cajas WHERE id_caja=:id");
        $s->execute(['id'=>$id]); return $s->fetch() ?: null;
    }
    public function contarAperturas(int $id): int {
        $s=$this->db->prepare("SELECT COUNT(*) FROM aperturas_caja WHERE id_caja=:id");
        $s->execute(['id'=>$id]); return (int)$s->fetchColumn();
    }
    public function eliminar(int $id): void {
        $this->db->prepare("DELETE FROM cajas WHERE id_caja=:id")->execute(['id'=>$id]);
    }
}
