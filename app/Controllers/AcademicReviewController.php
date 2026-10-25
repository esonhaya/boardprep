<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\App;
use App\Services\Accounts\AccountSessionService;
use App\Services\Question\AcademicReviewProposalService;
use App\Services\Question\AcademicReviewerAuthorizationService;
use App\Services\Question\AcademicReviewWorkflowService;
use App\Services\Question\Batch715AcademicPilotService;
use App\Services\Shared\CsrfTokenService;
use App\Services\Shared\TaxonomyStorageService;
use App\Services\Shared\SessionService;
use Throwable;

/** Developer-surface workbench whose mutations require a scoped reviewer grant. */
final class AcademicReviewController extends BaseDeveloperController
{
    public static function index(): void
    {
        [$account, $access] = self::authorizedAccountAny();
        $workflow = new AcademicReviewWorkflowService();
        $pageNumber = filter_var(Request::queryString('page_num'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;
        $page = $workflow->page([
            'search' => Request::queryString('search'),
            'board' => Request::queryString('board'),
            'subject' => Request::queryString('subject'),
            'status' => Request::queryString('status'),
            'assigned' => Request::queryString('assigned'),
            'pilot' => Request::queryString('pilot'),
        ], $pageNumber, 25, (string) $account['account_id']);
        $scopeOptions = self::allScopes($access, (string) $account['account_id']);
        $pilotSummary = self::pilotSummary($workflow, $scopeOptions, (string) $account['account_id']);
        self::renderDeveloper('developer/academic-review/index', [
            'pageTitle' => 'Academic Review Workbench',
            'page' => $page,
            'filters' => [
                'search' => Request::queryString('search'), 'board' => Request::queryString('board'),
                'subject' => Request::queryString('subject'), 'status' => Request::queryString('status'),
                'assigned' => Request::queryString('assigned'), 'pilot' => Request::queryString('pilot'),
            ],
            'scopeOptions' => $scopeOptions,
            'pilotSummary' => $pilotSummary,
            'csrfToken' => CsrfTokenService::token(),
        ]);
    }

    public static function proposal(): void
    {
        $id = Request::queryString('id');
        $workflow = new AcademicReviewWorkflowService();
        $proposal = $workflow->findProposal($id);
        if ($proposal === null) Response::text('Proposal not found.', 404);
        [$account, $access] = self::authorizedAccountAny($proposal);
        $canonicalQuestion = App::storage()->find('questions', (string) ($proposal['question_id'] ?? ''));
        $workflowRecord = $workflow->record((string) $proposal['id']);
        $currentProposal = $workflow->currentProposal((string) $proposal['id']);
        $version = $workflow->version((string) $proposal['id']);
        $capabilities = [];
        foreach (['REVIEW', 'SOURCE_REVIEW', 'TAXONOMY_RECONCILE', 'STUDY_LINK', 'EDIT_PROPOSAL', 'IMPORT'] as $capability) {
            $capabilities[$capability] = $access->allows((string) $account['account_id'], (string) $proposal['board_id'], (string) $proposal['subject_id'], $capability);
        }
        $preflight = null;
        if ($capabilities['IMPORT']) {
            try { $preflight = $workflow->importPreflight((string) $proposal['id'], (string) $account['account_id']); }
            catch (Throwable $exception) { $preflight = ['eligible' => false, 'blocked' => ['PREFLIGHT_ERROR:' . self::safeError($exception)]]; }
        }
        $domainOptions = array_values(array_filter(TaxonomyStorageService::domains(), static fn(array $row): bool => (string) ($row['subject_id'] ?? '') === (string) $proposal['subject_id']));
        $topicOptions = array_values(array_filter(TaxonomyStorageService::topics(), static fn(array $row): bool => in_array((string) ($row['domain_id'] ?? ''), array_map(static fn(array $row): string => (string) ($row['id'] ?? ''), $domainOptions), true)));
        $topicIds = array_map(static fn(array $row): string => (string) ($row['id'] ?? ''), $topicOptions);
        $conceptOptions = array_values(array_filter(TaxonomyStorageService::concepts(), static fn(array $row): bool => in_array((string) ($row['topic_id'] ?? ''), $topicIds, true)));
        $pilotItem = Batch715AcademicPilotService::find((string) ($proposal['question_id'] ?? ''));
        $pilotEvidence = $pilotItem === null ? null : Batch715AcademicPilotService::evidence((string) ($proposal['question_id'] ?? ''));
        self::renderDeveloper('developer/academic-review/proposal', [
            'pageTitle' => 'Review Proposal ' . (string) ($proposal['question_id'] ?? ''),
            'proposal' => $currentProposal,
            'originalProposal' => $proposal,
            'canonicalQuestion' => $canonicalQuestion,
            'workflow' => $workflowRecord,
            'proposalVersion' => $version,
            'capabilities' => $capabilities,
            'preflight' => $preflight,
            'csrfToken' => CsrfTokenService::token(),
            'domainOptions' => $domainOptions,
            'topicOptions' => $topicOptions,
            'conceptOptions' => $conceptOptions,
            'pilotItem' => $pilotItem,
            'pilotEvidence' => $pilotEvidence,
            'pilotEvidenceVersionCurrent' => $pilotItem === null || hash_equals((string) ($pilotItem['question_version'] ?? ''), $version),
            'nextReviewerAction' => self::nextReviewerAction($proposal, $workflowRecord, $capabilities, (string) $account['account_id'], $preflight),
            'boardName' => TaxonomyStorageService::label('boards', $proposal['board_id'] ?? ''),
            'subjectName' => TaxonomyStorageService::label('subjects', $proposal['subject_id'] ?? ''),
            'notice' => Request::queryString('notice'),
        ]);
    }

    public static function assign(): void
    {
        self::post('REVIEW', static function (AcademicReviewWorkflowService $service, array $account, array $proposal): void {
            $service->assign((string) $proposal['id'], (string) $account['account_id'], self::input('proposal_version'));
        });
    }

    public static function start(): void
    {
        self::post('REVIEW', static function (AcademicReviewWorkflowService $service, array $account, array $proposal): void {
            $service->startReview((string) $proposal['id'], (string) $account['account_id'], self::input('proposal_version'));
        });
    }

    public static function decision(): void
    {
        self::post('REVIEW', static function (AcademicReviewWorkflowService $service, array $account, array $proposal): void {
            $keys = ['question_accuracy','correct_answer','explanation_correctness','exam_relevance','subject_classification','adequate_source_support','source_currentness','clinical_safety','regulatory_scope_currentness'];
            $confirmations = [];
            foreach ($keys as $key) $confirmations[$key] = self::input($key) === '1';
            $service->decide((string) $proposal['id'], (string) $account['account_id'], [
                'decision' => self::input('decision'), 'proposal_version' => self::input('proposal_version'),
                'rationale' => self::input('rationale'), 'confirmations' => $confirmations,
            ]);
        });
    }

    public static function sourceReview(): void
    {
        self::post('SOURCE_REVIEW', static function (AcademicReviewWorkflowService $service, array $account, array $proposal): void {
            $service->reviewSource((string) $proposal['id'], self::input('source_id'), (string) $account['account_id'], [
                'proposal_version' => self::input('proposal_version'), 'status' => self::input('status'),
                'source_identity_verified' => self::input('source_identity_verified') === '1',
                'source_content_examined' => self::input('source_content_examined') === '1',
                'source_currentness_confirmed' => self::input('source_currentness_confirmed') === '1',
                'source_locator' => self::input('source_locator'), 'rationale' => self::input('rationale'),
                'limitations' => self::input('limitations'), 'source_scope' => self::input('source_scope'),
            ]);
        });
    }

    public static function taxonomy(): void
    {
        self::post('TAXONOMY_RECONCILE', static function (AcademicReviewWorkflowService $service, array $account, array $proposal): void {
            $service->reconcileTaxonomy((string) $proposal['id'], (string) $account['account_id'], self::input('proposal_version'), [
                'board_id' => self::input('board_id'), 'subject_id' => self::input('subject_id'),
                'domain_id' => self::input('domain_id'), 'topic_id' => self::input('topic_id'), 'concept_id' => self::input('concept_id'),
                'required_depth' => self::input('required_depth'),
            ], self::input('rationale'));
        });
    }

    public static function taxonomyProposal(): void
    {
        self::post('TAXONOMY_RECONCILE', static function (AcademicReviewWorkflowService $service, array $account, array $proposal): void {
            $service->proposeTaxonomyEntry((string) $proposal['id'], (string) $account['account_id'], self::input('proposal_version'), self::input('kind'), self::input('label'), self::input('parent_id'), self::input('rationale'));
        });
    }

    public static function studyLink(): void
    {
        self::post('STUDY_LINK', static function (AcademicReviewWorkflowService $service, array $account, array $proposal): void {
            $service->reconcileStudyLink((string) $proposal['id'], (string) $account['account_id'], self::input('proposal_version'), [
                'status' => self::input('status'), 'material_id' => self::input('material_id'), 'section_id' => self::input('section_id'),
            ], self::input('rationale'));
        });
    }

    public static function revision(): void
    {
        self::post('EDIT_PROPOSAL', static function (AcademicReviewWorkflowService $service, array $account, array $proposal): void {
            $service->recordRevision((string) $proposal['id'], (string) $account['account_id'], self::input('proposal_version'), [
                'stem' => self::input('stem'), 'choices' => (array) \App\Core\Request::input('choices', []),
                'proposed_answer' => self::input('proposed_answer'), 'explanation' => self::input('explanation'), 'difficulty' => self::input('difficulty'),
            ], self::input('reason'));
        });
    }

    public static function import(): void
    {
        self::post('IMPORT', static function (AcademicReviewWorkflowService $service, array $account, array $proposal): void {
            $service->import((string) $proposal['id'], (string) $account['account_id'], self::input('proposal_version'), self::input('preview_token'), self::input('confirm') === '1');
        });
    }

    /** @param callable(AcademicReviewWorkflowService,array,array):void $operation */
    private static function post(string $capability, callable $operation): void
    {
        if (!CsrfTokenService::valid(Request::input('csrf_token'))) Response::text('Invalid CSRF token.', 403);
        $id = self::input('proposal_id');
        $workflow = new AcademicReviewWorkflowService();
        $proposal = $workflow->findProposal($id);
        if ($proposal === null) Response::text('Proposal not found.', 404);
        [$account] = self::authorizedAccount($capability, $proposal);
        try {
            $operation($workflow, $account, $proposal);
            Response::redirect('/developer/academic-review/proposal?id=' . rawurlencode((string) $proposal['id']) . '&notice=Action%20recorded', 303);
        } catch (Throwable $exception) {
            $notice = self::safeError($exception);
            Response::redirect('/developer/academic-review/proposal?id=' . rawurlencode((string) $proposal['id']) . '&notice=' . rawurlencode($notice), 303);
        }
    }

    /** @return array{array<string,mixed>,AcademicReviewerAuthorizationService} */
    private static function authorizedAccount(string $capability, ?array $proposal = null): array
    {
        $account = AccountSessionService::current();
        if (!is_array($account)) Response::text('Authenticated reviewer account required.', 401);
        $access = new AcademicReviewerAuthorizationService();
        $accountId = (string) ($account['account_id'] ?? '');
        if ($proposal !== null) {
            if (!$access->allows($accountId, (string) ($proposal['board_id'] ?? ''), (string) ($proposal['subject_id'] ?? ''), $capability)) Response::text('Reviewer authorization does not cover this proposal action.', 403);
        } elseif ($access->scopesForAccount($accountId, $capability) === []) {
            Response::text('Authenticated account has no active academic reviewer scope.', 403);
        }
        return [$account, $access];
    }

    /** Allow a reviewer or a separately authorized importer to inspect their scoped queue. */
    private static function authorizedAccountAny(?array $proposal = null): array
    {
        $account = AccountSessionService::current();
        if (!is_array($account)) Response::text('Authenticated reviewer account required.', 401);
        $access = new AcademicReviewerAuthorizationService();
        $accountId = (string) ($account['account_id'] ?? '');
        if ($proposal === null) {
            if (self::allScopes($access, $accountId) === []) Response::text('Authenticated account has no active academic-review capability.', 403);
        } else {
            $allowed = false;
            foreach (['REVIEW', 'SOURCE_REVIEW', 'TAXONOMY_RECONCILE', 'STUDY_LINK', 'EDIT_PROPOSAL', 'IMPORT'] as $capability) {
                if ($access->allows($accountId, (string) ($proposal['board_id'] ?? ''), (string) ($proposal['subject_id'] ?? ''), $capability)) { $allowed = true; break; }
            }
            if (!$allowed) Response::text('Reviewer authorization does not cover this proposal.', 403);
        }
        return [$account, $access];
    }

    /** @return list<array{board_id:string,subject_id:string,capabilities:list<string>}> */
    private static function allScopes(AcademicReviewerAuthorizationService $access, string $accountId): array
    {
        $scopes = [];
        foreach (['REVIEW', 'SOURCE_REVIEW', 'TAXONOMY_RECONCILE', 'STUDY_LINK', 'EDIT_PROPOSAL', 'IMPORT'] as $capability) {
            foreach ($access->scopesForAccount($accountId, $capability) as $scope) {
                $key = $scope['board_id'] . "\0" . $scope['subject_id'];
                $scopes[$key] = $scopes[$key] ?? ['board_id' => $scope['board_id'], 'subject_id' => $scope['subject_id'], 'capabilities' => []];
                $scopes[$key]['capabilities'] = array_values(array_unique(array_merge($scopes[$key]['capabilities'], $scope['capabilities'])));
            }
        }
        return array_values($scopes);
    }

    /** @param list<array{board_id:string,subject_id:string,capabilities:list<string>}> $scopes
     * @return array<string,mixed>
     */
    private static function pilotSummary(AcademicReviewWorkflowService $workflow, array $scopes, string $accountId): array
    {
        $scopeKeys = [];
        foreach ($scopes as $scope) $scopeKeys[$scope['board_id'] . "\0" . $scope['subject_id']] = true;
        $summary = [
            'target' => 20, 'visible_in_scope' => 0, 'assigned_to_me' => 0,
            'states' => array_fill_keys(['PENDING_REVIEW','ASSIGNED','IN_REVIEW','REVISION_REQUESTED','APPROVED','REJECTED','DEFERRED'], 0),
            'decisions_recorded' => 0, 'source_reviews_pending' => 0,
            'taxonomy_adjudications_pending' => 0, 'study_adjudications_pending' => 0,
        ];
        foreach (Batch715AcademicPilotService::cohort()['items'] as $item) {
            $scopeKey = (string) ($item['board']['id'] ?? '') . "\0" . (string) ($item['subject']['id'] ?? '');
            if (!isset($scopeKeys[$scopeKey])) continue;
            $summary['visible_in_scope']++;
            $record = $workflow->record((string) ($item['proposal_id'] ?? ''));
            $state = strtoupper((string) ($record['state'] ?? 'PENDING_REVIEW'));
            if (isset($summary['states'][$state])) $summary['states'][$state]++;
            if ((string) ($record['assigned_reviewer_id'] ?? '') === $accountId) $summary['assigned_to_me']++;
            foreach ((array) ($record['decisions'] ?? []) as $event) {
                if (is_array($event) && in_array(strtoupper((string) ($event['decision'] ?? '')), ['APPROVE','REQUEST_REVISION','REJECT','DEFER'], true)) {
                    $summary['decisions_recorded']++;
                    break;
                }
            }
            $proposal = $workflow->findProposal((string) ($item['proposal_id'] ?? '')) ?? [];
            $requiredSources = array_values((array) ($proposal['source_ids'] ?? []));
            $reviewedSources = is_array($record['source_reviews'] ?? null) ? $record['source_reviews'] : [];
            if (count(array_intersect($requiredSources, array_keys($reviewedSources))) < count($requiredSources)) $summary['source_reviews_pending']++;
            if (!is_array($record['taxonomy_mapping'] ?? null)) $summary['taxonomy_adjudications_pending']++;
            if (!is_array($record['study_mapping'] ?? null)) $summary['study_adjudications_pending']++;
        }
        return $summary;
    }

    /** @param array<string,mixed> $proposal @param array<string,mixed> $workflow
     * @param array<string,bool> $capabilities @param array<string,mixed>|null $preflight
     */
    private static function nextReviewerAction(array $proposal, array $workflow, array $capabilities, string $accountId, ?array $preflight): string
    {
        $state = strtoupper((string) ($workflow['state'] ?? 'PENDING_REVIEW'));
        $assigned = (string) ($workflow['assigned_reviewer_id'] ?? '');
        if ($state === 'PENDING_REVIEW' || $state === 'DEFERRED') return !empty($capabilities['REVIEW']) ? 'Assign this proposal to yourself, then start its version-bound review.' : 'No action: this account has no academic-review capability for this scope.';
        if ($state === 'ASSIGNED') return $assigned === $accountId ? 'Start the assigned review, then inspect each evidence and classification gate.' : 'Assigned to another reviewer; wait for that reviewer or use the scoped assignment policy.';
        if ($state === 'IN_REVIEW' && $assigned !== $accountId) return 'Another reviewer owns the active review; do not submit a decision from this account.';
        if ($state === 'IN_REVIEW') {
            $proposalSources = array_values((array) ($proposal['source_ids'] ?? []));
            $reviewedSources = is_array($workflow['source_reviews'] ?? null) ? $workflow['source_reviews'] : [];
            if (!empty($capabilities['SOURCE_REVIEW']) && count(array_intersect($proposalSources, array_keys($reviewedSources))) < count($proposalSources)) return 'Inspect the cited content and record the independent item-level source review.';
            if (!is_array($workflow['taxonomy_mapping'] ?? null)) return !empty($capabilities['TAXONOMY_RECONCILE']) ? 'Reconcile taxonomy to an active scoped entry or leave a documented governance request pending.' : 'Taxonomy is unresolved; a scoped taxonomy reviewer must adjudicate it.';
            if (!is_array($workflow['study_mapping'] ?? null)) return !empty($capabilities['STUDY_LINK']) ? 'Verify the exact published section, or explicitly record the relationship as unresolved/optional.' : 'Study relationship is unresolved; a scoped study-link reviewer must adjudicate it.';
            return 'Complete the exact-version academic checklist and record an explicit decision; source review remains independent.';
        }
        if ($state === 'APPROVED') {
            if (is_array($preflight) && !empty($preflight['eligible'])) return 'Inspect the exact preview and confirm this single item under a separate import authorization.';
            $blocked = array_values((array) ($preflight['blocked'] ?? []));
            return $blocked === [] ? 'A separate authorized importer must run preflight.' : 'Import remains blocked: ' . implode(', ', array_slice($blocked, 0, 3));
        }
        if (in_array($state, ['REVISION_REQUESTED','REJECTED'], true)) return 'Review the preserved decision history and follow the authorized revision or closure process.';
        return 'Inspect the proposal history and follow the next action allowed by the current state and scope.';
    }

    private static function input(string $key): string
    {
        $value = Request::input($key, '');
        return is_scalar($value) ? trim((string) $value) : '';
    }

    private static function safeError(Throwable $exception): string
    {
        $message = $exception->getMessage();
        return preg_match('/\A[A-Z0-9_:-]{3,200}\z/', $message) === 1 ? $message : 'REVIEW_ACTION_FAILED';
    }
}
