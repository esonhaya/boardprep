<?php

declare(strict_types=1);

final class QuizSpecification
{
    public function __construct(

        public readonly string $board,

        public readonly string $subject,

        public readonly ?string $domain,

        public readonly array $topics,

        public readonly array $concepts,

        public readonly string $difficulty,

        public readonly int $questionCount,

        public readonly string $mode,

        public readonly bool $adaptive,

        public readonly bool $shuffle,

        public readonly ?int $boardBlueprintVersion,

        public readonly ?int $subjectBlueprintVersion,

    ) {
    }
}
