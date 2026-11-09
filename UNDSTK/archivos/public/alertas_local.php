<?php
/**
 * Endpoint de demostración SOLO para localhost.
 * No subir a producción sin autenticación real.
 */

$ip = $_SERVER['REMOTE_ADDR'] ?? '';
if (!in_array($ip, ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'ERROR', 'message' => 'Acceso permitido solo desde localhost.']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function responder_alertas(array $data, int $http = 200): void
{
    http_response_code($http);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Se requiere JSON y mismo origen en las operaciones que modifican datos.
$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($metodo !== 'GET') {
    $tipo = strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0]));
    if ($tipo !== 'application/json') {
        responder_alertas(['status'=>'ERROR','message'=>'Se requiere Content-Type: application/json.'], 415);
    }
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin !== '') {
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $origenEsperado = 'http://' . $host;
        if ($origin !== $origenEsperado) {
            responder_alertas(['status'=>'ERROR','message'=>'Origen no permitido.'], 403);
        }
    }
}

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/src/config/MongoDatabase.php';
require_once dirname(__DIR__) . '/src/Models/InventoryAlert.php';
require_once dirname(__DIR__) . '/src/Services/InventoryAlertService.php';

try {
    if ($metodo === 'GET') {
        responder_alertas(['status' => 'SUCCESS', 'data' => (new \Models\InventoryAlert())->listar()]);
    }

    if ($metodo === 'POST') {
        responder_alertas([
            'status' => 'SUCCESS',
            'data' => (new \Services\InventoryAlertService())->sincronizar()
        ]);
    }

    if ($metodo === 'PATCH') {
        $json = json_decode(file_get_contents('php://input'), true);
        $id = $json['id'] ?? null;
        if (!is_string($id) || !preg_match('/^[a-f0-9]{24}$/i', $id)) {
            responder_alertas(['status' => 'ERROR', 'message' => 'Identificador no válido.'], 400);
        }

        $ok = (new \Models\InventoryAlert())->atender($id);
        if (!$ok) {
            responder_alertas(['status' => 'ERROR', 'message' => 'La alerta ya fue atendida o no existe.'], 404);
        }
        responder_alertas(['status' => 'SUCCESS', 'message' => 'Alerta atendida correctamente.']);
    }

    responder_alertas(['status'=>'ERROR', 'message'=>'Método no permitido.'], 405);
} catch (Throwable $e) {
    error_log('[UNDERSTOCK / MongoDB] ' . $e->getMessage());
    responder_alertas(['status'=>'ERROR','message'=>'No se pudo completar la operación con MongoDB.'], 503);
}
