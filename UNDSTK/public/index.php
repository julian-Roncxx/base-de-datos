<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Evitar caché durante desarrollo
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");


// ==========================================
// AUTOCARGA DE CLASES
// ==========================================

spl_autoload_register(function ($class) {

    $classPath = str_replace('\\', '/', $class);

    $fileNormal =
        __DIR__ . '/../src/' . $classPath . '.php';

    $fileLower =
        __DIR__ . '/../src/' . strtolower($classPath) . '.php';


    if (file_exists($fileNormal)) {

        require_once $fileNormal;

    } elseif (file_exists($fileLower)) {

        require_once $fileLower;
    }
});


// ==========================================
// IMPORTS
// ==========================================

use Core\Router;

use Controllers\AuthController;
use Controllers\ProductController;
use Controllers\SaleController;
use Controllers\FinanceController;
use Controllers\DatabaseFeatureController;

use Models\Product;
use Models\Sale;
use Models\Finance;
use Models\DatabaseFeature;

use Middleware\AuthMiddleware;


// ==========================================
// NORMALIZAR URL
// ==========================================

// Ejemplo recibido:
//
// /base-de-datos/UNDSTK/public/api/products
//
// El Router solamente debe recibir:
//
// /api/products

$parsedUrl = parse_url(
    $_SERVER['REQUEST_URI'],
    PHP_URL_PATH
);


// Buscar dónde comienza /public/

$posPublic = strpos(
    $parsedUrl,
    '/public/'
);


if ($posPublic !== false) {

    $parsedUrl = substr(
        $parsedUrl,
        $posPublic + strlen('/public')
    );

} else {

    // Caso donde se acceda solamente a /public

    $posPublic = strpos(
        $parsedUrl,
        '/public'
    );

    if ($posPublic !== false) {

        $parsedUrl = substr(
            $parsedUrl,
            $posPublic + strlen('/public')
        );
    }
}


// Garantizar que siempre comience por /

$url = '/' . ltrim(
    $parsedUrl,
    '/'
);


$method = $_SERVER['REQUEST_METHOD'];


// ==========================================
// ROUTER
// ==========================================

$router = new Router();


// ==========================================
// AUTENTICACIÓN
// ==========================================

$router->add(
    'POST',
    '/api/login',
    function() {

        $auth = new AuthController();

        $auth->login();
    }
);


// ==========================================
// PRODUCTOS
// ==========================================

$router->add(
    'GET',
    '/api/products',
    function() {

        AuthMiddleware::verify();

        $productModel =
            new Product();

        $controller =
            new ProductController(
                $productModel
            );

        $controller->index();
    }
);


$router->add(
    'POST',
    '/api/products',
    function() {

        AuthMiddleware::verify();

        $productModel =
            new Product();

        $controller =
            new ProductController(
                $productModel
            );

        $controller->store();
    }
);


$router->add(
    'PUT',
    '/api/products',
    function() {

        AuthMiddleware::verify();

        $productModel =
            new Product();

        $controller =
            new ProductController(
                $productModel
            );

        $controller->update();
    }
);


$router->add(
    'DELETE',
    '/api/products',
    function() {

        AuthMiddleware::verify();

        $productModel =
            new Product();

        $controller =
            new ProductController(
                $productModel
            );

        $controller->destroy();
    }
);


// ==========================================
// VENTAS
// ==========================================

$router->add(
    'POST',
    '/api/sales',
    function() {

        AuthMiddleware::verify();

        $saleModel =
            new Sale();

        $controller =
            new SaleController(
                $saleModel
            );

        $controller->store();
    }
);


// ==========================================
// PROVEEDORES
// ==========================================

$router->add(
    'GET',
    '/api/providers',
    function() {

        AuthMiddleware::verify();

        $providerModel =
            new \Models\Provider();

        $controller =
            new \Controllers\ProviderController(
                $providerModel
            );

        $controller->index();
    }
);


$router->add(
    'POST',
    '/api/providers',
    function() {

        AuthMiddleware::verify();

        $providerModel =
            new \Models\Provider();

        $controller =
            new \Controllers\ProviderController(
                $providerModel
            );

        $controller->store();
    }
);


// ==========================================
// FINANZAS
// ==========================================

$router->add(
    'GET',
    '/api/finance',
    function() {

        AuthMiddleware::verify();

        $financeModel =
            new Finance();

        $controller =
            new FinanceController(
                $financeModel
            );

        $controller->index();
    }
);


// ==========================================
// FUNCIONALIDADES DE BASE DE DATOS
// ==========================================

$router->add(
    'GET',
    '/api/database-features',
    function() {

        AuthMiddleware::verify();

        $model =
            new DatabaseFeature();

        $controller =
            new DatabaseFeatureController(
                $model
            );

        $controller->index();
    }
);


// ==========================================
// DESPACHAR PETICIÓN
// ==========================================

$router->dispatch(
    $method,
    $url
);