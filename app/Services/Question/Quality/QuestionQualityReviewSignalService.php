<?php

declare(strict_types=1);

namespace App\Services\Question\Quality;

use App\Core\App;
use App\Repositories\QuestionRepository;
use App\Services\Question\AcademicReviewProposalService;
use App\Services\Question\AcademicReviewWorkflowService;
use App\Services\Study\SourceRegistryService;
use App\Services\Quality\Validators\QuestionValidator;
use Throwable;

/** Read-only projection of current B695 workflow and B717 readiness evidence. */
final class QuestionQualityReviewSignalService
{
    private const READINESS_PATH = '/docs/reports/batch-717-cohort-readiness.json';

    /**
     * @return list<array<string, mixed>>
     */
    public static function proposalSignals(): array
    {
        $root = dirname(__DIR__, 4);
        $readiness = self::currentReadiness($root);
        if ($readiness === null) {
            return [];
        }

        $proposalsById = [];
        foreach (AcademicReviewProposalService::all() as $proposal) {
            $id = trim((string) ($proposal['id'] ?? ''));
            if ($id !== '') {
                $proposalsById[$id] = $proposal;
            }
        }

        $workflow = new AcademicReviewWorkflowService();
        $canonicalStemIndex = self::canonicalStemIndex();
        $sourceRegistryById = [];
        foreach (SourceRegistryService::all() as $source) {
            $sourceId = trim((string) ($source['id'] ?? ''));
            if ($sourceId !== '') {
                $sourceRegistryById[$sourceId] = $source;
            }
        }
        $proposalStemGroups = [];
        $signals = [];
        foreach ($readiness['items'] as $readinessItem) {
            if (!is_array($readinessItem)) {
                continue;
            }
            $proposalId = trim((string) ($readinessItem['proposal_id'] ?? ''));
            $questionId = trim((string) ($readinessItem['question_id'] ?? ''));
            if ($proposalId === '' || $questionId === '') {
                continue;
            }

            $proposal = $proposalsById[$proposalId] ?? null;
            if (!is_array($proposal)) {
                $signals[] = self::signal(
                    $proposalId,
                    $questionId,
                    '',
                    '',
                    'FAIL',
                    'TECHNICAL_ISSUE',
                    'PROPOSAL_READINESS_RECORD_MISSING',
                    'The readiness evidence has no matching proposal record.',
                    'Reconcile the proposal inventory and readiness report before review.'
                );
                continue;
            }

            try {
                $record = $workflow->record($proposalId);
                $version = $workflow->versionForProposalRecord($proposal, $record);
            } catch (Throwable) {
                $signals[] = self::signal(
                    $proposalId,
                    $questionId,
                    '',
                    '',
                    'FAIL',
                    'TECHNICAL_ISSUE',
                    'ACADEMIC_WORKFLOW_RECORD_UNAVAILABLE',
                    'The current academic workflow record could not be read safely.',
                    'Repair the workflow record through its existing recovery procedure.'
                );
                continue;
            }

            $state = strtoupper(trim((string) ($record['state'] ?? 'UNKNOWN')));
            $snapshotVersion = trim((string) ($readinessItem['question_version_sha256'] ?? ''));
            $versionCurrent = $snapshotVersion !== '' && hash_equals($snapshotVersion, $version);
            $currentQuestion = self::currentQuestion($proposal, $record);
            $validationFindings = self::validateProposalQuestion($proposal, $currentQuestion);
            $deterministicFindings = array_values(array_filter(
                $validationFindings,
                static fn(array $finding): bool => in_array(strtolower((string) ($finding['severity'] ?? '')), ['error', 'warning'], true)
            ));
            $sourceReadiness = is_array($readinessItem['source_readiness'] ?? null)
                ? $readinessItem['source_readiness']
                : [];
            $taxonomy = is_array($readinessItem['taxonomy'] ?? null) ? $readinessItem['taxonomy'] : [];
            $study = is_array($readinessItem['study_relationship'] ?? null)
                ? $readinessItem['study_relationship']
                : [];
            $sourceIds = self::sourceIds($proposal);
            $sourceIntegrity = self::sourceSnapshotIntegrity($sourceReadiness, $proposal, $sourceIds, $sourceRegistryById);
            $sourceReviews = is_array($record['source_reviews'] ?? null) ? $record['source_reviews'] : [];
            $pendingSourceIds = self::pendingSourceReviews($sourceIds, $sourceReviews, $version);
            $currentDecision = self::latestCurrentDecision($record, $version);
            $confirmations = is_array($currentDecision['confirmations'] ?? null)
                ? $currentDecision['confirmations']
                : [];
            $hasCurrentApproval = self::hasCurrentApproval($record, $version);
            $boardId = trim((string) ($proposal['board_id'] ?? $readinessItem['board']['id'] ?? ''));
            $sourceScopeFlags = array_values(array_filter(
                (array) ($sourceReadiness['source_scope_flags'] ?? []),
                'is_string'
            ));
            $currentnessFlags = array_values(array_filter(
                (array) ($sourceReadiness['currentness_flags'] ?? []),
                'is_string'
            ));
            $sourceSnapshotsCurrent = $sourceIntegrity['proposal_current'] && $sourceIntegrity['readiness_current'];
            $readinessEvidenceCurrent = $versionCurrent && $sourceIntegrity['readiness_current'];
            $technicalIssueCount = count($deterministicFindings) + ($sourceIntegrity['proposal_current'] ? 0 : 1);
            $baseEvidence = [
                'proposal_id' => $proposalId,
                'question_id' => $questionId,
                'question_version_sha256' => $version,
                'readiness_version_sha256' => $snapshotVersion,
                'readiness_version_status' => $versionCurrent ? 'CURRENT' : 'STALE',
                'current_status' => $state,
                'source_ids' => $sourceIds,
                'pending_source_review_ids' => $pendingSourceIds,
                'source_snapshot_status' => (string) ($sourceReadiness['citation_source_snapshot_status'] ?? 'UNKNOWN'),
                'source_registry_snapshots_current' => $sourceSnapshotsCurrent,
                'source_snapshot_validation_status' => $sourceIntegrity['proposal_current'] ? 'CURRENT' : 'MISMATCH',
                'readiness_source_snapshot_validation_status' => $sourceIntegrity['readiness_current'] ? 'CURRENT' : 'STALE_OR_MISMATCH',
                'source_identity_mismatch_ids' => $sourceIntegrity['identity_mismatch_ids'],
                'source_registry_mismatch_ids' => $sourceIntegrity['registry_mismatch_ids'],
                'readiness_source_mismatch_ids' => $sourceIntegrity['readiness_mismatch_ids'],
                'currentness_flags' => $currentnessFlags,
                'source_scope_flags' => $sourceScopeFlags,
                'taxonomy_topic_id' => (string) ($taxonomy['topic_id'] ?? ''),
                'taxonomy_topic_registered' => ($taxonomy['topic_registered'] ?? false) === true,
                'taxonomy_concept_id' => (string) ($taxonomy['concept_id'] ?? ''),
                'taxonomy_concept_registered' => ($taxonomy['concept_registered'] ?? false) === true,
                'study_material_id' => (string) ($study['material_id'] ?? ''),
                'study_section_id' => (string) ($study['section_id'] ?? ''),
                'study_status' => (string) ($study['status'] ?? 'UNKNOWN'),
                'study_import_requirement' => (string) ($study['import_requirement'] ?? ''),
                'technical_status' => $technicalIssueCount === 0 ? 'NO_DETECTED_TECHNICAL_ISSUE' : 'TECHNICAL_ISSUE',
                'technical_validator' => 'QuestionValidator and source-reference integrity checks',
                'technical_validation_status' => $technicalIssueCount === 0 ? 'STRUCTURE_AND_REFERENCES_VALID' : 'DETERMINISTIC_FINDINGS',
                'technical_issue_count' => $technicalIssueCount,
            ];

            if (!$sourceIntegrity['proposal_current']) {
                $signals[] = self::signal(
                    $proposalId,
                    $questionId,
                    $boardId,
                    $state,
                    'FAIL',
                    'TECHNICAL_ISSUE',
                    'PROPOSAL_SOURCE_REFERENCE_INTEGRITY_MISMATCH',
                    'Proposal source IDs, source snapshots, or current registry records do not agree.',
                    'Reconcile the proposal source references against the current source registry before any review or import decision.',
                    $version,
                    $baseEvidence + ['technical_issue_type' => 'SOURCE_REFERENCE_INTEGRITY']
                );
            } elseif (!$sourceIntegrity['readiness_current']) {
                $signals[] = self::signal(
                    $proposalId,
                    $questionId,
                    $boardId,
                    $state,
                    'WARN',
                    'REVIEW_EVIDENCE_STALE',
                    'PROPOSAL_SOURCE_READINESS_SNAPSHOT_STALE',
                    'The readiness report source rows do not match the proposal source identities and current registry records.',
                    'Rebuild the readiness evidence from the current proposal and source registry before relying on its scope or currentness flags.',
                    $version,
                    $baseEvidence
                );
            }

            $normalizedStem = self::normalizeStem($currentQuestion['question'] ?? $currentQuestion['stem'] ?? '');
            if ($normalizedStem !== '') {
                $canonicalDuplicates = self::exactCanonicalDuplicateIds(
                    $normalizedStem,
                    $canonicalStemIndex,
                    $questionId
                );
                if ($canonicalDuplicates !== []) {
                    $signals[] = self::signal(
                        $proposalId,
                        $questionId,
                        $boardId,
                        $state,
                        'REVIEW',
                        'DUPLICATE_REVIEW_REQUIRED',
                        'EXACT_CANONICAL_STEM_DUPLICATE',
                        'The proposal stem exactly matches normalized text in the canonical question bank.',
                        'Compare the cited scope and question version with each matching canonical record; do not import or rewrite automatically.',
                        $version,
                        $baseEvidence + [
                            'duplicate_scope' => 'CANONICAL_QUESTION_BANK',
                            'duplicate_match_method' => 'lower-case and whitespace-normalized exact stem',
                            'duplicate_question_ids' => $canonicalDuplicates,
                        ]
                    );
                }
                $proposalStemGroups[$normalizedStem][] = [
                    'proposal_id' => $proposalId,
                    'question_id' => $questionId,
                    'board_id' => $boardId,
                    'current_status' => $state,
                    'question_version_sha256' => $version,
                    'evidence' => $baseEvidence,
                ];
            }

            if (!$versionCurrent) {
                $signals[] = self::signal(
                    $proposalId,
                    $questionId,
                    $boardId,
                    $state,
                    'WARN',
                    'REVIEW_EVIDENCE_STALE',
                    'PROPOSAL_READINESS_VERSION_STALE',
                    'The readiness inventory describes a different question version than the current workflow record.',
                    'Refresh the readiness inventory before using its source, taxonomy, or study classifications.',
                    $version,
                    $baseEvidence
                );
            }

            if ($deterministicFindings === [] && $sourceIntegrity['proposal_current']) {
                $signals[] = self::signal(
                    $proposalId,
                    $questionId,
                    $boardId,
                    $state,
                    'INFO',
                    'NO_DETECTED_TECHNICAL_ISSUE',
                    'QUESTION_STRUCTURE_VALIDATION_CLEAR',
                    'Deterministic structure, choice, answer-to-option, explanation-presence, and taxonomy-field validation found no technical issue. This does not establish factual correctness or academic approval.',
                    'Continue with the separate source, answer-key, explanation, taxonomy, study, and academic review gates.',
                    $version,
                    $baseEvidence
                );
            } else {
                foreach ($deterministicFindings as $finding) {
                    $findingSeverity = strtolower((string) ($finding['severity'] ?? 'warning'));
                    $codeSuffix = strtoupper((string) preg_replace('/[^A-Za-z0-9]+/', '_', (string) ($finding['type'] ?? 'STRUCTURE')));
                    $signals[] = self::signal(
                        $proposalId,
                        $questionId,
                        $boardId,
                        $state,
                        $findingSeverity === 'error' ? 'FAIL' : 'WARN',
                        'TECHNICAL_ISSUE',
                        'QUESTION_VALIDATION_' . trim($codeSuffix, '_'),
                        (string) ($finding['message'] ?? 'Deterministic question structure validation reported a finding.'),
                        'Repair the deterministic structure issue in the proposal workflow, then revalidate the exact version.',
                        $version,
                        $baseEvidence + [
                            'validator_issue_type' => (string) ($finding['type'] ?? ''),
                            'validator_severity' => $findingSeverity,
                        ]
                    );
                }
            }

            $pendingStates = ['PENDING_REVIEW', 'ASSIGNED', 'IN_REVIEW', 'REVISION_REQUESTED', 'DEFERRED'];
            if (in_array($state, $pendingStates, true) && !$hasCurrentApproval) {
                $signals[] = self::signal(
                    $proposalId,
                    $questionId,
                    $boardId,
                    $state,
                    'REVIEW',
                    'ACADEMIC_APPROVAL_PENDING',
                    'ACADEMIC_APPROVAL_PENDING',
                    'This noncanonical proposal has no approval for its current version.',
                    'Wait for an independently qualified reviewer to record a version-bound academic decision.',
                    $version,
                    $baseEvidence
                );
            }

            if ($pendingSourceIds !== []) {
                $signals[] = self::signal(
                    $proposalId,
                    $questionId,
                    $boardId,
                    $state,
                    'REVIEW',
                    'SOURCE_REVIEW_REQUIRED',
                    'SOURCE_REVIEW_REQUIRED',
                    'One or more cited sources lack a complete review for this exact question version.',
                    'Have a qualified reviewer inspect each cited source and record the source identity, locator, scope, and version-bound finding.',
                    $version,
                    $baseEvidence
                );
            }

            foreach ([
                'question_accuracy' => [
                    'FACTUAL_VERIFICATION_REQUIRED',
                    'FACTUAL_VERIFICATION_REQUIRED',
                    'The current-version review record does not confirm independent question accuracy.',
                    'Verify the factual claim against the cited material and record the genuine reviewer finding.',
                    'FACTUAL_VERIFICATION',
                ],
                'correct_answer' => [
                    'ANSWER_KEY_REVIEW_REQUIRED',
                    'ANSWER_KEY_REVIEW_REQUIRED',
                    'The current-version review record does not confirm the designated correct answer.',
                    'Independently validate the answer key against the source and available choices.',
                    'ANSWER_KEY',
                ],
                'explanation_correctness' => [
                    'EXPLANATION_REVIEW_REQUIRED',
                    'EXPLANATION_REVIEW_REQUIRED',
                    'The current-version review record does not confirm explanation correctness.',
                    'Review the explanation for accuracy and support from the cited source.',
                    'EXPLANATION',
                ],
            ] as $check => [$issueType, $code, $message, $action, $category]) {
                if (($confirmations[$check] ?? false) !== true) {
                    $signals[] = self::signal(
                        $proposalId,
                        $questionId,
                        $boardId,
                        $state,
                        'REVIEW',
                        $issueType,
                        $code,
                        $message,
                        $action,
                        $version,
                        $baseEvidence + ['review_category' => $category]
                    );
                }
            }

            if ($readinessEvidenceCurrent) {
                foreach ($currentnessFlags as $flag) {
                    $signals[] = self::signal(
                        $proposalId,
                        $questionId,
                        $boardId,
                        $state,
                        'WARN',
                        'SOURCE_CURRENTNESS_WARNING',
                        $flag,
                        'The current readiness evidence carries a source currentness warning: ' . $flag . '.',
                        'Recheck this proposal against current official material and preserve the scope of any historical reference.',
                        $version,
                        $baseEvidence
                    );
                }
                foreach ($sourceScopeFlags as $flag) {
                    $signals[] = self::signal(
                        $proposalId,
                        $questionId,
                        $boardId,
                        $state,
                        'WARN',
                        'SOURCE_SCOPE_WARNING',
                        $flag,
                        'The current readiness evidence identifies a source-scope interpretation still requiring review.',
                        'Resolve the policy-scope interpretation with an appropriately qualified human reviewer.',
                        $version,
                        $baseEvidence
                    );
                }
            }

            $mapping = is_array($record['taxonomy_mapping'] ?? null) ? $record['taxonomy_mapping'] : [];
            $taxonomyMappingCurrent = ($mapping['proposal_version'] ?? '') === $version
                && AcademicReviewWorkflowService::taxonomyErrors($mapping) === [];
            $taxonomyAlreadyRegistered = ($taxonomy['topic_registered'] ?? false) === true
                && ($taxonomy['concept_registered'] ?? false) === true;
            if ($readinessEvidenceCurrent && !$taxonomyMappingCurrent && !$taxonomyAlreadyRegistered) {
                $signals[] = self::signal(
                    $proposalId,
                    $questionId,
                    $boardId,
                    $state,
                    'REVIEW',
                    'TAXONOMY_MAPPING_REQUIRED',
                    'TAXONOMY_MAPPING_REQUIRED',
                    'The proposal has no valid current-version mapping to active taxonomy entries.',
                    'Use the authorized taxonomy reconciliation workflow; do not activate a mapping automatically.',
                    $version,
                    $baseEvidence
                );
            }

            $studyMapping = is_array($record['study_mapping'] ?? null) ? $record['study_mapping'] : [];
            $studyMappingCurrent = ($studyMapping['proposal_version'] ?? '') === $version
                && trim((string) ($studyMapping['material_id'] ?? '')) !== ''
                && trim((string) ($studyMapping['section_id'] ?? '')) !== '';
            if ($readinessEvidenceCurrent
                && !$studyMappingCurrent
                && ($study['status'] ?? '') === 'MISSING_SECTION_OPTIONAL') {
                $signals[] = self::signal(
                    $proposalId,
                    $questionId,
                    $boardId,
                    $state,
                    'INFO',
                    'STUDY_SECTION_MISSING',
                    'STUDY_SECTION_MISSING_OPTIONAL',
                    'No exact study section is recorded in the readiness snapshot; this is optional enrichment.',
                    'If enrichment is useful, propose an exact existing section through the authorized study-link workflow. This is not an import blocker by itself.',
                    $version,
                    $baseEvidence
                );
            }

            $scopeOrCurrentnessHold = $currentnessFlags !== [] || $sourceScopeFlags !== [];
            $readyForReview = $versionCurrent
                && $sourceSnapshotsCurrent
                && !$scopeOrCurrentnessHold
                && !$hasCurrentApproval
                && $state === 'PENDING_REVIEW'
                && trim((string) ($record['assigned_reviewer_id'] ?? '')) === '';
            if ($readyForReview) {
                $signals[] = self::signal(
                    $proposalId,
                    $questionId,
                    $boardId,
                    $state,
                    'REVIEW',
                    'READY_FOR_AUTHORIZED_REVIEW',
                    'READY_FOR_AUTHORIZED_REVIEW',
                    'Current source snapshots and question version are reconciled, with no recorded scope or currentness hold.',
                    'The proposal can be queued for a real, qualified reviewer when one is authorized; no reviewer assignment or approval is recorded.',
                    $version,
                    $baseEvidence + ['reviewer_assignment' => 'NONE', 'academic_approval' => 'PENDING']
                );
            }
        }

        foreach ($proposalStemGroups as $matches) {
            if (count($matches) < 2) {
                continue;
            }
            foreach ($matches as $match) {
                $duplicates = array_values(array_unique(array_map(
                    static fn(array $other): string => (string) $other['question_id'],
                    array_values(array_filter($matches, static fn(array $other): bool => $other['proposal_id'] !== $match['proposal_id']))
                )));
                sort($duplicates, SORT_STRING);
                $signals[] = self::signal(
                    (string) $match['proposal_id'],
                    (string) $match['question_id'],
                    (string) $match['board_id'],
                    (string) $match['current_status'],
                    'REVIEW',
                    'DUPLICATE_REVIEW_REQUIRED',
                    'EXACT_PROPOSAL_STEM_DUPLICATE',
                    'The proposal stem exactly matches normalized text in another B695 proposal.',
                    'Compare the proposal scopes and evidence; keep both records noncanonical until the existing review gates resolve the duplication.',
                    (string) $match['question_version_sha256'],
                    (array) $match['evidence'] + [
                        'duplicate_scope' => 'B695_PROPOSALS',
                        'duplicate_match_method' => 'lower-case and whitespace-normalized exact stem',
                        'duplicate_question_ids' => $duplicates,
                    ]
                );
            }
        }

        return $signals;
    }

