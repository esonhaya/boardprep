<?php

declare(strict_types=1);

namespace App\Services\Question\Query;

final class QuestionQueryPipeline
{
    public static function apply(array $questions, QuestionQueryFilters $filters): array
    {
        return array_values(
            array_filter(
                $questions,
                static function (mixed $question) use ($filters): bool {
                    if (!is_array($question)) {
                        return false;
                    }

                    return QuestionFilterMatcher::matches($question, $filters)
                        && QuestionSearchMatcher::matches($question, $filters->search)
                        && self::matchesTechnicalStatus($question, $filters);
                }
            )
        );
    }

    private static function matchesTechnicalStatus(array $question, QuestionQueryFilters $filters): bool
    {
        if ($filters->technicalStatus === '') {
            return true;
        }

        $hasTechnicalIssue = isset($filters->technicalIssueIds[(string) ($question['id'] ?? '')]);
        return $filters->technicalStatus === 'issue' ? $hasTechnicalIssue : !$hasTechnicalIssue;
    }
}
