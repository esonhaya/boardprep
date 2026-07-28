<?php

namespace App\Core;

use App\Storage\JsonStorage;
use App\Storage\MysqlStorage;
use App\Contracts\StorageInterface;

class App
{
    private static bool $booted = false;

    private static array $config = [];

    private static ?StorageInterface $storage = null;

    private static ?Database $database = null;

    public static function boot(): void
    {
        if (self::$booted) {
            return;
        }

        Env::load(
            dirname(__DIR__, 2) . '/.env'
        );

        self::$config = [
            'app' => require dirname(__DIR__, 2) . '/config/app.php',
            'database' => require dirname(__DIR__, 2) . '/config/database.php',
        ];

        self::applyEnvironment();

        self::$booted = true;
    }

    private static function applyEnvironment(): void
    {
        self::$config['app']['name'] =
            Env::get('APP_NAME', self::$config['app']['name']);

        self::$config['app']['environment'] =
            Env::get('APP_ENV', self::$config['app']['environment']);

        self::$config['app']['storage']['driver'] =
            Env::get('STORAGE_DRIVER', self::$config['app']['storage']['driver']);

        self::$config['database']['driver'] =
            Env::get('DB_DRIVER', self::$config['database']['driver']);

        self::$config['database']['host'] =
            Env::get('DB_HOST', self::$config['database']['host']);

        self::$config['database']['port'] =
            (int) Env::get('DB_PORT', self::$config['database']['port']);

        self::$config['database']['database'] =
            Env::get('DB_NAME', self::$config['database']['database']);

        self::$config['database']['username'] =
            Env::get('DB_USER', self::$config['database']['username']);

        self::$config['database']['password'] =
            Env::get('DB_PASS', self::$config['database']['password']);
    }

    public static function config(
        string $key,
        mixed $default = null
    ): mixed {
        self::boot();

        $value = self::$config;

        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    public static function storage(): StorageInterface
    {
        self::boot();

        if (self::$storage !== null) {
            return self::$storage;
        }

        self::$storage = match (self::config('app.storage.driver')) {
            'mysql' => new MysqlStorage(),
            default => new JsonStorage(),
        };

        return self::$storage;
    }

    public static function database(): Database
    {
        self::boot();

        if (self::$database !== null) {
            return self::$database;
        }

        self::$database = new Database();

        return self::$database;
    }
}
