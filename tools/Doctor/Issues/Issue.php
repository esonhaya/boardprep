<?php

declare(strict_types=1);

namespace Tools\Doctor\Issues;

final class Issue
{
    public function __construct(
        public string $severity,
        public string $rule,
        public string $title,
        public string $message,
        public ?string $file = null,
        public ?string $symbol = null,
        public ?string $recommendation = null,
    ) {
    }

    public function critical(): bool
    {
        return $this->severity === 'critical';
    }

    public function warning(): bool
    {
        return $this->severity === 'warning';
    }

    public function info(): bool
    {
        return $this->severity === 'info';
    }

    public function toArray(): array
    {
        return [
            'severity' => $this->severity,
            'rule' => $this->rule,
            'title' => $this->title,
            'message' => $this->message,
            'file' => $this->file,
            'symbol' => $this->symbol,
            'recommendation' => $this->recommendation,
        ];
    }
}
