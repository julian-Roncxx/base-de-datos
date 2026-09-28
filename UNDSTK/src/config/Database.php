<?php

namespace Config;

use PDO;
use PDOException;

class Database
{
    private static $instance = null;
    private $connection;

    private $host = '127.0.0.1';
    private $db_name = 'understock';
    private $username = 'root';
    private $password = '';
    private $port = '3306';

    private function __construct()
    {
        try {

            $this->connection = new PDO(
                "mysql:host={$this->host};port={$this->port};dbname={$this->db_name};charset=utf8mb4",
                $this->username,
                $this->password
            );

            $this->connection->setAttribute(
                PDO::ATTR_ERRMODE,
                PDO::ERRMODE_EXCEPTION
            );

            $this->connection->setAttribute(
                PDO::ATTR_DEFAULT_FETCH_MODE,
                PDO::FETCH_ASSOC
            );

        } catch (PDOException $e) {

            die(
                "Error de conexión con la base de datos: "
                . $e->getMessage()
            );
        }
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new Database();
        }

        return self::$instance;
    }

    public function getConnection()
    {
        return $this->connection;
    }
}