<?php

declare(strict_types=1);

final class SelectionRequest
{
    public function __construct(
        public readonly string $subject,
        public readonly ?string $domain,
        public readonly array $difficultyDistribution,
        public readonly int $questionCount,
        public readonly ?string $topic = null,
        public readonly ?string $concept = null,
        public readonly ?string $exam = null,
        /** @var array<string,mixed>|null */
        public readonly ?array $letVariant = null,
        public readonly ?string $letComponent = null
    ) {
    }
}
