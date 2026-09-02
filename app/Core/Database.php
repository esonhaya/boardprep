<?php

declare(strict_types=1);

namespace App\Core;

use App\Contracts\StorageInterface;
use App\Database\LegacyCollectionImporter;
use App\Database\MigrationRunner;
use App\Storage\JsonStorage;
use App\Storage\MysqlStorage;
use App\Storage\SqliteStorage;
use App\Storage\StorageRouter;
use PDO;
use RuntimeException;

class Database
{
    private StorageInterface $storage;

    private ?PDO $connection = null;

    private ?SqliteStorage $sqliteStorage = null;

    public function __construct(array $config)
    {
        $driver = $config['driver'] ?? null;
        if (!is_string($driver) || !in_array($driver, ['json', 'mysql', 'sqlite'], true)) {
            throw new RuntimeException('DB_DRIVER must be either "json", "mysql", or "sqlite".');
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

            $this->connection = new PDO(
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

            $this->storage = new MysqlStorage($this->connection);
        } elseif ($driver === 'sqlite') {
            if (!extension_loaded('pdo_sqlite')) {
                throw new RuntimeException('The pdo_sqlite extension is required for DB_DRIVER=sqlite.');
            }
            $path = $config['sqlite_path'] ?? null;
            if (!is_string($path) || trim($path) === '') {
                throw new RuntimeException('DB_SQLITE_PATH must be a non-empty path.');
            }
            $this->prepareStorageDirectory(dirname($path));
            try {
                $this->connection = new PDO('sqlite:' . $path, null, null, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);
                $this->connection->exec('PRAGMA foreign_keys = ON');
                $this->connection->exec('PRAGMA busy_timeout = 5000');
                (new MigrationRunner($config['migration_path']))->migrate($this->connection);
            } catch (\Throwable $exception) {
                throw new RuntimeException('Unable to initialize SQLite database.', 0, $exception);
            }
            $this->sqliteStorage = new SqliteStorage($this->connection);
            $json = $this->jsonStorage($config['path'] ?? null);
            $this->storage = new StorageRouter($json, [
                'attempts' => $this->sqliteStorage,
                'weakness' => $this->sqliteStorage,
            ]);
        } else {
            $this->storage = $this->jsonStorage($config['path'] ?? null);
        }
    }

    private function jsonStorage(mixed $path): JsonStorage
    {
        if (!is_string($path) || trim($path) === '') {
            throw new RuntimeException('APP_STORAGE_PATH must be a non-empty path.');
        }

        $this->prepareStorageDirectory($path);
        return new JsonStorage($path);
    }

    public function migrate(): array
    {
        if ($this->connection === null) {
            return [];
        }
        $config = App::config('database', []);
        return (new MigrationRunner($config['migration_path']))->migrate($this->connection);
    }

    /** @return array{existing:int, imported:int, skipped:int, invalid:int} */
    public function importLegacyCollection(string $collection): array
    {
        if ($this->sqliteStorage === null) {
            throw new RuntimeException('Legacy collection import requires DB_DRIVER=sqlite.');
        }
        $config = App::config('database', []);
        $source = new JsonStorage($config['path']);
        return (new LegacyCollectionImporter())->import($source, $this->sqliteStorage, $collection);
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

    public function usingSqlite(): bool
    {
        return $this->sqliteStorage !== null;
    }

    public function transaction(callable $callback): mixed
    {
        if ($this->connection === null) {
            throw new RuntimeException('Transactions require a database driver.');
        }
        $started = !$this->connection->inTransaction();
        if ($started) {
            $this->connection->beginTransaction();
        }
        try {
            $result = $callback($this->connection);
            if ($started) {
                $this->connection->commit();
            }
            return $result;
        } catch (\Throwable $exception) {
            if ($started && $this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            throw $exception;
        }
    }
}
