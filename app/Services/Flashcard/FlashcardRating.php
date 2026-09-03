<?php

declare(strict_types=1);

namespace App\Services\Flashcard;

use InvalidArgumentException;

final class FlashcardRating
{
    public const AGAIN = 'again';
    public const HARD = 'hard';
    public const GOOD = 'good';
    public const EASY = 'easy';

    /** @return list<string> */
    public static function all(): array
    {
        return [self::AGAIN, self::HARD, self::GOOD, self::EASY];
    }

    public static function normalize(string $rating): string
    {
        $normalized = strtolower(trim($rating));
        if (!in_array($normalized, self::all(), true)) {
            throw new InvalidArgumentException('Invalid flashcard rating.');
        }

        return $normalized;
    }
}
