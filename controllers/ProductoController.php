<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/permisos.php';
require_once __DIR__ . '/../helpers/productos.php';
require_once __DIR__ . '/../helpers/inventario.php';
require_once __DIR__ . '/../helpers/factura.php';
require_once __DIR__ . '/../models/Producto.php';
require_once __DIR__ . '/../models/Categoria.php';
require_once __DIR__ . '/../models/Auditoria.php';

class ProductoController {
    private const CARPETA_IMAGENES = __DIR__ . '/../assets/img/productos/';
    private const TIPOS_IMAGEN = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    private PDO $db;
    private Producto $productos;
    public function __construct() {
        $this->db=(new Database())->getConnection();
        $this->productos=new Producto($this->db);
    }

    public function listar(array $filtros): array { return $this->productos->listar($filtros); }
    public function buscar(int $id): ?array { return $this->productos->buscar($id); }
    public function categorias(): array { return (new Categoria($this->db))->activas(); }
    public function resumenStock(): array { return $this->productos->resumenStock(); }

    // Devuelve [id del producto, errores].
    public function crear(array $post, array $archivo): array {
        [$d,$errores]=$this->validar($post,0);
        $stockInicial=$this->numero($post['stock'] ?? 0);
        if ($stockInicial<0) $errores[]='El stock inicial no puede ser negativo.';
        elseif (!permiteDecimales($d['unidad_medida'] ?? 'unidad') && floor($stockInicial)!=$stockInicial) $errores[]='El stock inicial debe ser un número entero para esta unidad de medida.';
        $errores=array_merge($errores,$this->validarImagen($archivo));
        if ($errores) return [0,$errores];
        $this->db->beginTransaction();
        try {
            $id=$this->productos->crear($d);
            if ($stockInicial>0) actualizarStock($this->db,$id,$stockInicial,'entrada',(int)$_SESSION['id_usuario'],null,'Stock inicial',$d['codigo']);
            (new Auditoria($this->db))->registrar('productos','crear','productos',$id,"Producto {$d['codigo']} - {$d['nombre']}");
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            error_log('Crear producto: '.$e->getMessage());
            return [0,['No se pudo guardar el producto. Inténtalo de nuevo.']];
        }
        $this->guardarImagen($id,$archivo,null);
        return [$id,[]];
    }

    public function actualizar(int $id, array $post, array $archivo): array {
        $actual=$this->productos->buscar($id);
        if (!$actual) return ['El producto no existe.'];
        [$d,$errores]=$this->validar($post,$id);
        $errores=array_merge($errores,$this->validarImagen($archivo));
        // Ajuste de stock: solo con permiso de inventario y si el valor cambió.
        $ajustar=false;
        if (tienePermiso('inventario.ajuste') && isset($post['stock']) && $post['stock']!=='') {
            $nuevoStock=$this->numero($post['stock']);
            $ajustar=abs($nuevoStock-(float)$actual['stock'])>0.0005;
            if ($ajustar) {
                if ($nuevoStock<0) $errores[]='El stock no puede ser negativo.';
                elseif (!permiteDecimales($d['unidad_medida'] ?? 'unidad') && floor($nuevoStock)!=$nuevoStock) $errores[]='El stock debe ser un número entero para esta unidad de medida.';
                $motivo=mb_substr(trim($post['motivo_ajuste'] ?? ''),0,255);
                if ($motivo==='') $errores[]='Escribe el motivo del ajuste de stock (ej. conteo físico, producto dañado).';
            }
        }
        if ($errores) return $errores;
        $this->db->beginTransaction();
        try {
            $this->productos->actualizar($id,$d);
            if ($ajustar) actualizarStock($this->db,$id,$nuevoStock,'ajuste',(int)$_SESSION['id_usuario'],null,$motivo,$d['codigo']);
            (new Auditoria($this->db))->registrar('productos','editar','productos',$id,"Producto {$d['codigo']} - {$d['nombre']}"
                .($ajustar ? ". Stock ajustado de ".cantidad($actual['stock'])." a ".cantidad($nuevoStock).": {$motivo}" : ''));
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            error_log('Editar producto: '.$e->getMessage());
            return ['No se pudo guardar el producto. Inténtalo de nuevo.'];
        }
        if (!empty($post['quitar_imagen'])) $this->borrarImagen($id,$actual['imagen']);
        else $this->guardarImagen($id,$archivo,$actual['imagen']);
        return [];
    }

    public function cambiarEstado(int $id): string {
        $p=$this->productos->buscar($id);
        if (!$p) return 'El producto no existe.';
        $this->productos->cambiarEstado($id, $p['estado'] ? 0 : 1);
        (new Auditoria($this->db))->registrar('productos',$p['estado'] ? 'desactivar' : 'activar','productos',$id,"Producto {$p['codigo']} - {$p['nombre']}");
        return '';
    }

