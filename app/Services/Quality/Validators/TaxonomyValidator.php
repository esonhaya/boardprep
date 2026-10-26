<?php

declare(strict_types=1);

namespace App\Services\Quality\Validators;

final class TaxonomyValidator
{
    /** @return array<int, array{severity:string,type:string,message:string}> */
    public static function validate(array $question): array
    {
        $issues = [];
        $taxonomy = is_array($question['taxonomy'] ?? null)
            ? $question['taxonomy']
            : [];

        foreach (['domain', 'topic', 'concept'] as $field) {
            $value = $taxonomy[$field . '_id']
                ?? $question[$field . '_id']
                ?? $question[$field]
                ?? null;

            if (is_scalar($value) && trim((string) $value) !== '') {
                continue;
            }

            $invalidType = $value !== null && !is_scalar($value);
            $issues[] = [
                'severity' => 'error',
                'type' => ($invalidType ? 'invalid-' : 'missing-') . $field,
                'message' => $invalidType
                    ? ucfirst($field) . ' must be a scalar taxonomy identifier.'
                    : ucfirst($field) . ' is missing.',
            ];
        }

        return $issues;
    }
}
