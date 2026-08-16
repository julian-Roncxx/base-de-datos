<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "understock";
$port = "3306";

$conn = new mysqli($servername, $username, $password, $dbname,  port:$port);

if ($conn->connect_error) {
  die("Error de conexión: " . $conn->connect_error);
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $nombre = $_POST["nombre"] ?? '';
  $correoRegistro = $_POST["correoRegistro"] ?? '';
  $claveRegistro = $_POST["claveRegistro"] ?? '';
  $claveRegistro2 = $_POST["claveRegistro2"] ?? '';

  if (empty($nombre) || empty($correoRegistro) || empty($claveRegistro) || empty($claveRegistro2)) {
    echo "ERROR: Todos los campos son obligatorios.";
    exit;
  }

  if ($claveRegistro !== $claveRegistro2) {
    echo "ERROR: Las contraseñas no coinciden.";
    exit;
  }

  // Encriptar la contraseña
  $claveHash = password_hash($claveRegistro, PASSWORD_BCRYPT);

  $stmt = $conn->prepare("INSERT INTO formregistro (nombre, correoRegistro, claveRegistro) VALUES (?, ?, ?)");
  $stmt->bind_param("sss", $nombre, $correoRegistro, $claveHash);

  if ($stmt->execute()) {
    echo "SUCCESS: Usuario registrado correctamente.";
  } else {
    echo "ERROR: " . $stmt->error;
  }

  $stmt->close();
}

$conn->close();
?>
