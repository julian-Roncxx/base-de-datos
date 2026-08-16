<?php
namespace Core;

class Response {
    public static function json($data, $status = 200) {
        // Atributo de calidad: Interoperabilidad (JSON estándar)
        header('Content-Type: application/json');
        http_response_code($status);
        echo json_encode($data);
        exit;
    }
}