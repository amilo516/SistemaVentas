<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/agente_local.php';
require_once __DIR__ . '/../helpers/agente_ia.php';
require_once __DIR__ . '/../models/Auditoria.php';
require_once __DIR__ . '/../helpers/permisos.php';

// Orquesta el Asesor IA: usa el modelo de IA si hay clave configurada y, si no (o si falla), el motor de reglas local.
class AsesorController {
    private PDO $db;
    private AsesorDatos $datos;
    public function __construct() {
        $this->db=(new Database())->getConnection();
        $this->datos=new AsesorDatos($this->db);
        $this->instalar();
    }

    // Bases de datos creadas antes de este módulo: crea la tabla del historial y el permiso asesor.ver
    // (para Administrador y Supervisor) la primera vez, igual que actualizacion_asesor_ia.sql.
    private function instalar(): void {
        $existe=$this->db->query("SHOW TABLES LIKE 'recomendaciones_ia'")->fetchColumn();
        if ($existe) return;
        $sql=file_get_contents(__DIR__ . '/../actualizacion_asesor_ia.sql');
        preg_match('/CREATE TABLE IF NOT EXISTS recomendaciones_ia.*?\) ENGINE=InnoDB;/s', $sql, $m);
        $this->db->exec($m[0]);
        $nuevo=$this->db->exec("INSERT IGNORE INTO permisos (nombre, descripcion) VALUES ('asesor.ver', 'Consultar el Asesor IA')");
        if ($nuevo) {
            $this->db->exec("INSERT IGNORE INTO rol_permisos (id_rol, id_permiso)
                SELECT r.id_rol, p.id_permiso FROM roles r, permisos p
                WHERE r.id_rol IN (" . ROL_ADMINISTRADOR . "," . ROL_SUPERVISOR . ") AND p.nombre='asesor.ver'");
            marcarCambioPermisos($this->db);
        }
    }

    public function modoIA(): bool { return AgenteIA::disponible(); }

    public function analizar(string $pregunta): array {
        $pregunta=trim(mb_substr($pregunta,0,500));
        $inicio=microtime(true);
        $aviso=null;
        $agente=null;
        if ($this->modoIA()) {
            $agente=new AgenteIA($this->datos);
            try {
                $resultado=$agente->analizar($pregunta!=='' ? $pregunta
                    : 'Analiza el estado actual del negocio y dame las recomendaciones más importantes para esta semana.');
                $modo='ia';
            } catch (Throwable $e) {
                error_log('Asesor IA: ' . $e->getMessage());
                $aviso='No fue posible conectar con la IA; se muestran las recomendaciones del modo local.';
            }
        }
        if (!isset($resultado)) {
            $resultado=(new AgenteLocal($this->datos))->analizar();
            $modo='local';
            if ($pregunta!=='' && !$aviso) $aviso='Las preguntas libres requieren el modo IA. Estas son las recomendaciones automáticas.';
        }
        $resultado+=['modo'=>$modo,'aviso'=>$aviso,'segundos'=>round(microtime(true)-$inicio,1),
            'herramientas'=>array_values(array_unique($agente?->herramientasUsadas ?? []))];
        $id=$this->guardar($pregunta,$resultado,$agente);
        (new Auditoria($this->db))->registrar('asesor','consultar','recomendaciones_ia',$id,
            'Consultó el Asesor IA (' . ($modo==='ia' ? 'IA' : 'local') . '): ' . count($resultado['recomendaciones']) . ' recomendaciones'
            . ($pregunta!=='' ? '. Pregunta: ' . $pregunta : ''));
        return $resultado;
    }

    public function historial(int $limite=10): array {
        $s=$this->db->prepare("SELECT r.id_recomendacion,r.fecha,r.modo,r.pregunta,r.total_recomendaciones,u.nombre usuario
            FROM recomendaciones_ia r LEFT JOIN usuarios u ON u.id_usuario=r.id_usuario ORDER BY r.id_recomendacion DESC LIMIT " . max(1,$limite));
        $s->execute();
        return $s->fetchAll();
    }

    public function buscar(int $id): ?array {
        $s=$this->db->prepare("SELECT * FROM recomendaciones_ia WHERE id_recomendacion=:id");
        $s->execute(['id'=>$id]);
        $r=$s->fetch();
        if (!$r) return null;
        return (json_decode($r['respuesta'],true) ?: ['resumen'=>'','recomendaciones'=>[]])
            + ['modo'=>$r['modo'],'aviso'=>null,'segundos'=>null,'herramientas'=>json_decode((string)$r['herramientas'],true) ?: [],
               'fecha'=>$r['fecha'],'pregunta'=>$r['pregunta']];
    }

    private function guardar(string $pregunta, array $r, ?AgenteIA $agente): int {
        $this->db->prepare("INSERT INTO recomendaciones_ia (id_usuario,modo,pregunta,respuesta,herramientas,total_recomendaciones,tokens_entrada,tokens_salida)
            VALUES (:u,:m,:p,:r,:h,:t,:te,:ts)")->execute([
            'u'=>$_SESSION['id_usuario'] ?? null,'m'=>$r['modo'],'p'=>$pregunta ?: null,
            'r'=>json_encode(['resumen'=>$r['resumen'],'recomendaciones'=>$r['recomendaciones']],JSON_UNESCAPED_UNICODE),
            'h'=>json_encode($r['herramientas']),'t'=>count($r['recomendaciones']),
            'te'=>$agente?->tokensEntrada ?? 0,'ts'=>$agente?->tokensSalida ?? 0]);
        return (int)$this->db->lastInsertId();
    }
}
