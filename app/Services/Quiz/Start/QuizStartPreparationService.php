<?php

declare(strict_types=1);

namespace App\Services\Quiz\Start;

final class QuizStartPreparationService
{
    public static function prepare(array $input, array $questions): QuizStartPreparation
    {
        $specification = QuizStartSpecificationFactory::create($input);
        $result = \QuizGenerationService::generate($questions, $specification);

        return new QuizStartPreparation(
            $specification,
            $result->questions,
            $result->coverage,
            $result->issues
        );
    }
}
