<?php

declare(strict_types=1);

namespace App\Services\Quiz\Start;

final class QuizStartSpecificationFactory
{
    public static function create(array $input): \QuizSpecification
    {
        return \BaseSpecificationFactory::create(
            QuizStartInputNormalizer::normalize($input)
        );
    }
}
