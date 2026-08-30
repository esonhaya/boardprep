<?php

declare(strict_types=1);

namespace App\Services\Quiz\LearningContext;

final class QuizAttemptContextResolver
{
    public static function resolve(array $attempt, array $session, array $questions): array
    {
        $topics = QuizTopicContextResolver::topics($attempt, $session, $questions);

        return [
            'board' => self::field('board', $attempt, $session, $questions),
            'subject' => self::field('subject', $attempt, $session, $questions),
            'domain' => self::field('domain', $attempt, $session, $questions),
            'topic' => QuizContextValueReader::first([
                $attempt['topic'] ?? null,
                $topics[0] ?? null,
            ]),
            'topics' => $topics,
            'mode' => QuizContextValueReader::first([
                $attempt['mode'] ?? null,
                $session['mode'] ?? null,
                'practice',
            ]),
            'difficulty' => QuizContextValueReader::first([
                $attempt['difficulty'] ?? null,
                $session['difficulty'] ?? null,
                'mixed',
            ]),
        ];
    }

    private static function field(string $field, array $attempt, array $session, array $questions): string
    {
        return QuizContextValueReader::first([
            $attempt[$field] ?? null,
            $session[$field] ?? null,
            QuizQuestionTaxonomyReader::values($questions, $field)[0] ?? null,
        ]);
    }
}
