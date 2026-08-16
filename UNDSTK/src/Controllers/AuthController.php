<?php
namespace Controllers;

use Core\Response;
use Models\User;

class AuthController {
    public function login() {
        $input = json_decode(file_get_contents('php://input'), true);

        if (!isset($input['correo']) || !isset($input['clave'])) {
            Response::json(['error' => 'Faltan credenciales'], 400);
        }

        $userModel = new User();
        $user = $userModel->findByEmail($input['correo']);

        // Nivel Dios: Validamos texto plano (legacy) O hash seguro (nuevos registros)
        if ($user && ($input['clave'] === $user['claveRegistro'] || password_verify($input['clave'], $user['claveRegistro']))) {
            // Login exitoso
            unset($user['claveRegistro']); // Ocultamos la clave antes de enviarla al frontend
            Response::json(['status' => 'SUCCESS', 'message' => 'Login exitoso', 'data' => $user], 200);
        } else {
            // Login fallido
            Response::json(['error' => 'Correo o contraseña incorrectos'], 401);
        }
    }
}