<?php

declare(strict_types=1);

namespace App\Services\Blueprint\Creation;

final class BlueprintIdGenerator
{
    public static function generate(
        string $boardId,
        string $subjectId,
        int $version
    ): string {
        return strtolower(
            self::segment($boardId, 'board')
            . '-'
            . self::segment($subjectId, 'subject')
            . '-v'
            . $version
        );
    }

    private static function segment(string $value, string $fallback): string
    {
        $segment = preg_replace('/[^a-zA-Z0-9_-]+/', '-', trim($value));
        $segment = trim((string) $segment, '-');

        return $segment !== '' ? $segment : $fallback;
    }
}