    /** @param array<int, object|array<string, mixed>> $issues @return list<string> */
    public static function canonicalTechnicalQuestionIds(array $issues): array
    {
        $ids = [];
        foreach ($issues as $issue) {
            if (is_object($issue) && method_exists($issue, 'toArray')) {
                $row = $issue->toArray();
            } elseif (is_object($issue)) {
                $row = get_object_vars($issue);
            } else {
                $row = $issue;
            }
            if (!is_array($row)
                || strtoupper((string) ($row['status'] ?? '')) !== 'FAIL'
                || strtolower((string) ($row['entity_type'] ?? '')) !== 'question') {
                continue;
            }
            $id = trim((string) ($row['entity_id'] ?? ''));
            if ($id !== '') {
                $ids[$id] = true;
            }
        }
        return array_keys($ids);
    }

    /** @param array<string, list<string>> $canonicalStemIndex @return list<string> */
    public static function exactCanonicalDuplicateIds(mixed $stem, array $canonicalStemIndex, string $excludedQuestionId = ''): array
    {
        $normalizedStem = self::normalizeStem($stem);
        if ($normalizedStem === '') {
            return [];
        }
        $ids = array_values(array_unique(array_filter(
            (array) ($canonicalStemIndex[$normalizedStem] ?? []),
            static fn(mixed $id): bool => is_scalar($id) && trim((string) $id) !== '' && (string) $id !== $excludedQuestionId
        )));
        $ids = array_map('strval', $ids);
        sort($ids, SORT_STRING);
        return $ids;
    }

