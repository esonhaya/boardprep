<?php

declare(strict_types=1);

namespace App\Services\Quality\Validators\Content;

final class ExplanationValidator
{
    private const SHORT_THRESHOLD = 20;

    public static function validate(string $explanation): array
    {
        if ($explanation === '') {
            return [ContentIssueFactory::create('warning', 'missing-explanation', 'Explanation is missing.')];
        }

        if (ContentLength::lessThan($explanation, self::SHORT_THRESHOLD)) {
            return [ContentIssueFactory::create('info', 'short-explanation', 'Explanation is very short.')];
        }

        return [];
    }
}
