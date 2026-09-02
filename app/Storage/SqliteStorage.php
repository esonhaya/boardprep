<?php

declare(strict_types=1);

namespace App\Storage;

use App\Contracts\StorageInterface;
use App\Exceptions\StorageException;
use JsonException;
use PDO;

final class SqliteStorage implements StorageInterface
{
    public function __construct(private PDO $connection, private string $primaryKey = 'id')
    {
    }

    private function id(array $data): string
    {
        $value = $data[$this->primaryKey] ?? null;
        if (!is_string($value) && !is_int($value)) {
            throw new StorageException("Primary key '{$this->primaryKey}' must be a string or integer.");
        }
        $id = trim((string) $value);
        if ($id === '') {
            throw new StorageException("Primary key '{$this->primaryKey}' cannot be empty.");
        }
        return $id;
    }

    private function encode(array $data): string
    {
        try {
            return json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        } catch (JsonException $exception) {
            throw new StorageException('Unable to encode SQLite record.', 0, $exception);
        }
    }

    private function decode(string $payload): array
    {
        try {
            $record = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new StorageException('SQLite record contains malformed JSON.', 0, $exception);
        }
        if (!is_array($record)) {
            throw new StorageException('SQLite record payload must be an object.');
        }
        return $record;
    }

    /** @return list<array<string,mixed>> */
    public function all(string $collection): array
    {
        $statement = $this->connection->prepare(
            'SELECT payload FROM storage_records WHERE collection = ? ORDER BY rowid'
        );
        $statement->execute([$collection]);
        return array_map(fn (string $payload): array => $this->decode($payload), $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    public function find(string $collection, string $id): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT payload FROM storage_records WHERE collection = ? AND record_id = ? LIMIT 1'
        );
        $statement->execute([$collection, $id]);
        $payload = $statement->fetchColumn();
        return $payload === false ? null : $this->decode((string) $payload);
    }

    public function where(string $collection, array $criteria): array
    {
        return array_values(array_filter($this->all($collection), static function (array $record) use ($criteria): bool {
            foreach ($criteria as $key => $expected) {
                if (($record[$key] ?? null) !== $expected) {
                    return false;
                }
            }
            return true;
        }));
    }

    public function create(string $collection, array $data): array
    {
        $id = $this->id($data);
        $now = gmdate('c');
        try {
            $statement = $this->connection->prepare(
                'INSERT INTO storage_records (collection, record_id, payload, created_at, updated_at) VALUES (?, ?, ?, ?, ?)'
            );
            $statement->execute([$collection, $id, $this->encode($data), $now, $now]);
        } catch (\PDOException $exception) {
            throw new StorageException("Unable to create record '{$id}'.", 0, $exception);
        }
        return $data;
    }

    public function update(string $collection, string $id, array $data): ?array
    {
        $existing = $this->find($collection, $id);
        if ($existing === null) {
            return null;
        }
        if (array_key_exists($this->primaryKey, $data) && $this->id($data) !== $id) {
            throw new StorageException("Primary key '{$this->primaryKey}' cannot be changed during update.");
        }
        $updated = array_merge($existing, $data);
        $statement = $this->connection->prepare(
            'UPDATE storage_records SET payload = ?, updated_at = ? WHERE collection = ? AND record_id = ?'
        );
        $statement->execute([$this->encode($updated), gmdate('c'), $collection, $id]);
        return $updated;
    }

    public function updateBatch(string $collection, array $ids, callable $updater): void
    {
        if ($ids === []) {
            return;
        }
        $started = !$this->connection->inTransaction();
        if ($started) {
            $this->connection->beginTransaction();
        }
        try {
            foreach ($ids as $id) {
                $record = $this->find($collection, (string) $id);
                if ($record !== null) {
                    $this->update($collection, (string) $id, $updater($record));
                }
            }
            if ($started) {
                $this->connection->commit();
            }
        } catch (\Throwable $exception) {
            if ($started && $this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            throw $exception;
        }
    }

    public function delete(string $collection, string $id): bool
    {
        $statement = $this->connection->prepare(
            'DELETE FROM storage_records WHERE collection = ? AND record_id = ?'
        );
        $statement->execute([$collection, $id]);
        return $statement->rowCount() > 0;
    }

    public function exists(string $collection, string $id): bool
    {
        return $this->find($collection, $id) !== null;
    }

    public function replace(string $collection, array $records): void
    {
        $validated = [];
        $seen = [];
        foreach ($records as $record) {
            if (!is_array($record)) {
                throw new StorageException("Collection '{$collection}' replacement requires record arrays.");
            }
            $id = $this->id($record);
            if (isset($seen[$id])) {
                throw new StorageException("Duplicate primary key '{$id}' in collection replacement.");
            }
            $seen[$id] = true;
            $validated[] = [$id, $record];
        }
        $started = !$this->connection->inTransaction();
        if ($started) {
            $this->connection->beginTransaction();
        }
        try {
            $delete = $this->connection->prepare('DELETE FROM storage_records WHERE collection = ?');
            $delete->execute([$collection]);
            foreach ($validated as [$id, $record]) {
                $now = gmdate('c');
                $insert = $this->connection->prepare(
                    'INSERT INTO storage_records (collection, record_id, payload, created_at, updated_at) VALUES (?, ?, ?, ?, ?)'
                );
                $insert->execute([$collection, $id, $this->encode($record), $now, $now]);
            }
            if ($started) {
                $this->connection->commit();
            }
        } catch (\Throwable $exception) {
            if ($started && $this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            throw $exception;
        }
    }

    public function transaction(callable $callback): mixed
    {
        $started = !$this->connection->inTransaction();
        if ($started) {
            $this->connection->beginTransaction();
        }
        try {
            $result = $callback($this);
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
