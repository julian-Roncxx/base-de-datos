<?php
namespace Controllers;

use Models\Provider;
use PDOException;

class ProviderController {
    private $providerModel;

    // Recibe el modelo desde el index.php igual que ProductController
    public function __construct(Provider $providerModel) {
        $this->providerModel = $providerModel;
    }

    // Cambiado de obtenerProveedores() a index() para que coincida con index.php
    public function index() {
        try {
            $proveedores = $this->providerModel->getAll();
            
            echo json_encode([
                "status" => "SUCCESS",
                "data" => $proveedores
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(["status" => "ERROR", "message" => $e->getMessage()]);
        }
    }

    // Cambiado de guardarProveedor() a store() para que coincida con index.php
    public function store() {
        $input = json_decode(file_get_contents("php://input"), true);

        if (empty($input['nombre_empresa'])) {
            http_response_code(400);
            echo json_encode(["status" => "ERROR", "message" => "El nombre de la empresa es obligatorio."]);
            return;
        }

        try {
            $exito = $this->providerModel->create(
                $input['nombre_empresa'],
                $input['contacto'] ?? null,
                $input['telefono'] ?? null
            );

            if ($exito) {
                echo json_encode([
                    "status" => "SUCCESS",
                    "message" => "Proveedor registrado correctamente"
                ]);
            } else {
                throw new PDOException("No se pudo insertar el registro.");
            }
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(["status" => "ERROR", "message" => $e->getMessage()]);
        }
    }
}