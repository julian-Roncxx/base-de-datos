
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

    public function listar()
    {
        $cursor = $this->collection->find(
            [],
            ['sort' => ['actualizadaEn' => -1]]
        );

        $alertas = [];

        foreach ($cursor as $doc) {
            $alertas[] = [
                'id' => (string) $doc['_id'],
                'productoIdSQL' => (int) $doc['productoIdSQL'],
                'producto' => (string) $doc['producto'],
                'stockActual' => (int) $doc['stockActual'],
                'stockMinimo' => (int) $doc['stockMinimo'],
                'prioridad' => (string) $doc['prioridad'],
                'cantidadSugerida' => (int) $doc['cantidadSugerida'],
                'estado' => (string) $doc['estado']
            ];
        }

        return $alertas;
    }

    public function atender($id)
    {
        if (!preg_match('/^[a-f0-9]{24}$/i', $id)) {
            return false;
        }

        $fecha = new UTCDateTime();

        $resultado = $this->collection->updateOne(
            [
                '_id' => new ObjectId($id),
                'estado' => 'pendiente'
            ],
            [
                '$set' => [
                    'estado' => 'atendida',
                    'actualizadaEn' => $fecha
                ],
                '$push' => [
                    'historial' => [
                        'accion' => 'alerta_atendida',
                        'fecha' => $fecha
                    ]
                ]
            ]
        );

        return $resultado->getModifiedCount() === 1;
    }
}
