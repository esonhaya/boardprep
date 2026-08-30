<?php

declare(strict_types=1);

namespace App\Services\Quiz\ResultAction;

final class QuizResultActionContext
{
    public static function fromSession(array $session): array
    {
        $topic = self::firstTopic($session);

        return [
            'topic' => $topic,
            'subject' => self::text($session['subject'] ?? '') ?: 'English',
            'mode' => self::text($session['mode'] ?? '') ?: 'practice',
            'difficulty' => self::text($session['difficulty'] ?? '') ?: 'mixed',
            'count' => min(20, max(1, self::integer($session['question_count'] ?? $session['count'] ?? 10, 10))),
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

    private static function integer(mixed $value, int $fallback): int
    {
        return is_numeric($value) ? (int) round((float) $value) : $fallback;
    }
}
