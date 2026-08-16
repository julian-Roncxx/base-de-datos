<?php
namespace Config;
use PDO;
use PDOException;

class Database {
    private static $instance = null;
    private $connection;

    // Configuración para XAMPP
// Configuración para Clever Cloud
    private $host = 'blzgdilpfnglyxk8uzat-mysql.services.clever-cloud.com';
    private $db_name = 'blzgdilpfnglyxk8uzat';
    private $username = 'uwnusqatkvl0xxkd';
    private $password = 'hEnacqHdKIWGLEzKVv6p'; // Dale clic al candado amarillo en Clever Cloud para verla
    private $port = '3306';

    private function __construct() {
        try {
            $this->connection = new PDO(
                "mysql:host={$this->host};port={$this->port};dbname={$this->db_name};charset=utf8",
                $this->username,
                $this->password
            );
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $e) {
            die("Error de conexión: " . $e->getMessage());
        }
    }

    public static function getInstance() {
        if (self::$instance == null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->connection;
    }
}