<?php

declare(strict_types=1);

namespace App\Services\Question\Quality;

final class QuestionQualityIssueGrouper
{
    /** @param array<int,object> $issues */
    public static function group(array $issues): array
    {
        $buckets = [];
        foreach (QuestionQualityIssueCatalog::labels() as $key => $_label) {
            $buckets[$key] = [];
        }

        $byCode = [];
        $unclassified = [];
        $catalog = QuestionQualityIssueCatalog::legacyBuckets();

        foreach ($issues as $issue) {
            $code = (string) ($issue->code ?? 'unknown');
            $byCode[$code][] = $issue;

            if (isset($catalog[$code])) {
                $buckets[$catalog[$code]][] = $issue;
                continue;
            }

            $unclassified[] = $issue;
        }

        return $buckets + [
            'byCode' => $byCode,
            'unclassifiedIssues' => $unclassified,
        ];
    }
}
