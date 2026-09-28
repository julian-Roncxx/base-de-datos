<?php

namespace Models;

use Config\Database;
use PDO;

class Product
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }


    public function getAll()
    {
        $sql = "
            SELECT
                id,
                nombre,
                categoria,
                precio,
                cantidad,
                stockMinimo,
                estado,
                valorInventario
            FROM vw_inventario
            ORDER BY nombre
        ";

        $stmt = $this->db->query($sql);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    public function create($nombre, $cantidad, $precio)
    {
        $sql = "
            INSERT INTO productos
            (
                nombre,
                cantidad,
                precio,
                idCategoria
            )

            SELECT
                :nombre,
                :cantidad,
                :precio,
                idCategoria

            FROM categorias

            WHERE nombre = 'Sin categoría'

            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            'nombre' => $nombre,
            'cantidad' => $cantidad,
            'precio' => $precio
        ]);
    }


    public function update(
        $id,
        $nombre,
        $cantidad,
        $precio
    ) {

        $sql = "
            UPDATE productos

            SET
                nombre = :nombre,
                cantidad = :cantidad,
                precio = :precio

            WHERE idProducto = :id
        ";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            'id' => $id,
            'nombre' => $nombre,
            'cantidad' => $cantidad,
            'precio' => $precio
        ]);
    }


    public function delete($id)
    {
        $sql = "
            DELETE FROM productos
            WHERE idProducto = :id
        ";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            'id' => $id
        ]);
    }
}