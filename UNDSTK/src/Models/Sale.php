<?php
namespace Models;

use Config\Database;
use PDO;
use Exception;

class Sale {
    private $db;

    public function __construct() {
        // CORREGIDO: Usamos el Singleton correcto y lo guardamos en $this->db
        $this->db = Database::getInstance()->getConnection();
    }

    public function createSale($total, $items) {
        try {
            // Iniciamos la transacción
            $this->db->beginTransaction();

            // 1. Insertamos el total en la tabla ventas
            $stmt = $this->db->prepare("INSERT INTO ventas (total) VALUES (:total)");
            $stmt->execute(['total' => $total]);
            $ventaId = $this->db->lastInsertId(); // Obtenemos el ID de esta nueva venta

            // 2. Preparamos las consultas para los detalles y para actualizar el stock
            $stmtDetalle = $this->db->prepare("INSERT INTO detalles_venta (venta_id, producto_id, cantidad, precio_unitario) VALUES (:vid, :pid, :cant, :precio)");
            $stmtUpdateStock = $this->db->prepare("UPDATE productos SET cantidad = cantidad - :cant WHERE id = :pid AND cantidad >= :cant");

            // 3. Recorremos el carrito
            foreach ($items as $item) {
                // Guardar en detalles_venta
                $stmtDetalle->execute([
                    'vid' => $ventaId,
                    'pid' => $item['id'],
                    'cant' => $item['cantidad'],
                    'precio' => $item['precio']
                ]);

                // Restar del inventario
                $stmtUpdateStock->execute([
                    'cant' => $item['cantidad'],
                    'pid' => $item['id']
                ]);

                // Si rowCount es 0, significa que no había stock suficiente
                if ($stmtUpdateStock->rowCount() == 0) {
                    throw new Exception("Stock insuficiente para el producto: " . $item['nombre']);
                }
            }

            // Si todo salió bien, guardamos los cambios definitivamente
            $this->db->commit();
            return true;

        } catch (Exception $e) {
            // Si hubo algún error (ej. falta de stock), revertimos todo
            $this->db->rollBack();
            return $e->getMessage(); // Retornamos el mensaje de error
        }
    }
}