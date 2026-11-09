<?php

namespace Models;

use Config\MongoDatabase;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

class InventoryAlert
{
    private $collection;

    public function __construct()
    {
        $this->collection = MongoDatabase::alertas();
    }

    public function listar(): array
    {
        $cursor = $this->collection->find(
            ['productoMongoId' => ['$exists' => true]],
            ['sort' => ['actualizadaEn' => -1]]
        );

        $resultados = [];
        foreach ($cursor as $doc) {
            $resultados[] = [
                'id' => (string) $doc['_id'],
                'productoMongoId' => (string) ($doc['productoMongoId'] ?? ''),
                'producto' => (string) ($doc['producto'] ?? 'Producto sin nombre'),
                'stockActual' => (int) ($doc['stockActual'] ?? 0),
                'stockMinimo' => (int) ($doc['stockMinimo'] ?? 0),
                'prioridad' => (string) ($doc['prioridad'] ?? 'media'),
                'cantidadSugerida' => (int) ($doc['cantidadSugerida'] ?? 0),
                'estado' => (string) ($doc['estado'] ?? 'pendiente')
            ];
        }
        return $resultados;
    }

    public function atender(string $id): bool
    {
        if (!preg_match('/^[a-f0-9]{24}$/i', $id)) {
            return false;
        }

        $fecha = new UTCDateTime();
        $resultado = $this->collection->updateOne(
            ['_id' => new ObjectId($id), 'estado' => 'pendiente'],
            [
                '$set' => ['estado' => 'atendida', 'actualizadaEn' => $fecha],
                '$push' => [
                    'historial' => ['accion' => 'alerta_atendida', 'fecha' => $fecha]
                ]
            ]
        );
        return $resultado->getModifiedCount() === 1;
    }
}
