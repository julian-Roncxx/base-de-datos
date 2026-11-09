
<?php

namespace Services;

use Config\Database;
use Config\MongoDatabase;
use MongoDB\BSON\UTCDateTime;
use PDO;
use Throwable;

class InventoryAlertService
{
    public function sincronizar()
    {
        $mysql = Database::getInstance()->getConnection();
        $mongo = MongoDatabase::alertas();

        $mongo->createIndex(
            ['productoIdSQL' => 1],
            ['unique' => true]
        );

        $productos = $mysql->query("
            SELECT id, nombre, cantidad, stockMinimo
            FROM vw_inventario
        ")->fetchAll(PDO::FETCH_ASSOC);

        $generadas = 0;
        $resueltas = 0;
        $idsVigentes = [];

        foreach ($productos as $producto) {
            $id = (int) $producto['id'];
            $stock = (int) $producto['cantidad'];
            $minimo = (int) $producto['stockMinimo'];

            $idsVigentes[] = $id;

            $anterior = $mongo->findOne([
                'productoIdSQL' => $id
            ]);

            $fecha = new UTCDateTime();

            if ($stock <= $minimo) {
                $prioridad = $stock === 0
                    ? 'critica'
                    : ($stock <= $minimo / 2 ? 'alta' : 'media');

                $datos = [
                    'producto' => $producto['nombre'],
                    'stockActual' => $stock,
                    'stockMinimo' => $minimo,
                    'prioridad' => $prioridad,
                    'cantidadSugerida' => max(1, $minimo * 2 - $stock),
                    'actualizadaEn' => $fecha
                ];

                $estadoAnterior = $anterior['estado'] ?? null;

                if ($anterior === null || $estadoAnterior === 'resuelta') {
                    $datos['estado'] = 'pendiente';
                    $datos['creadaEn'] = $fecha;

                    $mongo->updateOne(
                        ['productoIdSQL' => $id],
                        [
                            '$set' => $datos,
                            '$push' => [
                                'historial' => [
                                    'accion' => 'alerta_generada',
                                    'fecha' => $fecha
                                ]
                            ]
                        ],
                        ['upsert' => true]
                    );

                    $generadas++;
                } else {
                    $mongo->updateOne(
                        ['productoIdSQL' => $id],
                        ['$set' => $datos]
                    );
                }
            } elseif (
                $anterior !== null &&
                in_array(
                    $anterior['estado'],
                    ['pendiente', 'atendida'],
                    true
                )
            ) {
                $mongo->updateOne(
                    ['productoIdSQL' => $id],
                    [
                        '$set' => [
                            'estado' => 'resuelta',
                            'stockActual' => $stock,
                            'actualizadaEn' => $fecha
                        ],
                        '$push' => [
                            'historial' => [
                                'accion' => 'stock_recuperado',
                                'fecha' => $fecha
                            ]
                        ]
                    ]
                );

                $resueltas++;
            }
        }

        // Resolver alertas de productos que ya no existen en SQL.
        $mongo->updateMany(
            [
                'productoIdSQL' => ['$nin' => $idsVigentes],
                'estado' => ['$in' => ['pendiente', 'atendida']]
            ],
            [
                '$set' => [
                    'estado' => 'resuelta',
                    'motivoCierre' => 'Producto eliminado del inventario',
                    'actualizadaEn' => new UTCDateTime()
                ]
            ]
        );

        return [
            'generadas' => $generadas,
            'resueltas' => $resueltas
        ];
    }

    public static function intentarSincronizar()
    {
        try {
            (new self())->sincronizar();
        } catch (Throwable $e) {
            // Nunca cancelar una venta ya confirmada en MySQL.
            error_log('Error sincronizando alertas MongoDB: ' . $e->getMessage());
        }
    }
}
