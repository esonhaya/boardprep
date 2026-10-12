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
        $timeLimitMinutes = $specification->mode === 'exam'
            ? max(0, (int) ($specification->timeLimitMinutes ?? 0))
            : 0;
        $timeLimitSeconds = $timeLimitMinutes * 60;
        return [
            'id' => 'quiz-' . bin2hex(random_bytes(8)),
            'board' => $specification->board,
            'subject' => $specification->subject,
            'domain' => $specification->domain,
            'topics' => $specification->topics,
            'mode' => $specification->mode,
            'session_type' => $specification->mode === 'exam' ? 'exam_simulation' : 'quiz',
            'timer_mode' => $timeLimitSeconds > 0 ? 'timed' : 'untimed',
            'time_limit_minutes' => $timeLimitMinutes,
            'time_limit_seconds' => $timeLimitSeconds,
            'deadline_epoch' => $timeLimitSeconds > 0 ? time() + $timeLimitSeconds : null,
            'time_expired' => false,
            'let_variant' => $specification->letVariant,
            'let_level' => $specification->letVariant['level'] ?? null,
            'let_specialization_id' => $specification->letVariant['specialization_id'] ?? null,
            'civil_service_level' => $specification->civilServiceLevel,
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
