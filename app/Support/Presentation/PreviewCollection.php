<?php

declare(strict_types=1);

namespace App\Support\Presentation;

final class PreviewCollection
{
    public const DEFAULT_LIMIT = 5;

    /** @return array<int,mixed> */
    public static function items(array $items, int $limit = self::DEFAULT_LIMIT): array
    {
        return array_slice($items, 0, max(0, $limit));
    }

    public static function hasMore(array $items, int $limit = self::DEFAULT_LIMIT): bool
    {
        return count($items) > $limit;
    }
}
