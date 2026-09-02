<?php

declare(strict_types=1);

namespace App\Database;

use PDO;
use RuntimeException;

final class MigrationRunner
{
    public function __construct(private string $directory)
    {
    }

    /** @return list<string> */
    public function migrate(PDO $connection): array
    {
        $connection->exec(
            'CREATE TABLE IF NOT EXISTS schema_migrations ('
            . 'version TEXT PRIMARY KEY, applied_at TEXT NOT NULL)'
        );

        $applied = $connection->query('SELECT version FROM schema_migrations')
            ->fetchAll(PDO::FETCH_COLUMN);
        $applied = array_fill_keys(array_map('strval', $applied), true);
        $files = glob($this->directory . DIRECTORY_SEPARATOR . '*.php') ?: [];
        sort($files, SORT_STRING);
        $completed = [];

        foreach ($files as $file) {
            $version = pathinfo($file, PATHINFO_FILENAME);
            if (isset($applied[$version])) {
                continue;
            }

            $migration = require $file;
            if (!is_callable($migration)) {
                throw new RuntimeException("Migration {$version} must return a callable.");
            }

            $started = !$connection->inTransaction();
            if ($started) {
                $connection->beginTransaction();
            }
            try {
                $migration($connection);
                $statement = $connection->prepare(
                    'INSERT INTO schema_migrations (version, applied_at) VALUES (?, ?)'
                );
                $statement->execute([$version, gmdate('c')]);
                if ($started) {
                    $connection->commit();
                }
                $completed[] = $version;
            } catch (\Throwable $exception) {
                if ($started && $connection->inTransaction()) {
                    $connection->rollBack();
                }
                throw $exception;
            }
        }

        return $completed;
    }
}
