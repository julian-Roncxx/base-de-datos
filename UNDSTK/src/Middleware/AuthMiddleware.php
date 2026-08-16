<?php
namespace Middleware;

class AuthMiddleware {
    public static function verify() {
        // Obtener todas las cabeceras de la petición HTTP
        $headers = apache_request_headers();
        
        // Buscamos la cabecera de Autorización (puede venir en mayúsculas o minúsculas)
        $token = null;
        if (isset($headers['Authorization'])) {
            $token = $headers['Authorization'];
        } elseif (isset($headers['authorization'])) {
            $token = $headers['authorization'];
        }

        // Si no hay token o está vacío, cortamos la comunicación de inmediato
        if (!$token || empty($token) || $token === 'null' || $token === 'undefined') {
            http_response_code(401); // Estado HTTP: No autorizado
            echo json_encode([
                "status" => "ERROR",
                "message" => "Acceso denegado. Token de seguridad inválido o ausente."
            ]);
            exit(); // Detiene por completo la ejecución de PHP
        }

        // Aquí puedes agregar validaciones extra si usas JWT (ej. verificar expiración o firma)
        return true; 
    }
}