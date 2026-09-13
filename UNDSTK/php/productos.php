<?php

require_once "conexion.php";

$sql = "SELECT idProducto, nombre, precio, cantidad, stockMinimo
        FROM productos
        ORDER BY nombre ASC";

$resultado = $conn->query($sql);

if (!$resultado) {
    die("Error al consultar los productos: " . $conn->error);
}

echo "<h2>Inventario de productos</h2>";

// 🆕 MENSAJE DE ÉXITO AL ACTUALIZAR
if (isset($_GET['mensaje']) && $_GET['mensaje'] === 'actualizado') {
    echo "<p style='color: green;'> Producto actualizado correctamente</p>";
}

if ($resultado->num_rows > 0) {

    echo "<table border='1' cellpadding='8'>";

    echo "<tr>
            <th>ID</th>
            <th>Producto</th>
            <th>Precio</th>
            <th>Cantidad</th>
            <th>Stock mínimo</th>
            <th>Estado</th>
            <th>Acciones</th>
          </tr>";

    while ($producto = $resultado->fetch_assoc()) {

        if ($producto["cantidad"] <= $producto["stockMinimo"]) {
            $estado = "Bajo stock";
        } else {
            $estado = "Disponible";
        }

        echo "<tr>";

        echo "<td>" . $producto["idProducto"] . "</td>";

        echo "<td>" . $producto["nombre"] . "</td>";

        echo "<td>$" . number_format(
            $producto["precio"],
            0,
            ",",
            "."
        ) . "</td>";

        echo "<td>" . $producto["cantidad"] . "</td>";

        echo "<td>" . $producto["stockMinimo"] . "</td>";

        echo "<td>" . $estado . "</td>";

        // 🆕 BOTÓN DE EDITAR
        echo "<td>
                <a href='editar.php?id=" . $producto["idProducto"] . "'>✏️ Editar</a>
              </td>";

        echo "</tr>";
    }

    echo "</table>";

} else {

    echo "<p>No hay productos registrados.</p>";
}

$conn->close();

?>

