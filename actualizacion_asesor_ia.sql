-- ============================================================
-- ACTUALIZACIÓN: módulo Asesor IA
-- Solo para bases de datos creadas antes de este módulo.
-- (Si importas database.sql desde cero, ya lo incluye.)
-- ============================================================
USE sistema_ventas;
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS recomendaciones_ia (
    id_recomendacion INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT UNSIGNED NULL,
    fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    modo ENUM('ia','local') NOT NULL,
    pregunta VARCHAR(500) NULL,
    respuesta JSON NOT NULL,
    herramientas JSON NULL,
    total_recomendaciones INT UNSIGNED NOT NULL DEFAULT 0,
    tokens_entrada INT UNSIGNED NOT NULL DEFAULT 0,
    tokens_salida INT UNSIGNED NOT NULL DEFAULT 0,
    CONSTRAINT fk_recomendacion_usuario
        FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario)
        ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_recomendacion_fecha (fecha)
) ENGINE=InnoDB;

INSERT IGNORE INTO permisos (nombre, descripcion) VALUES ('asesor.ver', 'Consultar el Asesor IA');

-- Administrador y Supervisor
INSERT IGNORE INTO rol_permisos (id_rol, id_permiso)
SELECT r.id_rol, p.id_permiso FROM roles r, permisos p
WHERE r.id_rol IN (1, 4) AND p.nombre = 'asesor.ver';

-- Avisa a las sesiones abiertas que recarguen sus permisos
INSERT INTO configuracion (clave, valor, descripcion) VALUES ('permisos_version', UUID(), 'Control interno de cambios de permisos')
ON DUPLICATE KEY UPDATE valor = UUID();
