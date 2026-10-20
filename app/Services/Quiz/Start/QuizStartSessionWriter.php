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
            'quiz_completed',
            'quiz_result',
            'flashcard_missed_scope',
            'exam_answer_revision',
            'exam_reviewed_revision',
        ] as $key) {
            \SessionService::remove($key);
        }
    }

    public static function write(
        object $specification,
        array $questions,
        array $coverage = [],
        array $issues = [],
        array $studyContext = []
    ): void
    {
        self::clear();
        \SessionService::set(
            'quiz_session',
            QuizStartSessionPayloadFactory::create($specification, $questions, $coverage, $issues, $studyContext)
        );
        \SessionService::set('questions', $questions);
        \SessionService::set('answers', []);
        \SessionService::set('feedback', null);
        \SessionService::set('mode', $specification->mode);
        \SessionService::set('exam_answer_revision', 0);
        \SessionService::remove('attempt_persisted');
        \SessionService::remove('quiz_completed');
        \SessionService::remove('quiz_result');
    }
}
