<?php
namespace Models;

use Config\Database;
use PDO;

class Finance {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function getSummary() {
        // Consultamos la suma total de la columna 'total' y el conteo de filas en tu tabla de ventas
        $stmt = $this->db->query("SELECT SUM(total) as total_ventas, COUNT(id) as cantidad_ventas FROM ventas");
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}