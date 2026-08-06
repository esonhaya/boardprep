<?php

declare(strict_types=1);

final class BlueprintExecutionResult
{
    public function __construct(

        public readonly array $questions,

        public readonly array $requests,

        public readonly array $coverage,

        public readonly array $issues,

        public readonly ?int $boardBlueprintVersion,

        public readonly ?int $subjectBlueprintVersion,

    ) {
    }
}
