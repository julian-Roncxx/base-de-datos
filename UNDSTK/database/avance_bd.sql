-- =========================================================
-- UNDERSTOCK
-- AVANCE DE BASE DE DATOS
--
-- Normalización
-- Vistas
-- Triggers
-- Transacciones
-- =========================================================


-- Verificar primero en qué base de datos estamos trabajando

SELECT DATABASE() AS base_de_datos_actual;


-- =========================================================
-- 1. NORMALIZACIÓN
-- =========================================================

CREATE TABLE IF NOT EXISTS categorias (

    idCategoria INT AUTO_INCREMENT PRIMARY KEY,

    nombre VARCHAR(80) NOT NULL UNIQUE,

    descripcion VARCHAR(255)

);


INSERT INTO categorias
(nombre, descripcion)

VALUES

(
    'Sin categoría',
    'Productos que todavía no tienen una categoría definida'
),

(
    'Periféricos',
    'Teclados, mouse, audífonos y accesorios'
),

(
    'Monitores',
    'Pantallas y monitores'
),

(
    'Otros',
    'Productos de otras categorías'
)

ON DUPLICATE KEY UPDATE
descripcion = VALUES(descripcion);


-- =========================================================
-- NORMALIZAR TABLA PRODUCTOS
-- =========================================================


-- Algunas versiones del proyecto utilizaban "id"
-- y otras "idProducto".
-- Dejamos idProducto como nombre definitivo.


SET @existe_idProducto = (

    SELECT COUNT(*)

    FROM information_schema.COLUMNS

    WHERE TABLE_SCHEMA = DATABASE()

      AND TABLE_NAME = 'productos'

      AND COLUMN_NAME = 'idProducto'
);


SET @existe_id = (

    SELECT COUNT(*)

    FROM information_schema.COLUMNS

    WHERE TABLE_SCHEMA = DATABASE()

      AND TABLE_NAME = 'productos'

      AND COLUMN_NAME = 'id'
);


SET @sql = IF(

    @existe_idProducto = 0
    AND @existe_id = 1,

    'ALTER TABLE productos
     CHANGE COLUMN id idProducto
     INT NOT NULL AUTO_INCREMENT',

    'SELECT 1'
);


PREPARE stmt FROM @sql;

EXECUTE stmt;

DEALLOCATE PREPARE stmt;


-- =========================================================
-- STOCK MÍNIMO
-- =========================================================


SET @existe_stockMinimo = (

    SELECT COUNT(*)

    FROM information_schema.COLUMNS

    WHERE TABLE_SCHEMA = DATABASE()

      AND TABLE_NAME = 'productos'

      AND COLUMN_NAME = 'stockMinimo'
);


SET @sql = IF(

    @existe_stockMinimo = 0,

    'ALTER TABLE productos
     ADD COLUMN stockMinimo
     INT NOT NULL DEFAULT 5',

    'SELECT 1'
);


PREPARE stmt FROM @sql;

EXECUTE stmt;

DEALLOCATE PREPARE stmt;


-- =========================================================
-- CATEGORÍA DEL PRODUCTO
-- =========================================================


SET @existe_idCategoria = (

    SELECT COUNT(*)

    FROM information_schema.COLUMNS

    WHERE TABLE_SCHEMA = DATABASE()

      AND TABLE_NAME = 'productos'

      AND COLUMN_NAME = 'idCategoria'
);


SET @sql = IF(

    @existe_idCategoria = 0,

    'ALTER TABLE productos
     ADD COLUMN idCategoria INT NULL',

    'SELECT 1'
);


PREPARE stmt FROM @sql;

EXECUTE stmt;

DEALLOCATE PREPARE stmt;


-- Categoría por defecto

SET @categoria_default = (

    SELECT idCategoria

    FROM categorias

    WHERE nombre = 'Sin categoría'

    LIMIT 1
);


UPDATE productos p

LEFT JOIN categorias c
    ON p.idCategoria = c.idCategoria

SET p.idCategoria = @categoria_default

WHERE c.idCategoria IS NULL;


-- Asignaciones iniciales

UPDATE productos p

JOIN categorias c
    ON c.nombre = 'Periféricos'

SET p.idCategoria = c.idCategoria

WHERE p.nombre IN (
    'Teclado',
    'Mouse',
    'Audifonos',
    'Audífonos'
);


UPDATE productos p

JOIN categorias c
    ON c.nombre = 'Monitores'

SET p.idCategoria = c.idCategoria

WHERE p.nombre LIKE '%Monitor%';


ALTER TABLE productos
MODIFY COLUMN idCategoria INT NOT NULL;


