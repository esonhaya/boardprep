<?php

declare(strict_types=1);

namespace Tools\Doctor\Diagnostics;

final class DiagnosticFinding
{
    /**
     * @param array<string,mixed> $evidence
     */
    public function __construct(
        public readonly string $id,
        public readonly string $severity,
        public readonly string $category,
        public readonly string $rule,
        public readonly string $title,
        public readonly string $message,
        public readonly ?string $file = null,
        public readonly ?string $symbol = null,
        public readonly ?string $recommendation = null,
        public readonly array $evidence = [],
    ) {
    }

    public function isCritical(): bool
    {
        return $this->severity === 'CRITICAL';
    }

    public function isError(): bool
    {
        return $this->severity === 'ERROR';
    }

    public function isWarning(): bool
    {
        return $this->severity === 'WARNING';
    }

    public function isInfo(): bool
    {
        return $this->severity === 'INFO';
    }

    public function impact(): int
    {
        return DiagnosticRegistry::impact($this->id);
    }

    public function effort(): string
    {
        return DiagnosticRegistry::effort($this->id);
    }

    public function priorityLabel(): string
    {
        return DiagnosticRegistry::label($this->id);
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'severity' => $this->severity,
            'category' => $this->category,
            'rule' => $this->rule,
            'title' => $this->title,
            'message' => $this->message,
            'file' => $this->file,
            'symbol' => $this->symbol,
            'recommendation' => $this->recommendation,
            'evidence' => $this->evidence,
            'impact' => $this->impact(),
            'effort' => $this->effort(),
            'priority' => $this->priorityLabel(),
        ];
    }
}
