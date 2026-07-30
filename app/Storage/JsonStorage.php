<?php

class JsonStorage
{
    private string $path;
    private string $primaryKey;

    public function __construct(string $path, string $primaryKey = "id")
    {
        $this->path = $path;
        $this->primaryKey = $primaryKey;

        $directory = dirname($this->path);

        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        if (!file_exists($this->path)) {
            file_put_contents($this->path, "[]", LOCK_EX);
        }
    }

    public function all(): array
    {
        $contents = file_get_contents($this->path);

        if ($contents === false || trim($contents) === '') {
            return [];
        }

        return json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    }

    public function find(string|int $id): ?array
    {
        foreach ($this->all() as $record) {
            if (($record[$this->primaryKey] ?? null) == $id) {
                return $record;
            }
        }

        return null;
    }

    public function create(array $data): void
    {
        if (!array_key_exists($this->primaryKey, $data)) {
            throw new InvalidArgumentException(
                "Missing primary key '{$this->primaryKey}'."
            );
        }

        if ($this->find($data[$this->primaryKey]) !== null) {
            throw new InvalidArgumentException(
                "Duplicate primary key '{$data[$this->primaryKey]}'."
            );
        }

        $records = $this->all();
        $records[] = $data;
        $this->write($records);
    }

    public function update(string|int $id, array $data): bool
    {
        $records = $this->all();

        foreach ($records as $index => $record) {
            if (($record[$this->primaryKey] ?? null) == $id) {
                $records[$index] = array_merge($record, $data);
                $this->write($records);
                return true;
            }
        }

        return false;
    }

    public function delete(string|int $id): bool
    {
        $records = $this->all();

        foreach ($records as $index => $record) {
            if (($record[$this->primaryKey] ?? null) == $id) {
                array_splice($records, $index, 1);
                $this->write($records);
                return true;
            }
        }

        return false;
    }

    private function write(array $records): void
    {
        $temp = $this->path . '.tmp';

        file_put_contents(
            $temp,
            json_encode(
                $records,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            ),
            LOCK_EX
        );

        rename($temp, $this->path);
    }
}
