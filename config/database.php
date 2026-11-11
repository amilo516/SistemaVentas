<?php
declare(strict_types=1);

class Database {
    private string $host = '127.0.0.1';
    private string $dbName = 'sistema_ventas';
    private string $username = 'root';
    private string $password = '';
    private ?PDO $connection = null;

    public function getConnection(): PDO {
        if ($this->connection === null) {
            $dsn = "mysql:host={$this->host};dbname={$this->dbName};charset=utf8mb4";
            try {
                $this->connection = new PDO($dsn, $this->username, $this->password, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
            } catch (PDOException $e) {
                if (PHP_SAPI === 'cli') throw $e;
                self::mostrarErrorConexion($e);
            }
        }
        return $this->connection;
    }

    // Página de ayuda cuando no hay conexión: dice qué falta en lugar de mostrar un error técnico.
    private static function mostrarErrorConexion(PDOException $e): never {
        error_log('Conexión a la base de datos: ' . $e->getMessage());
        $codigo = (int)($e->errorInfo[1] ?? $e->getCode());
        [$titulo, $ayuda] = match ($codigo) {
            1049 => ['La base de datos no existe', 'Abre phpMyAdmin (http://localhost/phpmyadmin), pestaña <b>Importar</b>, y carga el archivo <b>database.sql</b> que está en la carpeta del proyecto.'],
            1045 => ['Usuario o contraseña de MySQL incorrectos', 'Revisa el usuario y la contraseña en <b>config/database.php</b>. En XAMPP normalmente son <b>root</b> y contraseña vacía.'],
            2002, 2006 => ['MySQL no está encendido', 'Abre el panel de control de XAMPP y pulsa <b>Start</b> en <b>MySQL</b>.'],
            default => ['No se pudo conectar con la base de datos', 'Verifica que MySQL esté encendido en XAMPP y que hayas importado <b>database.sql</b>.'],
        };
        if (!headers_sent()) { http_response_code(500); header('Content-Type: text/html; charset=utf-8'); }
        echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>Error de conexión</title></head><body style="font-family:system-ui,sans-serif;max-width:640px;margin:60px auto;padding:0 16px;color:#1f2937">'
            . '<h1 style="font-size:24px">' . $titulo . '</h1><p style="line-height:1.6">' . $ayuda . '</p>'
            . '<p style="color:#6b7280;font-size:14px">Luego recarga esta página.</p></body></html>';
        exit;
    }
}
