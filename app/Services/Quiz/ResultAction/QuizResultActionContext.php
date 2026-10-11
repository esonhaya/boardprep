<?php

declare(strict_types=1);

namespace App\Services\Quiz\ResultAction;

use App\Services\Quiz\Start\QuizStartInputNormalizer;

final class QuizResultActionContext
{
    public static function fromSession(array $session): array
    {
        $topic = self::firstTopic($session);
        $board = self::firstText([
            $session['board'] ?? null,
            $session['exam'] ?? null,
            $session['learning_context']['board'] ?? null,
        ]);
        $letVariant = is_array($session['let_variant'] ?? null)
            ? $session['let_variant']
            : [];

        $normalized = QuizStartInputNormalizer::normalize([
            'exam' => $board,
            'civil_service_level' => $session['civil_service_level'] ?? '',
            'let_level' => $session['let_level'] ?? $letVariant['level'] ?? '',
            'let_specialization' => $session['let_specialization_id']
                ?? $session['let_specialization']
                ?? $letVariant['specialization_id']
                ?? '',
            'topic' => $topic,
            'subject' => $session['subject'] ?? '',
            'mode' => $session['mode'] ?? '',
            'difficulty' => $session['difficulty'] ?? '',
            'count' => $session['question_count'] ?? $session['count'] ?? 10,
        ]);

        return [
            'board' => $normalized['board'],
            'has_board_context' => $board !== '',
            'civil_service_level' => $normalized['civil_service_level'],
            'let_level' => $normalized['let_level'],
            'let_specialization' => $normalized['let_specialization'],
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

}