-- =========================================================
-- CLAVE FORÁNEA PRODUCTOS - CATEGORÍAS
-- =========================================================


SET @existe_fk_categoria = (

    SELECT COUNT(*)

    FROM information_schema.KEY_COLUMN_USAGE

    WHERE TABLE_SCHEMA = DATABASE()

      AND TABLE_NAME = 'productos'

      AND COLUMN_NAME = 'idCategoria'

      AND REFERENCED_TABLE_NAME = 'categorias'
);


SET @sql = IF(

    @existe_fk_categoria = 0,

    'ALTER TABLE productos
     ADD CONSTRAINT fk_productos_categoria
     FOREIGN KEY (idCategoria)
     REFERENCES categorias(idCategoria)
     ON UPDATE CASCADE
     ON DELETE RESTRICT',

    'SELECT 1'
);


PREPARE stmt FROM @sql;

EXECUTE stmt;

DEALLOCATE PREPARE stmt;


-- =========================================================
-- 2. VENTAS NORMALIZADAS
-- =========================================================


CREATE TABLE IF NOT EXISTS ventas (

    id INT AUTO_INCREMENT PRIMARY KEY,

    total DECIMAL(12,2) NOT NULL,

    fecha TIMESTAMP NOT NULL
        DEFAULT CURRENT_TIMESTAMP

);


-- Agregar fecha en caso de que la tabla ya existiera

SET @existe_fecha = (

    SELECT COUNT(*)

    FROM information_schema.COLUMNS

    WHERE TABLE_SCHEMA = DATABASE()

      AND TABLE_NAME = 'ventas'

      AND COLUMN_NAME = 'fecha'
);


SET @sql = IF(

    @existe_fecha = 0,

    'ALTER TABLE ventas
     ADD COLUMN fecha TIMESTAMP
     NOT NULL DEFAULT CURRENT_TIMESTAMP',

    'SELECT 1'
);


PREPARE stmt FROM @sql;

EXECUTE stmt;

DEALLOCATE PREPARE stmt;


CREATE TABLE IF NOT EXISTS detalles_venta (

    id INT AUTO_INCREMENT PRIMARY KEY,

    venta_id INT NOT NULL,

    producto_id INT NOT NULL,

    cantidad INT NOT NULL,

    precio_unitario DECIMAL(12,2) NOT NULL

);


-- Relación detalle -> venta

SET @existe_fk_venta = (

    SELECT COUNT(*)

    FROM information_schema.KEY_COLUMN_USAGE

    WHERE TABLE_SCHEMA = DATABASE()

      AND TABLE_NAME = 'detalles_venta'

      AND COLUMN_NAME = 'venta_id'

      AND REFERENCED_TABLE_NAME = 'ventas'
);


SET @sql = IF(

    @existe_fk_venta = 0,

    'ALTER TABLE detalles_venta
     ADD CONSTRAINT fk_detalle_venta
     FOREIGN KEY (venta_id)
     REFERENCES ventas(id)
     ON UPDATE CASCADE
     ON DELETE RESTRICT',

    'SELECT 1'
);


PREPARE stmt FROM @sql;

EXECUTE stmt;

DEALLOCATE PREPARE stmt;


-- Relación detalle -> producto

SET @existe_fk_producto = (

    SELECT COUNT(*)

    FROM information_schema.KEY_COLUMN_USAGE

    WHERE TABLE_SCHEMA = DATABASE()

      AND TABLE_NAME = 'detalles_venta'

      AND COLUMN_NAME = 'producto_id'

      AND REFERENCED_TABLE_NAME = 'productos'
);


SET @sql = IF(

    @existe_fk_producto = 0,

    'ALTER TABLE detalles_venta
     ADD CONSTRAINT fk_detalle_producto
     FOREIGN KEY (producto_id)
     REFERENCES productos(idProducto)
     ON UPDATE CASCADE
     ON DELETE RESTRICT',

    'SELECT 1'
);


PREPARE stmt FROM @sql;

EXECUTE stmt;

DEALLOCATE PREPARE stmt;


-- =========================================================
-- 3. MOVIMIENTOS DE INVENTARIO
-- =========================================================


CREATE TABLE IF NOT EXISTS movimientos_inventario (

    idMovimiento INT AUTO_INCREMENT PRIMARY KEY,

    idProducto INT NOT NULL,

    tipo ENUM(
        'ENTRADA',
        'SALIDA'
    ) NOT NULL,

    cantidad INT NOT NULL,

    stockAnterior INT NOT NULL,

    stockNuevo INT NOT NULL,

    motivo VARCHAR(150)
        NOT NULL
        DEFAULT 'Actualización de inventario',

    fecha TIMESTAMP
        NOT NULL
        DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_movimiento_producto

        FOREIGN KEY (idProducto)

        REFERENCES productos(idProducto)

        ON UPDATE CASCADE

        ON DELETE RESTRICT

);


