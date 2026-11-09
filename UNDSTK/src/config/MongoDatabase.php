
<?php

namespace Config;

use MongoDB\Client;
use RuntimeException;

class MongoDatabase
{
    private static $client = null;

    public static function alertas()
    {
        $autoload = dirname(__DIR__, 2) . '/vendor/autoload.php';

        if (!class_exists(Client::class)) {
            if (!file_exists($autoload)) {
                throw new RuntimeException(
                    'Falta instalar la libreria MongoDB con Composer.'
                );
            }

            require_once $autoload;
        }

        if (!extension_loaded('mongodb')) {
            throw new RuntimeException(
                'La extension mongodb de PHP no esta habilitada.'
            );
        }

        if (self::$client === null) {
            $uri = getenv('MONGODB_URI')
                ?: 'mongodb://127.0.0.1:27017';

            self::$client = new Client($uri);
        }

        return self::$client->selectCollection(
            'understock_nosql',
            'alertas_inventario'
        );
    }
}
