<?php
namespace Models;

use Config\Database;
use PDO;

class Product {
    private $db;

    public function __construct() {
        // Obtenemos la conexión única (Singleton)
        $this->db = Database::getInstance()->getConnection();
    }

    // GET: Obtener todos los productos
    public function getAll() {
        $stmt = $this->db->query("SELECT * FROM productos");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // POST: Crear un nuevo producto
    public function create($nombre, $cantidad, $precio) {
        $stmt = $this->db->prepare("INSERT INTO productos (nombre, cantidad, precio) VALUES (:nombre, :cantidad, :precio)");
        $stmt->bindParam(':nombre', $nombre);
        $stmt->bindParam(':cantidad', $cantidad);
        $stmt->bindParam(':precio', $precio);
        return $stmt->execute();
    }

    // PUT: Actualizar un producto
    public function update($id, $nombre, $cantidad, $precio) {
        $sql = "UPDATE productos SET nombre = :nombre, cantidad = :cantidad, precio = :precio WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':nombre', $nombre);
        $stmt->bindParam(':cantidad', $cantidad);
        $stmt->bindParam(':precio', $precio);
        
        return $stmt->execute();
    }

    // DELETE: Eliminar un producto
    public function delete($id) {
        $sql = "DELETE FROM productos WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }
}