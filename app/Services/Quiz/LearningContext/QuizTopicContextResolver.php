<?php

declare(strict_types=1);

namespace App\Services\Quiz\LearningContext;

final class QuizTopicContextResolver
{
    public static function topics(array $attempt, array $session, array $questions): array
    {
        foreach ([
            $attempt['topics'] ?? null,
            $session['topics'] ?? null,
        ] as $candidate) {
            $topics = self::normalizeList($candidate);
            if ($topics !== []) {
                return $topics;
            }
        }

        $single = QuizContextValueReader::first([
            $attempt['topic'] ?? null,
            $session['topic'] ?? null,
        ]);
        if ($single !== '') {
            return [$single];
        }

        return QuizQuestionTaxonomyReader::values($questions, 'topic');
    }

    private static function normalizeList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $topics = [];
        foreach ($value as $topic) {
            $text = QuizContextValueReader::text($topic);
            if ($text !== '' && !in_array($text, $topics, true)) {
                $topics[] = $text;
            }
        }

        return $topics;
    }
}
