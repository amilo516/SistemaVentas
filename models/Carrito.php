<?php
class Carrito {
    public function __construct(private PDO $db) {}

    // Devuelve el carrito activo del usuario; si no tiene, lo crea.
    public function activo(int $usuarioId): int {
        $s=$this->db->prepare("SELECT id_carrito FROM carritos WHERE id_usuario=:u AND estado='activo' ORDER BY id_carrito DESC LIMIT 1");
        $s->execute(['u'=>$usuarioId]);
        $id=$s->fetchColumn();
        if ($id) return (int)$id;
        $this->db->prepare("INSERT INTO carritos (id_usuario) VALUES (:u)")->execute(['u'=>$usuarioId]);
        return (int)$this->db->lastInsertId();
    }
    // Productos del carrito con el precio y stock actuales del producto.
    public function items(int $carritoId, bool $bloquear=false): array {
        $s=$this->db->prepare("SELECT cd.id_producto,cd.cantidad,p.nombre,p.codigo,p.precio_venta precio_unitario,p.stock,p.unidad_medida,p.estado
            FROM carrito_detalle cd INNER JOIN productos p ON p.id_producto=cd.id_producto
            WHERE cd.id_carrito=:c ORDER BY cd.id_carrito_detalle".($bloquear ? " FOR UPDATE" : ""));
        $s->execute(['c'=>$carritoId]);
        return $s->fetchAll();
    }
    public function cantidadDe(int $carritoId, int $productoId): float {
        $s=$this->db->prepare("SELECT cantidad FROM carrito_detalle WHERE id_carrito=:c AND id_producto=:p");
        $s->execute(['c'=>$carritoId,'p'=>$productoId]);
        return (float)$s->fetchColumn();
    }
    public function guardar(int $carritoId, int $productoId, float $cantidad, float $precio): void {
        $this->db->prepare("INSERT INTO carrito_detalle (id_carrito,id_producto,cantidad,precio_unitario,subtotal)
            VALUES (:c,:p,:cant,:precio,:sub)
            ON DUPLICATE KEY UPDATE cantidad=VALUES(cantidad),precio_unitario=VALUES(precio_unitario),subtotal=VALUES(subtotal)")
            ->execute(['c'=>$carritoId,'p'=>$productoId,'cant'=>$cantidad,'precio'=>$precio,'sub'=>round($cantidad*$precio,2)]);
    }
    public function quitar(int $carritoId, int $productoId): void {
        $this->db->prepare("DELETE FROM carrito_detalle WHERE id_carrito=:c AND id_producto=:p")->execute(['c'=>$carritoId,'p'=>$productoId]);
    }
    public function vaciar(int $carritoId): void {
        $this->db->prepare("DELETE FROM carrito_detalle WHERE id_carrito=:c")->execute(['c'=>$carritoId]);
    }
    public function cambiarEstado(int $carritoId, string $estado): void {
        $this->db->prepare("UPDATE carritos SET estado=:e WHERE id_carrito=:c")->execute(['e'=>$estado,'c'=>$carritoId]);
    }
}
