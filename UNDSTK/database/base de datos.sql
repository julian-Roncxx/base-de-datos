USE UnderStock;


CREATE TABLE IF NOT EXISTS formregistro (

    idUsuario INT AUTO_INCREMENT PRIMARY KEY,

    nombre VARCHAR(100) NOT NULL,

    correoRegistro VARCHAR(100) NOT NULL UNIQUE,

    claveRegistro VARCHAR(255) NOT NULL

);


CREATE TABLE IF NOT EXISTS productos (

    idProducto INT AUTO_INCREMENT PRIMARY KEY,

    nombre VARCHAR(100) NOT NULL,

    precio DECIMAL(10,2) NOT NULL,

    cantidad INT NOT NULL,

    stockMinimo INT NOT NULL DEFAULT 5

);


INSERT INTO productos
(nombre, precio, cantidad, stockMinimo)

VALUES

('Teclado', 80000, 10, 5),

('Mouse', 45000, 25, 8),

('Monitor', 650000, 5, 3),

('Audifonos', 120000, 15, 5);

SELECT *
FROM productos;

SELECT nombre, cantidad, stockMinimo
FROM productos
WHERE cantidad <= stockMinimo;

SELECT nombre, precio
FROM productos
ORDER BY precio DESC;

SELECT COUNT(*) AS totalProductos
FROM productos;

SELECT SUM(cantidad) AS unidadesDisponibles
FROM productos;