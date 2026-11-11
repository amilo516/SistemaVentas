<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/permisos.php';
require_once __DIR__ . '/../helpers/funciones.php';
require_once __DIR__ . '/../helpers/inventario.php';
require_once __DIR__ . '/../helpers/factura.php';
require_once __DIR__ . '/../helpers/productos.php';
require_once __DIR__ . '/../models/MovimientoInventario.php';
require_once __DIR__ . '/../models/Producto.php';
require_once __DIR__ . '/../models/Auditoria.php';

// Entradas, salidas y ajustes de inventario, y el kardex de movimientos.
class InventarioController {
    public const TIPOS = [
        'entrada' => ['titulo' => 'Entrada de inventario', 'permiso' => 'inventario.entrada', 'verbo' => 'Registrar entrada'],
        'salida' => ['titulo' => 'Salida de inventario', 'permiso' => 'inventario.salida', 'verbo' => 'Registrar salida'],
        'ajuste' => ['titulo' => 'Ajuste por conteo físico', 'permiso' => 'inventario.ajuste', 'verbo' => 'Guardar ajuste'],
    ];
    private PDO $db;
    private MovimientoInventario $movimientos;
    private Producto $productos;
    public function __construct() {
        $this->db=(new Database())->getConnection();
        $this->movimientos=new MovimientoInventario($this->db);
        $this->productos=new Producto($this->db);
    }

    public function listar(array $filtros): array { return $this->movimientos->listar($filtros); }
    public function conteoPorTipo(array $filtros): array { return $this->movimientos->conteoPorTipo($filtros); }
    public function producto(int $id): ?array { return $this->productos->buscar($id); }

    // Búsqueda para agregar productos al formulario (incluye stock y costo actuales).
    public function buscarProductos(string $texto): array {
        $texto=trim($texto);
        if ($texto==='') return [];
        return array_map(fn($p) => $p+['decimal'=>permiteDecimales($p['unidad_medida'])],
            $this->productos->buscarParaInventario(mb_substr($texto,0,100)));
    }

    /**
     * Registra una operación con varias líneas. $post['items'] = [id_producto => ['cantidad'=>..,'costo'=>..]].
     * En 'ajuste' la cantidad es el stock contado (el nuevo stock), no la diferencia.
     * Devuelve [número de productos afectados, errores].
     */
    public function registrar(string $tipo, array $post): array {
        if (!isset(self::TIPOS[$tipo])) return [0,['Tipo de movimiento no válido.']];
        $items=is_array($post['items'] ?? null) ? $post['items'] : [];
        $motivo=mb_substr(trim($post['motivo'] ?? ''),0,255);
        $referencia=mb_substr(trim($post['referencia'] ?? ''),0,100) ?: null;
        $errores=[];
        if (!$items) $errores[]='Agrega al menos un producto.';
        if ($motivo==='' && $tipo!=='entrada') $errores[]='Escribe el motivo.';
        if ($errores) return [0,$errores];
        if ($motivo==='') $motivo='Entrada de mercancía';
        $usuarioId=(int)$_SESSION['id_usuario'];
        $this->db->beginTransaction();
        try {
            $afectados=0; $resumen=[];
            foreach ($items as $id=>$linea) {
                $p=$this->productos->buscar((int)$id);
                if (!$p) throw new RuntimeException('Uno de los productos ya no existe.');
                if (!(int)$p['estado']) throw new RuntimeException("\"{$p['nombre']}\" está inactivo.");
                $cant=round((float)str_replace(',','.',(string)($linea['cantidad'] ?? '')),3);
                $etq="\"{$p['nombre']}\"";
                if (($linea['cantidad'] ?? '')==='' || $cant<0 || ($cant==0 && $tipo!=='ajuste')) throw new RuntimeException("Escribe una cantidad válida para {$etq}.");
                if (!permiteDecimales($p['unidad_medida']) && floor($cant)!=$cant) throw new RuntimeException("La cantidad de {$etq} debe ser un número entero.");
                if ($tipo==='salida' && $cant>(float)$p['stock']) throw new RuntimeException("Stock insuficiente para {$etq}. Disponible: ".cantidad($p['stock']).'.');
                if ($tipo==='ajuste' && abs($cant-(float)$p['stock'])<0.0005) continue; // el conteo coincide: nada que ajustar
                actualizarStock($this->db,(int)$id,$cant,$tipo,$usuarioId,null,$motivo,$referencia);
                // En entradas, un costo nuevo actualiza el precio de compra del producto.
                $costo=(float)str_replace(',','.',(string)($linea['costo'] ?? ''));
                if ($tipo==='entrada' && $costo>0 && abs($costo-(float)$p['precio_compra'])>=0.01) {
                    $this->db->prepare("UPDATE productos SET precio_compra=:c WHERE id_producto=:id")->execute(['c'=>round($costo,2),'id'=>$id]);
                }
                $resumen[]=$p['codigo'].' '.($tipo==='ajuste' ? cantidad($p['stock']).'→'.cantidad($cant) : cantidad($cant));
                $afectados++;
            }
            if ($afectados) (new Auditoria($this->db))->registrar('inventario',$tipo,'movimientos_inventario',0,
                ucfirst($tipo).($referencia ? " ({$referencia})" : '').": {$motivo}. ".implode(', ',$resumen));
            $this->db->commit();
            return [$afectados,[]];
        } catch (RuntimeException $e) {
            $this->db->rollBack();
            return [0,[$e->getMessage()]];
        }
    }
}
