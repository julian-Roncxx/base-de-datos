ñ<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Protocolos HTTP para destruir la caché en el navegador del cliente
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// 1. Normalizamos la ruta base tanto para desarrollo local como para hosting
$_SERVER['REQUEST_URI'] = preg_replace('#^/understock/UNDSTK/public#', '', $_SERVER['REQUEST_URI'], 1);
$_SERVER['REQUEST_URI'] = preg_replace('#^/public#', '', $_SERVER['REQUEST_URI'], 1);

// Autocarga inteligente de archivos
spl_autoload_register(function ($class) {
    $classPath = str_replace('\\', '/', $class);
    $fileNormal = __DIR__ . '/../src/' . $classPath . '.php';
    $fileLower = __DIR__ . '/../src/' . strtolower($classPath) . '.php';

    if (file_exists($fileNormal)) require_once $fileNormal;
    elseif (file_exists($fileLower)) require_once $fileLower;
});

use Core\Router;
use Controllers\AuthController;
use Controllers\ProductController;
use Controllers\SaleController;
use Models\Product;
use Models\Sale;
use Controllers\FinanceController;
use Models\Finance;
use Middleware\AuthMiddleware;

// 2. Obtener la URL solicitada
$parsedUrl = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$url = '/' . ltrim($parsedUrl, '/'); 
$method = $_SERVER['REQUEST_METHOD'];

$router = new Router();

// --- RUTAS DE AUTENTICACIÓN ---
$router->add('POST', '/api/login', function() {
    $auth = new AuthController();
    $auth->login();
});

// --- RUTAS DE PRODUCTOS (INVENTARIO) ---
$router->add('GET', '/api/products', function() {
    AuthMiddleware::verify(); 
    $productModel = new Product();
    $controller = new ProductController($productModel);
    $controller->index();
});

$router->add('POST', '/api/products', function() {
    AuthMiddleware::verify(); 
    $productModel = new Product();
    $controller = new ProductController($productModel);
    $controller->store();
});

$router->add('PUT', '/api/products', function() {
    AuthMiddleware::verify(); 
    $productModel = new Product();
    $controller = new ProductController($productModel);
    $controller->update();
});

$router->add('DELETE', '/api/products', function() {
    AuthMiddleware::verify(); 
    $productModel = new Product();
    $controller = new ProductController($productModel);
    $controller->destroy();
});

// --- RUTA DE VENTAS ---
$router->add('POST', '/api/sales', function() {
    AuthMiddleware::verify(); 
    $saleModel = new Sale();
    $controller = new SaleController($saleModel); 
    $controller->store();
});
// --- RUTAS DE PROVEEDORES ---
$router->add('GET', '/api/providers', function() {
    AuthMiddleware::verify(); 
    $providerModel = new \Models\Provider();
    $controller = new \Controllers\ProviderController($providerModel);
    $controller->index();
});

$router->add('POST', '/api/providers', function() {
    AuthMiddleware::verify(); 
    $providerModel = new \Models\Provider();
    $controller = new \Controllers\ProviderController($providerModel);
    $controller->store();
});
// --- RUTA DE FINANZAS ---
$router->add('GET', '/api/finance', function() {
    AuthMiddleware::verify(); 
    $financeModel = new Finance();
    $controller = new FinanceController($financeModel);
    $controller->index();
});

// Despachar la petición
$router->dispatch($method, $url);