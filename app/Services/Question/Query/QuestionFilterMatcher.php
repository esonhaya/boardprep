<?php

declare(strict_types=1);

namespace App\Services\Question\Query;

final class QuestionFilterMatcher
{
    public static function matches(
        array $question,
        QuestionQueryFilters $filters
    ): bool {
        if ($filters->status !== ''
            && QuestionValueReader::text($question, 'status') !== $filters->status
        ) {
            return false;
        }

        if (
            $filters->domain !== ''
            && QuestionValueReader::taxonomy($question, 'domain_id') !== $filters->domain
        ) {
            return false;
        }

        if (
            $filters->difficulty !== ''
            && QuestionValueReader::text($question, 'difficulty') !== $filters->difficulty
        ) {
            return false;
        }

        if (
            $filters->topic !== ''
            && QuestionValueReader::taxonomy($question, 'topic_id') !== $filters->topic
        ) {
            return false;
        }

        return true;
    }
}
