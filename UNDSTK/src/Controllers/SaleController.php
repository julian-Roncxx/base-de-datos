<?php
namespace Controllers;

use Models\Sale;

class SaleController {
    private function sendJson($data, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit();
    }

    public function store() {
        $data = json_decode(file_get_contents("php://input"), true);

        // Validar que el carrito no venga vacío
        if (!isset($data['total']) || !isset($data['items']) || empty($data['items'])) {
            $this->sendJson(['status' => 'ERROR', 'message' => 'Datos de venta incompletos'], 400);
        }

        $saleModel = new Sale();
        $resultado = $saleModel->createSale($data['total'], $data['items']);

        if ($resultado === true) {
            $this->sendJson(['status' => 'SUCCESS', 'message' => 'Venta procesada con éxito']);
        } else {
            // Si $resultado no es true, contiene el mensaje de error del Catch
            $this->sendJson(['status' => 'ERROR', 'message' => $resultado], 500);
        }
    }
}