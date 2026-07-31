<?php

declare(strict_types=1);

namespace App\Services\RepositoryHealth\DTO;

class HealthIssue
{
    public string $validator;

    public string $severity;

    public string $priority;

    public string $category;

    public string $code;

    public string $message;

    public string $recommendation;

    public bool $repairable;

    public ?string $entityType = null;

    public string|int|null $entityId = null;

    public array $metadata = [];
}	
