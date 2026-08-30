<?php

require_once "conexion.php";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $nombre = trim($_POST["nombre"] ?? "");
    $correoRegistro = trim($_POST["correoRegistro"] ?? "");
    $claveRegistro = $_POST["claveRegistro"] ?? "";
    $claveRegistro2 = $_POST["claveRegistro2"] ?? "";

    if (
        empty($nombre) ||
        empty($correoRegistro) ||
        empty($claveRegistro) ||
        empty($claveRegistro2)
    ) {
        echo "ERROR: Todos los campos son obligatorios.";
        exit;
    }

    if ($claveRegistro !== $claveRegistro2) {
        echo "ERROR: Las contraseñas no coinciden.";
        exit;
    }

    if (!filter_var($correoRegistro, FILTER_VALIDATE_EMAIL)) {
        echo "ERROR: El correo no es válido.";
        exit;
    }

    $claveHash = password_hash(
        $claveRegistro,
        PASSWORD_BCRYPT
    );

    $sql = "INSERT INTO formregistro
            (nombre, correoRegistro, claveRegistro)
            VALUES (?, ?, ?)";

    $stmt = $conn->prepare($sql);

    $stmt->bind_param(
        "sss",
        $nombre,
        $correoRegistro,
        $claveHash
    );

    if ($stmt->execute()) {
        echo "SUCCESS: Usuario registrado correctamente.";
    } else {
        echo "ERROR: No se pudo registrar el usuario.";
    }

    $stmt->close();
}

$conn->close();
?>