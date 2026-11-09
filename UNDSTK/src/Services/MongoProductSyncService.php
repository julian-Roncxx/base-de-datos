<?php

namespace Services;

use Config\Database;
use Config\MongoDatabase;
use MongoDB\BSON\Regex;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Replica las existencias oficiales de MySQL hacia understock.productos.
 * No modifica existencias en MySQL y no presupone que _id tenga el mismo valor.
 */
class MongoProductSyncService
{
    private PDO $sql;
    private $productos;

    public function __construct()
    {
        $this->sql = Database::getInstance()->getConnection();
        $this->productos = MongoDatabase::productos();
    }

    private function obtenerProducto(int $id): ?array
    {
        $stmt = $this->sql->prepare(
            'SELECT idProducto, nombre, precio, cantidad, stockMinimo, idCategoria
             FROM productos WHERE idProducto = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    private function mismoNombre(string $a, string $b): bool
    {
        $a = trim($a);
        $b = trim($b);
        return mb_strtolower($a, 'UTF-8') === mb_strtolower($b, 'UTF-8');
    }

    /**
     * Busca por mapeo explícito; luego por ID heredado; finalmente por nombre único.
     * Rechaza ambigüedades, en lugar de sobrescribir o duplicar datos.
     */
    private function buscarDocumento(int $id, string $nombre)
    {
        $mapeado = $this->productos->findOne(['idProductoSQL' => $id]);
        if ($mapeado !== null) {
            return $mapeado;
        }

        $colisionId = null;
        foreach ([$id, (string) $id] as $idLegado) {
            $documento = $this->productos->findOne(['_id' => $idLegado]);
            if ($documento !== null) {
                $idAsignado = $documento['idProductoSQL'] ?? null;
                if ($idAsignado !== null && (int) $idAsignado !== $id) {
                    throw new RuntimeException("Conflicto: el documento _id={$idLegado} está asignado a otro producto SQL.");
                }
                if ($this->mismoNombre((string) ($documento['nombre'] ?? ''), $nombre)) {
                    return $documento;
                }
                // Un _id igual no es garantía de identidad: no sobrescribir.
                $colisionId = $idLegado;
                break;
            }
        }

        $regex = new Regex('^' . preg_quote(trim($nombre), '/') . '$', 'i');
        $coincidencias = iterator_to_array(
            $this->productos->find([
    'nombre' => $regex,
    'idProductoSQL' => ['$exists' => false]
], ['limit' => 3]),
            false
        );
        if (count($coincidencias) > 1) {
            throw new RuntimeException("El nombre '{$nombre}' no identifica un documento MongoDB único.");
        }
        if (count($coincidencias) === 1) {
            $documento = $coincidencias[0];
            $asignado = $documento['idProductoSQL'] ?? null;
            if ($asignado !== null && (int) $asignado !== $id) {
                throw new RuntimeException("El documento '{$nombre}' ya pertenece a otro producto SQL.");
            }
            return $documento;
        }
        if ($colisionId !== null) {
            throw new RuntimeException(
                "Conflicto de IDs: MongoDB _id={$colisionId} y MySQL #{$id} tienen nombres distintos. " .
                'Corrige el mapeo; no se modificó ni duplicó ese documento.'
            );
        }
        return null;
    }

    public function sincronizarProducto(int $id): array
    {
        if ($id <= 0) {
            throw new RuntimeException('ID de producto inválido.');
        }
        $row = $this->obtenerProducto($id);
        if ($row === null) {
            throw new RuntimeException("El producto SQL #{$id} no existe.");
        }

        $datos = [
            'idProductoSQL' => $id,
            'nombre' => (string) $row['nombre'],
            'precio' => (float) $row['precio'],
            'cantidad' => (int) $row['cantidad'],
            'stockMinimo' => (int) $row['stockMinimo'],
            'idCategoria' => (int) $row['idCategoria'],
            'origenDatos' => 'MySQL'
        ];

        $actual = $this->buscarDocumento($id, (string) $row['nombre']);
        if ($actual !== null) {
            $this->productos->updateOne(['_id' => $actual['_id']], ['$set' => $datos]);
            return ['id' => $id, 'accion' => 'actualizado'];
        }

        // Para altas nuevas dejamos que MongoDB cree ObjectId: no colisiona
        // con _id antiguos de una migración y queda mapeado por idProductoSQL.
        $this->productos->insertOne($datos);
        return ['id' => $id, 'accion' => 'creado'];
    }

    public function eliminarProducto(int $id, string $nombre): array
    {
        $actual = $this->buscarDocumento($id, $nombre);
        if ($actual === null) {
            return ['id' => $id, 'accion' => 'no_encontrado'];
        }
        $this->productos->deleteOne(['_id' => $actual['_id']]);
        return ['id' => $id, 'accion' => 'eliminado'];
    }

    public function sincronizarIds(array $ids): array
    {
        $correctos = [];
        $errores = [];
        foreach (array_unique(array_map('intval', $ids)) as $id) {
            try {
                $correctos[] = $this->sincronizarProducto($id);
            } catch (Throwable $e) {
                $errores[] = ['id' => $id, 'mensaje' => $e->getMessage()];
                error_log("Sincronización MySQL->MongoDB producto {$id}: " . $e->getMessage());
            }
        }
        try {
            (new InventoryAlertService())->sincronizar();
        } catch (Throwable $e) {
            $errores[] = ['id' => null, 'mensaje' => 'No se pudieron recalcular las alertas: ' . $e->getMessage()];
            error_log('Recalcular alertas MongoDB: ' . $e->getMessage());
        }
        return [
            'estado' => count($errores) === 0 ? 'OK' : 'PENDIENTE',
            'sincronizados' => count($correctos),
            'detalles' => $correctos,
            'errores' => $errores
        ];
    }

    public function sincronizarTodo(): array
    {
        $ids = $this->sql->query('SELECT idProducto FROM productos ORDER BY idProducto')
            ->fetchAll(PDO::FETCH_COLUMN);
        return $this->sincronizarIds($ids);
    }

    /** Nunca convierte un COMMIT exitoso en una venta fallida. */
    public static function intentarSincronizarIds(array $ids): array
    {
        try {
            return (new self())->sincronizarIds($ids);
        } catch (Throwable $e) {
            error_log('Sincronización MySQL->MongoDB pendiente: ' . $e->getMessage());
            return ['estado' => 'PENDIENTE', 'sincronizados' => 0, 'errores' => [
                ['id' => null, 'mensaje' => $e->getMessage()]
            ]];
        }
    }

    public static function intentarEliminar(int $id, string $nombre): array
    {
        try {
            $servicio = new self();
            $servicio->eliminarProducto($id, $nombre);
            (new InventoryAlertService())->sincronizar();
            return ['estado' => 'OK'];
        } catch (Throwable $e) {
            error_log("Eliminación MySQL->MongoDB pendiente #{$id}: " . $e->getMessage());
            return ['estado' => 'PENDIENTE'];
        }
    }
}
