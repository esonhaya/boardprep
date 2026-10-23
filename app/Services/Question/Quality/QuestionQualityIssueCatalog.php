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
            'INVALID_QUESTION_SOURCE_REFERENCE' => 'sourceIntegrity',
            'QUESTION_REUSE_REVIEW_HOLD' => 'eligibilityReview',
            'SOURCE_URL_CONFIRMED_404' => 'sourceAvailability',
            'SOURCE_URL_ACCESS_RESTRICTED' => 'sourceAvailability',
            'SOURCE_TEXT_ACCESS_LIMITED' => 'sourceAvailability',
            'SOURCE_URL_CHECK_INCONCLUSIVE' => 'sourceAvailability',
            'SOURCE_URL_AUDIT_UNAVAILABLE' => 'sourceSnapshot',
            'SOURCE_URL_AUDIT_CORRUPT' => 'sourceSnapshot',
            'SOURCE_URL_AUDIT_STALE' => 'sourceSnapshot',
            'SOURCE_URL_AUDIT_PARTIAL' => 'sourceSnapshot',
            'ORPHAN_STUDY_MATERIAL' => 'standaloneMaterials',
            'STANDALONE_MATERIAL_DISCOVERABLE_REVIEW' => 'standaloneMaterials',
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
            'sourceIntegrity' => 'Question source references',
            'eligibilityReview' => 'Question eligibility review',
            'sourceAvailability' => 'Source reachability and text access',
            'sourceSnapshot' => 'Source audit evidence',
            'standaloneMaterials' => 'Standalone study materials',
        ];
    }
}
