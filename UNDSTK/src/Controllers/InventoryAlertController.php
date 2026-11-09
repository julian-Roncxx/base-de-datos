
<?php

namespace Controllers;

use Models\InventoryAlert;
use Services\InventoryAlertService;
use Throwable;

class InventoryAlertController
{
    private function responder($datos, $codigo = 200)
    {
        http_response_code($codigo);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($datos);
        exit;
    }

    public function index()
    {
        try {
            $alertas = (new InventoryAlert())->listar();

            $this->responder([
                'status' => 'SUCCESS',
                'data' => $alertas
            ]);
        } catch (Throwable $e) {
            error_log($e->getMessage());

            $this->responder([
                'status' => 'ERROR',
                'message' => 'No fue posible consultar MongoDB.'
            ], 503);
        }
    }

    public function sincronizar()
    {
        try {
            $resultado = (new InventoryAlertService())->sincronizar();

            $this->responder([
                'status' => 'SUCCESS',
                'data' => $resultado
            ]);
        } catch (Throwable $e) {
            error_log($e->getMessage());

            $this->responder([
                'status' => 'ERROR',
                'message' => 'No fue posible sincronizar las alertas.'
            ], 503);
        }
    }

    public function atender()
    {
        $datos = json_decode(file_get_contents('php://input'), true);
        $id = $datos['id'] ?? '';

        if (!is_string($id) || $id === '') {
            $this->responder([
                'status' => 'ERROR',
                'message' => 'ID de alerta obligatorio.'
            ], 400);
        }

        try {
            $actualizada = (new InventoryAlert())->atender($id);

            if (!$actualizada) {
                $this->responder([
                    'status' => 'ERROR',
                    'message' => 'Alerta no encontrada o ya atendida.'
                ], 404);
            }

            $this->responder([
                'status' => 'SUCCESS',
                'message' => 'Alerta marcada como atendida.'
            ]);
        } catch (Throwable $e) {
            error_log($e->getMessage());

            $this->responder([
                'status' => 'ERROR',
                'message' => 'No fue posible actualizar MongoDB.'
            ], 503);
        }
    }
}
