<?php

declare(strict_types=1);

namespace App\Services\RepositoryHealth\Engine;

/**
 * Stable catalog for the canonical RepositoryHealthEngine checks.
 *
 * The catalog is intentionally descriptive: the engine remains the only
 * implementation of the scan, while CLI, CI, tests, and developer views use
 * the same codes, ownership, severity discipline, and fix guidance.
 */
final class RepositoryHealthCheckCatalog
{
    /**
     * @return array<string, array{category: string, owner: string, default_severity: string, entity_type: string, description: string, fix_guidance: string}>
     */
    public static function definitions(): array
    {
        return [
            'STRUCTURED_CONTENT' => self::definition('STRUCTURED_CONTENT', 'StructuredContentService', 'FAIL', 'question', 'Canonical structured-content schema and active study metadata.', 'Repair the content block through the canonical producer contract.'),
            'FLASHCARD_DERIVABILITY' => self::definition('FLASHCARDS', 'FlashcardDerivationService', 'FAIL', 'question', 'Production-equivalent flashcard derivation for eligible questions.', 'Repair derivation inputs or the production card contract.'),
            'FLASHCARD_EXCLUSIONS' => self::definition('FLASHCARDS', 'FlashcardExclusionRegistry', 'FAIL', 'repository_record', 'Explicit, reviewable flashcard exclusion records.', 'Add complete evidence or derive the card; never hide a defect as an exclusion.'),
            'CARD_KEYS' => self::definition('FLASHCARDS', 'FlashcardCardValidator', 'FAIL', 'question', 'Stable and unique derived card identities.', 'Repair the card identity inputs and source chain.'),
            'QUESTION_COUNTS' => self::definition('COUNTS', 'QuestionPoolFilter', 'FAIL', 'board', 'Canonical, effective-declared, and runtime board counts.', 'Repair the production selection or eligibility mapping.'),
            'TRACEABILITY' => self::definition('TRACEABILITY', 'ContentIntegrityService', 'FAIL', 'question', 'Question-to-study and source-chain traceability.', 'Repair the study link and linked source chain.'),
            'TAXONOMY' => self::definition('TAXONOMY', 'QuestionValidationService', 'FAIL', 'question', 'Taxonomy identifiers, hierarchy, and structural references.', 'Repair the taxonomy path using existing taxonomy records.'),
            'ELIGIBILITY' => self::definition('ELIGIBILITY', 'QuestionEligibilityIndex', 'FAIL', 'question', 'Production eligibility mappings and reachability.', 'Repair eligibility records or the production index.'),
            'DEPTH_COVERAGE' => self::definition('DEPTH_COVERAGE', 'RepositoryHealthEngine', 'WARN', 'question', 'Depth and coverage metadata, including explicit unknown values.', 'Review unknown metadata without inferring semantic correctness.'),
            'SOURCE_STUDY' => self::definition('SOURCE_STUDY', 'ContentIntegrityService', 'FAIL', 'repository_record', 'Source and study record linkage and deterministic references.', 'Repair the source/study record or its internal link.'),
            'CALCULATION_METADATA' => self::definition('CALCULATION', 'CalculationMetadataContract', 'FAIL', 'question', 'Calculation metadata contract and deterministic contradictions.', 'Persist the canonical calculation payload or correct the calculation flag.'),
            'VERSION_CURRENTNESS' => self::definition('CURRENTNESS', 'RepositoryHealthEngine', 'REVIEW', 'question', 'Deterministic currentness debt and review-status metadata.', 'Review currentness against the applicable authority and effective period.'),
            'SOURCE_READINESS' => self::definition('SOURCE_READINESS', 'SourceReadinessValidator', 'FAIL', 'taxonomy_leaf', 'Per-leaf source, study, scope, depth, and bounded expansion readiness.', 'Repair the readiness record or resolve its source/study evidence before authoring.'),
            'BALANCE_DIVERSITY' => self::definition('BALANCE', 'RepositoryHealthEngine', 'WARN', 'board', 'Active leaf balance, documented area deltas, and cheap concentration signals.', 'Use the signal for planning; do not rewrite valid content solely to rebalance.'),
            'REPOSITORY_HEALTH_FOUNDATION' => self::definition('REPOSITORY', 'RepositoryHealthEngine', 'PASS', 'repository', 'Canonical machine-readable health facade, catalog, and action queue.', 'Keep the single RepositoryHealthEngine entrypoint available to CLI, CI, and UI.'),
        ];
    }

    /** @return array{category: string, owner: string, default_severity: string, entity_type: string, description: string, fix_guidance: string} */
    private static function definition(string $category, string $owner, string $severity, string $entityType, string $description, string $fixGuidance): array
    {
        return [
            'category' => $category,
            'owner' => $owner,
            'default_severity' => $severity,
            'entity_type' => $entityType,
            'description' => $description,
            'fix_guidance' => $fixGuidance,
        ];
    }
}
