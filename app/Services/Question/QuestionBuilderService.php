<?php

declare(strict_types=1);

namespace App\Services\Question;

final class QuestionBuilderService
{
    public static function build(
        int $id,
        array $input,
        ?array $existing = null
    ): array {
        $now = date(DATE_ATOM);

        return array_merge(
            QuestionMetadataBuilderService::build(
                $id,
                $existing,
                $now
            ),
            [
                'taxonomy' => self::buildTaxonomy($input, $existing),
                'difficulty' => self::inputOrExisting(
                    $input,
                    'difficulty',
                    $existing,
                    ''
                ),
                'type' => self::inputOrExisting(
                    $input,
                    'type',
                    $existing,
                    'multiple_choice'
                ),
                'question' => self::inputOrExisting(
                    $input,
                    'question',
                    $existing,
                    ''
                ),
                'options' => self::buildOptions($input, $existing),
                'explanation' => self::inputOrExisting(
                    $input,
                    'explanation',
                    $existing,
                    ''
                ),
            ]
        );
    }

    private static function buildOptions(
        array $input,
        ?array $existing = null
    ): array {
        $hasOptionInput = false;

        for ($i = 1; $i <= 4; $i++) {
            if (
                array_key_exists("option_{$i}", $input)
                || array_key_exists('correct_option', $input)
            ) {
                $hasOptionInput = true;
                break;
            }
        }

        if (
            !$hasOptionInput
            && is_array($existing['options'] ?? null)
        ) {
            return $existing['options'];
        }

        $options = [];

        for ($i = 1; $i <= 4; $i++) {
            $optionId = "option-{$i}";

            $options[] = [
                'id' => $optionId,
                'text' => trim(
                    (string) (
                        $input["option_{$i}"]
                        ?? ($existing['options'][$i - 1]['text'] ?? '')
                    )
                ),
                'correct' => (
                    ($input['correct_option'] ?? '') === $optionId
                ),
            ];
        }

        return $options;
    }

    private static function buildTaxonomy(
        array $input,
        ?array $existing
    ): array {
        $taxonomy = is_array($existing['taxonomy'] ?? null)
            ? $existing['taxonomy']
            : [];

        return [
            'board_id' => self::inputOrExisting(
                $input,
                'board_id',
                $existing,
                $taxonomy['board_id'] ?? '',
                'board'
            ),
            'subject_id' => self::inputOrExisting(
                $input,
                'subject_id',
                $existing,
                $taxonomy['subject_id'] ?? '',
                'subject'
            ),
            'domain_id' => self::inputOrExisting(
                $input,
                'domain_id',
                $existing,
                $taxonomy['domain_id'] ?? '',
                'domain'
            ),
            'topic_id' => self::inputOrExisting(
                $input,
                'topic_id',
                $existing,
                $taxonomy['topic_id'] ?? '',
                'topic'
            ),
            'concept_id' => self::inputOrExisting(
                $input,
                'concept_id',
                $existing,
                $taxonomy['concept_id'] ?? '',
                'concept'
            ),
        ];
    }

    private static function inputOrExisting(
        array $input,
        string $key,
        ?array $existing,
        string $default,
        ?string $legacyKey = null
    ): string {
        $value = $input[$key]
            ?? (
                $legacyKey !== null
                    ? ($input[$legacyKey] ?? null)
                    : null
            )
            ?? ($existing[$key] ?? $default);

        return trim((string) $value);
    }
}
