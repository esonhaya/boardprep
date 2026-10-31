<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\Accounts\AccountSessionService;
use App\Services\Question\AcademicReviewerAuthorizationService;
use App\Services\Question\QuestionIssueReportService;
use App\Services\Shared\CsrfTokenService;
use InvalidArgumentException;
use Throwable;

/** Reviewer triage does not change questions, answers, eligibility or academic decisions. */
final class QuestionIssueReportsController extends BaseDeveloperController
{
    public static function index(): void
    {
        [$account, $access] = self::reviewer();
        $accountId = (string) ($account['account_id'] ?? '');
        $scopes = $access->scopesForAccount($accountId, 'REVIEW');
        $rawPage = Request::queryString('page', '1');
        $page = preg_match('/\A[1-9]\d{0,2}\z/', $rawPage) === 1 ? (int) $rawPage : 1;
        $status = Request::queryString('status', 'OPEN');
        try {
            $queue = (new QuestionIssueReportService())->reviewerPage($scopes, $status, $page);
            $error = null;
        } catch (Throwable) {
            $queue = ['items' => [], 'total' => 0, 'page' => 1, 'pages' => 1, 'status' => 'OPEN'];
            $error = 'The scoped question-report queue is temporarily unavailable.';
        }
        self::renderDeveloper('developer/question-issue-reports', [
            'pageTitle' => 'Learner Question Reports',
            'queue' => $queue,
            'error' => $error,
            'csrfToken' => CsrfTokenService::token(),
        ]);
    }

    public static function update(): void
    {
        [$account, $access] = self::reviewer();
        if (!CsrfTokenService::valid(Request::input('csrf_token'))) {
            \SessionService::flash('error', 'This reviewer form expired. Reload the queue and try again.');
            Response::redirect('/developer/question-reports', 303);
        }
        $id = Request::input('report_id');
        $status = Request::input('status');
        if (!is_scalar($id) || !is_scalar($status)) {
            \SessionService::flash('error', 'The report update was invalid.');
            Response::redirect('/developer/question-reports', 303);
        }
        try {
            $changed = (new QuestionIssueReportService())->transition(
                (string) $id,
                (string) $status,
                (string) ($account['account_id'] ?? ''),
                $access
            );
            if (!$changed) {
                throw new InvalidArgumentException('The report is unavailable in your authorized review scope.');
            }
            \SessionService::flash('success', 'Question-report status updated. No canonical academic data was changed.');
        } catch (Throwable $exception) {
            \SessionService::flash('error', $exception instanceof InvalidArgumentException
                ? $exception->getMessage()
                : 'The report status could not be updated.');
        }
        Response::redirect('/developer/question-reports', 303);
    }

    /** @return array{array<string,mixed>,AcademicReviewerAuthorizationService} */
    private static function reviewer(): array
    {
        $account = AccountSessionService::current();
        if (!is_array($account)) {
            Response::text('Authenticated reviewer account required.', 401);
        }
        $access = new AcademicReviewerAuthorizationService();
        if ($access->scopesForAccount((string) ($account['account_id'] ?? ''), 'REVIEW') === []) {
            Response::text('An active qualified review scope is required.', 403);
        }
        return [$account, $access];
    }
}
