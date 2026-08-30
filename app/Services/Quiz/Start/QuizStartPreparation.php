<?php

declare(strict_types=1);

namespace App\Services\Quiz\Start;

final class QuizStartPreparation
{
    public function __construct(
        public readonly \QuizSpecification $specification,
        public readonly array $questions,
        public readonly array $coverage = [],
        public readonly array $issues = [],
    ) {
    }

    public function isEmpty(): bool
    {
        return $this->questions === [];
    }
}
