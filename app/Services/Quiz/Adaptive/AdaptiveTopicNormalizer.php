<?php

declare(strict_types=1);

final class AdaptiveTopicNormalizer
{
    public static function normalize(mixed $topic): string
    {
        return strtolower(trim((string) $topic));
    }
}