    /** @return array<string, mixed>|null */
    private static function currentReadiness(string $root): ?array
    {
        $path = $root . self::READINESS_PATH;
        $contents = @file_get_contents($path);
        if (!is_string($contents)) {
            return null;
        }
        try {
            $readiness = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return null;
        }
        if (!is_array($readiness)
            || ($readiness['schema'] ?? '') !== 'boardprep.batch-717-cohort-readiness.v1'
            || !is_array($readiness['items'] ?? null)
            || !is_array($readiness['provenance'] ?? null)) {
            return null;
        }

        foreach ($readiness['provenance'] as $relativePath => $expectedHash) {
            if (!is_string($relativePath) || !is_string($expectedHash)
                || str_starts_with($relativePath, '/')
                || preg_match('~(?:^|/)\.\.(?:/|$)~', $relativePath) === 1) {
                return null;
            }
            $file = $root . DIRECTORY_SEPARATOR . $relativePath;
            if (!is_file($file)) {
                return null;
            }
            $actualHash = hash_file('sha256', $file);
            if (!is_string($actualHash) || !hash_equals($expectedHash, $actualHash)) {
                return null;
            }
        }

        return $readiness;
    }

    /** @param array<string, mixed> $proposal @return list<string> */
    private static function sourceIds(array $proposal): array
    {
        $ids = array_values((array) ($proposal['source_ids'] ?? []));
        $ids = array_values(array_unique(array_filter(array_map(
            static fn(mixed $id): string => is_scalar($id) ? trim((string) $id) : '',
            $ids
        ))));
        sort($ids, SORT_STRING);
        return $ids;
    }

