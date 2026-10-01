<?php

declare(strict_types=1);

namespace App\Services\Flashcard;

use App\Services\Question\StructuredContentService;

final class FlashcardCardValidator
{
    /** @return string[] */
    public static function validate(array $card): array
    {
        $errors = [];
        foreach (['card_key', 'question_id', 'card_type', 'prompt', 'answer', 'explanation'] as $field) {
            if (!is_scalar($card[$field] ?? null) || trim((string) $card[$field]) === '') {
                $errors[] = 'MALFORMED_CARD:' . $field;
            }
        }

        $key = trim((string) ($card['card_key'] ?? ''));
        if ($key !== '' && preg_match('/^q:[^:]+:[a-z0-9-]+:[a-z0-9-]+:r[0-9]+$/', $key) !== 1) {
            $errors[] = 'INVALID_CARD_KEY';
        }

        $types = ['concept', 'reasoning', 'formula_or_data', 'misconception'];
        if (!in_array((string) ($card['card_type'] ?? ''), $types, true)) {
            $errors[] = 'MALFORMED_CARD:card_type';
        }

        $prompt = trim((string) ($card['prompt'] ?? ''));
        $answer = trim((string) ($card['answer'] ?? ''));
        if ($prompt !== '' && $answer !== '' && self::containsAnswer($prompt, $answer)) {
            $errors[] = 'ANSWER_LEAKAGE_IN_PROMPT';
        }

        $references = is_array($card['study_references'] ?? null) ? $card['study_references'] : [];
        if ($references === []) {
            $errors[] = 'BROKEN_CARD_SOURCE';
        } else {
            $hasSource = false;
            foreach ($references as $reference) {
                if (!is_array($reference)) {
                    $errors[] = 'MALFORMED_CARD:study_reference';
                    continue;
                }
                foreach ((array) ($reference['source_ids'] ?? []) as $sourceId) {
                    if (is_scalar($sourceId) && trim((string) $sourceId) !== '') {
                        $hasSource = true;
                    }
                }
            }
            if (!$hasSource) {
                $errors[] = 'BROKEN_CARD_SOURCE';
            }
        }

        $errors = array_merge($errors, array_map(
            static fn(string $message): string => 'MALFORMED_CARD:' . $message,
            StructuredContentService::validate($card)
        ));

        return array_values(array_unique($errors));
    }

    private static function containsAnswer(string $prompt, string $answer): bool
    {
        $normalize = static function (string $value): string {
            $value = mb_strtolower(trim($value));
            return preg_replace('/\s+/u', ' ', $value) ?? $value;
        };

        $answer = $normalize($answer);
        if ($answer === '' || mb_strlen($answer) < 4) {
            return false;
        }

        return str_contains($normalize($prompt), $answer);
    }
}
