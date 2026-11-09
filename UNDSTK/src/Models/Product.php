<?php

namespace Models;

use Config\Database;
use PDO;

class Product
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function getAll(): array
    {
        return $this->db->query(
            'SELECT id, nombre, categoria, precio, cantidad, stockMinimo, estado, valorInventario
             FROM vw_inventario ORDER BY nombre'
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT idProducto, nombre, precio, cantidad, stockMinimo, idCategoria
             FROM productos WHERE idProducto = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $producto = $stmt->fetch(PDO::FETCH_ASSOC);
        return $producto === false ? null : $producto;
    }

    /** Devuelve el ID de MySQL, no solamente true. */
    public function create($nombre, $cantidad, $precio)
    {
        $sql = "INSERT INTO productos (nombre, cantidad, precio, idCategoria)
                SELECT :nombre, :cantidad, :precio, idCategoria
                FROM categorias WHERE nombre = 'Sin categoría' LIMIT 1";
        $stmt = $this->db->prepare($sql);
        if (!$stmt->execute([
            'nombre' => $nombre,
            'cantidad' => $cantidad,
            'precio' => $precio
        ]) || $stmt->rowCount() !== 1) {
            return false;
        }
        return (int) $this->db->lastInsertId();
    }

    public function update($id, $nombre, $cantidad, $precio): bool
    {
        if ($this->getById((int) $id) === null) {
            return false;
        }
        $stmt = $this->db->prepare(
            'UPDATE productos SET nombre = :nombre, cantidad = :cantidad, precio = :precio
             WHERE idProducto = :id'
        );
        return $stmt->execute([
            'id' => $id,
            'nombre' => $nombre,
            'cantidad' => $cantidad,
            'precio' => $precio
        ]);
    }

    public function delete($id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM productos WHERE idProducto = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->rowCount() === 1;
    }
}
