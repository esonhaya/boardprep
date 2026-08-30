<?php

declare(strict_types=1);

namespace App\Services\Learning\Recommendation;

final class LearningAttemptContext
{
    public static function fromAttempt(array $attempt): array
    {
        $learning = is_array($attempt['learning_context'] ?? null)
            ? $attempt['learning_context']
            : [];

        return [
            'subject' => self::text($attempt['subject'] ?? ($learning['subject'] ?? '')),
            'topic' => self::topic($attempt, $learning),
        ];
    }

    private static function topic(array $attempt, array $learning): string
    {
        foreach ([$attempt['topic'] ?? null, $learning['topic'] ?? null, $attempt['domain'] ?? null] as $value) {
            $text = self::text($value);
            if ($text !== '') {
                return $text;
            }
        }

        return 'General';
    }

    private static function text(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }
}
