<?php

declare(strict_types=1);

namespace App\Services\Quiz\Start;

final class QuizStartViewModelFactory
{
    public static function create(object $specification, array $questions): array
    {
        return [
            'question' => $questions[0],
            'current' => 0,
            'total' => count($questions),
            'mode' => $specification->mode,
            'feedback' => null,
        ];
    }
}
