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
        $letVariant = self::firstArray([
            $attempt['let_variant'] ?? null,
            $learning['let_variant'] ?? null,
        ]);

        return [
            'subject' => self::text($attempt['subject'] ?? ($learning['subject'] ?? '')),
            'topic' => self::topic($attempt, $learning),
            'board' => self::firstText([
                $attempt['board'] ?? null,
                $attempt['exam'] ?? null,
                $learning['board'] ?? null,
            ]),
            'let_level' => self::firstText([
                $attempt['let_level'] ?? null,
                $attempt['let_variant']['level'] ?? null,
                $learning['let_level'] ?? null,
                $letVariant['level'] ?? null,
            ]),
            'let_specialization' => self::firstText([
                $attempt['let_specialization_id'] ?? null,
                $attempt['let_specialization'] ?? null,
                $attempt['let_variant']['specialization_id'] ?? null,
                $learning['let_specialization_id'] ?? null,
                $learning['let_specialization'] ?? null,
                $letVariant['specialization_id'] ?? null,
            ]),
            'civil_service_level' => self::firstText([
                $attempt['civil_service_level'] ?? null,
                $learning['civil_service_level'] ?? null,
            ]),
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

    private static function firstText(array $values): string
    {
        foreach ($values as $value) {
            $text = self::text($value);
            if ($text !== '') {
                return $text;
            }
        }

        return '';
    }

    private static function firstArray(array $values): array
    {
        foreach ($values as $value) {
            if (is_array($value)) {
                return $value;
            }
        }

        return [];
    }
}
