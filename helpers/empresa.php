<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Configuracion.php';

// Datos de la empresa (una consulta por petición). Si la BD no responde, usa valores por defecto.
function datosEmpresa(): array {
    static $datos = null;
    if ($datos === null) {
        try { $datos = (new Configuracion((new Database())->getConnection()))->todas(); }
        catch (Throwable $e) { error_log('Configuración: ' . $e->getMessage()); $datos = []; }
    }
    return $datos;
}
function nombreEmpresa(): string {
    $n = trim((string)(datosEmpresa()['nombre_empresa'] ?? ''));
    return $n !== '' && $n !== 'Mi Empresa' ? $n : APP_NAME;
}
function urlLogoEmpresa(): ?string {
    $logo = datosEmpresa()['logo'] ?? '';
    return $logo !== '' && is_file(__DIR__ . '/../assets/img/empresa/' . basename($logo)) ? APP_URL . '/assets/img/empresa/' . rawurlencode(basename($logo)) : null;
}
