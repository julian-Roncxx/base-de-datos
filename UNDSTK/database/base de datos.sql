use UnderStock;

create table formregistro (
nombre varchar(100) not null,
correoRegistro varchar(100) not null unique,
claveRegistro varchar(255) not null
);

CREATE TABLE productos (
    idProducto INT IDENTITY(1,1) PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    precio DECIMAL(10,2) NOT NULL,
    cantidad INT NOT NULL
);

INSERT INTO productos (nombre, precio, cantidad)
VALUES
('Teclado', 80000, 10),
('Mouse', 45000, 25),
('Monitor', 650000, 5),
('Audifonos', 120000, 15);


select * from formregistro ;

SELECT nombre, cantidad
FROM productos
WHERE cantidad > 10;

SELECT nombre, precio
FROM productos
ORDER BY precio DESC;
