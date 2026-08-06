<?php

declare(strict_types=1);

final class SubjectAssemblyService
{
    public static function assemble(
        array $questions,
        QuizSpecification $specification
    ): array {

        return QuestionSelectionService::select(
            $questions,
            $specification
        );

    }
}
