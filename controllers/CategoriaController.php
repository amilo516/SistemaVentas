<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/permisos.php';
require_once __DIR__ . '/../models/Categoria.php';
require_once __DIR__ . '/../models/Auditoria.php';

class CategoriaController {
    private PDO $db;
    private Categoria $categorias;
    public function __construct() {
        $this->db=(new Database())->getConnection();
        $this->categorias=new Categoria($this->db);
    }
    public function listar(): array { return $this->categorias->listar(); }
    public function buscar(int $id): ?array { return $this->categorias->buscar($id); }

    // Crea ($id=0) o actualiza una categoría. Devuelve la lista de errores.
    public function guardar(int $id, array $d): array {
        $nombre=trim($d['nombre'] ?? '');
        $descripcion=mb_substr(trim($d['descripcion'] ?? ''),0,255) ?: null;
        $errores=[];
        if ($nombre==='' || mb_strlen($nombre)>100) $errores[]='El nombre es obligatorio (máximo 100 caracteres).';
        elseif ($this->categorias->existeNombre($nombre,$id)) $errores[]="Ya existe una categoría llamada \"{$nombre}\".";
        if ($id && !$this->categorias->buscar($id)) $errores[]='La categoría no existe.';
        if ($errores) return $errores;
        $accion=$id ? 'editar' : 'crear';
        if ($id) $this->categorias->actualizar($id,$nombre,$descripcion); else $id=$this->categorias->crear($nombre,$descripcion);
        (new Auditoria($this->db))->registrar('categorias',$accion,'categorias',$id,"Categoría {$nombre}");
        return [];
    }
    public function cambiarEstado(int $id): string {
        $c=$this->categorias->buscar($id);
        if (!$c) return 'La categoría no existe.';
        $this->categorias->cambiarEstado($id, $c['estado'] ? 0 : 1);
        return '';
    }
    public function eliminar(int $id): string {
        $c=$this->categorias->buscar($id);
        if (!$c) return 'La categoría no existe.';
        $n=$this->categorias->contarProductos($id);
        if ($n) return "No se puede eliminar \"{$c['nombre']}\": tiene {$n} producto(s). Muévelos a otra categoría o desactívala.";
        $this->categorias->eliminar($id);
        (new Auditoria($this->db))->registrar('categorias','eliminar','categorias',0,"Categoría {$c['nombre']} eliminada");
        return '';
    }
}
