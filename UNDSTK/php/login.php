<?php

require_once "conexion.php";

header("Content-Type: application/json");

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $correo = trim($_POST["correo"] ?? "");
    $clave = trim($_POST["clave"] ?? "");

    if (empty($correo) || empty($clave)) {
        echo json_encode([
            "status" => "ERROR",
            "message" => "Todos los campos son obligatorios."
        ]);
        exit;
    }

    $sql = "SELECT nombre, correoRegistro, claveRegistro
            FROM formregistro
            WHERE correoRegistro = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $correo);
    $stmt->execute();

    $resultado = $stmt->get_result();

    if ($resultado->num_rows === 1) {

        $usuario = $resultado->fetch_assoc();

        if (password_verify($clave, $usuario["claveRegistro"])) {

            echo json_encode([
                "status" => "SUCCESS",
                "nombre" => $usuario["nombre"],
                "correo" => $usuario["correoRegistro"]
            ]);

        } else {

            echo json_encode([
                "status" => "ERROR",
                "message" => "Contraseña incorrecta."
            ]);
        }

    } else {

        echo json_encode([
            "status" => "ERROR",
            "message" => "No existe una cuenta con ese correo."
        ]);
    }

    $stmt->close();
}

$conn->close();
?>