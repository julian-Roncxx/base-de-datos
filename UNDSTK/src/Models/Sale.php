<?php

namespace Models;

use Config\Database;
use PDO;
use Services\MongoProductSyncService;
use Throwable;
use RuntimeException;

class Sale
{
    private PDO $db;
    private string $mongoSyncStatus = 'PENDIENTE';

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function getMongoSyncStatus(): string
    {
        return $this->mongoSyncStatus;
    }

    private function registrarAuditoria(?int $ventaId, string $estado, string $mensaje): void
    {
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO auditoria_transacciones (ventaId, operacion, estado, mensaje)
                 VALUES (:ventaId, 'VENTA', :estado, :mensaje)"
            );
            $stmt->execute([
                'ventaId' => $ventaId,
                'estado' => $estado,
                'mensaje' => $mensaje
            ]);
        } catch (Throwable $e) {
            error_log('Auditoría de venta: ' . $e->getMessage());
        }
    }

    /**
     * Conserva el contrato existente: true si MySQL confirmó la venta;
     * string con el error si se revirtió. MongoDB no provoca un rollback falso.
     */
    public function createSale($totalCliente, $items)
    {
        $ventaId = null;
        $pedidos = [];
        $this->mongoSyncStatus = 'PENDIENTE';

        try {
            if (!is_array($items) || count($items) === 0) {
                throw new RuntimeException('La venta no contiene productos.');
            }
            // Agrupar IDs repetidos en el carrito para no vender más stock del disponible.
            foreach ($items as $item) {
                $id = (int) ($item['id'] ?? 0);
                $cantidad = (int) ($item['cantidad'] ?? 0);
                if ($id <= 0 || $cantidad <= 0) {
                    throw new RuntimeException('Producto o cantidad inválida.');
                }
                $pedidos[$id] = ($pedidos[$id] ?? 0) + $cantidad;
            }

            $this->db->beginTransaction();
            $this->db->exec("SET @motivo_movimiento = 'Venta POS'");

            $consultar = $this->db->prepare(
                'SELECT idProducto, nombre, precio, cantidad
                 FROM productos WHERE idProducto = :id FOR UPDATE'
            );
            $productosVenta = [];
            $totalCalculado = 0.0;

            foreach ($pedidos as $id => $cantidad) {
                $consultar->execute(['id' => $id]);
                $producto = $consultar->fetch(PDO::FETCH_ASSOC);
                if (!$producto) {
                    throw new RuntimeException("Producto #{$id} no encontrado.");
                }
                if ((int) $producto['cantidad'] < $cantidad) {
                    throw new RuntimeException('Stock insuficiente para ' . $producto['nombre']);
                }
                $precio = (float) $producto['precio'];
                $totalCalculado += $precio * $cantidad;
                $productosVenta[] = [
                    'id' => $id,
                    'cantidad' => $cantidad,
                    'precio' => $precio
                ];
            }

            $totalCalculado = round($totalCalculado, 2);
            if (!is_numeric($totalCliente) || abs((float) $totalCliente - $totalCalculado) > 0.01) {
                throw new RuntimeException('El total enviado no coincide con el calculado.');
            }

            $stmtVenta = $this->db->prepare('INSERT INTO ventas (total) VALUES (:total)');
            $stmtVenta->execute(['total' => $totalCalculado]);
            $ventaId = (int) $this->db->lastInsertId();

            $detalle = $this->db->prepare(
                'INSERT INTO detalles_venta (venta_id, producto_id, cantidad, precio_unitario)
                 VALUES (:venta, :producto, :cantidad, :precio)'
            );
            $descontar = $this->db->prepare(
                'UPDATE productos SET cantidad = cantidad - :cantidad
                 WHERE idProducto = :producto AND cantidad >= :cantidad_minima'
            );

            foreach ($productosVenta as $producto) {
                $detalle->execute([
                    'venta' => $ventaId,
                    'producto' => $producto['id'],
                    'cantidad' => $producto['cantidad'],
                    'precio' => $producto['precio']
                ]);
                $descontar->execute([
                    'cantidad' => $producto['cantidad'],
                    'producto' => $producto['id'],
                    'cantidad_minima' => $producto['cantidad']
                ]);
                if ($descontar->rowCount() !== 1) {
                    throw new RuntimeException('No fue posible descontar el inventario.');
                }
            }

            $this->db->commit();
            try {
                $this->db->exec('SET @motivo_movimiento = NULL');
            } catch (Throwable $e) {
                error_log('Limpiar motivo movimiento: ' . $e->getMessage());
            }

            $this->registrarAuditoria($ventaId, 'COMMIT', "Venta #{$ventaId} procesada correctamente.");

            // Se ejecuta DESPUÉS del COMMIT. Si Mongo falla, la venta sigue siendo válida.
            $sync = MongoProductSyncService::intentarSincronizarIds(array_keys($pedidos));
            $this->mongoSyncStatus = $sync['estado'];
            return true;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            try {
                $this->db->exec('SET @motivo_movimiento = NULL');
            } catch (Throwable $ignored) {
            }
            $this->registrarAuditoria(null, 'ROLLBACK', $e->getMessage());
            return $e->getMessage();
        }
    }
}
