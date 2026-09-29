<?php
class Auditoria {
    public function __construct(private PDO $db) {}

    // $idUsuario permite registrar eventos sin sesión (ej. intento de inicio de sesión fallido).
    public function registrar(string $modulo, string $accion, string $tabla, int $idRegistro, string $descripcion, ?int $idUsuario=null): void {
        $this->db->prepare("INSERT INTO auditoria (id_usuario,modulo,accion,tabla_afectada,id_registro,descripcion,ip)
            VALUES (:u,:m,:a,:t,:id,:d,:ip)")
            ->execute(['u'=>$idUsuario ?? ($_SESSION['id_usuario'] ?? null),'m'=>$modulo,'a'=>$accion,'t'=>$tabla ?: null,
                'id'=>$idRegistro ?: null,'d'=>mb_substr($descripcion,0,2000),'ip'=>$_SERVER['REMOTE_ADDR'] ?? null]);
    }

    // Filtros: desde, hasta (Y-m-d), id_usuario, modulo, accion, q (texto en la descripción).
    private function filtros(array $f): array {
        $where=['a.fecha >= :desde','a.fecha < DATE_ADD(:hasta, INTERVAL 1 DAY)'];
        $p=['desde'=>$f['desde'],'hasta'=>$f['hasta']];
        if (!empty($f['id_usuario'])) { $where[]='a.id_usuario=:u'; $p['u']=(int)$f['id_usuario']; }
        if (!empty($f['modulo'])) { $where[]='a.modulo=:m'; $p['m']=$f['modulo']; }
        if (!empty($f['accion'])) { $where[]='a.accion=:a'; $p['a']=$f['accion']; }
        if (($f['q'] ?? '')!=='') { $where[]='a.descripcion LIKE :q'; $p['q']='%'.$f['q'].'%'; }
        return [implode(' AND ',$where),$p];
    }
    public function contar(array $f): int {
        [$w,$p]=$this->filtros($f);
        $s=$this->db->prepare("SELECT COUNT(*) FROM auditoria a WHERE {$w}"); $s->execute($p);
        return (int)$s->fetchColumn();
    }
    public function listar(array $f, int $limite, int $desde=0): array {
        [$w,$p]=$this->filtros($f);
        $s=$this->db->prepare("SELECT a.*,u.nombre usuario,u.usuario login FROM auditoria a LEFT JOIN usuarios u ON u.id_usuario=a.id_usuario
            WHERE {$w} ORDER BY a.fecha DESC, a.id_auditoria DESC LIMIT ".(int)$limite." OFFSET ".(int)$desde);
        $s->execute($p);
        return $s->fetchAll();
    }
    public function modulos(): array { return $this->db->query("SELECT DISTINCT modulo FROM auditoria WHERE modulo IS NOT NULL ORDER BY modulo")->fetchAll(PDO::FETCH_COLUMN); }
    public function acciones(): array { return $this->db->query("SELECT DISTINCT accion FROM auditoria ORDER BY accion")->fetchAll(PDO::FETCH_COLUMN); }
    public function usuarios(): array { return $this->db->query("SELECT id_usuario,nombre FROM usuarios ORDER BY nombre")->fetchAll(); }
    public function resumen(array $f): array {
        [$w,$p]=$this->filtros($f);
        $s=$this->db->prepare("SELECT COUNT(*) total,COUNT(DISTINCT a.id_usuario) usuarios,SUM(a.accion='login fallido') fallidos,
            SUM(a.accion IN ('anular','desactivar','cerrar','permisos','ajuste','salida')) sensibles FROM auditoria a WHERE {$w}");
        $s->execute($p); return $s->fetch();
    }
}
