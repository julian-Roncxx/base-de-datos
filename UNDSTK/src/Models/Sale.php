<?php

namespace Models;

use Config\Database;
use PDO;
use Throwable;

class Sale
{
    private $db;


    public function __construct()
    {
        $this->db =
            Database::getInstance()
            ->getConnection();
    }


    private function registrarAuditoria(
        $ventaId,
        $estado,
        $mensaje
    ) {

        try {

            $stmt =
                $this->db->prepare("
                    INSERT INTO auditoria_transacciones
                    (
                        ventaId,
                        operacion,
                        estado,
                        mensaje
                    )

                    VALUES
                    (
                        :ventaId,
                        'VENTA',
                        :estado,
                        :mensaje
                    )
                ");


            $stmt->execute([

                'ventaId' =>
                    $ventaId,

                'estado' =>
                    $estado,

                'mensaje' =>
                    $mensaje

            ]);

        } catch (Throwable $e) {

            // La auditoría no debe
            // afectar la venta principal.

        }
    }


    public function createSale(
        $totalCliente,
        $items
    ) {

        $ventaId = null;


        try {

            if (
                !is_array($items) ||
                empty($items)
            ) {

                throw new \Exception(
                    "La venta no contiene productos."
                );
            }


            $this->db->beginTransaction();


            $this->db->exec(
                "SET @motivo_movimiento = 'Venta POS'"
            );


            $productosVenta = [];

            $totalCalculado = 0;


            $stmtProducto =
                $this->db->prepare("
                    SELECT
                        idProducto,
                        nombre,
                        precio,
                        cantidad

                    FROM productos

                    WHERE idProducto = :id

                    FOR UPDATE
                ");


            foreach ($items as $item) {

                $idProducto =
                    intval(
                        $item['id'] ?? 0
                    );


                $cantidad =
                    intval(
                        $item['cantidad'] ?? 0
                    );


                if (
                    $idProducto <= 0 ||
                    $cantidad <= 0
                ) {

                    throw new \Exception(
                        "Producto o cantidad inválida."
                    );
                }


                $stmtProducto->execute([

                    'id' =>
                        $idProducto

                ]);


                $producto =
                    $stmtProducto->fetch(
                        PDO::FETCH_ASSOC
                    );


                if (!$producto) {

                    throw new \Exception(
                        "Producto no encontrado."
                    );
                }


                if (
                    intval(
                        $producto['cantidad']
                    ) < $cantidad
                ) {

                    throw new \Exception(
                        "Stock insuficiente para "
                        . $producto['nombre']
                    );
                }


                $precio =
                    floatval(
                        $producto['precio']
                    );


                $totalCalculado +=
                    $precio * $cantidad;


                $productosVenta[] = [

                    'id' =>
                        $idProducto,

                    'cantidad' =>
                        $cantidad,

                    'precio' =>
                        $precio

                ];
            }


            $totalCalculado =
                round(
                    $totalCalculado,
                    2
                );


            if (
                abs(
                    floatval($totalCliente)
                    -
                    $totalCalculado
                ) > 0.01
            ) {

                throw new \Exception(
                    "El total enviado no coincide con el total calculado."
                );
            }


            $stmtVenta =
                $this->db->prepare("
                    INSERT INTO ventas
                    (total)

                    VALUES
                    (:total)
                ");


            $stmtVenta->execute([

                'total' =>
                    $totalCalculado

            ]);


            $ventaId =
                intval(
                    $this->db
                    ->lastInsertId()
                );


            $stmtDetalle =
                $this->db->prepare("
                    INSERT INTO detalles_venta
                    (
                        venta_id,
                        producto_id,
                        cantidad,
                        precio_unitario
                    )

                    VALUES
                    (
                        :venta,
                        :producto,
                        :cantidad,
                        :precio
                    )
                ");


            $stmtStock =
                $this->db->prepare("
                    UPDATE productos

                    SET cantidad =
                        cantidad - :cantidad

                    WHERE idProducto =
                        :producto
                ");


            foreach (
                $productosVenta
                as $producto
            ) {

                $stmtDetalle->execute([

                    'venta' =>
                        $ventaId,

                    'producto' =>
                        $producto['id'],

                    'cantidad' =>
                        $producto['cantidad'],

                    'precio' =>
                        $producto['precio']

                ]);


                $stmtStock->execute([

                    'cantidad' =>
                        $producto['cantidad'],

                    'producto' =>
                        $producto['id']

                ]);


                if (
                    $stmtStock->rowCount()
                    !== 1
                ) {

                    throw new \Exception(
                        "No fue posible actualizar el inventario."
                    );
                }
            }


            $this->db->commit();


            $this->db->exec(
                "SET @motivo_movimiento = NULL"
            );


            $this->registrarAuditoria(

                $ventaId,

                'COMMIT',

                "Venta #$ventaId procesada correctamente."

            );


            return true;


        } catch (Throwable $e) {


            if (
                $this->db->inTransaction()
            ) {

                $this->db->rollBack();
            }


            try {

                $this->db->exec(
                    "SET @motivo_movimiento = NULL"
                );

            } catch (Throwable $ignored) {

            }


            $this->registrarAuditoria(

                null,

                'ROLLBACK',

                $e->getMessage()

            );


            return $e->getMessage();
        }
    }
}