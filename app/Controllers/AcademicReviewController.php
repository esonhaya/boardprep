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
        ], $pageNumber, 25, (string) $account['account_id']);
        $scopeOptions = self::allScopes($access, (string) $account['account_id']);
        self::renderDeveloper('developer/academic-review/index', [
            'pageTitle' => 'Academic Review Workbench',
            'page' => $page,
            'filters' => [
                'search' => Request::queryString('search'), 'board' => Request::queryString('board'),
                'subject' => Request::queryString('subject'), 'status' => Request::queryString('status'),
                'assigned' => Request::queryString('assigned'),
            ],
            'scopeOptions' => $scopeOptions,
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
            $keys = ['question_accuracy','correct_answer','explanation_correctness','exam_relevance','subject_classification','adequate_source_support','source_currentness','clinical_safety'];
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