    /** @param list<string> $sourceIds @param array<string, mixed> $reviews @return list<string> */
    private static function pendingSourceReviews(array $sourceIds, array $reviews, string $version): array
    {
        $pending = [];
        foreach ($sourceIds as $sourceId) {
            $review = $reviews[$sourceId] ?? null;
            if (!is_array($review)
                || ($review['proposal_version'] ?? '') !== $version
                || trim((string) ($review['reviewer_id'] ?? '')) === ''
                || trim((string) ($review['reviewer_grant_id'] ?? '')) === ''
                || !in_array(($review['status'] ?? ''), [
                    'ITEM_LEVEL_CLAIM_SUPPORTED',
                    'ITEM_LEVEL_CLAIM_NOT_SUPPORTED',
                    'SOURCE_UNAVAILABLE',
                    'SOURCE_SCOPE_LIMITED',
                ], true)
                || trim((string) ($review['source_locator'] ?? '')) === ''
                || trim((string) ($review['source_scope'] ?? '')) === ''
                || mb_strlen(trim((string) ($review['rationale'] ?? ''))) < 20) {
                $pending[] = $sourceId;
            }
        }
        return $pending;
    }

    /** @param array<string, mixed> $record @return array<string, mixed> */
    private static function latestCurrentDecision(array $record, string $version): array
    {
        $current = [];
        foreach ((array) ($record['decisions'] ?? []) as $event) {
            if (is_array($event) && ($event['proposal_version'] ?? '') === $version) {
                $current[] = $event;
            }
        }
        return $current === [] ? [] : $current[array_key_last($current)];
    }

