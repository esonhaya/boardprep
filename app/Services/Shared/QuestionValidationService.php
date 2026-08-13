<?php

declare(strict_types=1);

namespace App\Services\Shared;

final class QuestionValidationService
{
    public static function validate(array $question): array
    {
        $errors = [];

        self::validateIdentity($question, $errors);
        self::validateTaxonomy($question, $errors);
        self::validateQuestion($question, $errors);
        self::validateOptions($question, $errors);
        self::validateExplanation($question, $errors);

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    private static function validateIdentity(array $question, array &$errors): void
    {
        if (trim((string) ($question['id'] ?? '')) === '') {
            $errors[] = 'Missing ID';
        }
    }

    private static function validateTaxonomy(array $question, array &$errors): void
    {
        $taxonomy = $question['taxonomy'] ?? [];

        if (!is_array($taxonomy)) {
            $errors[] = 'Invalid taxonomy';
            return;
        }

        self::requireTaxonomyValue($taxonomy, 'board_id', 'Missing board', $errors);
        self::requireTaxonomyValue($taxonomy, 'subject_id', 'Missing subject', $errors);
        self::requireTaxonomyValue($taxonomy, 'domain_id', 'Missing domain', $errors);
        self::requireTaxonomyValue($taxonomy, 'topic_id', 'Missing topic', $errors);
        self::requireTaxonomyValue($taxonomy, 'concept_id', 'Missing concept', $errors);
    }

    private static function requireTaxonomyValue(
        array $taxonomy,
        string $key,
        string $message,
        array &$errors
    ): void {
        if (trim((string) ($taxonomy[$key] ?? '')) === '') {
            $errors[] = $message;
        }
    }

    private static function validateQuestion(array $question, array &$errors): void
    {
        if (trim((string) ($question['difficulty'] ?? '')) === '') {
            $errors[] = 'Missing difficulty';
        }

        if (trim((string) ($question['type'] ?? '')) === '') {
            $errors[] = 'Missing question type';
        }

        if (trim((string) ($question['question'] ?? '')) === '') {
            $errors[] = 'Missing question';
        }
    }

    private static function validateOptions(array $question, array &$errors): void
    {
        $options = $question['options'] ?? [];

        if (!is_array($options)) {
            $errors[] = 'Invalid options';
            return;
        }

        if (count($options) < 2) {
            $errors[] = 'At least two options are required.';
            return;
        }

        $texts = [];
        $correctCount = 0;

        foreach ($options as $option) {
            if (!is_array($option)) {
                $errors[] = 'Invalid option structure.';
                continue;
            }

            $text = trim((string) ($option['text'] ?? ''));

            if ($text === '') {
                $errors[] = 'Option text cannot be empty.';
                continue;
            }

            $normalized = self::normalizeText($text);

            if (in_array($normalized, $texts, true)) {
                $errors[] = 'Options must be unique.';
            }

            $texts[] = $normalized;

            if (($option['correct'] ?? false) === true) {
                $correctCount++;
            }
        }

        if ($correctCount === 0) {
            $errors[] = 'A correct option is required.';
        }

        if ($correctCount > 1) {
            $errors[] = 'Only one correct option is allowed.';
        }
    }

    private static function validateExplanation(array $question, array &$errors): void
    {
        if (trim((string) ($question['explanation'] ?? '')) === '') {
            $errors[] = 'Missing explanation';
        }
    }

    private static function normalizeText(string $text): string
    {
        $text = trim($text);
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;

        return strtolower($text);
    }
}
