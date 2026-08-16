<?php
namespace Controllers;

use Models\Finance;
use PDOException;

class FinanceController {
    private $financeModel;

    public function __construct(Finance $financeModel) {
        $this->financeModel = $financeModel;
    }

    public function index() {
        try {
            $summary = $this->financeModel->getSummary();

            // Si no hay ventas aún, forzamos a que devuelva 0 en lugar de valores nulos
            $data = [
                "total_ventas" => $summary['total_ventas'] ?? 0,
                "cantidad_ventas" => $summary['cantidad_ventas'] ?? 0
            ];
            
            echo json_encode([
                "status" => "SUCCESS",
                "data" => $data
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(["status" => "ERROR", "message" => $e->getMessage()]);
        }
    }
}