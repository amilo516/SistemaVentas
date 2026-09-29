<?php
function actualizarStock(PDO $db,int $productoId,float $cantidad,string $tipo,int $usuarioId,?int $ventaId=null,?string $motivo=null,?string $referencia=null): void {
    $s=$db->prepare("SELECT stock FROM productos WHERE id_producto=:id FOR UPDATE");
    $s->execute(['id'=>$productoId]); $p=$s->fetch();
    if (!$p) throw new RuntimeException('Producto no encontrado.');
    $anterior=(float)$p['stock'];
    $nuevo=match($tipo) {
        'entrada','devolucion'=>$anterior+$cantidad,
        'salida'=>$anterior-$cantidad,
        'ajuste'=>$cantidad,
        default=>throw new InvalidArgumentException('Tipo inválido.')
    };
    if ($nuevo<0) throw new RuntimeException('Stock insuficiente.');
    $u=$db->prepare("UPDATE productos SET stock=:stock WHERE id_producto=:id");
    $u->execute(['stock'=>$nuevo,'id'=>$productoId]);
    $m=$db->prepare("INSERT INTO movimientos_inventario
        (id_producto,id_usuario,id_venta,tipo,cantidad,stock_anterior,stock_nuevo,motivo,referencia)
        VALUES (:p,:u,:v,:t,:c,:a,:n,:m,:r)");
    $m->execute(['p'=>$productoId,'u'=>$usuarioId,'v'=>$ventaId,'t'=>$tipo,'c'=>$cantidad,'a'=>$anterior,'n'=>$nuevo,'m'=>$motivo,'r'=>$referencia]);
}
