<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/permisos.php';

class AuthController {
    private PDO $db;
    public function __construct() { $this->db=(new Database())->getConnection(); }
}
