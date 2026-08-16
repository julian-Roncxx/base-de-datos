<?php
namespace Models;

use Config\Database;
use PDO;

class User {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function findByEmail($correo) {
        $stmt = $this->db->prepare("SELECT * FROM formregistro WHERE correoRegistro = :correo");
        $stmt->bindParam(':correo', $correo);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}