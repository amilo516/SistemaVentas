<?php
class Producto {
    public function __construct(private PDO $db) {}

    // Filtros: q (nombre/código/código de barras), categoria, estado ('1','0' o ''), stock_bajo (bool).
    public function listar(array $f=[]): array {
        $where=['1=1']; $p=[];
        if (($f['q'] ?? '')!=='') {
            $where[]='(p.nombre LIKE :q1 OR p.codigo LIKE :q2 OR p.codigo_barras LIKE :q3)';
            $p['q1']=$p['q2']=$p['q3']='%'.$f['q'].'%';
        }
        if (!empty($f['categoria'])) { $where[]='p.id_categoria=:cat'; $p['cat']=(int)$f['categoria']; }
        if (($f['estado'] ?? '')!=='') { $where[]='p.estado=:estado'; $p['estado']=(int)$f['estado']; }
        if (!empty($f['stock_bajo'])) $where[]='p.stock<=p.stock_minimo';
        $s=$this->db->prepare("SELECT p.*,c.nombre categoria FROM productos p
            INNER JOIN categorias c ON c.id_categoria=p.id_categoria
            WHERE ".implode(' AND ',$where)." ORDER BY p.estado DESC, p.nombre LIMIT 500");
        $s->execute($p);
        return $s->fetchAll();
    }
    public function buscar(int $id): ?array {
        $s=$this->db->prepare("SELECT p.*,c.nombre categoria FROM productos p
            INNER JOIN categorias c ON c.id_categoria=p.id_categoria WHERE p.id_producto=:id");
        $s->execute(['id'=>$id]); return $s->fetch() ?: null;
    }
    // Búsqueda para el punto de venta: coincidencia exacta de código/código de barras primero.
    public function buscarParaVenta(string $texto, int $limite=15): array {
        $s=$this->db->prepare("SELECT id_producto,codigo,codigo_barras,nombre,precio_venta,stock,unidad_medida
            FROM productos
            WHERE estado=1 AND (nombre LIKE :l1 OR codigo LIKE :l2 OR codigo_barras=:e1)
            ORDER BY (codigo=:e2 OR codigo_barras=:e3) DESC, nombre
            LIMIT {$limite}");
        $like='%'.$texto.'%';
        $s->execute(['l1'=>$like,'l2'=>$like,'e1'=>$texto,'e2'=>$texto,'e3'=>$texto]);
        return $s->fetchAll();
    }
    // Búsqueda para inventario: incluye costo; solo productos activos.
    public function buscarParaInventario(string $texto, int $limite=15): array {
        $s=$this->db->prepare("SELECT id_producto,codigo,codigo_barras,nombre,precio_compra,stock,stock_minimo,unidad_medida
            FROM productos WHERE estado=1 AND (nombre LIKE :l1 OR codigo LIKE :l2 OR codigo_barras=:e1)
            ORDER BY (codigo=:e2 OR codigo_barras=:e3) DESC, nombre LIMIT {$limite}");
        $like='%'.$texto.'%';
        $s->execute(['l1'=>$like,'l2'=>$like,'e1'=>$texto,'e2'=>$texto,'e3'=>$texto]);
        return $s->fetchAll();
    }
    public function existeCodigo(string $campo, string $valor, int $excluirId=0): bool {
        $campo=$campo==='codigo_barras' ? 'codigo_barras' : 'codigo';
        $s=$this->db->prepare("SELECT 1 FROM productos WHERE {$campo}=:v AND id_producto<>:id LIMIT 1");
        $s->execute(['v'=>$valor,'id'=>$excluirId]); return (bool)$s->fetchColumn();
    }
    public function crear(array $d): int {
        $this->db->prepare("INSERT INTO productos (id_categoria,codigo,codigo_barras,nombre,descripcion,precio_compra,precio_venta,stock,stock_minimo,unidad_medida)
            VALUES (:id_categoria,:codigo,:codigo_barras,:nombre,:descripcion,:precio_compra,:precio_venta,0,:stock_minimo,:unidad_medida)")
            ->execute($this->campos($d));
        return (int)$this->db->lastInsertId();
    }
    public function actualizar(int $id, array $d): void {
        $this->db->prepare("UPDATE productos SET id_categoria=:id_categoria,codigo=:codigo,codigo_barras=:codigo_barras,nombre=:nombre,
            descripcion=:descripcion,precio_compra=:precio_compra,precio_venta=:precio_venta,stock_minimo=:stock_minimo,unidad_medida=:unidad_medida
            WHERE id_producto=:id")->execute($this->campos($d)+['id'=>$id]);
    }
    public function actualizarImagen(int $id, ?string $imagen): void {
        $this->db->prepare("UPDATE productos SET imagen=:i WHERE id_producto=:id")->execute(['i'=>$imagen,'id'=>$id]);
    }
    public function cambiarEstado(int $id, int $estado): void {
        $this->db->prepare("UPDATE productos SET estado=:e WHERE id_producto=:id")->execute(['e'=>$estado,'id'=>$id]);
    }
    public function resumenStock(): array {
        return $this->db->query("SELECT COUNT(*) activos, SUM(stock<=stock_minimo) bajos, SUM(stock<=0) agotados,
            COALESCE(SUM(stock*precio_compra),0) valor_costo FROM productos WHERE estado=1")->fetch();
    }
    public function usos(int $id): array {
        $s=$this->db->prepare("SELECT (SELECT COUNT(*) FROM detalle_venta WHERE id_producto=:a) ventas,
            (SELECT COUNT(*) FROM detalle_devolucion WHERE id_producto=:b) devoluciones");
        $s->execute(['a'=>$id,'b'=>$id]); return $s->fetch();
    }
    // Borra el producto y sus datos auxiliares (carritos abiertos y movimientos de inventario propios).
    public function eliminar(int $id): void {
        $this->db->prepare("DELETE FROM carrito_detalle WHERE id_producto=:id")->execute(['id'=>$id]);
        $this->db->prepare("DELETE FROM movimientos_inventario WHERE id_producto=:id")->execute(['id'=>$id]);
        $this->db->prepare("DELETE FROM productos WHERE id_producto=:id")->execute(['id'=>$id]);
    }
    private function campos(array $d): array {
        return array_intersect_key($d, array_flip(['id_categoria','codigo','codigo_barras','nombre','descripcion','precio_compra','precio_venta','stock_minimo','unidad_medida']));
    }
}
