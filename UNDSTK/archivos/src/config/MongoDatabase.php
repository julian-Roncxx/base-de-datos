<?php

namespace Config;

use MongoDB\Client;
use RuntimeException;

class MongoDatabase
{
    private static ?Client $client = null;

    private static function cliente(): Client
    {
        if (!extension_loaded('mongodb')) {
            throw new RuntimeException('La extension mongodb de PHP no esta habilitada.');
        }

        if (!class_exists(Client::class)) {
            $autoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
            if (!is_file($autoload)) {
                throw new RuntimeException('No existe vendor/autoload.php. Instala las dependencias de Composer.');
            }
            require_once $autoload;
        }

        if (self::$client === null) {
            $uri = getenv('MONGODB_URI') ?: 'mongodb://127.0.0.1:27017';
            self::$client = new Client($uri);
        }

        return self::$client;
    }

    public static function alertas()
    {
        return self::cliente()->selectCollection('understock', 'alertas_inventario');
    }

    public static function productos()
    {
        return self::cliente()->selectCollection('understock', 'productos');
    }
}
