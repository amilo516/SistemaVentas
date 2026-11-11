<?php
declare(strict_types=1);
// Configuración del Asesor IA.
// La clave NO se escribe aquí: cópiala en config/ia.local.php (ver ia.local.ejemplo.php)
// o en la variable de entorno IA_API_KEY. Sin clave, el asesor trabaja en modo local (reglas).
function configIA(): array {
    static $config = null;
    if ($config !== null) return $config;
    $local = is_file(__DIR__ . '/ia.local.php') ? require __DIR__ . '/ia.local.php' : [];
    $config = array_merge([
        'api_key' => getenv('IA_API_KEY') ?: '',
        'modelo' => 'claude-opus-5-5', // identificador técnico del modelo en el servicio de IA
        'esfuerzo' => 'medium',      // low | medium | high: más alto = análisis más profundo y más costoso
        'max_iteraciones' => 8,      // vueltas máximas del ciclo agente <-> herramientas
        'base_url' => null,          // solo para pruebas con un servidor simulado
    ], is_array($local) ? $local : []);
    return $config;
}
