<?php

declare(strict_types=1);

namespace App\Services\Quiz\Start;

use App\Services\Question\QuestionEligibilityIndex;

final class QuizStartPreparationService
{
    public static function prepare(array $input, array $questions, ?QuestionEligibilityIndex $eligibilityIndex = null): QuizStartPreparation
    {
        $specification = QuizStartSpecificationFactory::create($input);
        $result = \QuizGenerationService::generate($questions, $specification, $eligibilityIndex);

        return new QuizStartPreparation(
            $specification,
            $result->questions,
            $result->coverage,
            $result->issues
        );
    }
}
