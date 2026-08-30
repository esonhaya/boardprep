<?php

declare(strict_types=1);

namespace App\Services\Quality\Validators\Content;

final class QuestionTextValidator
{
    private const SHORT_THRESHOLD = 15;

    public static function validate(string $text): array
    {
        if ($text === '') {
            return [ContentIssueFactory::create('error', 'empty-question', 'Question text is empty.')];
        }

        if (ContentLength::lessThan($text, self::SHORT_THRESHOLD)) {
            return [ContentIssueFactory::create('warning', 'short-question', 'Question text is unusually short.')];
        }

        return [];
    }
}