-- =========================================================
-- 4. AUDITORÍA DE TRANSACCIONES
-- =========================================================


CREATE TABLE IF NOT EXISTS auditoria_transacciones (

    idTransaccion INT AUTO_INCREMENT PRIMARY KEY,

    ventaId INT NULL,

    operacion VARCHAR(50)
        NOT NULL DEFAULT 'VENTA',

    estado ENUM(
        'COMMIT',
        'ROLLBACK'
    ) NOT NULL,

    mensaje VARCHAR(255) NOT NULL,

    fecha TIMESTAMP
        NOT NULL DEFAULT CURRENT_TIMESTAMP

);


-- =========================================================
-- 5. VISTA DE INVENTARIO
-- =========================================================


CREATE OR REPLACE VIEW vw_inventario AS

SELECT

    p.idProducto AS id,

    p.nombre,

    c.nombre AS categoria,

    p.precio,

    p.cantidad,

    p.stockMinimo,

    CASE

        WHEN p.cantidad = 0
            THEN 'Agotado'

        WHEN p.cantidad <= p.stockMinimo
            THEN 'Bajo stock'

        ELSE 'Disponible'

    END AS estado,

    ROUND(
        p.precio * p.cantidad,
        2
    ) AS valorInventario

FROM productos p

INNER JOIN categorias c

    ON p.idCategoria =
       c.idCategoria;


-- =========================================================
-- 6. VISTA DE MOVIMIENTOS
-- =========================================================


CREATE OR REPLACE VIEW vw_movimientos_inventario AS

SELECT

    m.idMovimiento,

    p.idProducto AS id,

    p.nombre AS producto,

    c.nombre AS categoria,

    m.tipo,

    m.cantidad,

    m.stockAnterior,

    m.stockNuevo,

    m.motivo,

    m.fecha

FROM movimientos_inventario m

INNER JOIN productos p

    ON m.idProducto =
       p.idProducto

INNER JOIN categorias c

    ON p.idCategoria =
       c.idCategoria;


-- =========================================================
-- 7. VISTA DE TRANSACCIONES
-- =========================================================


CREATE OR REPLACE VIEW vw_transacciones AS

SELECT

    idTransaccion,

    ventaId,

    operacion,

    estado,

    mensaje,

    fecha

FROM auditoria_transacciones;


-- =========================================================
-- 8. TRIGGER DE CAMBIO DE STOCK
-- =========================================================


DROP TRIGGER IF EXISTS trg_movimiento_stock;


CREATE TRIGGER trg_movimiento_stock

AFTER UPDATE ON productos

FOR EACH ROW

INSERT INTO movimientos_inventario
(
    idProducto,
    tipo,
    cantidad,
    stockAnterior,
    stockNuevo,
    motivo
)

SELECT

    NEW.idProducto,

    CASE

        WHEN NEW.cantidad > OLD.cantidad
            THEN 'ENTRADA'

        ELSE 'SALIDA'

    END,

    ABS(
        NEW.cantidad -
        OLD.cantidad
    ),

    OLD.cantidad,

    NEW.cantidad,

    COALESCE(
        @motivo_movimiento,
        'Ajuste manual de inventario'
    )

WHERE OLD.cantidad <> NEW.cantidad;


-- =========================================================
-- 9. TRIGGER DE STOCK INICIAL
-- =========================================================


DROP TRIGGER IF EXISTS trg_stock_inicial;


CREATE TRIGGER trg_stock_inicial

AFTER INSERT ON productos

FOR EACH ROW

INSERT INTO movimientos_inventario
(
    idProducto,
    tipo,
    cantidad,
    stockAnterior,
    stockNuevo,
    motivo
)

SELECT

    NEW.idProducto,

    'ENTRADA',

    NEW.cantidad,

    0,

    NEW.cantidad,

    'Stock inicial del producto'

WHERE NEW.cantidad > 0;


-- =========================================================
-- COMPROBACIONES
-- =========================================================


SELECT *
FROM categorias;


SELECT *
FROM vw_inventario;


SELECT *
FROM vw_movimientos_inventario
ORDER BY fecha DESC;


SELECT *
FROM vw_transacciones
ORDER BY fecha DESC;


SHOW TRIGGERS;


SELECT

    TABLE_NAME

FROM information_schema.VIEWS

WHERE TABLE_SCHEMA = DATABASE()

ORDER BY TABLE_NAME;