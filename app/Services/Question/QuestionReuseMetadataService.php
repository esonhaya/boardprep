<?php

declare(strict_types=1);

namespace App\Services\Question;

final class QuestionReuseMetadataService
{
    public const POLICIES = ['AUTO', 'REVIEW', 'MANUAL_ONLY'];
    public const DEPTHS = [1, 2, 3, 4, 5, 'UNKNOWN'];
    public const CONTEXT_MODES = ['NEUTRAL', 'PROFESSIONAL', 'REGULATORY', 'MIXED', 'UNKNOWN'];
    public const STATUSES = ['VERIFIED', 'INFERRED', 'REVIEW_REQUIRED'];
    public const PROVENANCE_TYPES = [
        'OFFICIAL_BLUEPRINT',
        'OFFICIAL_SOURCE',
        'TAXONOMY_IDENTITY',
        'EXISTING_VERIFIED_MAPPING',
        'BATCH605_VERIFIED',
        'BATCH608_AUDIT',
        'LEGACY_MIGRATION',
        'CONTENT_MARKERS',
        'ADMIN_VERIFIED',
        'MANUAL_REVIEW',
        'OTHER',
    ];

    public static function forAuthoring(array $question, ?array $existing = null): array
    {
        if (is_array($existing) && self::validate($existing) === []) {
            return $existing;
        }

        $context = self::contextMode($question);
        return [
            'reuse_policy' => 'REVIEW',
            'required_depth' => 'UNKNOWN',
            'context_mode' => $context,
            'metadata_status' => 'REVIEW_REQUIRED',
            'metadata_provenance' => [[
                'type' => 'TAXONOMY_IDENTITY',
                'reference' => (string)(($question['taxonomy']['concept_id'] ?? '') ?: 'new-question'),
                'note' => 'Safe authoring default; advanced reuse compatibility remains subject to review.',
                'verified_status' => 'INFERRED',
            ]],
        ];
    }

    public static function migrate(array $question, bool $shared, bool $batch605Verified): array
    {
        $context = self::contextMode($question);
        $provenance = [[
            'type' => 'LEGACY_MIGRATION',
            'reference' => 'batch-609',
            'note' => 'Generated from the existing canonical question without changing content.',
            'verified_status' => 'VERIFIED',
        ], [
            'type' => 'BATCH608_AUDIT',
            'reference' => 'c9a436f3b6b03ebdfb99e1ff8c690d55258d1d7619591f084fa38209f7e05520',
            'note' => 'Context/reuse classification follows the deterministic Batch 608 audit.',
            'verified_status' => 'VERIFIED',
        ]];
        if ($shared) {
            $provenance[] = [
                'type' => 'EXISTING_VERIFIED_MAPPING',
                'reference' => 'question-eligibility',
                'note' => 'Question already has direct eligibility for multiple boards.',
                'verified_status' => 'VERIFIED',
            ];
        }
        if ($batch605Verified) {
            $provenance[] = [
                'type' => 'BATCH605_VERIFIED',
                'reference' => 'question-eligibility.audit.batch=605',
                'note' => 'Existing Batch 605 eligibility evidence was preserved.',
                'verified_status' => 'VERIFIED',
            ];
        }
        if ($context !== 'NEUTRAL') {
            $provenance[] = [
                'type' => 'CONTENT_MARKERS',
                'reference' => (string)($question['id'] ?? ''),
                'note' => 'Explicit question flags or professional/regulatory markers determined context mode.',
                'verified_status' => 'INFERRED',
            ];
        }

        return [
            'reuse_policy' => $context === 'REGULATORY' && !$shared ? 'MANUAL_ONLY' : 'REVIEW',
            'required_depth' => 'UNKNOWN',
            'context_mode' => $context,
            'metadata_status' => $shared ? 'VERIFIED' : 'INFERRED',
            'metadata_provenance' => $provenance,
        ];
    }

