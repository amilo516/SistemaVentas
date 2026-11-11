<?php
class Permiso {
    public function __construct(private PDO $db) {}

    public function todos(): array {
        return $this->db->query("SELECT id_permiso,nombre,descripcion FROM permisos ORDER BY id_permiso")->fetchAll();
    }
    public function deRol(int $rolId): array {
        $s=$this->db->prepare("SELECT p.nombre FROM rol_permisos rp INNER JOIN permisos p ON p.id_permiso=rp.id_permiso WHERE rp.id_rol=:r");
        $s->execute(['r'=>$rolId]); return $s->fetchAll(PDO::FETCH_COLUMN);
    }
    // Reemplaza los permisos del rol por la lista de nombres dada.
    public function asignar(int $rolId, array $nombres): void {
        $this->db->prepare("DELETE FROM rol_permisos WHERE id_rol=:r")->execute(['r'=>$rolId]);
        if (!$nombres) return;
        $marcas=implode(',',array_fill(0,count($nombres),'?'));
        $this->db->prepare("INSERT INTO rol_permisos (id_rol,id_permiso) SELECT ?,id_permiso FROM permisos WHERE nombre IN ({$marcas})")
            ->execute(array_merge([$rolId],array_values($nombres)));
    }
}
