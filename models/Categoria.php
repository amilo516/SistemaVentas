<?php
class Categoria {
    public function __construct(private PDO $db) {}

    public function listar(): array {
        return $this->db->query("SELECT c.*, (SELECT COUNT(*) FROM productos p WHERE p.id_categoria=c.id_categoria) productos
            FROM categorias c ORDER BY c.estado DESC, c.nombre")->fetchAll();
    }
    public function activas(): array {
        return $this->db->query("SELECT id_categoria,nombre FROM categorias WHERE estado=1 ORDER BY nombre")->fetchAll();
    }
    public function buscar(int $id): ?array {
        $s=$this->db->prepare("SELECT * FROM categorias WHERE id_categoria=:id");
        $s->execute(['id'=>$id]); return $s->fetch() ?: null;
    }
    public function existeNombre(string $nombre, int $excluirId=0): bool {
        $s=$this->db->prepare("SELECT 1 FROM categorias WHERE nombre=:n AND id_categoria<>:id LIMIT 1");
        $s->execute(['n'=>$nombre,'id'=>$excluirId]); return (bool)$s->fetchColumn();
    }
    public function crear(string $nombre, ?string $descripcion): int {
        $this->db->prepare("INSERT INTO categorias (nombre,descripcion) VALUES (:n,:d)")->execute(['n'=>$nombre,'d'=>$descripcion]);
        return (int)$this->db->lastInsertId();
    }
    public function actualizar(int $id, string $nombre, ?string $descripcion): void {
        $this->db->prepare("UPDATE categorias SET nombre=:n,descripcion=:d WHERE id_categoria=:id")->execute(['n'=>$nombre,'d'=>$descripcion,'id'=>$id]);
    }
    public function cambiarEstado(int $id, int $estado): void {
        $this->db->prepare("UPDATE categorias SET estado=:e WHERE id_categoria=:id")->execute(['e'=>$estado,'id'=>$id]);
    }
    public function existeActiva(int $id): bool {
        $s=$this->db->prepare("SELECT 1 FROM categorias WHERE id_categoria=:id AND estado=1");
        $s->execute(['id'=>$id]); return (bool)$s->fetchColumn();
    }
    public function contarProductos(int $id): int {
        $s=$this->db->prepare("SELECT COUNT(*) FROM productos WHERE id_categoria=:id");
        $s->execute(['id'=>$id]); return (int)$s->fetchColumn();
    }
    public function eliminar(int $id): void {
        $this->db->prepare("DELETE FROM categorias WHERE id_categoria=:id")->execute(['id'=>$id]);
    }
}