    public static function validate(mixed $metadata): array
    {
        if (!is_array($metadata)) {
            return ['REUSE_METADATA_INVALID'];
        }
        $errors = [];
        $policy = $metadata['reuse_policy'] ?? null;
        if (!is_string($policy) || !in_array($policy, self::POLICIES, true)) {
            $errors[] = 'INVALID_REUSE_POLICY';
        }
        $depth = $metadata['required_depth'] ?? null;
        if (!(is_int($depth) && in_array($depth, [1, 2, 3, 4, 5], true)) && $depth !== 'UNKNOWN') {
            $errors[] = 'INVALID_REQUIRED_DEPTH';
        }
        if (($policy ?? null) === 'AUTO' && $depth === 'UNKNOWN') {
            $errors[] = 'AUTO_WITH_UNKNOWN_DEPTH';
        }
        $context = $metadata['context_mode'] ?? null;
        if (!is_string($context) || !in_array($context, self::CONTEXT_MODES, true)) {
            $errors[] = 'INVALID_CONTEXT_MODE';
        }
        $status = $metadata['metadata_status'] ?? null;
        if (!is_string($status) || !in_array($status, self::STATUSES, true)) {
            $errors[] = 'INVALID_METADATA_STATUS';
        }
        foreach (self::validateProvenance($metadata['metadata_provenance'] ?? null) as $error) {
            $errors[] = 'METADATA_' . $error;
        }
        return array_values(array_unique($errors));
    }

    /**
     * Provenance is deliberately structured so a later audit can explain a
     * reuse decision without making ordinary question authoring fill it in.
     */
    public static function validateProvenance(mixed $provenance): array
    {
        if (!is_array($provenance) || $provenance === []) {
            return ['MISSING_PROVENANCE'];
        }

        foreach ($provenance as $record) {
            if (!is_array($record)
                || !is_string($record['type'] ?? null)
                || !in_array($record['type'], self::PROVENANCE_TYPES, true)
                || !is_string($record['reference'] ?? null)
                || trim($record['reference']) === ''
                || !is_string($record['note'] ?? null)
                || trim($record['note']) === ''
                || !in_array($record['verified_status'] ?? null, self::STATUSES, true)) {
                return ['INVALID_PROVENANCE'];
            }
        }

        return [];
    }

    public static function contextMode(array $question): string
    {
        foreach (['legal_procedure_application', 'ethics_scope_application'] as $field) {
            if (!empty($question[$field])) return 'REGULATORY';
        }
        $text = self::normalize(implode(' ', [
            (string)($question['question'] ?? ''),
            (string)($question['explanation'] ?? ''),
            (string)(($question['taxonomy']['concept_id'] ?? '')),
        ]));
        foreach (['national building code', 'philippine electrical code', 'nursing practice act', 'criminal procedure', 'auditing standard'] as $marker) {
            if (str_contains($text, $marker)) return 'REGULATORY';
        }
        foreach (['professional_judgment', 'context_necessary', 'forensic_interpretation', 'correctional_application', 'technical_reasoning', 'site_plan_spatial'] as $field) {
            if (!empty($question[$field])) return 'PROFESSIONAL';
        }
        $board = strtolower(trim((string)(($question['taxonomy']['board_id'] ?? $question['board'] ?? ''))));
        $concept = strtolower(trim((string)(($question['taxonomy']['concept_id'] ?? ''))));
        if ($board !== '' && !in_array($board, ['let', 'civil-service'], true)
            && preg_match('/(^|[-_])(cpa|ce|me|ree|arch|nle|ple|cle)([-_]|$)/', $concept) === 1) {
            return 'PROFESSIONAL';
        }
        if ($board === '' || $concept === '') return 'UNKNOWN';
        return 'NEUTRAL';
    }

    private static function normalize(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?? '';
        return trim(preg_replace('/\s+/', ' ', $value) ?? '');
    }
}
