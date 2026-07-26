<?php

class ValidationResult
{
    public array $issues = [];

    public array $statistics = [];

    public array $suggestions = [];

    public float $executionTime = 0;

    public function addIssue(
        HealthIssue $issue
    ): void
    {
        $this->issues[] = $issue;
    }
}
