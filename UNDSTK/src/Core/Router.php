<?php
namespace Core;

class Router {
    private $routes = [];

    public function add($method, $path, $handler) {
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'handler' => $handler
        ];
    }

    public function dispatch($method, $uri) {
        // Eliminar parámetros de la URL para comparar la ruta limpia
        $path = parse_url($uri, PHP_URL_PATH);
        
        foreach ($this->routes as $route) {
            if ($route['method'] === $method && $route['path'] === $path) {
                return call_user_func($route['handler']);
            }
        }
        
        // Si no encuentra la ruta, responde con 404 estandarizado
        Response::json(['error' => 'Endpoint no encontrado'], 404);
    }
}