<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "understock";
$port = "3307";

$conn = new mysqli($servername, $username, $password, $dbname, port:$port);
  if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
  }

  if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $correo = trim($_POST["correo"] ?? '');
    $clave  = trim($_POST["clave"] ?? '');

    if (empty($correo) || empty($clave)) {
      echo "ERROR: Todos los campos son obligatorios.";
      exit;
    }

    $stmt = $conn->prepare("SELECT nombre, correoRegistro, claveRegistro FROM formregistro WHERE correoRegistro = ?");
    $stmt->bind_param("s", $correo);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows > 0) {
      $usuario = $resultado->fetch_assoc();

      if (password_verify($clave, $usuario['claveRegistro'])) {
        // ✅ Enviamos JSON en lugar de texto plano
        echo json_encode([
          "status" => "SUCCESS",
          "nombre" => $usuario['nombre'],
          "correo" => $usuario['correoRegistro']
        ]);
      } else {
        echo json_encode(["status" => "ERROR", "message" => "Contraseña incorrecta."]);
      }
    } else {
      echo json_encode(["status" => "ERROR", "message" => "No existe una cuenta con ese correo."]);
    }

    $stmt->close();
  }

$conn->close();
?>