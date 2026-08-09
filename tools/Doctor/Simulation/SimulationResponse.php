<?php

declare(strict_types=1);

namespace Tools\Doctor\Simulation;

final class SimulationResponse
{
    public function __construct(
        public readonly int $status,
        public readonly string $body = '',
        public readonly array $headers = [],
    ) {
    }

    public function isSuccessful(): bool
    {
        return $this->status >= 200
            && $this->status < 300;
    }

    public function contains(string $needle): bool
    {
        return str_contains(
            $this->body,
            $needle
        );
    }
}
