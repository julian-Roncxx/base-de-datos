<?php

namespace Models;

use Config\Database;
use PDO;

class DatabaseFeature
{
    private $db;

    public function __construct()
    {
        $this->db =
            Database::getInstance()
            ->getConnection();
    }


    public function getDashboard()
    {

        // VISTA SQL

        $inventario =
            $this->db->query("
                SELECT *
                FROM vw_inventario
                ORDER BY nombre
            ")
            ->fetchAll(PDO::FETCH_ASSOC);


        // TRIGGERS

        $movimientos =
            $this->db->query("
                SELECT *
                FROM vw_movimientos_inventario
                ORDER BY fecha DESC
                LIMIT 20
            ")
            ->fetchAll(PDO::FETCH_ASSOC);


        // NORMALIZACIÓN

        $categorias =
            $this->db->query("
                SELECT
                    c.idCategoria,
                    c.nombre AS categoria,
                    COUNT(p.idProducto)
                        AS totalProductos

                FROM categorias c

                LEFT JOIN productos p
                    ON c.idCategoria =
                       p.idCategoria

                GROUP BY
                    c.idCategoria,
                    c.nombre

                ORDER BY c.nombre
            ")
            ->fetchAll(PDO::FETCH_ASSOC);


        // TRANSACCIONES REALIZADAS

        $ventas =
            $this->db->query("
                SELECT
                    id,
                    total,
                    fecha

                FROM ventas

                ORDER BY fecha DESC

                LIMIT 10
            ")
            ->fetchAll(PDO::FETCH_ASSOC);


        return [

            'inventario' =>
                $inventario,

            'movimientos' =>
                $movimientos,

            'categorias' =>
                $categorias,

            'transacciones' =>
                $ventas

        ];
    }
}