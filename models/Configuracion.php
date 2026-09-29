<?php
class Configuracion {
    public function __construct(private PDO $db) {}
    public function todas(): array {
        return $this->db->query("SELECT clave,valor FROM configuracion")->fetchAll(PDO::FETCH_KEY_PAIR);
    }
    public function obtener(string $clave, string $porDefecto=''): string {
        $s=$this->db->prepare("SELECT valor FROM configuracion WHERE clave=:c LIMIT 1");
        $s->execute(['c'=>$clave]);
        $v=$s->fetchColumn();
        return $v===false || $v===null ? $porDefecto : (string)$v;
    }
    // Crea la clave si no existe (ej. 'pie_factura', que no viene en database.sql).
    public function guardar(string $clave, ?string $valor, string $descripcion=''): void {
        $this->db->prepare("INSERT INTO configuracion (clave,valor,descripcion) VALUES (:c,:v,:d) ON DUPLICATE KEY UPDATE valor=VALUES(valor)")
            ->execute(['c'=>$clave,'v'=>$valor,'d'=>$descripcion ?: null]);
    }
}
