<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/permisos.php';
require_once __DIR__ . '/../helpers/factura.php';
require_once __DIR__ . '/../helpers/productos.php';
require_once __DIR__ . '/../models/Carrito.php';
require_once __DIR__ . '/../models/Producto.php';
require_once __DIR__ . '/../models/Configuracion.php';

// Carrito del punto de venta. Los errores para el usuario se lanzan como RuntimeException.
class CarritoController {
    private PDO $db;
    private Carrito $carrito;
    private Producto $productos;
    private int $carritoId;
    public function __construct() {
        $this->db=(new Database())->getConnection();
        $this->carrito=new Carrito($this->db);
        $this->productos=new Producto($this->db);
        $this->carritoId=$this->carrito->activo((int)$_SESSION['id_usuario']);
    }

    public function estado(): array {
        $items=array_map(fn($i) => $i+['decimal'=>permiteDecimales($i['unidad_medida'])], $this->carrito->items($this->carritoId));
        $pct=(float)(new Configuracion($this->db))->obtener('impuesto','0');
        return ['items'=>$items,'totales'=>calcularTotales($items, 0, $pct)];
    }
    public function buscarProductos(string $texto): array {
        $texto=trim($texto);
        return $texto==='' ? [] : $this->productos->buscarParaVenta(mb_substr($texto,0,100));
    }
    public function agregar(int $productoId, float $cantidad): void {
        $this->fijarCantidad($productoId, $this->carrito->cantidadDe($this->carritoId,$productoId)+$cantidad);
    }
    public function actualizar(int $productoId, float $cantidad): void {
        if ($cantidad<=0) { $this->carrito->quitar($this->carritoId,$productoId); return; }
        $this->fijarCantidad($productoId, $cantidad);
    }
    public function quitar(int $productoId): void { $this->carrito->quitar($this->carritoId,$productoId); }
    public function vaciar(): void { $this->carrito->vaciar($this->carritoId); }

    private function fijarCantidad(int $productoId, float $cantidad): void {
        $p=$this->productos->buscar($productoId);
        if (!$p || !(int)$p['estado']) throw new RuntimeException('El producto no existe o está inactivo.');
        if ($cantidad<=0) throw new RuntimeException('La cantidad debe ser mayor que cero.');
        if (!permiteDecimales($p['unidad_medida']) && floor($cantidad)!=$cantidad) throw new RuntimeException("\"{$p['nombre']}\" se vende por cantidades enteras.");
        if ($cantidad>(float)$p['stock']) throw new RuntimeException("Stock insuficiente para \"{$p['nombre']}\". Disponible: ".cantidad($p['stock']).'.');
        $this->carrito->guardar($this->carritoId,$productoId,round($cantidad,3),(float)$p['precio_venta']);
    }
}
