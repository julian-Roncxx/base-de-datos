<?php

namespace Services;

use Config\MongoDatabase;
use MongoDB\BSON\UTCDateTime;
use Throwable;

class InventoryAlertService
{
    public function sincronizar(): array
    {
        $productos = MongoDatabase::productos();
        $alertas = MongoDatabase::alertas();
        $alertas->createIndex(['productoMongoId' => 1], ['unique' => true]);

        $generadas = 0;
        $resueltas = 0;
        $revisados = 0;
        $omitidos = 0;
        $idsVigentes = [];

        foreach ($productos->find([]) as $producto) {
            $revisados++;
            $cantidad = $producto['cantidad'] ?? null;
            $minimo = $producto['stockMinimo'] ?? $producto['stock_minimo'] ?? null;

            if (!is_numeric($cantidad) || !is_numeric($minimo)) {
                $omitidos++;
                continue;
            }

            $id = (string) $producto['_id'];
            $idsVigentes[] = $id;
            $nombre = (string) ($producto['nombre'] ?? 'Sin nombre');
            $stock = (int) $cantidad;
            $stockMinimo = (int) $minimo;
            $anterior = $alertas->findOne(['productoMongoId' => $id]);
            $fecha = new UTCDateTime();

            if ($stock <= $stockMinimo) {
                $prioridad = $stock <= 0 ? 'critica'
                    : ($stock <= $stockMinimo / 2 ? 'alta' : 'media');
                $datos = [
                    'productoMongoId' => $id,
                    'producto' => $nombre,
                    'stockActual' => $stock,
                    'stockMinimo' => $stockMinimo,
                    'prioridad' => $prioridad,
                    'cantidadSugerida' => max(1, $stockMinimo * 2 - $stock),
                    'actualizadaEn' => $fecha
                ];

                if ($anterior === null || ($anterior['estado'] ?? '') === 'resuelta') {
                    $datos['estado'] = 'pendiente';
                    $alertas->updateOne(
                        ['productoMongoId' => $id],
                        [
                            '$set' => $datos,
                            '$setOnInsert' => ['creadaEn' => $fecha],
                            '$push' => [
                                'historial' => ['accion' => 'alerta_generada', 'fecha' => $fecha]
                            ]
                        ],
                        ['upsert' => true]
                    );
                    $generadas++;
                } else {
                    // Una alerta atendida no vuelve a "pendiente" si no ha habido reposicion.
                    $alertas->updateOne(['productoMongoId' => $id], ['$set' => $datos]);
                }
            } elseif (
                $anterior !== null &&
                in_array(($anterior['estado'] ?? ''), ['pendiente', 'atendida'], true)
            ) {
                $alertas->updateOne(
                    ['productoMongoId' => $id],
                    [
                        '$set' => [
                            'estado' => 'resuelta',
                            'stockActual' => $stock,
                            'actualizadaEn' => $fecha
                        ],
                        '$push' => [
                            'historial' => ['accion' => 'stock_recuperado', 'fecha' => $fecha]
                        ]
                    ]
                );
                $resueltas++;
            }
        }

        // Cierra alertas de productos eliminados de la colección MongoDB.
        $cerradas = $alertas->updateMany(
            [
                'productoMongoId' => ['$exists' => true, '$nin' => $idsVigentes],
                'estado' => ['$in' => ['pendiente', 'atendida']]
            ],
            ['$set' => [
                'estado' => 'resuelta',
                'motivoCierre' => 'Producto ausente del inventario MongoDB',
                'actualizadaEn' => new UTCDateTime()
            ]]
        );
        $resueltas += $cerradas->getModifiedCount();

        return [
            'productosRevisados' => $revisados,
            'productosOmitidos' => $omitidos,
            'generadas' => $generadas,
            'resueltas' => $resueltas
        ];
    }

    public static function intentarSincronizar(): void
    {
        try {
            (new self())->sincronizar();
        } catch (Throwable $e) {
            error_log('Error sincronizando alertas MongoDB: ' . $e->getMessage());
        }
    }
}
