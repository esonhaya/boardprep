<?php

declare(strict_types=1);

namespace App\Services\Quiz\Start;

final class QuizStartQuestionIdExtractor
{
    public static function fromQuestions(array $questions): array
    {
        return array_values(array_filter(array_map(
            static fn(mixed $question): ?string => is_array($question) && isset($question['id'])
                ? (string) $question['id']
                : null,
            $questions
        )));
    }
}
