<?php
class Rol {
    public function __construct(private PDO $db) {}

    public function listarActivos(): array {
        return $this->db->query("SELECT id_rol,nombre,descripcion FROM roles WHERE estado=1 ORDER BY id_rol")->fetchAll();
    }
    public function existeActivo(int $id): bool {
        $s=$this->db->prepare("SELECT 1 FROM roles WHERE id_rol=:id AND estado=1");
        $s->execute(['id'=>$id]);
        return (bool)$s->fetchColumn();
    }
    public function listar(): array {
        return $this->db->query("SELECT r.*,
                (SELECT COUNT(*) FROM usuarios u WHERE u.rol_id=r.id_rol AND u.estado=1) usuarios,
                (SELECT COUNT(*) FROM rol_permisos rp WHERE rp.id_rol=r.id_rol) permisos
            FROM roles r ORDER BY r.estado DESC, r.id_rol")->fetchAll();
    }
    public function buscar(int $id): ?array {
        $s=$this->db->prepare("SELECT r.*,(SELECT COUNT(*) FROM usuarios u WHERE u.rol_id=r.id_rol AND u.estado=1) usuarios FROM roles r WHERE r.id_rol=:id");
        $s->execute(['id'=>$id]); return $s->fetch() ?: null;
    }
    public function existeNombre(string $nombre, int $excluirId=0): bool {
        $s=$this->db->prepare("SELECT 1 FROM roles WHERE nombre=:n AND id_rol<>:id LIMIT 1");
        $s->execute(['n'=>$nombre,'id'=>$excluirId]); return (bool)$s->fetchColumn();
    }
    public function crear(string $nombre, ?string $descripcion): int {
        $this->db->prepare("INSERT INTO roles (nombre,descripcion) VALUES (:n,:d)")->execute(['n'=>$nombre,'d'=>$descripcion]);
        return (int)$this->db->lastInsertId();
    }
    public function actualizar(int $id, string $nombre, ?string $descripcion): void {
        $this->db->prepare("UPDATE roles SET nombre=:n,descripcion=:d WHERE id_rol=:id")->execute(['n'=>$nombre,'d'=>$descripcion,'id'=>$id]);
    }
    public function cambiarEstado(int $id, int $estado): void {
        $this->db->prepare("UPDATE roles SET estado=:e WHERE id_rol=:id")->execute(['e'=>$estado,'id'=>$id]);
    }
    public function usuarios(int $id): array {
        $s=$this->db->prepare("SELECT nombre,usuario FROM usuarios WHERE rol_id=:id AND estado=1 ORDER BY nombre");
        $s->execute(['id'=>$id]); return $s->fetchAll();
    }
    public function contarUsuarios(int $id): int {
        $s=$this->db->prepare("SELECT COUNT(*) FROM usuarios WHERE rol_id=:id");
        $s->execute(['id'=>$id]); return (int)$s->fetchColumn();
    }
    public function eliminar(int $id): void {
        $this->db->prepare("DELETE FROM roles WHERE id_rol=:id")->execute(['id'=>$id]); // rol_permisos se borra en cascada
    }
}
