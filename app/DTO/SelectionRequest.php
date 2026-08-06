<?php

declare(strict_types=1);

final class SelectionRequest
{
    public function __construct(

        public readonly ?string $domain,

        public readonly ?string $topic,

        public readonly ?string $concept,

        public readonly array $difficultyDistribution,

        public readonly int $questionCount,

    ) {
    }
}
