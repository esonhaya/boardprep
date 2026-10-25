<?php

declare(strict_types=1);

namespace App\Services\Question;

use App\Contracts\StorageInterface;
use App\Core\App;
use App\Services\Shared\QuestionValidationService;
use App\Services\Shared\TaxonomyStorageService;
use App\Services\Study\StudyLibraryService;
use Closure;
use RuntimeException;
use Throwable;

/** Versioned, authenticated academic review and individually authorized import workflow. */
final class AcademicReviewWorkflowService
{
    private const WORKFLOW_COLLECTION = 'academic-proposal-workflow';
    private const JOURNAL_COLLECTION = 'academic-import-journal';
    private StorageInterface $storage;
    private string $lockDirectory;
    private ?Closure $faultInjector;

    /** @param list<array<string,mixed>>|null $proposals */
    public function __construct(
        ?StorageInterface $storage = null,
        private ?array $proposals = null,
        private ?AcademicReviewerAuthorizationService $authorization = null,
        ?string $lockDirectory = null,
        ?callable $faultInjector = null
    ) {
        $this->storage = $storage ?? App::storage();
        $config = (array) App::config('database', []);
        $this->lockDirectory = $lockDirectory ?? (string) ($config['path'] ?? dirname(__DIR__, 4) . '/storage');
        $this->authorization = $authorization ?? new AcademicReviewerAuthorizationService($this->storage);
        $this->faultInjector = $faultInjector === null ? null : Closure::fromCallable($faultInjector);
    }

    /** @return list<array<string,mixed>> */
    private function proposals(): array
    {
        return $this->proposals ?? AcademicReviewProposalService::all();
    }

    /** @return array<string,mixed>|null */
    public function findProposal(string $id): ?array
    {
        $id = $this->normalizeProposalId($id);
        foreach ($this->proposals() as $proposal) {
            if ($this->normalizeProposalId((string) ($proposal['id'] ?? '')) === $id) return $proposal;
        }
        return null;
    }

    public function version(string $proposalId): string
    {
        $proposal = $this->findProposal($proposalId);
        if ($proposal === null) throw new RuntimeException('PROPOSAL_NOT_FOUND');
        return $this->proposalVersion($proposal, $this->record($this->normalizeProposalId($proposalId)));
    }

    /** @return array<string,mixed> */
    public function currentProposal(string $proposalId): array
    {
        $proposal = $this->findProposal($proposalId);
        if ($proposal === null) throw new RuntimeException('PROPOSAL_NOT_FOUND');
        $record = $this->record((string) $proposal['id']);
        $latest = end($record['revisions']);
        if (is_array($latest) && is_array($latest['proposed_question'] ?? null)) {
            $proposal['proposed_question'] = $latest['proposed_question'];
        }
        return $proposal;
    }

    /** @return array<string,mixed> */
    public function record(string $proposalId): array
    {
        $id = $this->normalizeProposalId($proposalId);
        $found = $this->storage->find(self::WORKFLOW_COLLECTION, $id);
        if ($found !== null) {
            $this->assertHistoryIntegrity($found);
            if (!isset($found['imported'])) {
                $proposal = $this->findProposal($id);
                $canonicalId = is_array($proposal) ? (string) ($proposal['question_id'] ?? '') : '';
                $canonical = $canonicalId !== '' ? $this->storage->find('questions', $canonicalId) : null;
                $audit = is_array($canonical['academic_import'] ?? null) ? $canonical['academic_import'] : [];
                if (($audit['proposal_id'] ?? '') === $id && ($audit['state'] ?? '') === 'IMPORTED') {
                    $found['imported'] = ['question_id' => $canonicalId, 'proposal_version' => (string) ($audit['proposal_version'] ?? ''), 'audit_id' => (string) ($audit['audit_id'] ?? ''), 'imported_at' => (string) ($audit['imported_at'] ?? '')];
                }
            }
            return $found;
        }
        return [
            'id' => $id,
            'proposal_id' => $id,
            'state' => 'PENDING_REVIEW',
            'assigned_reviewer_id' => null,
            'revisions' => [],
            'decisions' => [],
            'source_reviews' => [],
            'source_review_history' => [],
            'taxonomy_proposals' => [],
            'taxonomy_mapping' => null,
            'study_mapping' => null,
            'updated_at' => null,
        ];
    }

