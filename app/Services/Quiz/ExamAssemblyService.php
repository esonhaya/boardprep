<?php

declare(strict_types=1);

final class ExamAssemblyService
{
    public static function assemble(
        array $questions,
        QuizSpecification $specification
    ): array {

        return QuizGenerationService::generate(

            $questions,

            $specification

        )->questions;

    }
}
