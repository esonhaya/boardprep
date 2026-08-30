<?php

declare(strict_types=1);

namespace App\Core;

use App\Contracts\StorageInterface;
use App\Storage\JsonStorage;
use App\Storage\MysqlStorage;
use PDO;
use RuntimeException;

class Database
{
    private StorageInterface $storage;

    public function __construct(array $config)
    {
        $driver = $config['driver'] ?? null;
        if (!is_string($driver) || !in_array($driver, ['json', 'mysql'], true)) {
            throw new RuntimeException('DB_DRIVER must be either "json" or "mysql".');
        }

        if ($driver === 'mysql') {
            if (!extension_loaded('pdo_mysql')) {
                throw new RuntimeException('The pdo_mysql extension is required for DB_DRIVER=mysql.');
            }

            $port = filter_var($config['port'] ?? null, FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 1, 'max_range' => 65535],
            ]);
            if ($port === false) {
                throw new RuntimeException('DB_PORT must be an integer from 1 to 65535.');
            }

            $pdo = new PDO(
                sprintf(
                    'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                    $config['host'],
                    $port,
                    $config['database']
                ),
                $config['username'],
                $config['password'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]
            );

            $this->storage = new MysqlStorage($pdo);
        } else {
            if (!isset($config['path']) || !is_string($config['path']) || trim($config['path']) === '') {
                throw new RuntimeException('APP_STORAGE_PATH must be a non-empty path.');
            }

            $this->prepareStorageDirectory($config['path']);
            $this->storage = new JsonStorage(
                $config['path']
            );
        }
    }

    private function prepareStorageDirectory(string $path): void
    {
        if (!is_dir($path) && !mkdir($path, 0777, true) && !is_dir($path)) {
            throw new RuntimeException("Unable to create storage directory: {$path}");
        }

        if (!is_readable($path) || !is_writable($path)) {
            throw new RuntimeException("Storage directory must be readable and writable: {$path}");
        }
    }

    public function storage(): StorageInterface
    {
        return $this->storage;
    }

    public function usingJson(): bool
    {
        return $this->storage instanceof JsonStorage;
    }

    public function usingMysql(): bool
    {
        return $this->storage instanceof MysqlStorage;
    }
}
