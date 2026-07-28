<?php

class MysqlStorage
    implements StorageInterface
{
    public function all(
        string $table
    ): array
    {
        throw new RuntimeException(
            "MySQL storage not implemented."
        );
    }

    public function find(
        string $table,
        string $id
    ): ?array
    {
        throw new RuntimeException(
            "MySQL storage not implemented."
        );
    }

    public function insert(
        string $table,
        array $row
    ): void
    {
        throw new RuntimeException(
            "MySQL storage not implemented."
        );
    }

    public function update(
        string $table,
        string $id,
        array $row
    ): void
    {
        throw new RuntimeException(
            "MySQL storage not implemented."
        );
    }

    public function delete(
        string $table,
        string $id
    ): void
    {
        throw new RuntimeException(
            "MySQL storage not implemented."
        );
    }
}
