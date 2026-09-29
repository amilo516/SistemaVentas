<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/permisos.php';
require_once __DIR__ . '/../helpers/funciones.php';
require_once __DIR__ . '/../models/Configuracion.php';
require_once __DIR__ . '/../models/MetodoPago.php';
require_once __DIR__ . '/../models/Auditoria.php';

class ConfiguracionController {
    private const CARPETA_LOGO = __DIR__ . '/../assets/img/empresa/';
    private const TIPOS_IMAGEN = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    private PDO $db;
    private Configuracion $config;
    private MetodoPago $metodos;
    public function __construct() {
        $this->db=(new Database())->getConnection();
        $this->config=new Configuracion($this->db);
        $this->metodos=new MetodoPago($this->db);
    }

    public function valores(): array { return $this->config->todas(); }
    public function metodosPago(): array { return $this->metodos->listar(); }

    // Guarda empresa y facturación. Devuelve la lista de errores.
    public function guardar(array $post, array $logo): array {
        $v=fn($k,$max) => mb_substr(trim((string)($post[$k] ?? '')),0,$max);
        $d=[
            'nombre_empresa'=>$v('nombre_empresa',150), 'nit'=>$v('nit',30), 'direccion'=>$v('direccion',200),
            'telefono'=>$v('telefono',50), 'email'=>$v('email',100),
            'prefijo_factura'=>strtoupper($v('prefijo_factura',10)), 'impuesto'=>str_replace(',','.',$v('impuesto',10)),
            'pie_factura'=>$v('pie_factura',300),
        ];
        $e=[];
        if ($d['nombre_empresa']==='') $e[]='El nombre de la empresa es obligatorio.';
        if ($d['nit']!=='' && !preg_match('/^[0-9.\-]{5,30}$/',$d['nit'])) $e[]='El NIT solo puede tener números, puntos y guion (ej. 900.123.456-7).';
        if ($d['email']!=='' && !filter_var($d['email'],FILTER_VALIDATE_EMAIL)) $e[]='El correo electrónico no es válido.';
        if (!preg_match('/^[A-Z0-9]{1,10}$/',$d['prefijo_factura'])) $e[]='El prefijo de factura debe tener entre 1 y 10 letras o números, sin espacios (ej. FAC).';
        if (!is_numeric($d['impuesto']) || (float)$d['impuesto']<0 || (float)$d['impuesto']>100) $e[]='El impuesto debe ser un porcentaje entre 0 y 100.';
        $e=array_merge($e,$this->validarLogo($logo));
        if ($e) return $e;
        $d['impuesto']=rtrim(rtrim(number_format((float)$d['impuesto'],2,'.',''),'0'),'.');
        $anterior=$this->config->todas();
        $this->db->beginTransaction();
        foreach ($d as $clave=>$valor) $this->config->guardar($clave,$valor,$clave==='pie_factura' ? 'Mensaje al pie de la factura' : '');
        $cambios=array_keys(array_filter($d, fn($val,$k) => ($anterior[$k] ?? '')!==$val, ARRAY_FILTER_USE_BOTH));
        if ($cambios) (new Auditoria($this->db))->registrar('configuracion','editar','configuracion',0,'Cambió: '.implode(', ',$cambios));
        $this->db->commit();
        if (!empty($post['quitar_logo'])) $this->borrarLogo($anterior['logo'] ?? '');
        else $this->guardarLogo($logo,$anterior['logo'] ?? '');
        return [];
    }

    public function crearMetodo(string $nombre): string {
        $nombre=trim($nombre);
        if ($nombre==='' || mb_strlen($nombre)>50) return 'El nombre del método de pago es obligatorio (máximo 50 caracteres).';
        if ($this->metodos->existeNombre($nombre)) return "Ya existe el método de pago \"{$nombre}\".";
        $id=$this->metodos->crear($nombre);
        (new Auditoria($this->db))->registrar('configuracion','crear método de pago','metodos_pago',$id,$nombre);
        return '';
    }
    public function cambiarEstadoMetodo(int $id): string {
        $m=$this->metodos->buscar($id);
        if (!$m) return 'El método de pago no existe.';
        if ($m['estado'] && $this->metodos->activos()<=1) return 'Debe quedar al menos un método de pago activo.';
        $this->metodos->cambiarEstado($id,$m['estado'] ? 0 : 1);
        (new Auditoria($this->db))->registrar('configuracion',$m['estado'] ? 'desactivar método de pago' : 'activar método de pago','metodos_pago',$id,$m['nombre']);
        return '';
    }

    public function eliminarMetodo(int $id): string {
        $m=$this->metodos->buscar($id);
        if (!$m) return 'El método de pago no existe.';
        if (mb_strtolower($m['nombre'])==='efectivo') return 'El método Efectivo no se puede eliminar: lo usa la caja.';
        $usos=array_column($this->metodos->listar(),'usos','id_metodo_pago')[$id] ?? 0;
        if ($usos) return "No se puede eliminar \"{$m['nombre']}\": se usó en {$usos} pago(s). Puedes desactivarlo.";
        if ($m['estado'] && $this->metodos->activos()<=1) return 'Debe quedar al menos un método de pago activo.';
        $this->metodos->eliminar($id);
        (new Auditoria($this->db))->registrar('configuracion','eliminar método de pago','metodos_pago',0,$m['nombre']);
        return '';
    }

    private function validarLogo(array $a): array {
        if (($a['error'] ?? UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE) return [];
        if ($a['error']!==UPLOAD_ERR_OK || $a['size']>1024*1024) return ['El logo no puede pesar más de 1 MB.'];
        $tipo=(new finfo(FILEINFO_MIME_TYPE))->file($a['tmp_name']);
        if (!isset(self::TIPOS_IMAGEN[$tipo]) || !getimagesize($a['tmp_name'])) return ['El logo debe ser JPG, PNG o WEBP.'];
        return [];
    }
    private function guardarLogo(array $a, string $anterior): void {
        if (($a['error'] ?? UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK) return;
        $nombre='logo-'.bin2hex(random_bytes(6)).'.'.self::TIPOS_IMAGEN[(new finfo(FILEINFO_MIME_TYPE))->file($a['tmp_name'])];
        if (!move_uploaded_file($a['tmp_name'], self::CARPETA_LOGO.$nombre)) { error_log('No se pudo guardar el logo'); return; }
        $this->config->guardar('logo',$nombre);
        if ($anterior) @unlink(self::CARPETA_LOGO.basename($anterior));
    }
    private function borrarLogo(string $anterior): void {
        $this->config->guardar('logo','');
        if ($anterior) @unlink(self::CARPETA_LOGO.basename($anterior));
    }
}