    /** @param array<string, mixed> $record */
    private static function hasCurrentApproval(array $record, string $version): bool
    {
        foreach ((array) ($record['decisions'] ?? []) as $event) {
            if (is_array($event)
                && ($event['proposal_version'] ?? '') === $version
                && ($event['decision'] ?? '') === 'APPROVE') {
                return true;
            }
        }
        return false;
    }

    /**
     * @param array<string, mixed> $sourceReadiness
     * @param array<string, mixed> $proposal
     * @param list<string> $proposalSourceIds
     * @param array<string, array<string, mixed>> $sourceRegistryById
     * @return array{proposal_current:bool,readiness_current:bool,identity_mismatch_ids:list<string>,registry_mismatch_ids:list<string>,readiness_mismatch_ids:list<string>}
     */
    private static function sourceSnapshotIntegrity(
        array $sourceReadiness,
        array $proposal,
        array $proposalSourceIds,
        array $sourceRegistryById
    ): array
    {
        $evidenceById = [];
        $identityMismatches = [];
        $rawProposalIds = array_values((array) ($proposal['source_ids'] ?? []));
        if ($rawProposalIds === []) {
            $identityMismatches[] = '<no-source-references>';
        }
        $rawProposalIdCount = 0;
        foreach ($rawProposalIds as $rawId) {
            if (!is_scalar($rawId) || trim((string) $rawId) === '') {
                $identityMismatches[] = '<invalid-proposal-source-id>';
                continue;
            }
            $rawProposalIdCount++;
        }
        if ($rawProposalIdCount !== count($proposalSourceIds)) {
            $identityMismatches[] = '<duplicate-or-invalid-proposal-source-id>';
        }
        foreach (array_values(array_filter((array) ($proposal['source_evidence'] ?? []), 'is_array')) as $evidence) {
            $sourceId = trim((string) ($evidence['source_id'] ?? ''));
            if ($sourceId === '' || isset($evidenceById[$sourceId])) {
                $identityMismatches[] = $sourceId !== '' ? $sourceId : '<missing-source-id>';
                continue;
            }
            $evidenceById[$sourceId] = $evidence;
        }

        $evidenceIds = array_keys($evidenceById);
        sort($evidenceIds, SORT_STRING);
        $proposalIds = $proposalSourceIds;
        sort($proposalIds, SORT_STRING);
        foreach (array_values(array_diff($proposalIds, $evidenceIds)) as $sourceId) {
            $identityMismatches[] = $sourceId;
        }
        foreach (array_values(array_diff($evidenceIds, $proposalIds)) as $sourceId) {
            $identityMismatches[] = $sourceId;
        }
        $identityMismatches = array_values(array_unique($identityMismatches));
        sort($identityMismatches, SORT_STRING);

        $registryMismatches = [];
        foreach ($proposalIds as $sourceId) {
            $source = $sourceRegistryById[$sourceId] ?? null;
            $evidence = $evidenceById[$sourceId] ?? null;
            $snapshot = is_array($evidence['source_record_snapshot'] ?? null)
                ? $evidence['source_record_snapshot']
                : [];
            if (!is_array($source) || $snapshot === []
                || trim((string) ($snapshot['id'] ?? '')) !== $sourceId
                || !hash_equals(self::canonicalRecordHash($snapshot), self::canonicalRecordHash($source))) {
                $registryMismatches[] = $sourceId;
            }
        }

        $readinessRows = array_values(array_filter((array) ($sourceReadiness['source_records'] ?? []), 'is_array'));
        $readinessById = [];
        foreach ($readinessRows as $row) {
            $sourceId = trim((string) ($row['source_id'] ?? ''));
            if ($sourceId !== '' && !isset($readinessById[$sourceId])) {
                $readinessById[$sourceId] = $row;
            }
        }
        $readinessIds = array_keys($readinessById);
        sort($readinessIds, SORT_STRING);
        $readinessDeclaredIds = self::sortedIds((array) ($sourceReadiness['source_ids'] ?? []));
        $readinessMismatches = array_values(array_unique(array_merge(
            array_diff($proposalIds, $readinessIds),
            array_diff($readinessIds, $proposalIds),
            array_diff($proposalIds, $readinessDeclaredIds),
            array_diff($readinessDeclaredIds, $proposalIds)
        )));
        foreach ($proposalIds as $sourceId) {
            $row = $readinessById[$sourceId] ?? null;
            $source = $sourceRegistryById[$sourceId] ?? null;
            if (!is_array($row) || !is_array($source)
                || ($row['proposal_snapshot_matches_current_registry'] ?? false) !== true
                || !is_string($row['registry_record_sha256'] ?? null)
                || !hash_equals($row['registry_record_sha256'], self::canonicalRecordHash($source))) {
                $readinessMismatches[] = $sourceId;
            }
        }
        $readinessMismatches = array_values(array_unique($readinessMismatches));
        sort($readinessMismatches, SORT_STRING);
        $registryMismatches = array_values(array_unique($registryMismatches));
        sort($registryMismatches, SORT_STRING);

        return [
            'proposal_current' => $proposalIds !== [] && $identityMismatches === [] && $registryMismatches === [],
            'readiness_current' => $proposalIds !== [] && $readinessMismatches === [],
            'identity_mismatch_ids' => $identityMismatches,
            'registry_mismatch_ids' => $registryMismatches,
            'readiness_mismatch_ids' => $readinessMismatches,
        ];
    }

