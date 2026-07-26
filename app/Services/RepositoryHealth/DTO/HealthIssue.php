<?php

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

    public ?string $entityId = null;

    public array $metadata = [];

}
