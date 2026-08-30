<?php

declare(strict_types=1);

namespace App\Services\Quiz\ResultAction;

use App\Services\Quiz\Start\QuizStartInputNormalizer;

final class QuizResultActionContext
{
    public static function fromSession(array $session): array
    {
        $topic = self::firstTopic($session);

        $normalized = QuizStartInputNormalizer::normalize([
            'topic' => $topic,
            'subject' => $session['subject'] ?? '',
            'mode' => $session['mode'] ?? '',
            'difficulty' => $session['difficulty'] ?? '',
            'count' => $session['question_count'] ?? $session['count'] ?? 10,
        ]);

        return [
            'topic' => $normalized['topics'][0] ?? '',
            'subject' => $normalized['subject'],
            'mode' => $normalized['mode'],
            'difficulty' => $normalized['difficulty'],
            'count' => $normalized['count'],
        ];
    }

    private static function firstTopic(array $session): string
    {
        if (is_array($session['topics'] ?? null)) {
            foreach ($session['topics'] as $topic) {
                $text = self::text($topic);
                if ($text !== '') {
                    return $text;
                }
            }
        }

        return self::text($session['topic'] ?? '');
    }

    private static function text(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }

}
