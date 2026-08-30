<?php

declare(strict_types=1);

namespace App\Services\Quiz\Start;

final class QuizStartSessionPayloadFactory
{
    public static function create(object $specification, array $questions): array
    {
        return [
            'id' => 'quiz-' . bin2hex(random_bytes(8)),
            'board' => $specification->board,
            'subject' => $specification->subject,
            'domain' => $specification->domain,
            'topics' => $specification->topics,
            'mode' => $specification->mode,
            'difficulty' => $specification->difficulty,
            'question_count' => count($questions),
            'question_ids' => QuizStartQuestionIdExtractor::fromQuestions($questions),
            'started_at' => date('c'),
        ];
    }
}
