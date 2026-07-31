<?php

declare(strict_types=1);

namespace App\Services\RepositoryHealth\DTO;

class ValidationResult
{
    /**
     * @var HealthIssue[]
     */
    public array $issues = [];

    public function addIssue(
        HealthIssue $issue
    ): void {
        $this->issues[] = $issue;
    }

    public function hasIssues(): bool
    {
        return $this->issues !== [];
    }

    public function hasErrors(): bool
    {
        foreach ($this->issues as $issue) {
            if ($issue->severity === "error") {
                return true;
            }
        }

        return false;
    }

    public function count(): int
    {
        return count($this->issues);
    }
}
