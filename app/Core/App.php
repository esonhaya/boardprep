<?php

declare(strict_types=1);

namespace App\Core;

use App\Contracts\StorageInterface;

class App
{
    private static ?self $instance = null;

    private array $config = [];

    private Database $database;

    private Container $container;

    private function __construct()
    {
    }

    public static function boot(): void
    {
        if (self::$instance !== null) {
            return;
        }

        $app = new self();
        Environment::load(dirname(__DIR__, 2) . '/.env');
        $app->loadConfiguration();
        $app->configureRuntime();
        $app->database = new Database($app->config['database']);
        $app->container = new Container();
        $app->registerCoreBindings();

        self::$instance = $app;
    }

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::boot();
        }

        return self::$instance;
    }

    private function registerCoreBindings(): void
    {
        $this->container->singleton(
            self::class,
            fn () => $this
        );

        $this->container->singleton(
            Container::class,
            fn () => $this->container
        );

        $this->container->singleton(
            Database::class,
            fn () => $this->database
        );

        $this->container->singleton(
            StorageInterface::class,
            fn () => $this->database->storage()
        );
    }

    private function loadConfiguration(): void
    {
        $this->config = require dirname(__DIR__, 2)
            . '/config/app.php';
    }

    private function configureRuntime(): void
    {
        $timezone = $this->config['timezone'] ?? null;
        if (!is_string($timezone) || !in_array($timezone, timezone_identifiers_list(), true)) {
            throw new \RuntimeException('APP_TIMEZONE must be a valid timezone identifier.');
        }

        date_default_timezone_set($timezone);
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

    public static function container(): Container
    {
        return self::instance()->container;
    }
}
