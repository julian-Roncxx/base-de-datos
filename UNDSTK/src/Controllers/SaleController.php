<?php

namespace Controllers;

use Models\Sale;

class SaleController
{
    private Sale $model;

    public function __construct(Sale $model)
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

    public function store(): void
    {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!is_array($data) || !isset($data['total'], $data['items']) || !is_array($data['items']) || count($data['items']) === 0) {
            $this->sendJson([
                'status' => 'ERROR',
                'message' => 'Datos de venta incompletos'
            ], 400);
        }

        $resultado = $this->model->createSale($data['total'], $data['items']);
        if ($resultado === true) {
            $this->sendJson([
                'status' => 'SUCCESS',
                'message' => 'Venta procesada con COMMIT',
                'mongoSync' => $this->model->getMongoSyncStatus()
            ]);
        }

        $this->sendJson([
            'status' => 'ERROR',
            'message' => $resultado,
            'transaction' => 'ROLLBACK'
        ], 409);
    }
}
