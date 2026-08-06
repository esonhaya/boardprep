<?php

declare(strict_types=1);

final class QuizGenerationService
{
    public static function generate(
        array $questions,
        array $options = []
    ): array {

        return QuizEngineService::generate(
            $questions,
            $options
        );

    }
}
