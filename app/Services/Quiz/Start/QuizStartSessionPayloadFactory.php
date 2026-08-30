<?php

declare(strict_types=1);

namespace App\Services\Quiz\Start;

final class QuizStartSessionPayloadFactory
{
    public static function create(
        object $specification,
        array $questions,
        array $coverage = [],
        array $issues = []
    ): array
    {
        return [
            'id' => 'quiz-' . bin2hex(random_bytes(8)),
            'board' => $specification->board,
            'subject' => $specification->subject,
            'domain' => $specification->domain,
            'topics' => $specification->topics,
            'mode' => $specification->mode,
            'session_type' => $specification->mode === 'exam' ? 'exam_simulation' : 'quiz',
            'difficulty' => $specification->difficulty,
            'requested_question_count' => $specification->questionCount,
            'question_count' => count($questions),
            'question_ids' => QuizStartQuestionIdExtractor::fromQuestions($questions),
            'started_at' => date('c'),
            'blueprint_coverage' => $coverage,
            'blueprint_issues' => $issues,
        ];
    }
}