    public function eliminar(int $id): string {
        $p=$this->productos->buscar($id);
        if (!$p) return 'El producto no existe.';
        $u=$this->productos->usos($id);
        if ($u['ventas'] || $u['devoluciones']) return "No se puede eliminar \"{$p['nombre']}\": aparece en {$u['ventas']} venta(s) y el historial debe conservarse. Puedes desactivarlo para que no se venda más.";
        $this->db->beginTransaction();
        $this->productos->eliminar($id);
        (new Auditoria($this->db))->registrar('productos','eliminar','productos',0,"Producto {$p['codigo']} - {$p['nombre']} eliminado (stock ".cantidad($p['stock']).')');
        $this->db->commit();
        if ($p['imagen']) @unlink(self::CARPETA_IMAGENES.basename($p['imagen']));
        return '';
    }

    private function validar(array $post, int $id): array {
        $d=[
            'id_categoria'=>(int)($post['id_categoria'] ?? 0),
            'codigo'=>trim($post['codigo'] ?? ''),
            'codigo_barras'=>trim($post['codigo_barras'] ?? '') ?: null,
            'nombre'=>trim($post['nombre'] ?? ''),
            'descripcion'=>trim($post['descripcion'] ?? '') ?: null,
            'precio_compra'=>round($this->numero($post['precio_compra'] ?? 0),2),
            'precio_venta'=>round($this->numero($post['precio_venta'] ?? 0),2),
            'stock_minimo'=>round($this->numero($post['stock_minimo'] ?? 0),3),
            'unidad_medida'=>(string)($post['unidad_medida'] ?? ''),
        ];
        $e=[];
        if (!(new Categoria($this->db))->existeActiva($d['id_categoria'])) $e[]='Selecciona una categoría.';
        if (!preg_match('/^[A-Za-z0-9._\-]{1,50}$/',$d['codigo'])) $e[]='El código es obligatorio: hasta 50 letras, números, punto o guion, sin espacios.';
        elseif ($this->productos->existeCodigo('codigo',$d['codigo'],$id)) $e[]="Ya existe un producto con el código \"{$d['codigo']}\".";
        if ($d['codigo_barras']!==null) {
            if (!preg_match('/^[A-Za-z0-9\-]{1,100}$/',$d['codigo_barras'])) $e[]='El código de barras solo puede tener letras, números y guiones.';
            elseif ($this->productos->existeCodigo('codigo_barras',$d['codigo_barras'],$id)) $e[]='Ese código de barras ya está asignado a otro producto.';
        }
        if ($d['nombre']==='' || mb_strlen($d['nombre'])>150) $e[]='El nombre es obligatorio (máximo 150 caracteres).';
        if ($d['precio_compra']<0) $e[]='El precio de compra no puede ser negativo.';
        if ($d['precio_venta']<=0) $e[]='El precio de venta debe ser mayor que cero.';
        if ($d['stock_minimo']<0) $e[]='El stock mínimo no puede ser negativo.';
        if (!isset(unidadesMedida()[$d['unidad_medida']])) $e[]='Selecciona una unidad de medida válida.';
        return [$d,$e];
    }

    private function numero(mixed $v): float { return (float)str_replace(',','.',(string)$v); }

    private function validarImagen(array $archivo): array {
        if (($archivo['error'] ?? UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE) return [];
        if ($archivo['error']!==UPLOAD_ERR_OK) return ['No se pudo subir la imagen (máximo 2 MB).'];
        if ($archivo['size']>2*1024*1024) return ['La imagen no puede pesar más de 2 MB.'];
        $tipo=(new finfo(FILEINFO_MIME_TYPE))->file($archivo['tmp_name']);
        if (!isset(self::TIPOS_IMAGEN[$tipo]) || !getimagesize($archivo['tmp_name'])) return ['La imagen debe ser JPG, PNG o WEBP.'];
        return [];
    }
    private function guardarImagen(int $id, array $archivo, ?string $anterior): void {
        if (($archivo['error'] ?? UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK) return;
        $ext=self::TIPOS_IMAGEN[(new finfo(FILEINFO_MIME_TYPE))->file($archivo['tmp_name'])];
        $nombre='producto-'.$id.'-'.bin2hex(random_bytes(6)).'.'.$ext;
        if (!move_uploaded_file($archivo['tmp_name'], self::CARPETA_IMAGENES.$nombre)) { error_log('No se pudo mover la imagen del producto '.$id); return; }
        $this->productos->actualizarImagen($id,$nombre);
        if ($anterior) @unlink(self::CARPETA_IMAGENES.basename($anterior));
    }
    private function borrarImagen(int $id, ?string $imagen): void {
        $this->productos->actualizarImagen($id,null);
        if ($imagen) @unlink(self::CARPETA_IMAGENES.basename($imagen));
    }
}
