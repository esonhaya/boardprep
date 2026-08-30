<?php

declare(strict_types=1);

namespace App\Services\Quiz\Start;

final class QuizStartInputNormalizer
{
    public static function normalize(array $input): array
    {
        $topic = trim((string) ($input['topic'] ?? ''));

        return [
            'board' => (string) ($input['exam'] ?? 'LET'),
            'subject' => (string) ($input['subject'] ?? ''),
            'domain' => ($input['domain'] ?? null) ?: null,
            'topics' => $topic === '' ? [] : [$topic],
            'difficulty' => (string) ($input['difficulty'] ?? 'mixed'),
            'count' => (int) ($input['count'] ?? 10),
            'mode' => (string) ($input['mode'] ?? 'practice'),
            'adaptive' => isset($input['adaptive']),
            'shuffle' => true,
        ];
    }
}
