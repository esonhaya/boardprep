<?php

declare(strict_types=1);

namespace Tools\Doctor\Project\BoardPrep\Simulation;

final class SimulationContext
{
    private array $data = [];

    public function set(
        string $key,
        mixed $value
    ): void {
        $this->data[$key] = $value;
    }

    public function get(
        string $key,
        mixed $default = null
    ): mixed {
        return $this->data[$key] ?? $default;
    }

    public function has(
        string $key
    ): bool {
        return array_key_exists(
            $key,
            $this->data
        );
    }

    public function all(): array
    {
        return $this->data;
    }

    public function forget(
        string $key
    ): void {
        unset(
            $this->data[$key]
        );
    }

    public function clear(): void
    {
        $this->data = [];
    }
}
