<?php
class Usuario {
    public function __construct(private PDO $db) {}

    public function listar(): array {
        return $this->db->query("SELECT u.id_usuario,u.nombre,u.usuario,u.rol_id,u.estado,u.ultimo_acceso,r.nombre rol
            FROM usuarios u INNER JOIN roles r ON r.id_rol=u.rol_id
            ORDER BY u.estado DESC,u.nombre")->fetchAll();
    }
    public function buscar(int $id): ?array {
        $s=$this->db->prepare("SELECT id_usuario,nombre,usuario,rol_id,estado FROM usuarios WHERE id_usuario=:id");
        $s->execute(['id'=>$id]);
        return $s->fetch() ?: null;
    }
    public function existeUsuario(string $usuario, int $excluirId=0): bool {
        $s=$this->db->prepare("SELECT 1 FROM usuarios WHERE usuario=:usuario AND id_usuario<>:id LIMIT 1");
        $s->execute(['usuario'=>$usuario,'id'=>$excluirId]);
        return (bool)$s->fetchColumn();
    }
    public function crear(string $nombre, string $usuario, string $password, int $rolId): int {
        $s=$this->db->prepare("INSERT INTO usuarios (nombre,usuario,password,rol_id) VALUES (:nombre,:usuario,:password,:rol)");
        $s->execute(['nombre'=>$nombre,'usuario'=>$usuario,'password'=>password_hash($password, PASSWORD_DEFAULT),'rol'=>$rolId]);
        return (int)$this->db->lastInsertId();
    }
    public function actualizar(int $id, string $nombre, string $usuario, int $rolId, ?string $password): void {
        $sql="UPDATE usuarios SET nombre=:nombre,usuario=:usuario,rol_id=:rol".($password!==null ? ",password=:password" : "")." WHERE id_usuario=:id";
        $datos=['nombre'=>$nombre,'usuario'=>$usuario,'rol'=>$rolId,'id'=>$id];
        if ($password!==null) $datos['password']=password_hash($password, PASSWORD_DEFAULT);
        $this->db->prepare($sql)->execute($datos);
    }
    public function cambiarEstado(int $id, int $estado): void {
        $this->db->prepare("UPDATE usuarios SET estado=:estado WHERE id_usuario=:id")->execute(['estado'=>$estado,'id'=>$id]);
    }
    // Registros de operación hechos por el usuario (no incluye la auditoría).
    public function operaciones(int $id): int {
        $total=0;
        foreach (['ventas','aperturas_caja','movimientos_caja','movimientos_inventario','devoluciones'] as $tabla) {
            $s=$this->db->prepare("SELECT COUNT(*) FROM {$tabla} WHERE id_usuario=:id");
            $s->execute(['id'=>$id]); $total+=(int)$s->fetchColumn();
        }
        return $total;
    }
    public function administradoresActivos(): int {
        return (int)$this->db->query("SELECT COUNT(*) FROM usuarios WHERE rol_id=".ROL_ADMINISTRADOR." AND estado=1")->fetchColumn();
    }
    // Sus carritos (y su detalle, en cascada) se borran; la auditoría conserva los registros sin usuario.
    public function eliminar(int $id): void {
        $this->db->prepare("DELETE FROM carritos WHERE id_usuario=:id")->execute(['id'=>$id]);
        $this->db->prepare("DELETE FROM usuarios WHERE id_usuario=:id")->execute(['id'=>$id]);
    }
}
