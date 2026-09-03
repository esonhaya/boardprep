<?php

declare(strict_types=1);

namespace App\Services\Flashcard;

use InvalidArgumentException;
use App\Support\Slugger;

final class FlashcardCardKeyBuilder
{
    public static function build(
        array $question,
        string $cardType,
        string $revision = 'r1'
    ): string {
        $questionId = self::required($question['id'] ?? null, 'question ID');
        $conceptId = self::required(
            $question['taxonomy']['concept_id'] ?? null,
            'concept ID'
        );
        $cardType = Slugger::slugify($cardType);
        $revision = Slugger::slugify($revision);

        if ($cardType === 'item' || $revision === 'item') {
            throw new InvalidArgumentException('Card type and revision must be meaningful.');
        }

        return 'q:' . $questionId . ':' . $cardType . ':' . Slugger::slugify($conceptId) . ':' . $revision;
    }

    private static function required(mixed $value, string $label): string
    {
        if (!is_scalar($value) || trim((string) $value) === '') {
            throw new InvalidArgumentException("Missing {$label}.");
        }

        return trim((string) $value);
    }
}
