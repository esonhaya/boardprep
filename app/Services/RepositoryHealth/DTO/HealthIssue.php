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

    /** Canonical Repository Health vocabulary. */
    public string $status = 'FAIL';

    public ?string $boardId = null;

    public array $evidence = [];

    public string $suggestedAction = '';

    public string $sourceCheck = '';

    public bool $justifiedExclusion = false;

    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'status' => $this->status,
            'severity' => strtolower($this->severity),
            'category' => $this->category,
            'board_id' => $this->boardId,
            'entity_type' => $this->entityType,
            'entity_id' => $this->entityId,
            'message' => $this->message,
            'evidence' => $this->evidence,
            'suggested_action' => $this->suggestedAction !== '' ? $this->suggestedAction : $this->recommendation,
            'source_check' => $this->sourceCheck !== '' ? $this->sourceCheck : $this->validator,
            'justified_exclusion' => $this->justifiedExclusion,
            'validator' => $this->validator,
            'priority' => $this->priority,
            'repairable' => $this->repairable,
            'metadata' => $this->metadata,
        ];
    }
}	
