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
            $this->connection = new PDO($dsn, $this->username, $this->password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        }
        return $this->connection;
    }
}
