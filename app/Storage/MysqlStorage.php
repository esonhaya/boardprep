<?php

declare(strict_types=1);

namespace App\Storage;

use App\Contracts\StorageInterface;
use App\Exceptions\StorageException;
use PDO;

class MysqlStorage implements StorageInterface
{
    private PDO $connection;

    private string $primaryKey;

    public function __construct(
        PDO $connection,
        string $primaryKey = 'id'
    ) {
        $this->connection = $connection;
        $this->primaryKey = $primaryKey;
    }

    private function tableExists(
        string $table
    ): bool {
        $statement = $this->connection->prepare(
            "SHOW TABLES LIKE ?"
        );

        $statement->execute([$table]);

        return $statement->fetchColumn() !== false;
    }

    public function all(
        string $table
    ): array {
        $statement = $this->connection->query(
            "SELECT * FROM `{$table}`"
        );

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(
        string $table,
        string $id
    ): ?array {
        $statement = $this->connection->prepare(
            "SELECT * FROM `{$table}` WHERE `{$this->primaryKey}` = ? LIMIT 1"
        );

        $statement->execute([$id]);

        $record = $statement->fetch(PDO::FETCH_ASSOC);

        return $record ?: null;
    }

    public function where(
        string $table,
        array $criteria
    ): array {
        if (empty($criteria)) {
            return $this->all($table);
        }

        $conditions = [];
        $values = [];

        foreach ($criteria as $column => $value) {
            $conditions[] = "`{$column}` = ?";
            $values[] = $value;
        }

        $sql = sprintf(
            "SELECT * FROM `%s` WHERE %s",
            $table,
            implode(' AND ', $conditions)
        );

        $statement = $this->connection->prepare($sql);

        $statement->execute($values);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(
        string $table,
        array $data
    ): array {
        $columns = array_keys($data);

        $placeholders = implode(
            ', ',
            array_fill(0, count($columns), '?')
        );

        $sql = sprintf(
            "INSERT INTO `%s` (`%s`) VALUES (%s)",
            $table,
            implode('`, `', $columns),
            $placeholders
        );

        $statement = $this->connection->prepare($sql);

        $statement->execute(array_values($data));

        return $data;
    }

    public function update(
        string $table,
        string $id,
        array $data
    ): ?array {
        $assignments = [];
        $values = [];

        foreach ($data as $column => $value) {
            $assignments[] = "`{$column}` = ?";
            $values[] = $value;
        }

        $values[] = $id;

        $sql = sprintf(
            "UPDATE `%s` SET %s WHERE `%s` = ?",
            $table,
            implode(', ', $assignments),
            $this->primaryKey
        );

        $statement = $this->connection->prepare($sql);

        $statement->execute($values);

        return $this->find($table, $id);
    }

    public function delete(
        string $table,
        string $id
    ): bool {
        $statement = $this->connection->prepare(
            "DELETE FROM `{$table}` WHERE `{$this->primaryKey}` = ?"
        );

        $statement->execute([$id]);

        return $statement->rowCount() > 0;
    }

    public function exists(
        string $table,
        string $id
    ): bool {
        return $this->find($table, $id) !== null;
    }

    public function replace(
        string $table,
        array $records
    ): void {
        $started = !$this->connection->inTransaction();

        if ($started) {
            $this->connection->beginTransaction();
        }

        try {
            $this->connection->exec("DELETE FROM `{$table}`");

            foreach ($records as $record) {
                if (!is_array($record)) {
                    throw new StorageException(
                        "Replacement records for '{$table}' must be arrays."
                    );
                }

                $this->create($table, $record);
            }

            if ($started) {
                $this->connection->commit();
            }
        } catch (\Throwable $exception) {
            if ($started && $this->connection->inTransaction()) {
                $this->connection->rollBack();
            }

            if ($exception instanceof StorageException) {
                throw $exception;
            }

            throw new StorageException(
                "Unable to replace table: {$table}",
                0,
                $exception
            );
        }
    }
}
