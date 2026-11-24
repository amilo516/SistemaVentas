<?php
require_once __DIR__ . '/../config/constantes.php';

class Rol {
    public function __construct(private PDO $db) {}

    // Rol vigente del usuario y su descuento máximo, leídos en el momento (no de la sesión), para que un
    // cambio de límite o de rol aplique desde la siguiente venta. 'maximo' null = sin límite (Administrador).
    public function descuentoMaximoDeUsuario(int $idUsuario): ?array {
        $s=$this->db->prepare("SELECT r.id_rol,r.nombre,r.descuento_maximo FROM usuarios u
            INNER JOIN roles r ON r.id_rol=u.rol_id WHERE u.id_usuario=:id");
        $s->execute(['id'=>$idUsuario]);
        $r=$s->fetch();
        if (!$r) return null;
        return ['rol'=>$r['nombre'],'maximo'=>(int)$r['id_rol']===ROL_ADMINISTRADOR ? null : (float)$r['descuento_maximo']];
    }

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
    public function crear(string $nombre, ?string $descripcion, float $descuentoMaximo=10.00): int {
        $this->db->prepare("INSERT INTO roles (nombre,descripcion,descuento_maximo) VALUES (:n,:d,:dm)")
            ->execute(['n'=>$nombre,'d'=>$descripcion,'dm'=>$descuentoMaximo]);
        return (int)$this->db->lastInsertId();
    }
    public function actualizarDescuentoMaximo(int $id, float $valor): void {
        $this->db->prepare("UPDATE roles SET descuento_maximo=:v WHERE id_rol=:id")->execute(['v'=>$valor,'id'=>$id]);
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
