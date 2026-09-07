<?php

require_once "conexion.php";

$mensaje = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $nombre = trim($_POST["nombre"] ?? "");
    $precio = $_POST["precio"] ?? "";
    $cantidad = $_POST["cantidad"] ?? "";
    $stockMinimo = $_POST["stockMinimo"] ?? "";

    if (
        empty($nombre) ||
        $precio === "" ||
        $cantidad === "" ||
        $stockMinimo === ""
    ) {

        $mensaje = "Todos los campos son obligatorios.";

    } elseif (
        $precio < 0 ||
        $cantidad < 0 ||
        $stockMinimo < 0
    ) {

        $mensaje = "Los valores no pueden ser negativos.";

    } else {

        $sql = "INSERT INTO productos
                (nombre, precio, cantidad, stockMinimo)
                VALUES (?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);

        $stmt->bind_param(
            "sdii",
            $nombre,
            $precio,
            $cantidad,
            $stockMinimo
        );

        if ($stmt->execute()) {
            $mensaje = "Producto registrado correctamente.";
        } else {
            $mensaje = "Error al registrar el producto.";
        }

        $stmt->close();
    }
}

$conn->close();

?>
<!DOCTYPE html>

<html lang="es">

<head>

    <meta charset="UTF-8">

    <title>Registrar producto</title>

</head>

<body>

<h2>Registrar producto</h2>

<?php

if (!empty($mensaje)) {
    echo "<p>" . $mensaje . "</p>";
}

?>

<form method="POST">

    <label>Nombre:</label>

    <br>

    <input
        type="text"
        name="nombre"
        required
    >

    <br><br>

    <label>Precio:</label>

    <br>

    <input
        type="number"
        name="precio"
        min="0"
        step="0.01"
        required
    >

    <br><br>

    <label>Cantidad:</label>

    <br>

    <input
        type="number"
        name="cantidad"
        min="0"
        required
    >

    <br><br>

    <label>Stock mínimo:</label>

    <br>

    <input
        type="number"
        name="stockMinimo"
        min="0"
        required
    >

    <br><br>

    <button type="submit">
        Registrar producto
    </button>

</form>

</body>

</html>
