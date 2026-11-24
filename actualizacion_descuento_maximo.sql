-- ============================================================
-- ACTUALIZACIÓN: descuento máximo permitido por rol
-- Solo para bases de datos creadas antes de esta funcionalidad.
-- (Si importas database.sql desde cero, ya lo incluye.)
-- Se puede ejecutar más de una vez sin error (MariaDB / XAMPP).
-- En MySQL 8, quita "IF NOT EXISTS" del ALTER TABLE.
-- ============================================================
USE sistema_ventas;
SET NAMES utf8mb4;

-- Porcentaje máximo de descuento (0–100, dos decimales). Los roles existentes quedan en 10 %.
ALTER TABLE roles
    ADD COLUMN IF NOT EXISTS descuento_maximo DECIMAL(5,2) NOT NULL DEFAULT 10.00
    AFTER descripcion;

-- El Administrador no tiene límite.
UPDATE roles SET descuento_maximo = 100.00 WHERE id_rol = 1;

-- Avisa a las sesiones abiertas que recarguen su rol
INSERT INTO configuracion (clave, valor, descripcion) VALUES ('permisos_version', UUID(), 'Control interno de cambios de permisos')
ON DUPLICATE KEY UPDATE valor = UUID();
