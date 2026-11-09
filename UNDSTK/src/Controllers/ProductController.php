<?php

namespace Controllers;

use Models\Product;
use Services\MongoProductSyncService;
use Throwable;

class ProductController
{
    private Product $model;

    public function __construct(Product $model)
    {
        $this->model = $model;
    }

    private function sendJson($data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data);
        exit;
    }

    public function index(): void
    {
        $this->sendJson(['status' => 'SUCCESS', 'data' => $this->model->getAll()]);
    }

    public function store(): void
    {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!is_array($data) || !isset($data['nombre'], $data['cantidad'], $data['precio'])) {
            $this->sendJson(['status' => 'ERROR', 'message' => 'Faltan datos obligatorios'], 400);
        }
        try {
            $id = $this->model->create($data['nombre'], $data['cantidad'], $data['precio']);
            if (!$id) {
                $this->sendJson(['status' => 'ERROR', 'message' => 'No se pudo crear el producto'], 500);
            }
            $sync = MongoProductSyncService::intentarSincronizarIds([(int) $id]);
            $this->sendJson([
                'status' => 'SUCCESS',
                'message' => 'Producto creado',
                'id' => $id,
                'mongoSync' => $sync['estado']
            ]);
        } catch (Throwable $e) {
            error_log('Crear producto: ' . $e->getMessage());
            $this->sendJson(['status' => 'ERROR', 'message' => 'Error al guardar el producto en MySQL'], 500);
        }
    }

    public function update(): void
    {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!is_array($data) || !isset($data['id'], $data['nombre'], $data['cantidad'], $data['precio'])) {
            $this->sendJson(['status' => 'ERROR', 'message' => 'Faltan datos para actualizar'], 400);
        }
        try {
            $ok = $this->model->update($data['id'], $data['nombre'], $data['cantidad'], $data['precio']);
            if (!$ok) {
                $this->sendJson(['status' => 'ERROR', 'message' => 'Producto no encontrado'], 404);
            }
            $sync = MongoProductSyncService::intentarSincronizarIds([(int) $data['id']]);
            $this->sendJson([
                'status' => 'SUCCESS',
                'message' => 'Producto actualizado',
                'mongoSync' => $sync['estado']
            ]);
        } catch (Throwable $e) {
            error_log('Actualizar producto: ' . $e->getMessage());
            $this->sendJson(['status' => 'ERROR', 'message' => 'Error al actualizar en MySQL'], 500);
        }
    }

    public function destroy(): void
    {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!is_array($data) || !isset($data['id'])) {
            $this->sendJson(['status' => 'ERROR', 'message' => 'Se requiere el ID del producto'], 400);
        }
        try {
            $id = (int) $data['id'];
            $anterior = $this->model->getById($id);
            if ($anterior === null || !$this->model->delete($id)) {
                $this->sendJson(['status' => 'ERROR', 'message' => 'Producto no encontrado o no eliminado'], 404);
            }
            $sync = MongoProductSyncService::intentarEliminar($id, (string) $anterior['nombre']);
            $this->sendJson([
                'status' => 'SUCCESS',
                'message' => 'Producto eliminado correctamente',
                'mongoSync' => $sync['estado']
            ]);
        } catch (Throwable $e) {
            error_log('Eliminar producto: ' . $e->getMessage());
            $this->sendJson(['status' => 'ERROR', 'message' => 'Error al eliminar en MySQL'], 500);
        }
    }
}
