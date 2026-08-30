<?php

declare(strict_types=1);

namespace App\Services\Question\Query;

final class QuestionSearchMatcher
{
    public static function matches(array $question, string $search): bool
    {
        $needle = strtolower(trim($search));

        if ($needle === '') {
            return true;
        }

        foreach (self::searchableValues($question) as $value) {
            if (str_contains(strtolower($value), $needle)) {
                return true;
            }
        }

        return false;
    }

    private static function searchableValues(array $question): array
    {
        return [
            QuestionValueReader::text($question, 'question'),
            QuestionValueReader::taxonomy($question, 'subject_id'),
            QuestionValueReader::taxonomy($question, 'domain_id'),
            QuestionValueReader::taxonomy($question, 'topic_id'),
            QuestionValueReader::taxonomy($question, 'concept_id'),
        ];
    }
}
