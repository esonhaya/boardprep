<?php

declare(strict_types=1);

namespace App\Core;

use App\Contracts\StorageInterface;
use App\Storage\JsonStorage;
use App\Storage\MysqlStorage;
use PDO;

class Database
{
    private StorageInterface $storage;

    public function __construct(array $config)
    {
        if (($config['driver'] ?? 'json') === 'mysql') {
            $pdo = new PDO(
                sprintf(
                    'mysql:host=%s;dbname=%s;charset=utf8mb4',
                    $config['host'],
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
            $this->storage = new JsonStorage(
                $config['path']
            );
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
