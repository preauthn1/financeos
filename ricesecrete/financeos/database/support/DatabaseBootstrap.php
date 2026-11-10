<?php

declare(strict_types=1);

namespace database\support;

use Illuminate\Database\Capsule\Manager as Capsule;

class DatabaseBootstrap
{
    private static bool $booted = false;

    public static function init(): Capsule
    {
        if (self::$booted) {
            return Capsule::getInstance();
        }

        if (file_exists(base_path() . '/.env')) {
            \Dotenv\Dotenv::createUnsafeImmutable(base_path())->load();
        }

        $config = require config_path('database.php');
        $capsule = new Capsule();
        $capsule->addConnection($config['connections'][$config['default']]);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        self::$booted = true;

        return $capsule;
    }
}
