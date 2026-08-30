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
            'subject' => self::text($session['subject'] ?? ''),
            'mode' => self::text($session['mode'] ?? '') ?: 'practice',
            'difficulty' => self::text($session['difficulty'] ?? '') ?: 'mixed',
            'count' => max(1, (int) ($session['question_count'] ?? $session['count'] ?? 10)),
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
