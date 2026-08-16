<?php
namespace Models;

use Config\Database;
use PDO;

class Provider {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function getAll() {
        $stmt = $this->db->query("SELECT id, nombre_empresa, contacto, telefono FROM proveedores ORDER BY id DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create($nombreEmpresa, $contacto, $telefono) {
        $stmt = $this->db->prepare("INSERT INTO proveedores (nombre_empresa, contacto, telefono) VALUES (:nombre_empresa, :contacto, :telefono)");
        return $stmt->execute([
            'nombre_empresa' => $nombreEmpresa,
            'contacto' => $contacto,
            'telefono' => $telefono
        ]);
    }
}