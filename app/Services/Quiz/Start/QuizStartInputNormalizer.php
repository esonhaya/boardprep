<?php

declare(strict_types=1);

namespace App\Services\Quiz\Start;

final class QuizStartInputNormalizer
{
    public static function normalize(array $input): array
    {
        $topic = self::text($input['topic'] ?? '');
        $difficulty = strtolower(self::text($input['difficulty'] ?? 'mixed'));
        if (!in_array($difficulty, ['easy', 'medium', 'hard', 'mixed'], true)) {
            $difficulty = 'mixed';
        }
        $mode = self::text($input['mode'] ?? 'practice');
        if (!in_array($mode, ['practice', 'exam', 'review'], true)) {
            $mode = 'practice';
        }
        $defaultCount = $mode === 'exam' ? 150 : 10;
        $rawCount = $input['count'] ?? null;
        $numericCount = is_numeric($rawCount) ? (float) $rawCount : NAN;
        $count = is_finite($numericCount)
            ? (int) round($numericCount)
            : $defaultCount;

        return [
            'board' => self::text($input['exam'] ?? 'LET') ?: 'LET',
            'subject' => self::text($input['subject'] ?? '') ?: 'English',
            'domain' => self::nullableText($input['domain'] ?? null),
            'topics' => $topic === '' ? [] : [$topic],
            'difficulty' => $difficulty,
            'count' => max(1, min($mode === 'exam' ? 150 : 20, $count)),
            'mode' => $mode,
            'adaptive' => isset($input['adaptive']),
            'shuffle' => true,
        ];
    }

    private static function text(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }

    private static function nullableText(mixed $value): ?string
    {
        $text = self::text($value);
        return $text === '' ? null : $text;
    }
}
