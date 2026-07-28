<?php

declare(strict_types=1);

namespace App\Core;

use App\Contracts\StorageInterface;

class App
{
    private static ?self $instance = null;

    private array $config = [];

    private Database $database;

    private function __construct()
    {
    }

    public static function boot(): void
    {
        if (self::$instance !== null) {
            return;
        }

        self::$instance = new self();

        self::$instance->loadEnvironment();

        self::$instance->loadConfiguration();

        self::$instance->database = new Database(
            self::$instance->config['database']
        );
    }

    public static function instance(): self
    {
        return self::$instance;
    }

    private function loadEnvironment(): void
    {
        $path = dirname(__DIR__, 2) . '/.env';

        if (!is_file($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            [$key, $value] = array_pad(
                explode('=', $line, 2),
                2,
                ''
            );

            $_ENV[trim($key)] = trim($value);
        }
    }

    private function loadConfiguration(): void
    {
        $this->config = require dirname(__DIR__, 2)
            . '/config/app.php';
    }

    private function env(
        string $key,
        mixed $default = null
    ): mixed {
        return $_ENV[$key] ?? $default;
    }

    public static function config(
        ?string $key = null,
        mixed $default = null
    ): mixed {
        $config = self::instance()->config;

        if ($key === null) {
            return $config;
        }

        return $config[$key] ?? $default;
    }

    public static function database(): Database
    {
        return self::instance()->database;
    }

    public static function storage(): StorageInterface
    {
        return self::database()->storage();
    }
}
