<?php
class MetodoPago {
    public function __construct(private PDO $db) {}
    public function listar(): array {
        return $this->db->query("SELECT m.*,(SELECT COUNT(*) FROM venta_pagos vp WHERE vp.id_metodo_pago=m.id_metodo_pago) usos
            FROM metodos_pago m ORDER BY m.estado DESC, m.id_metodo_pago")->fetchAll();
    }
    public function buscar(int $id): ?array {
        $s=$this->db->prepare("SELECT * FROM metodos_pago WHERE id_metodo_pago=:id");
        $s->execute(['id'=>$id]); return $s->fetch() ?: null;
    }
    public function existeNombre(string $nombre): bool {
        $s=$this->db->prepare("SELECT 1 FROM metodos_pago WHERE nombre=:n LIMIT 1");
        $s->execute(['n'=>$nombre]); return (bool)$s->fetchColumn();
    }
    public function activos(): int { return (int)$this->db->query("SELECT COUNT(*) FROM metodos_pago WHERE estado=1")->fetchColumn(); }
    public function crear(string $nombre): int {
        $this->db->prepare("INSERT INTO metodos_pago (nombre) VALUES (:n)")->execute(['n'=>$nombre]);
        return (int)$this->db->lastInsertId();
    }
    public function cambiarEstado(int $id, int $estado): void {
        $this->db->prepare("UPDATE metodos_pago SET estado=:e WHERE id_metodo_pago=:id")->execute(['e'=>$estado,'id'=>$id]);
    }
    public function eliminar(int $id): void {
        $this->db->prepare("DELETE FROM metodos_pago WHERE id_metodo_pago=:id")->execute(['id'=>$id]);
    }
}
