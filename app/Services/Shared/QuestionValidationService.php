<?php

declare(strict_types=1);

namespace App\Services\Shared;

final class QuestionValidationService
{
    public static function validate(array $question): array
    {
        $errors = [];

        $errors = array_merge($errors, \App\Services\Question\StructuredContentService::validate($question));

        self::validateIdentity($question, $errors);
        self::validateTaxonomy($question, $errors);
        self::validateQuestion($question, $errors);
        self::validateStatus($question, $errors);
        self::validateOptions($question, $errors);
        self::validateExplanation($question, $errors);
        self::validateReuseMetadata($question, $errors);
        self::validateCalculationMetadata($question, $errors);

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    private static function validateIdentity(array $question, array &$errors): void
    {
        if (!self::nonEmptyScalar($question['id'] ?? null)) {
            $errors[] = 'Missing ID';
        }
    }

    private static function validateTaxonomy(array $question, array &$errors): void
    {
        $taxonomy = $question['taxonomy'] ?? [];

        if (array_key_exists('taxonomy', $question) && !is_array($taxonomy)) {
            $errors[] = 'Invalid taxonomy';
            return;
        }

        $taxonomy = is_array($taxonomy) ? $taxonomy : [];

        foreach (['board', 'subject', 'domain', 'topic', 'concept'] as $name) {
            $value = $taxonomy[$name . '_id'] ?? $question[$name . '_id'] ?? $question[$name] ?? null;
            if (!self::nonEmptyScalar($value)) {
                $errors[] = 'Missing ' . $name;
            }
        }

        if ($taxonomy !== []) {
            self::validateCanonicalTaxonomy($taxonomy, $errors);
        }
    }

    private static function validateCanonicalTaxonomy(array $taxonomy, array &$errors): void
    {
        $collections = [
            'board_id' => 'boards',
            'subject_id' => 'subjects',
            'domain_id' => 'taxonomy/domains',
            'topic_id' => 'taxonomy/topics',
            'concept_id' => 'taxonomy/concepts',
        ];
        foreach ($collections as $field => $collection) {
            $value = $taxonomy[$field] ?? null;
            if (!self::nonEmptyScalar($value)) {
                continue;
            }
            if (TaxonomyStorageService::find($collection, (string) $value) === null) {
                $errors[] = 'Invalid ' . str_replace('_id', '', $field);
            }
        }

        self::validateTaxonomyHierarchy($taxonomy, $errors);
    }

    private static function validateTaxonomyHierarchy(array $taxonomy, array &$errors): void
    {
        $links = [
            ['collection' => 'taxonomy/domains', 'id' => 'domain_id', 'parent' => 'subject_id'],
            ['collection' => 'taxonomy/topics', 'id' => 'topic_id', 'parent' => 'domain_id'],
            ['collection' => 'taxonomy/concepts', 'id' => 'concept_id', 'parent' => 'topic_id'],
        ];

        foreach ($links as $link) {
            $record = TaxonomyStorageService::find(
                $link['collection'],
                (string) ($taxonomy[$link['id']] ?? '')
            );
            if ($record !== null
                && (string) ($record[$link['parent']] ?? '') !== (string) ($taxonomy[$link['parent']] ?? '')) {
                $errors[] = 'Inconsistent taxonomy hierarchy';
                return;
            }
        }

        if (TaxonomyStorageService::boardSubjects() === []) {
            return;
        }
        if (TaxonomyStorageService::hasBoardSubject(
            (string) ($taxonomy['board_id'] ?? ''),
            (string) ($taxonomy['subject_id'] ?? '')
        )) {
            return;
        }
        $errors[] = 'Inconsistent board and subject taxonomy';
    }

    private static function validateQuestion(array $question, array &$errors): void
    {
        $difficulty = self::text($question['difficulty'] ?? null);
        if ($difficulty === '') {
            $errors[] = 'Missing difficulty';
        } elseif (!in_array(strtolower($difficulty), ['easy', 'medium', 'hard'], true)) {
            $errors[] = 'Invalid difficulty';
        }

        $type = self::text($question['type'] ?? 'multiple_choice');
        if ($type === '') {
            $errors[] = 'Missing question type';
        } elseif ($type !== 'multiple_choice') {
            $errors[] = 'Invalid question type';
        }

        if (self::text($question['question'] ?? null) === '') {
            $errors[] = 'Missing question';
        }
    }

    private static function validateOptions(array $question, array &$errors): void
    {
        if (array_key_exists('options', $question)) {
            self::validateCanonicalOptions($question['options'], $errors);
            return;
        }

        self::validateLegacyChoices($question, $errors);
    }

    private static function validateStatus(array $question, array &$errors): void
    {
        if (!array_key_exists('status', $question)) {
            return;
        }
        $status = strtolower(self::text($question['status']));
        if (!in_array($status, ['draft', 'active', 'approved', 'archived'], true)) {
            $errors[] = 'Invalid status';
        }
    }

    private static function validateCanonicalOptions(mixed $options, array &$errors): void
    {

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

            $text = self::text($option['text'] ?? null);

            if ($text === '') {
                $errors[] = 'Option text cannot be empty.';
                continue;
            }

            $normalized = self::normalizeText($text);

            if (in_array($normalized, $texts, true)) {
                $errors[] = 'Options must be unique.';
            }

            $texts[] = $normalized;

            if (array_key_exists('correct', $option) && !is_bool($option['correct'])) {
                $errors[] = 'Option correct flag must be boolean.';
            } elseif (($option['correct'] ?? false) === true) {
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

    private static function validateLegacyChoices(array $question, array &$errors): void
    {
        $choices = $question['choices'] ?? null;
        if (!is_array($choices) || count($choices) < 2) {
            $errors[] = 'At least two choices are required.';
            return;
        }

        $normalized = [];
        foreach ($choices as $choice) {
            $text = self::text($choice);
            if ($text === '') {
                $errors[] = 'Choice text cannot be empty.';
                continue;
            }
            $key = self::normalizeText($text);
            if (isset($normalized[$key])) {
                $errors[] = 'Choices must be unique.';
            }
            $normalized[$key] = true;
        }

        $answer = self::text($question['answer'] ?? null);
        if ($answer === '' || !isset($normalized[self::normalizeText($answer)])) {
            $errors[] = 'The correct answer must match an available choice.';
        }
    }

    private static function validateExplanation(array $question, array &$errors): void
    {
        if (self::text($question['explanation'] ?? null) === '') {
            $errors[] = 'Missing explanation';
        }
    }

    private static function validateReuseMetadata(array $question, array &$errors): void
    {
        if (!array_key_exists('reuse', $question)) {
            return;
        }

        foreach (\App\Services\Question\QuestionReuseMetadataService::validate($question['reuse']) as $error) {
            $errors[] = $error;
        }
    }

    private static function validateCalculationMetadata(array $question, array &$errors): void
    {
        $contract = \App\Services\Question\CalculationMetadataContract::inspect($question);
        if (($contract['valid'] ?? false) === true) {
            return;
        }

        $errors[] = (string) ($contract['message'] ?? 'Invalid calculation metadata');
    }

    private static function normalizeText(string $text): string
    {
        $text = trim($text);
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;

        return strtolower($text);
    }

    private static function nonEmptyScalar(mixed $value): bool
    {
        return is_scalar($value) && trim((string) $value) !== '';
    }

    private static function text(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }
}
