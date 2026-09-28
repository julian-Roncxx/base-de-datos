<?php

require_once __DIR__ . '/../src/config/Database.php';
require_once __DIR__ . '/../src/Models/Product.php';

use Models\Product;

echo "<pre>";

try {

    $modelo = new Product();

    $productos = $modelo->getAll();

    echo "PRODUCT MODEL: OK\n\n";

    print_r($productos);

} catch (Throwable $e) {

    echo "ERROR:\n\n";

    echo $e->getMessage();

}