    /** @return array{items:list<array<string,mixed>>,total:int,page:int,per_page:int,pages:int} */
    public function page(array $filters, int $page, int $perPage, string $accountId): array
    {
        $perPage = max(1, min(50, $perPage));
        $needle = mb_strtolower(trim((string) ($filters['search'] ?? '')));
        $board = mb_strtolower(trim((string) ($filters['board'] ?? '')));
        $subject = mb_strtolower(trim((string) ($filters['subject'] ?? '')));
        $status = strtoupper(trim((string) ($filters['status'] ?? '')));
        $assigned = trim((string) ($filters['assigned'] ?? ''));
        $pilotOnly = trim((string) ($filters['pilot'] ?? '')) === '1';
        $pilotIds = $pilotOnly ? array_fill_keys(Batch715AcademicPilotService::questionIds(), true) : [];
        $scopes = [];
        foreach (['REVIEW', 'SOURCE_REVIEW', 'TAXONOMY_RECONCILE', 'STUDY_LINK', 'EDIT_PROPOSAL', 'IMPORT'] as $capability) {
            foreach ($this->authorization->scopesForAccount($accountId, $capability) as $scope) {
                $scopes[$scope['board_id'] . "\0" . $scope['subject_id']] = $scope;
            }
        }
        $allowed = [];
        foreach ($scopes as $scope) $allowed[$scope['board_id'] . "\0" . $scope['subject_id']] = true;
        $rows = [];
        foreach ($this->proposals() as $proposal) {
            $proposalId = (string) ($proposal['id'] ?? '');
            if ($pilotOnly && !isset($pilotIds[(string) ($proposal['question_id'] ?? '')])) continue;
            $b = (string) ($proposal['board_id'] ?? '');
            $s = (string) ($proposal['subject_id'] ?? '');
            if (!isset($allowed[$b . "\0" . $s])) continue;
            $state = $this->record($proposalId);
            if ($board !== '' && mb_strtolower($b) !== $board) continue;
            if ($subject !== '' && mb_strtolower($s) !== $subject) continue;
            if ($status !== '' && strtoupper((string) $state['state']) !== $status) continue;
            if ($assigned === 'mine' && ($state['assigned_reviewer_id'] ?? '') !== $accountId) continue;
            if ($assigned === 'unassigned' && trim((string) ($state['assigned_reviewer_id'] ?? '')) !== '') continue;
            if ($needle !== '') {
                $current = $this->currentProposal($proposalId);
                $question = (array) ($current['proposed_question'] ?? []);
                $haystack = mb_strtolower(implode(' ', [(string) ($current['question_id'] ?? ''), (string) ($question['stem'] ?? '')]));
                if (mb_stripos($haystack, $needle) === false) continue;
            }
            $rows[] = [
                'proposal' => $proposal,
                'workflow' => $state,
                'proposal_version' => $this->proposalVersion($proposal, $state),
            ];
        }
        $total = count($rows);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($page, $pages));
        return ['items' => array_slice($rows, ($page - 1) * $perPage), 'total' => $total, 'page' => $page, 'per_page' => $perPage, 'pages' => $pages];
    }

    /** @return array<string,mixed> */
    public function assign(string $proposalId, string $accountId, string $expectedVersion): array
    {
        return $this->mutate($proposalId, function (array $proposal, array $record) use ($accountId, $expectedVersion): array {
            $version = $this->proposalVersion($proposal, $record);
            $this->assertVersion($version, $expectedVersion);
            $this->assertAllowed($accountId, $proposal, 'REVIEW');
            if (!in_array($record['state'], ['PENDING_REVIEW', 'DEFERRED', 'ASSIGNED'], true)) throw new RuntimeException('ASSIGNMENT_STATE_INVALID');
            $assigned = (string) ($record['assigned_reviewer_id'] ?? '');
            if ($assigned !== '' && $assigned !== $accountId) throw new RuntimeException('PROPOSAL_ASSIGNED_TO_ANOTHER_REVIEWER');
            if ($assigned === $accountId && $record['state'] === 'ASSIGNED') return $record;
            $before = (string) $record['state'];
            $record['assigned_reviewer_id'] = $accountId;
            $record['state'] = 'ASSIGNED';
            $this->appendEvent($record, $this->event($accountId, $proposal, 'ASSIGN', $version, $before, 'ASSIGNED', 'Reviewer self-assigned proposal.'));
            return $record;
        });
    }

    /** @return array<string,mixed> */
    public function startReview(string $proposalId, string $accountId, string $expectedVersion): array
    {
        return $this->mutate($proposalId, function (array $proposal, array $record) use ($accountId, $expectedVersion): array {
            $version = $this->proposalVersion($proposal, $record);
            $this->assertVersion($version, $expectedVersion);
            $this->assertAllowed($accountId, $proposal, 'REVIEW');
            if (($record['assigned_reviewer_id'] ?? '') !== $accountId || $record['state'] !== 'ASSIGNED') throw new RuntimeException('ASSIGNMENT_REQUIRED');
            $record['state'] = 'IN_REVIEW';
            $this->appendEvent($record, $this->event($accountId, $proposal, 'START_REVIEW', $version, 'ASSIGNED', 'IN_REVIEW', 'Reviewer opened the assigned proposal.'));
            return $record;
        });
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function decide(string $proposalId, string $accountId, array $input): array
    {
        return $this->mutate($proposalId, function (array $proposal, array $record) use ($accountId, $input): array {
            $version = $this->proposalVersion($proposal, $record);
            $this->assertVersion($version, (string) ($input['proposal_version'] ?? ''));
            $this->assertAllowed($accountId, $proposal, 'REVIEW');
            if (in_array($record['state'], ['APPROVED', 'REJECTED', 'DEFERRED', 'REVISION_REQUESTED'], true)) throw new RuntimeException('DUPLICATE_DECISION');
            if (($record['assigned_reviewer_id'] ?? '') !== $accountId || $record['state'] !== 'IN_REVIEW') throw new RuntimeException('ASSIGNED_REVIEW_REQUIRED');
            $action = strtoupper(trim((string) ($input['decision'] ?? '')));
            $transitions = ['APPROVE' => 'APPROVED', 'REQUEST_REVISION' => 'REVISION_REQUESTED', 'REJECT' => 'REJECTED', 'DEFER' => 'DEFERRED'];
            if (!isset($transitions[$action])) throw new RuntimeException('INVALID_DECISION');
            $rationale = trim((string) ($input['rationale'] ?? ''));
            if (mb_strlen($rationale) < 20) throw new RuntimeException('RATIONALE_TOO_SHORT');
            $checks = is_array($input['confirmations'] ?? null) ? $input['confirmations'] : [];
            if ($action === 'APPROVE') {
                foreach (['question_accuracy', 'correct_answer', 'explanation_correctness', 'exam_relevance', 'subject_classification', 'adequate_source_support', 'source_currentness'] as $key) {
                    if (($checks[$key] ?? false) !== true) throw new RuntimeException('APPROVAL_CONFIRMATION_REQUIRED:' . $key);
                }
                if (in_array(strtolower((string) ($proposal['board_id'] ?? '')), ['let', 'architecture'], true)
                    && ($checks['regulatory_scope_currentness'] ?? false) !== true) {
                    throw new RuntimeException('APPROVAL_CONFIRMATION_REQUIRED:regulatory_scope_currentness');
                }
                if (strtolower((string) ($proposal['board_id'] ?? '')) === 'nursing') {
                    if (($checks['clinical_safety'] ?? false) !== true) throw new RuntimeException('APPROVAL_CONFIRMATION_REQUIRED:clinical_safety');
                    $clinicalGrant = $this->authorization->grantFor($accountId, (string) $proposal['board_id'], (string) $proposal['subject_id'], 'REVIEW') ?? [];
                    if (!in_array(strtoupper((string) ($clinicalGrant['qualification_type'] ?? '')), ['NURSING_EXPERT', 'CLINICAL_REVIEWER', 'LICENSED_CLINICIAN', 'REGULATORY_EXPERT'], true)) throw new RuntimeException('CLINICAL_QUALIFICATION_REQUIRED');
                }
            }
            $before = (string) $record['state'];
            $record['state'] = $transitions[$action];
            $record['assigned_reviewer_id'] = null;
            $grant = $this->authorization->grantFor($accountId, (string) $proposal['board_id'], (string) $proposal['subject_id'], 'REVIEW') ?? [];
            $event = $this->event($accountId, $proposal, $action, $version, $before, $record['state'], $rationale);
            $event['reviewer_scope'] = ['board_id' => (string) $proposal['board_id'], 'subject_id' => (string) $proposal['subject_id']];
            $event['reviewer_grant_id'] = (string) ($grant['id'] ?? '');
            $event['confirmations'] = $checks;
            $event['taxonomy_mapping_sha256'] = $this->taxonomyMappingHash($record['taxonomy_mapping'] ?? null);
            $event['study_mapping_sha256'] = $this->studyMappingHash($record['study_mapping'] ?? null);
            $event['source_evidence'] = array_values(array_map(static fn(array $evidence): array => [
                'source_id' => (string) ($evidence['source_id'] ?? ''),
                'source_evidence_status' => (string) ($evidence['source_evidence_status'] ?? ''),
                'limitations' => array_values((array) ($evidence['limitations'] ?? [])),
            ], array_values(array_filter((array) ($proposal['source_evidence'] ?? []), 'is_array'))));
            $this->appendEvent($record, $event);
            return $record;
        });
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function reviewSource(string $proposalId, string $sourceId, string $accountId, array $input): array
    {
        return $this->mutate($proposalId, function (array $proposal, array $record) use ($sourceId, $accountId, $input): array {
            $version = $this->proposalVersion($proposal, $record);
            $this->assertVersion($version, (string) ($input['proposal_version'] ?? ''));
            $this->assertAllowed($accountId, $proposal, 'SOURCE_REVIEW');
            $known = false;
            foreach ((array) ($proposal['source_evidence'] ?? []) as $evidence) if (is_array($evidence) && (string) ($evidence['source_id'] ?? '') === $sourceId) $known = true;
            if (!$known) throw new RuntimeException('SOURCE_NOT_CITED_BY_PROPOSAL');
            $status = strtoupper(trim((string) ($input['status'] ?? '')));
            if (!in_array($status, ['ITEM_LEVEL_CLAIM_SUPPORTED', 'ITEM_LEVEL_CLAIM_NOT_SUPPORTED', 'SOURCE_UNAVAILABLE', 'SOURCE_SCOPE_LIMITED'], true)) throw new RuntimeException('INVALID_SOURCE_REVIEW_STATUS');
            $locator = trim((string) ($input['source_locator'] ?? ''));
            $rationale = trim((string) ($input['rationale'] ?? ''));
            if ($locator === '' || mb_strlen($rationale) < 20) throw new RuntimeException('SOURCE_EVIDENCE_INCOMPLETE');
            $identity = ($input['source_identity_verified'] ?? false) === true;
            $content = ($input['source_content_examined'] ?? false) === true;
            $currentness = ($input['source_currentness_confirmed'] ?? false) === true;
            if ($status === 'ITEM_LEVEL_CLAIM_SUPPORTED' && (!$identity || !$content || !$currentness)) throw new RuntimeException('SOURCE_VERIFICATION_CHECKS_REQUIRED');
            $registrySource = $this->storage->find('sources', $sourceId);
            if ($status === 'ITEM_LEVEL_CLAIM_SUPPORTED' && !is_array($registrySource)) throw new RuntimeException('SOURCE_IDENTITY_NOT_REGISTERED');
            $urlReachability = strtoupper(trim((string) ($input['url_reachability'] ?? 'NOT_CHECKED')));
            if (!in_array($urlReachability, ['URL_REACHABLE', 'URL_UNREACHABLE', 'NOT_CHECKED'], true)) throw new RuntimeException('INVALID_URL_REACHABILITY');
            $grant = $this->authorization->grantFor($accountId, (string) $proposal['board_id'], (string) $proposal['subject_id'], 'SOURCE_REVIEW') ?? [];
            $sourceReview = [
                'source_id' => $sourceId,
                'proposal_version' => $version,
                'status' => $status,
                'source_identity_verified' => $identity,
                'source_content_examined' => $content,
                'item_level_claim_supported' => $status === 'ITEM_LEVEL_CLAIM_SUPPORTED',
                'source_currentness_confirmed' => $currentness,
                'url_reachability' => $urlReachability,
                'source_registry_snapshot_sha256' => is_array($registrySource) ? hash('sha256', json_encode($registrySource, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)) : null,
                'source_locator' => $locator,
                'source_scope' => trim((string) ($input['source_scope'] ?? '')),
                'rationale' => $rationale,
                'limitations' => trim((string) ($input['limitations'] ?? '')),
                'reviewer_id' => $accountId,
                'reviewer_scope' => ['board_id' => (string) $proposal['board_id'], 'subject_id' => (string) $proposal['subject_id']],
                'reviewer_grant_id' => (string) ($grant['id'] ?? ''),
                'reviewed_at' => gmdate(DATE_ATOM),
            ];
            $record['source_reviews'][$sourceId] = $sourceReview;
            $record['source_review_history'][] = $sourceReview;
            $before = (string) $record['state'];
            if ($before === 'APPROVED') {
                $record['state'] = 'PENDING_REVIEW';
                $record['assigned_reviewer_id'] = null;
            }
            $this->appendEvent($record, $this->event($accountId, $proposal, 'SOURCE_REVIEW:' . $status, $version, $before, (string) $record['state'], $rationale, 'SOURCE_REVIEW'));
            return $record;
        });
    }

    /** @param array<string,string> $mapping @return array<string,mixed> */
    public function reconcileTaxonomy(string $proposalId, string $accountId, string $expectedVersion, array $mapping, string $rationale): array
    {
        return $this->mutate($proposalId, function (array $proposal, array $record) use ($accountId, $expectedVersion, $mapping, $rationale): array {
            $version = $this->proposalVersion($proposal, $record);
            $this->assertVersion($version, $expectedVersion);
            $this->assertAllowed($accountId, $proposal, 'TAXONOMY_RECONCILE');
            if (mb_strlen(trim($rationale)) < 20) throw new RuntimeException('TAXONOMY_RATIONALE_REQUIRED');
            $normalized = [
                'board_id' => trim((string) ($mapping['board_id'] ?? $proposal['board_id'] ?? '')),
                'subject_id' => trim((string) ($mapping['subject_id'] ?? $proposal['subject_id'] ?? '')),
                'domain_id' => trim((string) ($mapping['domain_id'] ?? '')),
                'topic_id' => trim((string) ($mapping['topic_id'] ?? '')),
                'concept_id' => trim((string) ($mapping['concept_id'] ?? '')),
            ];
            if ($normalized['board_id'] !== (string) $proposal['board_id'] || $normalized['subject_id'] !== (string) $proposal['subject_id']) throw new RuntimeException('TAXONOMY_BOARD_SUBJECT_CHANGE_REQUIRES_NEW_EXPERT_SCOPE');
            $errors = self::taxonomyErrors($normalized);
            if ($errors !== []) throw new RuntimeException('TAXONOMY_MAPPING_INVALID:' . implode(',', $errors));
            $requiredDepth = filter_var($mapping['required_depth'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 5]]);
            if ($requiredDepth !== false && mb_strlen(trim($rationale)) < 20) throw new RuntimeException('REUSE_DEPTH_RATIONALE_REQUIRED');
            $grant = $this->authorization->grantFor($accountId, (string) $proposal['board_id'], (string) $proposal['subject_id'], 'TAXONOMY_RECONCILE') ?? [];
            $record['taxonomy_mapping'] = $normalized + [
                'proposal_version' => $version,
                'status' => 'MAPPED_TO_EXISTING_APPROVED_TAXONOMY',
                'required_depth' => $requiredDepth === false ? null : $requiredDepth,
                'depth_rationale' => $requiredDepth === false ? '' : trim($rationale),
                'rationale' => trim($rationale),
                'reviewer_id' => $accountId,
                'reviewer_grant_id' => (string) ($grant['id'] ?? ''),
                'reviewed_at' => gmdate(DATE_ATOM),
            ];
            $beforeState = (string) $record['state'];
            if ($record['state'] === 'APPROVED') {
                $record['state'] = 'PENDING_REVIEW';
                $record['assigned_reviewer_id'] = null;
            }
            $this->appendEvent($record, $this->event($accountId, $proposal, 'TAXONOMY_RECONCILED', $version, $beforeState, (string) $record['state'], trim($rationale), 'TAXONOMY_RECONCILE'));
            return $record;
        });
    }

    /** Store a proposed taxonomy label for governed review without changing active taxonomy. */
    public function proposeTaxonomyEntry(string $proposalId, string $accountId, string $expectedVersion, string $kind, string $label, string $parentId, string $rationale): array
    {
        return $this->mutate($proposalId, function (array $proposal, array $record) use ($accountId, $expectedVersion, $kind, $label, $parentId, $rationale): array {
            $version = $this->proposalVersion($proposal, $record);
            $this->assertVersion($version, $expectedVersion);
            $this->assertAllowed($accountId, $proposal, 'TAXONOMY_RECONCILE');
            $kind = strtoupper(trim($kind));
            $label = trim($label);
            $parentId = trim($parentId);
            $rationale = trim($rationale);
            if (!in_array($kind, ['TOPIC', 'CONCEPT'], true) || $label === '' || mb_strlen($label) > 120 || $parentId === '' || mb_strlen($rationale) < 20) throw new RuntimeException('TAXONOMY_PROPOSAL_INCOMPLETE');
            if ($kind === 'TOPIC') {
                $parent = TaxonomyStorageService::find('taxonomy/domains', $parentId);
                if (!is_array($parent) || (string) ($parent['subject_id'] ?? '') !== (string) $proposal['subject_id']) throw new RuntimeException('TAXONOMY_PARENT_SCOPE_INVALID');
                $siblings = TaxonomyStorageService::topics();
                $parentField = 'domain_id';
            } else {
                $parent = TaxonomyStorageService::find('taxonomy/topics', $parentId);
                $domain = is_array($parent) ? TaxonomyStorageService::find('taxonomy/domains', (string) ($parent['domain_id'] ?? '')) : null;
                if (!is_array($parent) || !is_array($domain) || (string) ($domain['subject_id'] ?? '') !== (string) $proposal['subject_id']) throw new RuntimeException('TAXONOMY_PARENT_SCOPE_INVALID');
                $siblings = TaxonomyStorageService::concepts();
                $parentField = 'topic_id';
            }
            foreach ($siblings as $sibling) {
                if (is_array($sibling) && (string) ($sibling[$parentField] ?? '') === $parentId && mb_strtolower(trim((string) ($sibling['name'] ?? ''))) === mb_strtolower($label)) throw new RuntimeException('TAXONOMY_LABEL_ALREADY_EXISTS');
            }
            $proposalRecord = [
                'id' => 'taxonomy-proposal-' . bin2hex(random_bytes(10)),
                'kind' => $kind,
                'label' => $label,
                'parent_id' => $parentId,
                'board_id' => (string) $proposal['board_id'],
                'subject_id' => (string) $proposal['subject_id'],
                'proposal_version' => $version,
                'status' => 'AWAITING_TAXONOMY_GOVERNANCE',
                'rationale' => $rationale,
                'source_evidence_ids' => array_values(array_map(static fn(array $e): string => (string) ($e['source_id'] ?? ''), array_values(array_filter((array) ($proposal['source_evidence'] ?? []), 'is_array')))),
                'submitted_by' => $accountId,
                'submitted_at' => gmdate(DATE_ATOM),
            ];
            $record['taxonomy_proposals'][] = $proposalRecord;
            $this->appendEvent($record, $this->event($accountId, $proposal, 'TAXONOMY_ENTRY_PROPOSED:' . $kind, $version, (string) $record['state'], (string) $record['state'], $rationale, 'TAXONOMY_RECONCILE') + ['taxonomy_proposal_id' => $proposalRecord['id']]);
            return $record;
        });
    }

    /** @param array<string,string> $mapping @return array<string,mixed> */
    public function reconcileStudyLink(string $proposalId, string $accountId, string $expectedVersion, array $mapping, string $rationale): array
    {
        return $this->mutate($proposalId, function (array $proposal, array $record) use ($accountId, $expectedVersion, $mapping, $rationale): array {
            $version = $this->proposalVersion($proposal, $record);
            $this->assertVersion($version, $expectedVersion);
            $this->assertAllowed($accountId, $proposal, 'STUDY_LINK');
            $status = strtoupper(trim((string) ($mapping['status'] ?? 'AWAITING_REVIEW')));
            if (!in_array($status, ['APPROVED_EXACT_LINK', 'UNRESOLVED', 'OPTIONAL_ENRICHMENT'], true)) throw new RuntimeException('INVALID_STUDY_LINK_STATUS');
            if (mb_strlen(trim($rationale)) < 20) throw new RuntimeException('STUDY_LINK_RATIONALE_REQUIRED');
            $materialId = trim((string) ($mapping['material_id'] ?? ''));
            $sectionId = trim((string) ($mapping['section_id'] ?? ''));
            if ($status === 'APPROVED_EXACT_LINK') {
                if (!$this->studySectionExists($materialId, $sectionId, (string) $proposal['board_id'], (string) $proposal['subject_id'])) throw new RuntimeException('STUDY_SECTION_NOT_FOUND_OR_SCOPE_MISMATCH');
            } else {
                $materialId = '';
                $sectionId = '';
            }
            $record['study_mapping'] = [
                'status' => $status,
                'material_id' => $materialId,
                'section_id' => $sectionId,
                'proposal_version' => $version,
                'rationale' => trim($rationale),
                'reviewer_id' => $accountId,
                'reviewed_at' => gmdate(DATE_ATOM),
            ];
            $beforeState = (string) $record['state'];
            if ($record['state'] === 'APPROVED') {
                $record['state'] = 'PENDING_REVIEW';
                $record['assigned_reviewer_id'] = null;
            }
            $this->appendEvent($record, $this->event($accountId, $proposal, 'STUDY_LINK:' . $status, $version, $beforeState, (string) $record['state'], trim($rationale), 'STUDY_LINK'));
            return $record;
        });
    }

    /** Record a new authored proposal revision without changing the original inventory snapshot. */
    public function recordRevision(string $proposalId, string $accountId, string $expectedVersion, array $question, string $reason): array
    {
        return $this->mutate($proposalId, function (array $proposal, array $record) use ($accountId, $expectedVersion, $question, $reason): array {
            $oldVersion = $this->proposalVersion($proposal, $record);
            $this->assertVersion($oldVersion, $expectedVersion);
            $this->assertAllowed($accountId, $proposal, 'EDIT_PROPOSAL');
            if ($record['state'] !== 'REVISION_REQUESTED') throw new RuntimeException('REVISION_NOT_REQUESTED');
            if (mb_strlen(trim($reason)) < 20) throw new RuntimeException('REVISION_PROVENANCE_REQUIRED');
            $normalized = [
                'stem' => trim((string) ($question['stem'] ?? '')),
                'choices' => array_values(array_map(static fn(mixed $v): string => trim((string) $v), (array) ($question['choices'] ?? []))),
                'proposed_answer' => trim((string) ($question['proposed_answer'] ?? '')),
                'explanation' => trim((string) ($question['explanation'] ?? '')),
                'difficulty' => strtolower(trim((string) ($question['difficulty'] ?? ''))),
            ];
            if ($normalized['stem'] === '' || count($normalized['choices']) < 2 || count(array_unique($normalized['choices'])) !== count($normalized['choices']) || !in_array($normalized['proposed_answer'], $normalized['choices'], true) || $normalized['explanation'] === '' || !in_array($normalized['difficulty'], ['easy', 'medium', 'hard'], true)) throw new RuntimeException('REVISION_CONTENT_INVALID');
            $record['revisions'][] = [
                'revision_number' => count($record['revisions']) + 1,
                'proposed_question' => $normalized,
                'reason' => trim($reason),
                'created_by' => $accountId,
                'created_at' => gmdate(DATE_ATOM),
                'previous_version' => $oldVersion,
            ];
            $record['taxonomy_mapping'] = null;
            $record['study_mapping'] = null;
            $record['source_reviews'] = [];
            $newVersion = $this->proposalVersion($proposal, $record);
            $record['revisions'][array_key_last($record['revisions'])]['proposal_version'] = $newVersion;
            $record['state'] = 'PENDING_REVIEW';
            $record['assigned_reviewer_id'] = null;
            $this->appendEvent($record, $this->event($accountId, $proposal, 'REVISION_RECORDED', $newVersion, 'REVISION_REQUESTED', 'PENDING_REVIEW', trim($reason), 'EDIT_PROPOSAL'));
            return $record;
        });
    }

    /** @return array<string,mixed> */
    public function importPreflight(string $proposalId, string $accountId): array
    {
        return $this->withLock(function () use ($proposalId, $accountId): array {
            $proposal = $this->findProposal($proposalId);
            if ($proposal === null) return ['eligible' => false, 'blocked' => ['PROPOSAL_NOT_FOUND']];
            $record = $this->record((string) $proposal['id']);
            return $this->preflightUnlocked($proposal, $record, $accountId);
        });
    }

    /** @return array<string,mixed> */
    private function preflightUnlocked(array $proposal, array $record, string $accountId): array
    {
        $blocked = [];
        $version = $this->proposalVersion($proposal, $record);
        $taxonomy = is_array($record['taxonomy_mapping'] ?? null) ? $record['taxonomy_mapping'] : [];
        $this->addGateRequirement($blocked, $record['state'] === 'APPROVED', 'ACADEMIC_APPROVAL_REQUIRED');
        $approval = null;
        foreach (array_reverse((array) ($record['decisions'] ?? [])) as $event) {
            if (is_array($event) && ($event['decision'] ?? '') === 'APPROVE' && ($event['proposal_version'] ?? '') === $version) { $approval = $event; break; }
        }
        $this->addGateRequirement($blocked, $approval !== null, 'CURRENT_VERSION_APPROVAL_REQUIRED');
        if ($approval !== null) {
            $this->addGateRequirement($blocked, (string) ($approval['reviewer_id'] ?? '') !== $accountId, 'IMPORTER_MUST_DIFFER_FROM_APPROVER');
            $approvalReviewerId = (string) ($approval['reviewer_id'] ?? '');
            $approvalGrant = $approvalReviewerId === '' ? null : $this->authorization->grantFor(
                $approvalReviewerId,
                (string) $proposal['board_id'],
                (string) $proposal['subject_id'],
                'REVIEW'
            );
            $approvalScope = is_array($approval['reviewer_scope'] ?? null) ? $approval['reviewer_scope'] : [];
            $approvalAuthorizationValid = is_array($approvalGrant)
                && (string) ($approvalGrant['id'] ?? '') === (string) ($approval['reviewer_grant_id'] ?? '')
                && (string) ($approvalGrant['qualification_reference'] ?? '') === (string) ($approval['reviewer_qualification_reference'] ?? '')
                && ($approvalScope['board_id'] ?? '') === (string) $proposal['board_id']
                && ($approvalScope['subject_id'] ?? '') === (string) $proposal['subject_id'];
            $this->addGateRequirement($blocked, $approvalAuthorizationValid, 'APPROVAL_REVIEWER_AUTHORIZATION_INVALID');
        }
        $this->addGateRequirement($blocked, is_array($taxonomy) && ($taxonomy['proposal_version'] ?? '') === $version && self::taxonomyErrors($taxonomy) === [], 'VALID_REVIEWED_TAXONOMY_REQUIRED');
        if ($approval !== null) {
            $this->addGateRequirement($blocked, ($approval['taxonomy_mapping_sha256'] ?? '') === $this->taxonomyMappingHash($taxonomy), 'APPROVAL_TAXONOMY_VERSION_MISMATCH');
            $this->addGateRequirement($blocked, ($approval['study_mapping_sha256'] ?? '') === $this->studyMappingHash($record['study_mapping'] ?? null), 'APPROVAL_STUDY_MAPPING_MISMATCH');
        }
        foreach ((array) ($proposal['source_evidence'] ?? []) as $evidence) {
            if (!is_array($evidence)) continue;
            $sourceId = (string) ($evidence['source_id'] ?? '');
            $snapshot = is_array($evidence['source_record_snapshot'] ?? null) ? $evidence['source_record_snapshot'] : [];
            $this->addGateRequirement($blocked, $sourceId !== '' && $snapshot !== [] && (string) ($snapshot['id'] ?? '') === $sourceId, 'SOURCE_PROVENANCE_SNAPSHOT_REQUIRED:' . ($sourceId !== '' ? $sourceId : 'UNKNOWN'));
            $review = $record['source_reviews'][$sourceId] ?? null;
            $sourceReviewerId = is_array($review) ? (string) ($review['reviewer_id'] ?? '') : '';
            $sourceReviewerGrant = $sourceReviewerId === '' ? null : $this->authorization->grantFor(
                $sourceReviewerId,
                (string) $proposal['board_id'],
                (string) $proposal['subject_id'],
                'SOURCE_REVIEW'
            );
            $sourceReviewerAuthorizationValid = is_array($review)
                && is_array($sourceReviewerGrant)
                && (string) ($sourceReviewerGrant['id'] ?? '') === (string) ($review['reviewer_grant_id'] ?? '')
                && (($review['reviewer_scope']['board_id'] ?? '') === (string) $proposal['board_id'])
                && (($review['reviewer_scope']['subject_id'] ?? '') === (string) $proposal['subject_id']);
            $this->addGateRequirement($blocked, $sourceReviewerAuthorizationValid, 'SOURCE_REVIEWER_AUTHORIZATION_INVALID:' . ($sourceId !== '' ? $sourceId : 'UNKNOWN'));
            $ok = is_array($review)
                && ($review['proposal_version'] ?? '') === $version
                && ($review['status'] ?? '') === 'ITEM_LEVEL_CLAIM_SUPPORTED'
                && ($review['source_identity_verified'] ?? false) === true
                && ($review['source_content_examined'] ?? false) === true
                && ($review['item_level_claim_supported'] ?? false) === true
                && ($review['source_currentness_confirmed'] ?? false) === true
                && trim((string) ($review['source_scope'] ?? '')) !== ''
                && trim((string) ($review['source_locator'] ?? '')) !== '';
            if ($ok) {
                $registered = $this->storage->find('sources', $sourceId);
                $registeredHash = is_array($registered) ? hash('sha256', json_encode($registered, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)) : '';
                $registryUnchanged = $registeredHash !== '' && hash_equals((string) ($review['source_registry_snapshot_sha256'] ?? ''), $registeredHash);
                $this->addGateRequirement($blocked, $registryUnchanged, 'SOURCE_REGISTRY_CHANGED:' . $sourceId);
                $ok = $registryUnchanged;
            }
            $this->addGateRequirement($blocked, $ok, 'SOURCE_REVIEW_REQUIRED:' . ($sourceId !== '' ? $sourceId : 'UNKNOWN'));
        }
        $sourceEvidence = array_values(array_filter((array) ($proposal['source_evidence'] ?? []), 'is_array'));
        $proposalSourceIds = array_values(array_unique(array_map('strval', (array) ($proposal['source_ids'] ?? []))));
        $evidenceSourceIds = array_values(array_unique(array_map(static fn(array $item): string => trim((string) ($item['source_id'] ?? '')), $sourceEvidence)));
        $this->addGateRequirement($blocked, $sourceEvidence !== [] && $proposalSourceIds !== [], 'SOURCE_EVIDENCE_REQUIRED');
        $this->addGateRequirement($blocked, array_diff($proposalSourceIds, $evidenceSourceIds) === [] && array_diff($evidenceSourceIds, $proposalSourceIds) === [], 'SOURCE_EVIDENCE_CITATION_MISMATCH');
        $this->addGateRequirement($blocked, $this->authorization->allows($accountId, (string) $proposal['board_id'], (string) $proposal['subject_id'], 'IMPORT'), 'IMPORT_AUTHORIZATION_REQUIRED');
        $current = $this->currentProposal((string) $proposal['id']);
        $question = $this->canonicalQuestion($current, $record, 'active');
        $id = (string) ($question['id'] ?? '');
        $existing = $this->storage->find('questions', $id);
        $alreadyImported = is_array($existing['academic_import'] ?? null)
            && ($existing['academic_import']['proposal_id'] ?? '') === (string) $proposal['id'];
        $this->addGateRequirement($blocked, !$alreadyImported, 'ALREADY_IMPORTED');
        $this->addGateRequirement($blocked, !is_array($record['imported'] ?? null), 'WORKFLOW_ALREADY_IMPORTED');
        $this->addGateRequirement($blocked, $existing === null, 'CANONICAL_ID_COLLISION');
        $this->addGateRequirement($blocked, $approval !== null && $this->approvalChecksComplete($approval, (string) $proposal['board_id']), 'APPROVAL_CHECKLIST_INCOMPLETE');
        if ($blocked !== []) {
            return [
                'eligible' => false,
                'blocked' => array_values(array_unique($blocked)),
                'proposal_id' => (string) $proposal['id'],
                'question_id' => $id,
                'proposal_version' => $version,
                'canonical_exists' => $existing !== null,
            ];
        }
        $historyCollision = $this->storage->find('question-statistics', $id) !== null;
        if (!$historyCollision) {
            foreach ($this->storage->all('attempts') as $attempt) {
                if ($this->attemptContainsQuestion($attempt, $id)) { $historyCollision = true; break; }
            }
        }
        $this->addGateRequirement($blocked, !$historyCollision, 'LEARNER_HISTORY_ID_COLLISION');
        if ($blocked !== []) {
            return [
                'eligible' => false,
                'blocked' => array_values(array_unique($blocked)),
                'proposal_id' => (string) $proposal['id'],
                'question_id' => $id,
                'proposal_version' => $version,
                'canonical_exists' => $existing !== null,
            ];
        }
        $this->addGateRequirement($blocked, $this->noExactDuplicate($question), 'EXACT_DUPLICATE_QUESTION');
        $validation = \App\Services\Question\QuestionService::validate($question);
        $readiness = \App\Services\RepositoryHealth\Readiness\SourceReadinessService::validateQuestionForCreate($question);
        $validationErrors = array_merge((array) ($validation['errors'] ?? []), (array) ($readiness['errors'] ?? []));
        $this->addGateRequirement($blocked, ($validation['valid'] ?? false) === true && ($readiness['valid'] ?? true) === true, 'CANONICAL_VALIDATION_FAILED:' . implode('|', $validationErrors));
        $this->addGateRequirement($blocked, $this->taxonomyMappingMatchesQuestion($question, $taxonomy), 'TAXONOMY_MAPPING_MISMATCH');
        $this->addGateRequirement($blocked, $this->answerChoiceIsUnique($question), 'PROPOSED_ANSWER_NOT_UNIQUE_CHOICE');
        $studyLink = $this->approvedStudyLink($proposal, $record, $question, $version);
        if (is_array($studyLink)) {
            $this->addGateRequirement($blocked, $this->storage->find('study-question-links', (string) $studyLink['id']) === null, 'STUDY_LINK_ID_COLLISION');
        }
        $previewQuestion = $question;
        unset($previewQuestion['status']);
        $token = hash('sha256', json_encode([$proposal['id'], $version, $previewQuestion, $accountId], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        return [
            'eligible' => $blocked === [],
            'blocked' => array_values(array_unique($blocked)),
            'proposal_id' => (string) $proposal['id'],
            'question_id' => $id,
            'proposal_version' => $version,
            'preview_token' => $token,
            'question' => $question,
            'study_link' => $studyLink,
            'source_review_ids' => array_values(array_map(static fn(mixed $r): string => is_array($r) ? (string) ($r['source_id'] ?? '') : '', array_values(array_filter((array) ($record['source_reviews'] ?? []), 'is_array')))),
            'canonical_exists' => $existing !== null,
        ];
    }

    /** Individual import after a fresh preflight and deliberate confirmation. @return array<string,mixed> */
    public function import(string $proposalId, string $accountId, string $expectedVersion, string $previewToken, bool $confirm): array
    {
        if (!$confirm) throw new RuntimeException('IMPORT_CONFIRMATION_REQUIRED');
        return $this->withLock(function () use ($proposalId, $accountId, $expectedVersion, $previewToken): array {
            $this->recoverIncompleteImportsUnlocked();
            $proposal = $this->findProposal($proposalId);
            if ($proposal === null) throw new RuntimeException('PROPOSAL_NOT_FOUND');
            $record = $this->record((string) $proposal['id']);
            $preflight = $this->preflightUnlocked($proposal, $record, $accountId);
            if (($preflight['eligible'] ?? false) !== true) throw new RuntimeException('IMPORT_PREFLIGHT_BLOCKED:' . implode(',', (array) ($preflight['blocked'] ?? [])));
            $this->assertVersion((string) $preflight['proposal_version'], $expectedVersion);
            if (!hash_equals((string) $preflight['preview_token'], $previewToken)) throw new RuntimeException('IMPORT_PREVIEW_STALE');
            $questionId = (string) $preflight['question_id'];
            $proposalKey = (string) $proposal['id'];
            $journalId = 'academic-import-' . hash('sha256', $proposalKey);
            $link = $preflight['study_link'];
            $journal = [
                'id' => $journalId,
                'proposal_id' => $proposalKey,
                'question_id' => $questionId,
                'proposal_version' => $preflight['proposal_version'],
                'state' => 'PREPARING',
                'study_link_id' => is_array($link) ? (string) $link['id'] : null,
                'study_link' => is_array($link) ? $link : null,
                'created_at' => gmdate(DATE_ATOM),
                'created_by' => $accountId,
            ];
            $this->upsert(self::JOURNAL_COLLECTION, $journal);
            $draft = $preflight['question'];
            $draft['status'] = 'draft';
            $draft['academic_import'] = [
                'state' => 'STAGED', 'operation_id' => $journalId,
                'proposal_id' => $proposalKey, 'proposal_version' => $preflight['proposal_version'],
            ];
            try {
                $this->storage->create('questions', $draft);
                $this->inject('after_draft_question');
                if (is_array($link)) {
                    $link['status'] = 'STAGED';
                    $link['import_operation_id'] = $journalId;
                    $this->storage->create('study-question-links', $link);
                }
                $this->inject('after_study_link');
                $audit = $this->importAudit($preflight, $proposal, $record, $accountId, $journalId);
                $committed = $this->storage->update('questions', $questionId, ['status' => 'active', 'academic_import' => $audit]);
                if (!is_array($committed) || ($committed['status'] ?? '') !== 'active') throw new RuntimeException('CANONICAL_ACTIVATION_FAILED');
                $this->activateJournalStudyLink($journal);
                $this->inject('after_activation');
                $journal['state'] = 'COMMITTED';
                $journal['canonical_audit_id'] = (string) $audit['audit_id'];
                $journal['committed_at'] = gmdate(DATE_ATOM);
                $this->upsert(self::JOURNAL_COLLECTION, $journal);
                $record['imported'] = ['question_id' => $questionId, 'proposal_version' => $preflight['proposal_version'], 'audit_id' => $audit['audit_id'], 'imported_at' => $audit['imported_at']];
                $this->upsert(self::WORKFLOW_COLLECTION, $record);
                return ['status' => 'IMPORTED', 'question' => $committed, 'audit' => $audit];
            } catch (Throwable $exception) {
                $active = $this->storage->find('questions', $questionId);
                $activeAudit = is_array($active['academic_import'] ?? null) ? $active['academic_import'] : [];
                if (($active['status'] ?? '') === 'active' && ($activeAudit['operation_id'] ?? '') === $journalId && ($activeAudit['state'] ?? '') === 'IMPORTED') {
                    $this->activateJournalStudyLink($journal);
                    $journal['state'] = 'COMMITTED';
                    $journal['canonical_audit_id'] = (string) ($activeAudit['audit_id'] ?? '');
                    $journal['committed_at'] = gmdate(DATE_ATOM);
                    $this->upsert(self::JOURNAL_COLLECTION, $journal);
                    return ['status' => 'IMPORTED', 'question' => $active, 'audit' => $activeAudit];
                }
                $this->rollbackJournalUnlocked($journal);
                throw $exception;
            }
        });
    }

    /** Recover interrupted staged imports; active audited questions are retained. */
    public function recoverIncompleteImports(): array
    {
        return $this->withLock(fn(): array => $this->recoverIncompleteImportsUnlocked());
    }

    /** @return list<string> */
    private function recoverIncompleteImportsUnlocked(): array
    {
        $recovered = [];
        foreach ($this->storage->all(self::JOURNAL_COLLECTION) as $journal) {
            if (!is_array($journal) || ($journal['state'] ?? '') !== 'PREPARING') continue;
            $question = $this->storage->find('questions', (string) ($journal['question_id'] ?? ''));
            $audit = is_array($question['academic_import'] ?? null) ? $question['academic_import'] : [];
            if (($question['status'] ?? '') === 'active' && ($audit['operation_id'] ?? '') === ($journal['id'] ?? '') && ($audit['state'] ?? '') === 'IMPORTED') {
                $this->activateJournalStudyLink($journal);
                $journal['state'] = 'COMMITTED';
                $journal['recovered_at'] = gmdate(DATE_ATOM);
                $journal['canonical_audit_id'] = (string) ($audit['audit_id'] ?? '');
                $this->upsert(self::JOURNAL_COLLECTION, $journal);
                $recovered[] = (string) $journal['id'] . ':COMMITTED';
            } else {
                $this->rollbackJournalUnlocked($journal);
                $recovered[] = (string) $journal['id'] . ':ROLLED_BACK';
            }
        }
        return $recovered;
    }

    /** Complete the optional exact study link only for the committed import. */
    private function activateJournalStudyLink(array $journal): void
    {
        $linkId = trim((string) ($journal['study_link_id'] ?? ''));
        if ($linkId === '') return;
        $operationId = (string) ($journal['id'] ?? '');
        $link = $this->storage->find('study-question-links', $linkId);
        if ($link === null) {
            $staged = $journal['study_link'] ?? null;
            if (!is_array($staged) || (string) ($staged['id'] ?? '') !== $linkId) throw new RuntimeException('STUDY_LINK_RECOVERY_EVIDENCE_MISSING');
            $staged['status'] = 'APPROVED';
            $staged['import_operation_id'] = $operationId;
            $this->storage->create('study-question-links', $staged);
            return;
        }
        if (($link['import_operation_id'] ?? '') !== $operationId) throw new RuntimeException('STUDY_LINK_IMPORT_OWNERSHIP_MISMATCH');
        if (($link['status'] ?? '') !== 'APPROVED') {
            $updated = $this->storage->update('study-question-links', $linkId, ['status' => 'APPROVED']);
            if (!is_array($updated) || ($updated['status'] ?? '') !== 'APPROVED') throw new RuntimeException('STUDY_LINK_ACTIVATION_FAILED');
        }
    }

    private function rollbackJournalUnlocked(array $journal): void
    {
        $id = (string) ($journal['question_id'] ?? '');
        $question = $id !== '' ? $this->storage->find('questions', $id) : null;
        if (is_array($question)
            && ($question['status'] ?? '') === 'draft'
            && is_array($question['academic_import'] ?? null)
            && ($question['academic_import']['operation_id'] ?? '') === ($journal['id'] ?? '')) {
            $this->storage->delete('questions', $id);
        }
        if (is_array($question)
            && ($question['status'] ?? '') === 'active'
            && is_array($question['academic_import'] ?? null)
            && ($question['academic_import']['operation_id'] ?? '') === ($journal['id'] ?? '')
            && ($question['academic_import']['state'] ?? '') === 'IMPORTED') {
            $journal['state'] = 'COMMITTED';
            $journal['canonical_audit_id'] = (string) ($question['academic_import']['audit_id'] ?? '');
            $journal['recovered_at'] = gmdate(DATE_ATOM);
            $this->upsert(self::JOURNAL_COLLECTION, $journal);
            return;
        }
        $linkId = trim((string) ($journal['study_link_id'] ?? ''));
        if ($linkId !== '') {
            $link = $this->storage->find('study-question-links', $linkId);
            if (is_array($link) && ($link['import_operation_id'] ?? '') === ($journal['id'] ?? '')) $this->storage->delete('study-question-links', $linkId);
        }
        $journal['state'] = 'ROLLED_BACK';
        $journal['recovered_at'] = gmdate(DATE_ATOM);
        $this->upsert(self::JOURNAL_COLLECTION, $journal);
    }

    /** @return array<string,mixed> */
    private function importAudit(array $preflight, array $proposal, array $record, string $accountId, string $operationId): array
    {
        $approval = null;
        foreach (array_reverse((array) ($record['decisions'] ?? [])) as $event) if (is_array($event) && ($event['decision'] ?? '') === 'APPROVE' && ($event['proposal_version'] ?? '') === $preflight['proposal_version']) { $approval = $event; break; }
        $sourceReviews = [];
        foreach ((array) ($record['source_reviews'] ?? []) as $review) if (is_array($review) && ($review['proposal_version'] ?? '') === $preflight['proposal_version']) $sourceReviews[] = $review;
        return [
            'state' => 'IMPORTED',
            'audit_id' => 'academic-import-' . bin2hex(random_bytes(12)),
            'operation_id' => $operationId,
            'proposal_id' => (string) $proposal['id'],
            'proposal_version' => (string) $preflight['proposal_version'],
            'source_branch' => (string) ($proposal['provenance']['original_source_branch'] ?? 'batch-695-academic-review'),
            'source_commit_sha' => (string) ($proposal['provenance']['original_source_commit_sha'] ?? ''),
            'academic_decision_id' => (string) ($approval['event_id'] ?? ''),
            'academic_decision_sha256' => (string) ($approval['event_sha256'] ?? ''),
            'academic_reviewer_id' => (string) ($approval['reviewer_id'] ?? ''),
            'imported_by' => $accountId,
            'import_authorization_id' => (string) (($this->authorization->grantFor($accountId, (string) $proposal['board_id'], (string) $proposal['subject_id'], 'IMPORT')['id'] ?? '')),
            'source_review_ids' => array_values(array_map(static fn(array $item): string => (string) ($item['source_id'] ?? ''), $sourceReviews)),
            'taxonomy_mapping' => is_array($record['taxonomy_mapping'] ?? null) ? $record['taxonomy_mapping'] : [],
            'study_mapping' => is_array($record['study_mapping'] ?? null) ? $record['study_mapping'] : [],
            'source_evidence' => array_values(array_filter((array) ($proposal['source_evidence'] ?? []), 'is_array')),
            'imported_at' => gmdate(DATE_ATOM),
            'canonical_question_sha256' => hash('sha256', json_encode($preflight['question'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)),
        ];
    }

    /** @return array<string,mixed> */
    private function canonicalQuestion(array $proposal, array $record, string $status): array
    {
        $content = (array) ($proposal['proposed_question'] ?? []);
        $taxonomy = is_array($record['taxonomy_mapping'] ?? null) ? $record['taxonomy_mapping'] : [];
        $choices = array_values(array_map(static fn(mixed $value): string => trim((string) $value), (array) ($content['choices'] ?? [])));
        $question = [
            'id' => ctype_digit((string) ($proposal['question_id'] ?? '')) ? (int) $proposal['question_id'] : (string) ($proposal['question_id'] ?? ''),
            'question' => trim((string) ($content['stem'] ?? '')),
            'choices' => $choices,
            'answer' => trim((string) ($content['proposed_answer'] ?? '')),
            'explanation' => trim((string) ($content['explanation'] ?? '')),
            'difficulty' => strtolower(trim((string) ($content['difficulty'] ?? ''))),
            'type' => 'multiple_choice',
            'status' => $status,
            'taxonomy' => [
                'board_id' => (string) ($taxonomy['board_id'] ?? ''),
                'subject_id' => (string) ($taxonomy['subject_id'] ?? ''),
                'domain_id' => (string) ($taxonomy['domain_id'] ?? ''),
                'topic_id' => (string) ($taxonomy['topic_id'] ?? ''),
                'concept_id' => (string) ($taxonomy['concept_id'] ?? ''),
            ],
            'source_ids' => array_values(array_unique(array_map('strval', (array) ($proposal['source_ids'] ?? [])))),
            'source_provenance' => array_values(array_filter((array) ($proposal['source_evidence'] ?? []), 'is_array')),
            'source_scope' => array_values(array_unique(array_filter(array_map(static fn(mixed $review): string => is_array($review) ? trim((string) ($review['source_scope'] ?? '')) : '', (array) ($record['source_reviews'] ?? []))))),
            'study_material_ids' => (($record['study_mapping']['status'] ?? '') === 'APPROVED_EXACT_LINK' && ($record['study_mapping']['proposal_version'] ?? '') === $this->proposalVersion($proposal, $record)) ? [(string) ($record['study_mapping']['material_id'] ?? '')] : [],
        ];
        if (is_int($taxonomy['required_depth'] ?? null)) {
            $reuse = QuestionReuseMetadataService::forAuthoring([
                'taxonomy' => $question['taxonomy'], 'question' => $question['question'], 'explanation' => $question['explanation'],
            ]);
            $reuse['required_depth'] = $taxonomy['required_depth'];
            $reuse['reuse_policy'] = 'REVIEW';
            $reuse['metadata_status'] = 'REVIEW_REQUIRED';
            $reuse['metadata_provenance'][] = [
                'type' => 'MANUAL_REVIEW',
                'reference' => (string) ($proposal['id'] ?? ''),
                'note' => 'Scoped academic reviewer recorded the required depth; reuse-policy approval remains separate.',
                'verified_status' => 'REVIEW_REQUIRED',
            ];
            $question['reuse'] = $reuse;
        }
        return $question;
    }

    /** @return array<string,mixed>|null */
    private function approvedStudyLink(array $proposal, array $record, array $question, string $version): ?array
    {
        $mapping = is_array($record['study_mapping'] ?? null) ? $record['study_mapping'] : [];
        if (($mapping['status'] ?? '') !== 'APPROVED_EXACT_LINK' || ($mapping['proposal_version'] ?? '') !== $version) return null;
        $material = (string) ($mapping['material_id'] ?? '');
        $section = (string) ($mapping['section_id'] ?? '');
        if (!$this->studySectionExists($material, $section, (string) ($proposal['board_id'] ?? ''), (string) ($proposal['subject_id'] ?? ''))) return null;
        return [
            'id' => 'link-b714-' . substr(hash('sha256', (string) $proposal['id']), 0, 24),
            'question_id' => (string) $question['id'],
            'material_id' => $material,
            'section_id' => $section,
            'source_ids' => array_values(array_unique(array_map('strval', (array) ($proposal['source_ids'] ?? [])))),
            'semantic_review_status' => 'APPROVED_BY_AUTHORIZED_REVIEWER',
            'approval_evidence_id' => 'study-review-' . substr(hash('sha256', (string) $proposal['id'] . $version), 0, 20),
            'review_guidance' => trim((string) ($mapping['rationale'] ?? '')),
        ];
    }

    private function studySectionExists(string $materialId, string $sectionId, string $boardId = '', string $subjectId = ''): bool
    {
        if ($materialId === '' || $sectionId === '' || $boardId === '' || $subjectId === '') return false;
        $material = StudyLibraryService::find($materialId);
        if (!is_array($material)) return false;
        $taxonomy = is_array($material['taxonomy'] ?? null) ? $material['taxonomy'] : [];
        if (strtolower((string) ($taxonomy['subject_id'] ?? '')) !== strtolower($subjectId)) return false;
        $exams = array_map(static fn(mixed $exam): string => strtolower(trim((string) $exam)), (array) ($material['exams'] ?? []));
        if (!in_array(strtolower($boardId), $exams, true)) return false;
        foreach ($this->storage->all('study-lessons') as $lesson) {
            if (!is_array($lesson)
                || ($lesson['status'] ?? '') !== 'published'
                || (string) ($lesson['material_id'] ?? '') !== $materialId
                || strtolower((string) ($lesson['board_id'] ?? '')) !== strtolower($boardId)
                || strtolower((string) ($lesson['subject_id'] ?? '')) !== strtolower($subjectId)) continue;
            foreach ((array) ($lesson['sections'] ?? []) as $section) {
                if (is_array($section) && (string) ($section['id'] ?? '') === $sectionId) return true;
            }
        }
        return false;
    }

    /** @return list<string> */
    public static function taxonomyErrors(array $taxonomy): array
    {
        $errors = [];
        foreach ([['board_id','boards'], ['subject_id','subjects'], ['domain_id','taxonomy/domains'], ['topic_id','taxonomy/topics'], ['concept_id','taxonomy/concepts']] as [$field, $collection]) {
            $value = trim((string) ($taxonomy[$field] ?? ''));
            if ($value === '' || TaxonomyStorageService::find($collection, $value) === null) $errors[] = 'MISSING_OR_UNREGISTERED_' . strtoupper($field);
        }
        $domain = TaxonomyStorageService::find('taxonomy/domains', (string) ($taxonomy['domain_id'] ?? ''));
        $topic = TaxonomyStorageService::find('taxonomy/topics', (string) ($taxonomy['topic_id'] ?? ''));
        $concept = TaxonomyStorageService::find('taxonomy/concepts', (string) ($taxonomy['concept_id'] ?? ''));
        if (is_array($domain) && (string) ($domain['subject_id'] ?? '') !== (string) ($taxonomy['subject_id'] ?? '')) $errors[] = 'DOMAIN_SUBJECT_MISMATCH';
        if (is_array($topic) && (string) ($topic['domain_id'] ?? '') !== (string) ($taxonomy['domain_id'] ?? '')) $errors[] = 'TOPIC_DOMAIN_MISMATCH';
        if (is_array($concept) && (string) ($concept['topic_id'] ?? '') !== (string) ($taxonomy['topic_id'] ?? '')) $errors[] = 'CONCEPT_TOPIC_MISMATCH';
        if (!TaxonomyStorageService::hasBoardSubject((string) ($taxonomy['board_id'] ?? ''), (string) ($taxonomy['subject_id'] ?? ''))) $errors[] = 'BOARD_SUBJECT_MISMATCH';
        return array_values(array_unique($errors));
    }

    /** @return array<string,mixed> */
    private function event(string $accountId, array $proposal, string $decision, string $version, string $before, string $after, string $rationale, string $capability = 'REVIEW'): array
    {
        $grant = $this->authorization->grantFor($accountId, (string) ($proposal['board_id'] ?? ''), (string) ($proposal['subject_id'] ?? ''), $capability) ?? [];
        return [
            'event_id' => 'review-' . bin2hex(random_bytes(12)),
            'proposal_id' => (string) $proposal['id'],
            'question_id' => (string) ($proposal['question_id'] ?? ''),
            'reviewer_id' => $accountId,
            'decision' => $decision,
            'rationale' => $rationale,
            'source_evidence' => array_values(array_map(static fn(array $e): string => (string) ($e['source_id'] ?? ''), array_values(array_filter((array) ($proposal['source_evidence'] ?? []), 'is_array')))),
            'review_timestamp' => gmdate(DATE_ATOM),
            'proposal_version' => $version,
            'review_state_before' => $before,
            'review_state_after' => $after,
            'board_id' => (string) ($proposal['board_id'] ?? ''),
            'subject_id' => (string) ($proposal['subject_id'] ?? ''),
            'reviewer_scope' => ['board_id' => (string) ($proposal['board_id'] ?? ''), 'subject_id' => (string) ($proposal['subject_id'] ?? '')],
            'reviewer_grant_id' => (string) ($grant['id'] ?? ''),
            'reviewer_qualification_reference' => (string) ($grant['qualification_reference'] ?? ''),
        ];
    }

    private function appendEvent(array &$record, array $event): void
    {
        $events = is_array($record['decisions'] ?? null) ? $record['decisions'] : [];
        $last = $events === [] ? null : $events[array_key_last($events)];
        $previous = is_array($last) ? (string) ($last['event_sha256'] ?? '') : 'GENESIS';
        $event['previous_event_sha256'] = $previous;
        unset($event['event_sha256']);
        $event['event_sha256'] = hash('sha256', $previous . "\n" . json_encode($event, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        $events[] = $event;
        $record['decisions'] = $events;
    }

    private function assertHistoryIntegrity(array $record): void
    {
        $previous = 'GENESIS';
        foreach ((array) ($record['decisions'] ?? []) as $event) {
            if (!is_array($event) || ($event['previous_event_sha256'] ?? null) !== $previous) throw new RuntimeException('ACADEMIC_REVIEW_HISTORY_INTEGRITY_FAILURE');
            $expected = (string) ($event['event_sha256'] ?? '');
            $payload = $event;
            unset($payload['event_sha256']);
            $actual = hash('sha256', $previous . "\n" . json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            if ($expected === '' || !hash_equals($expected, $actual)) throw new RuntimeException('ACADEMIC_REVIEW_HISTORY_INTEGRITY_FAILURE');
            $previous = $expected;
        }
    }

    private function proposalVersion(array $proposal, array $record): string
    {
        $revisions = is_array($record['revisions'] ?? null) ? $record['revisions'] : [];
        $latest = end($revisions);
        $question = is_array($latest['proposed_question'] ?? null) ? $latest['proposed_question'] : (is_array($proposal['proposed_question'] ?? null) ? $proposal['proposed_question'] : []);
        $payload = [
            'proposal_id' => (string) ($proposal['id'] ?? ''),
            'question_id' => (string) ($proposal['question_id'] ?? ''),
            'board_id' => (string) ($proposal['board_id'] ?? ''),
            'subject_id' => (string) ($proposal['subject_id'] ?? ''),
            'question' => $question,
            'source_ids' => array_values((array) ($proposal['source_ids'] ?? [])),
            'source_evidence' => array_values((array) ($proposal['source_evidence'] ?? [])),
        ];
        return hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    private function currentVersionProposal(array $proposal, array $record): array
    {
        $revision = end($record['revisions']);
        if (is_array($revision) && is_array($revision['proposed_question'] ?? null)) $proposal['proposed_question'] = $revision['proposed_question'];
        return $proposal;
    }

    private function mutate(string $proposalId, callable $callback): array
    {
        return $this->withLock(function () use ($proposalId, $callback): array {
            $id = $this->normalizeProposalId($proposalId);
            $proposal = $this->findProposal($id);
            if ($proposal === null) throw new RuntimeException('PROPOSAL_NOT_FOUND');
            $record = $this->record($id);
            $updated = $callback($proposal, $record);
            $updated['updated_at'] = gmdate(DATE_ATOM);
            $this->upsert(self::WORKFLOW_COLLECTION, $updated);
            return $updated;
        });
    }

    private function upsert(string $collection, array $record): void
    {
        $id = (string) ($record['id'] ?? '');
        if ($id === '') throw new RuntimeException('WORKFLOW_RECORD_ID_REQUIRED');
        if ($this->storage->find($collection, $id) === null) $this->storage->create($collection, $record);
        else $this->storage->update($collection, $id, $record);
    }

    private function withLock(callable $callback): mixed
    {
        if (!is_dir($this->lockDirectory) && !mkdir($this->lockDirectory, 0700, true) && !is_dir($this->lockDirectory)) throw new RuntimeException('ACADEMIC_REVIEW_STORAGE_UNAVAILABLE');
        $real = realpath($this->lockDirectory);
        $public = realpath(dirname(__DIR__, 4) . '/public');
        if (!is_string($real) || (is_string($public) && ($real === $public || str_starts_with($real, $public . DIRECTORY_SEPARATOR)))) throw new RuntimeException('ACADEMIC_REVIEW_STORAGE_MUST_BE_PRIVATE');
        $path = rtrim($real, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '.academic-review.lock';
        if (is_link($path)) throw new RuntimeException('ACADEMIC_REVIEW_LOCK_PATH_UNSAFE');
        $handle = fopen($path, 'c');
        if ($handle === false || !flock($handle, LOCK_EX)) throw new RuntimeException('ACADEMIC_REVIEW_LOCK_UNAVAILABLE');
        try { return $callback(); }
        finally { flock($handle, LOCK_UN); fclose($handle); }
    }

    private function assertAllowed(string $accountId, array $proposal, string $capability): void
    {
        if ($accountId === '' || !$this->authorization->allows($accountId, (string) $proposal['board_id'], (string) $proposal['subject_id'], $capability)) throw new RuntimeException('REVIEWER_NOT_AUTHORIZED:' . $capability);
    }

    private function assertVersion(string $actual, string $expected): void
    {
        if ($expected === '' || !hash_equals($actual, $expected)) throw new RuntimeException('STALE_PROPOSAL_VERSION');
    }

    private function addGateRequirement(array &$blocked, bool $condition, string $reason): void
    {
        if (!$condition) $blocked[] = $reason;
    }

    private function normalizeProposalId(string $id): string
    {
        $id = trim($id);
        if (preg_match('/\A\d+\z/', $id) === 1) return 'batch695-' . $id;
        return $id;
    }

    private function taxonomyMappingMatchesQuestion(array $question, array $taxonomy): bool
    {
        foreach (['board_id','subject_id','domain_id','topic_id','concept_id'] as $field) if (($question['taxonomy'][$field] ?? '') !== ($taxonomy[$field] ?? null)) return false;
        return true;
    }

    private function answerChoiceIsUnique(array $question): bool
    {
        $answer = trim((string) ($question['answer'] ?? ''));
        return $answer !== '' && count(array_filter((array) ($question['choices'] ?? []), static fn(mixed $choice): bool => trim((string) $choice) === $answer)) === 1;
    }

    private function approvalChecksComplete(array $approval, string $boardId): bool
    {
        $checks = is_array($approval['confirmations'] ?? null) ? $approval['confirmations'] : [];
        foreach (['question_accuracy','correct_answer','explanation_correctness','exam_relevance','subject_classification','adequate_source_support','source_currentness'] as $key) if (($checks[$key] ?? false) !== true) return false;
        if (in_array(strtolower($boardId), ['let', 'architecture'], true) && ($checks['regulatory_scope_currentness'] ?? false) !== true) return false;
        return strtolower($boardId) !== 'nursing' || ($checks['clinical_safety'] ?? false) === true;
    }

    private function taxonomyMappingHash(mixed $taxonomy): string
    {
        if (!is_array($taxonomy)) return '';
        $keys = ['board_id','subject_id','domain_id','topic_id','concept_id','required_depth'];
        $payload = array_intersect_key($taxonomy, array_flip($keys));
        return hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    private function studyMappingHash(mixed $mapping): string
    {
        if (!is_array($mapping)) return '';
        return hash('sha256', json_encode($mapping, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    private function noExactDuplicate(array $candidate): bool
    {
        $stem = self::normalizeStem((string) ($candidate['question'] ?? ''));
        foreach ($this->storage->all('questions') as $existing) {
            if (!is_array($existing)) continue;
            if (self::normalizeStem((string) ($existing['question'] ?? '')) === $stem) return false;
        }
        return $stem !== '';
    }

    private static function normalizeStem(string $stem): string
    {
        $stem = preg_replace('/\s+/u', ' ', trim($stem)) ?? trim($stem);
        return mb_strtolower($stem);
    }

    private function attemptContainsQuestion(mixed $attempt, string $id): bool
    {
        if (!is_array($attempt)) return false;
        foreach ((array) ($attempt['review'] ?? []) as $item) if (is_array($item) && (string) ($item['question_id'] ?? $item['question']['id'] ?? '') === $id) return true;
        foreach ((array) ($attempt['questions'] ?? []) as $candidate) if ((string) $candidate === $id) return true;
        return false;
    }

    private function inject(string $stage): void
    {
        if ($this->faultInjector !== null) ($this->faultInjector)($stage);
    }
}
