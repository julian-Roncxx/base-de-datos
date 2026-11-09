<?php

namespace Controllers;

use Models\Product;
use Services\InventoryAlertService;

class ProductController
{
    private $model;

    public function __construct(Product $model)
    {
        $this->model = $model;
    }

    // Enviar respuestas JSON
    private function sendJson($data, $statusCode = 200)
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');

        echo json_encode($data);
        exit();
    }

    // Obtener todos los productos
    public function index()
    {
        $productos = $this->model->getAll();

        $this->sendJson([
            'status' => 'SUCCESS',
            'data' => $productos
        ]);
    }

    // Crear nuevo producto
    public function store()
    {
        $data = json_decode(
            file_get_contents("php://input"),
            true
        );

        if (
            !isset($data['nombre']) ||
            !isset($data['cantidad']) ||
            !isset($data['precio'])
        ) {
            $this->sendJson([
                'status' => 'ERROR',
                'message' => 'Faltan datos obligatorios'
            ], 400);
        }

        if ($this->model->create(
            $data['nombre'],
            $data['cantidad'],
            $data['precio']
        )) {

            // NUEVO: sincronizar alertas con MongoDB
            InventoryAlertService::intentarSincronizar();

            $this->sendJson([
                'status' => 'SUCCESS',
                'message' => 'Producto creado'
            ]);

        } else {
            $this->sendJson([
                'status' => 'ERROR',
                'message' => 'Error al guardar en BD'
            ], 500);
        }
    }

    // Actualizar producto existente
    public function update()
    {
        $data = json_decode(
            file_get_contents("php://input"),
            true
        );

        if (
            !isset($data['id']) ||
            !isset($data['nombre']) ||
            !isset($data['cantidad']) ||
            !isset($data['precio'])
        ) {
            $this->sendJson([
                'status' => 'ERROR',
                'message' => 'Faltan datos para actualizar'
            ], 400);
        }

        if ($this->model->update(
            $data['id'],
            $data['nombre'],
            $data['cantidad'],
            $data['precio']
        )) {

            // NUEVO: actualizar alertas en MongoDB
            InventoryAlertService::intentarSincronizar();

            $this->sendJson([
                'status' => 'SUCCESS',
                'message' => 'Producto actualizado'
            ]);

        } else {
            $this->sendJson([
                'status' => 'ERROR',
                'message' => 'Error al actualizar en BD'
            ], 500);
        }
    }

    // Eliminar producto
    public function destroy()
    {
        $data = json_decode(
            file_get_contents("php://input"),
            true
        );

        if (!isset($data['id'])) {
            $this->sendJson([
                'status' => 'ERROR',
                'message' => 'Se requiere el ID del producto'
            ], 400);
        }

        if ($this->model->delete($data['id'])) {

            // NUEVO: sincronizar MongoDB después de eliminar
            InventoryAlertService::intentarSincronizar();

            $this->sendJson([
                'status' => 'SUCCESS',
                'message' => 'Producto eliminado correctamente'
            ]);

        } else {
            $this->sendJson([
                'status' => 'ERROR',
                'message' => 'Error al eliminar en BD'
            ], 500);
        }
    }
}
