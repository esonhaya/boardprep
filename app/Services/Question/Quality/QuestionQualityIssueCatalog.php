<?php

declare(strict_types=1);

namespace App\Services\Question\Quality;

final class QuestionQualityIssueCatalog
{
    /** @return array<string,string> */
    public static function legacyBuckets(): array
    {
        return [
            'draft' => 'drafts',
            'missing-explanation' => 'missingExplanation',
            'invalid-answer' => 'invalidAnswers',
            'missing-choices' => 'missingChoices',
            'duplicate-choices' => 'duplicateChoices',
            'empty-question' => 'emptyQuestion',
        ];
    }

    /** @return array<string,string> */
    public static function labels(): array
    {
        return [
            'drafts' => 'Draft questions',
            'missingExplanation' => 'Missing explanations',
            'invalidAnswers' => 'Invalid answers',
            'missingChoices' => 'Missing choices',
            'duplicateChoices' => 'Duplicate choices',
            'emptyQuestion' => 'Empty questions',
        ];
    }
}
