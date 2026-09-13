<?php
require_once "conexion.php";

$mensaje = "";
$producto = null;

// ─── PASO 1: Si llega POST, actualizar ───
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $idProducto  = intval($_POST["idProducto"] ?? 0);
    $nombre      = trim($_POST["nombre"] ?? "");
    $precio      = $_POST["precio"] ?? "";
    $cantidad    = $_POST["cantidad"] ?? "";
    $stockMinimo = $_POST["stockMinimo"] ?? "";

    if (
        empty($nombre) ||
        $precio === "" ||
        $cantidad === "" ||
        $stockMinimo === "" ||
        $idProducto <= 0
    ) {
        $mensaje = "⚠️ Todos los campos son obligatorios.";
    } elseif ($precio < 0 || $cantidad < 0 || $stockMinimo < 0) {
        $mensaje = "⚠️ Los valores no pueden ser negativos.";
    } else {

        $sql = "UPDATE productos 
                SET nombre = ?, precio = ?, cantidad = ?, stockMinimo = ? 
                WHERE idProducto = ?";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            "sdiii",
            $nombre,
            $precio,
            $cantidad,
            $stockMinimo,
            $idProducto
        );

        if ($stmt->execute()) {
            header("Location: productos.php?mensaje=actualizado");
            exit();
        } else {
            $mensaje = "Error al actualizar el producto.";
        }

        $stmt->close();
    }

    // Recargar los datos para mostrarlos en el formulario
    $sql = "SELECT idProducto, nombre, precio, cantidad, stockMinimo 
            FROM productos WHERE idProducto = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $idProducto);
    $stmt->execute();
    $producto = $stmt->get_result()->fetch_assoc();
    $stmt->close();

} else {

    // ─── PASO 2: Si llega GET, cargar el producto ───
    if (!isset($_GET["id"]) || !is_numeric($_GET["id"])) {
        header("Location: productos.php");
        exit();
    }

    $id = intval($_GET["id"]);

    $sql = "SELECT idProducto, nombre, precio, cantidad, stockMinimo 
            FROM productos WHERE idProducto = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows === 0) {
        die("Producto no encontrado.");
    }

    $producto = $resultado->fetch_assoc();
    $stmt->close();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar producto - UnderStock</title>
</head>
<body>

<h2>✏️ Editar producto</h2>

<?php if (!empty($mensaje)): ?>
    <p style="color: red;"><?= htmlspecialchars($mensaje) ?></p>
<?php endif; ?>

<?php if ($producto): ?>

<form action="editar.php" method="POST">

    <input type="hidden" name="idProducto" value="<?= $producto['idProducto'] ?>">

    <label>Nombre:</label><br>
    <input 
        type="text" 
        name="nombre" 
        value="<?= htmlspecialchars($producto['nombre']) ?>" 
        required
    >
    <br><br>

    <label>Precio:</label><br>
    <input 
        type="number" 
        name="precio" 
        min="0" 
        step="0.01" 
        value="<?= $producto['precio'] ?>" 
        required
    >
    <br><br>

    <label>Cantidad:</label><br>
    <input 
        type="number" 
        name="cantidad" 
        min="0" 
        value="<?= $producto['cantidad'] ?>" 
        required
    >
    <br><br>

    <label>Stock mínimo:</label><br>
    <input 
        type="number" 
        name="stockMinimo" 
        min="0" 
        value="<?= $producto['stockMinimo'] ?>" 
        required
    >
    <br><br>

    <button type="submit">💾 Actualizar producto</button>
    <a href="productos.php">Cancelar</a>

</form>

<?php endif; ?>

</body>
</html>
