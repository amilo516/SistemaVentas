<?php
class Cliente {
    public function __construct(private PDO $db) {}

    public function listarActivos(): array {
        return $this->db->query("SELECT id_cliente,documento,nombre FROM clientes WHERE estado=1 ORDER BY id_cliente=1 DESC,nombre")->fetchAll();
    }
    public function existeActivo(int $id): bool {
        $s=$this->db->prepare("SELECT 1 FROM clientes WHERE id_cliente=:id AND estado=1");
        $s->execute(['id'=>$id]); return (bool)$s->fetchColumn();
    }
    // Filtros: q (nombre, documento, teléfono o email), estado ('1','0' o ''). Incluye resumen de compras.
    public function listar(array $f=[]): array {
        $where=['1=1']; $p=[];
        if (($f['q'] ?? '')!=='') {
            $where[]='(c.nombre LIKE :q1 OR c.documento LIKE :q2 OR c.telefono LIKE :q3 OR c.email LIKE :q4)';
            $p['q1']=$p['q2']=$p['q3']=$p['q4']='%'.$f['q'].'%';
        }
        if (($f['estado'] ?? '')!=='') { $where[]='c.estado=:estado'; $p['estado']=(int)$f['estado']; }
        $s=$this->db->prepare("SELECT c.*,COUNT(v.id_venta) compras,COALESCE(SUM(v.total),0) total_compras,MAX(v.fecha_venta) ultima_compra
            FROM clientes c LEFT JOIN ventas v ON v.id_cliente=c.id_cliente AND v.estado<>'anulada'
            WHERE ".implode(' AND ',$where)."
            GROUP BY c.id_cliente ORDER BY c.estado DESC, c.id_cliente=1 DESC, c.nombre LIMIT 500");
        $s->execute($p);
        return $s->fetchAll();
    }
    public function buscar(int $id): ?array {
        $s=$this->db->prepare("SELECT * FROM clientes WHERE id_cliente=:id");
        $s->execute(['id'=>$id]); return $s->fetch() ?: null;
    }
    public function existeDocumento(string $documento, int $excluirId=0): bool {
        $s=$this->db->prepare("SELECT 1 FROM clientes WHERE documento=:d AND id_cliente<>:id LIMIT 1");
        $s->execute(['d'=>$documento,'id'=>$excluirId]); return (bool)$s->fetchColumn();
    }
    public function crear(array $d): int {
        $this->db->prepare("INSERT INTO clientes (documento,nombre,telefono,email,direccion,ciudad)
            VALUES (:documento,:nombre,:telefono,:email,:direccion,:ciudad)")->execute($d);
        return (int)$this->db->lastInsertId();
    }
    public function actualizar(int $id, array $d): void {
        $this->db->prepare("UPDATE clientes SET documento=:documento,nombre=:nombre,telefono=:telefono,email=:email,
            direccion=:direccion,ciudad=:ciudad WHERE id_cliente=:id")->execute($d+['id'=>$id]);
    }
    public function cambiarEstado(int $id, int $estado): void {
        $this->db->prepare("UPDATE clientes SET estado=:e WHERE id_cliente=:id")->execute(['e'=>$estado,'id'=>$id]);
    }
    // Resumen de compras (sin anuladas). $usuarioId limita a las ventas de ese vendedor.
    public function resumenCompras(int $id, ?int $usuarioId=null): array {
        $s=$this->db->prepare("SELECT COUNT(*) compras,COALESCE(SUM(total),0) total,COALESCE(AVG(total),0) promedio,MAX(fecha_venta) ultima
            FROM ventas WHERE id_cliente=:id AND estado<>'anulada'".($usuarioId ? " AND id_usuario=:u" : ""));
        $s->execute(['id'=>$id]+($usuarioId ? ['u'=>$usuarioId] : []));
        return $s->fetch();
    }
    public function contarVentas(int $id): int {
        $s=$this->db->prepare("SELECT COUNT(*) FROM ventas WHERE id_cliente=:id");
        $s->execute(['id'=>$id]); return (int)$s->fetchColumn();
    }
    public function eliminar(int $id): void {
        $this->db->prepare("DELETE FROM clientes WHERE id_cliente=:id")->execute(['id'=>$id]);
    }
}
