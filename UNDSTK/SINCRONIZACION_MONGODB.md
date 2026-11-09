# Sincronización de inventario MySQL -> MongoDB

## Qué se modificó

- `src/Services/MongoProductSyncService.php`: nuevo servicio que lee el estado actual del producto desde MySQL y lo replica en `understock.productos` en MongoDB.
- `src/Models/Sale.php`: después de confirmar una venta en MySQL, sincroniza las existencias finales de los productos afectados y vuelve a calcular las alertas.
- `src/Models/Product.php`: `create()` ahora devuelve el ID generado por MySQL para poder crear/actualizar el documento correspondiente en MongoDB.
- `src/Controllers/ProductController.php`: al crear, editar o eliminar un producto, sincroniza también la colección de MongoDB y actualiza las alertas.

## Regla de identificación

La implementación asume que el `_id` numérico del documento en MongoDB es igual a `idProducto` en MySQL. En la captura de Compass, por ejemplo, el producto jabonero aparece con `_id: 7`. Verifica que MySQL también tenga `idProducto = 7` para ese producto antes de probar. Si los identificadores no coinciden, se debe ajustar el mapeo para evitar documentos duplicados.

## Requisitos

1. MongoDB debe estar ejecutándose en `mongodb://127.0.0.1:27017`, o define la variable de entorno `MONGODB_URI` con la URI correcta.
2. PHP debe tener habilitada la extensión `mongodb`.
3. Instala las dependencias desde la carpeta `UNDSTK`:

   ```bash
   composer install
   ```

4. La base MySQL configurada en `src/config/Database.php` debe estar disponible. En el proyecto entregado, la conexión está configurada para `127.0.0.1:3306`, base `understock`.

## Prueba recomendada

1. En MySQL, identifica el jabón y su ID/cantidad:

   ```sql
   SELECT idProducto, nombre, cantidad, stockMinimo
   FROM productos
   WHERE nombre LIKE '%jabon%';
   ```

2. En MongoDB Compass, abre `understock` > `productos` y verifica el documento con el mismo `_id` numérico.
3. Realiza una venta desde la web.
4. Refresca la colección `productos` en Compass. El campo `cantidad` debe coincidir con el valor de MySQL.
5. Abre `alertas_inventario` y comprueba que el stock y el estado de las alertas se recalcularon.

## Consideración importante

MySQL continúa siendo la fuente principal de verdad para productos y ventas. MongoDB recibe una copia sincronizada del estado actual para consultas y alertas. La venta no se revierte si MongoDB está temporalmente fuera de servicio: el error queda registrado en el log de PHP y la respuesta de creación/edición de productos incluye `mongoSync: PENDIENTE` cuando no se pudo sincronizar. En ese caso, hay que revisar el log y volver a sincronizar.
