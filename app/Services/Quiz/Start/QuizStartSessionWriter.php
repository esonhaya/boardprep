<?php

declare(strict_types=1);

namespace App\Services\Quiz\Start;

final class QuizStartSessionWriter
{
    public static function clear(): void
    {
        foreach ([
            'quiz_session',
            'questions',
            'answers',
            'feedback',
            'mode',
            'currentQuestion',
            'attempt_persisted',
            'quiz_attempt_id',
            'quiz_completed',
            'quiz_result',
            'flashcard_missed_scope',
            'exam_answer_revision',
            'exam_reviewed_revision',
            'exam_review_flags',
            'exam_review_flag_versions',
            'exam_review_flag_session_id',
        ] as $key) {
            \SessionService::remove($key);
        }
    }

    public static function write(
        object $specification,
        array $questions,
        array $coverage = [],
        array $issues = [],
        array $studyContext = [],
        ?string $selectionExplanation = null
    ): void
    {
        self::clear();
        $session = QuizStartSessionPayloadFactory::create(
            $specification,
            $questions,
            $coverage,
            $issues,
            $studyContext,
            $selectionExplanation
        );
        \SessionService::set('quiz_session', $session);
        \SessionService::set('questions', $questions);
        \SessionService::set('answers', []);
        \SessionService::set('feedback', null);
        \SessionService::set('mode', $specification->mode);
        \SessionService::set('exam_answer_revision', 0);
        \SessionService::set('exam_review_flags', []);
        \SessionService::set('exam_review_flag_versions', []);
        \SessionService::set('exam_review_flag_session_id', (string) ($session['id'] ?? ''));
        \SessionService::remove('attempt_persisted');
        \SessionService::remove('quiz_completed');
        \SessionService::remove('quiz_result');
    }
}
