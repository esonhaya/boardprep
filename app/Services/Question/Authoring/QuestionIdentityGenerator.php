<?php

declare(strict_types=1);

namespace App\Services\Question\Authoring;

final class QuestionIdentityGenerator
{
    public static function resolve(int|string $requestedId, ?array $existing): int|string
    {
        if (trim((string) $requestedId) !== '' && trim((string) $requestedId) !== '0') {
            return $requestedId;
        }

        $existingId = $existing['id'] ?? null;
        if (is_int($existingId) || (is_string($existingId) && trim($existingId) !== '')) {
            return $existingId;
        }

        return uniqid('q');
    }
}
