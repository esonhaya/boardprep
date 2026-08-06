<?php

declare(strict_types=1);

final class SelectionRequest
{
    public function __construct(

        public readonly string $subject,

        public readonly string $domain,

        public readonly array $difficultyDistribution,

        public readonly int $questionCount,

    ) {
    }
}