    /** @param array<int, mixed> $ids @return list<string> */
    private static function sortedIds(array $ids): array
    {
        $normalized = array_values(array_unique(array_filter(array_map(
            static fn(mixed $id): string => is_scalar($id) ? trim((string) $id) : '',
            $ids
        ))));
        sort($normalized, SORT_STRING);
        return $normalized;
    }

    /** @param array<string, mixed> $record */
    private static function canonicalRecordHash(array $record): string
    {
        $canonicalize = static function (mixed $value) use (&$canonicalize): mixed {
            if (!is_array($value)) {
                return $value;
            }
            if (array_is_list($value)) {
                return array_map($canonicalize, $value);
            }
            ksort($value, SORT_STRING);
            foreach ($value as $key => $child) {
                $value[$key] = $canonicalize($child);
            }
            return $value;
        };
        return hash('sha256', json_encode($canonicalize($record), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    /** @param array<string, mixed> $proposal @param array<string, mixed> $record @return array<string, mixed> */
    private static function currentQuestion(array $proposal, array $record): array
    {
        $revisions = array_values(array_filter((array) ($record['revisions'] ?? []), 'is_array'));
        $latestRevision = $revisions === [] ? [] : $revisions[array_key_last($revisions)];
        if (is_array($latestRevision['proposed_question'] ?? null)) {
            return $latestRevision['proposed_question'];
        }
        return is_array($proposal['proposed_question'] ?? null) ? $proposal['proposed_question'] : [];
    }

    /** @return array<string, list<string>> */
    private static function canonicalStemIndex(): array
    {
        $index = [];
        $questions = App::container()->get(QuestionRepository::class)->all();
        foreach ($questions as $question) {
            if (!is_array($question)) {
                continue;
            }
            $stem = self::normalizeStem($question['question'] ?? $question['stem'] ?? '');
            $id = trim((string) ($question['id'] ?? ''));
            if ($stem !== '' && $id !== '') {
                $index[$stem][] = $id;
            }
        }
        return $index;
    }

    private static function normalizeStem(mixed $value): string
    {
        if (!is_scalar($value)) {
            return '';
        }
        $text = trim((string) $value);
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;
        return strtolower($text);
    }

    /** @param array<string, mixed> $proposal @param array<string, mixed> $question @return list<array{severity:string,type:string,message:string}> */
    private static function validateProposalQuestion(array $proposal, array $question): array
    {
        $taxonomy = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
        $taxonomy += [
            'board_id' => $proposal['board_id'] ?? '',
            'subject_id' => $proposal['subject_id'] ?? '',
            'domain_id' => $proposal['domain_id'] ?? '',
            'topic_id' => $proposal['topic_id'] ?? '',
            'concept_id' => $proposal['concept_id'] ?? '',
        ];
        $normalized = [
            'id' => $proposal['question_id'] ?? $proposal['id'] ?? '',
            'question' => $question['question'] ?? $question['stem'] ?? '',
            'choices' => $question['choices'] ?? $question['options'] ?? [],
            'answer' => $question['answer'] ?? $question['proposed_answer'] ?? '',
            'explanation' => $question['explanation'] ?? '',
            'difficulty' => $question['difficulty'] ?? '',
            'taxonomy' => $taxonomy,
        ];
        return QuestionValidator::validate($normalized);
    }

    /** @param array<string, mixed> $evidence @return array<string, mixed> */
    private static function signal(
        string $proposalId,
        string $questionId,
        string $boardId,
        string $currentStatus,
        string $status,
        string $issueType,
        string $code,
        string $message,
        string $action,
        string $version = '',
        array $evidence = []
    ): array {
        $evidence['proposal_id'] = $proposalId;
        $evidence['question_id'] = $questionId;
        $evidence['current_status'] = $currentStatus;
        if ($version !== '') {
            $evidence['question_version_sha256'] = $version;
        }
        return [
            'status' => $status,
            'category' => self::categoryFor($issueType),
            'entity_type' => 'proposal',
            'entity_id' => $questionId,
            'proposal_id' => $proposalId,
            'board_id' => $boardId,
            'current_status' => $currentStatus,
            'question_version_sha256' => $version,
            'issue_type' => $issueType,
            'code' => $code,
            'message' => $message,
            'suggested_action' => $action,
            'evidence' => $evidence,
        ];
    }

    private static function categoryFor(string $issueType): string
    {
        return match ($issueType) {
            'SOURCE_REVIEW_REQUIRED' => 'SOURCE_REVIEW',
            'FACTUAL_VERIFICATION_REQUIRED' => 'FACTUAL_VERIFICATION',
            'ANSWER_KEY_REVIEW_REQUIRED' => 'ANSWER_KEY',
            'EXPLANATION_REVIEW_REQUIRED' => 'EXPLANATION',
            'SOURCE_CURRENTNESS_WARNING' => 'SOURCE_CURRENTNESS',
            'SOURCE_SCOPE_WARNING' => 'SOURCE_SCOPE',
            'TAXONOMY_MAPPING_REQUIRED' => 'TAXONOMY',
            'STUDY_SECTION_MISSING' => 'STUDY',
            'ACADEMIC_APPROVAL_PENDING' => 'ACADEMIC_REVIEW',
            'READY_FOR_AUTHORIZED_REVIEW' => 'REVIEW_READINESS',
            'REVIEW_EVIDENCE_STALE' => 'EVIDENCE_FRESHNESS',
            default => 'TECHNICAL_REVIEW',
        };
    }
}
