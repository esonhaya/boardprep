<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Services\Learning\LearnerIdentityService;
use App\Services\Question\QuestionIssueReportService;
use App\Services\Shared\CsrfTokenService;
use InvalidArgumentException;
use Throwable;

final class LearnerQuestionReportController
{
    public static function create(): void
    {
        $attemptId = Request::query('attempt_id');
        $questionId = Request::query('question_id');
        if (!is_scalar($attemptId) || !is_scalar($questionId)) {
            Response::text('Question not found.', 404);
        }
        try {
            $context = self::service()->questionContextForAttempt(
                LearnerIdentityService::current(),
                (string) $attemptId,
                (string) $questionId
            );
        } catch (InvalidArgumentException) {
            $context = null;
        }
        if ($context === null) {
            Response::text('Question not found.', 404);
        }

        View::render('learning/question-report', [
            'pageTitle' => 'Report a question concern | BoardPrep',
            'question' => $context,
            'attemptId' => (string) $attemptId,
            'types' => QuestionIssueReportService::types(),
            'csrfToken' => CsrfTokenService::token(),
        ]);
    }

    public static function submit(): void
    {
        $rawAttemptId = Request::input('attempt_id');
        $rawQuestionId = Request::input('question_id');
        $attemptId = is_scalar($rawAttemptId) ? trim((string) $rawAttemptId) : '';
        $questionId = is_scalar($rawQuestionId) ? trim((string) $rawQuestionId) : '';
        $redirect = $attemptId !== '' && strlen($attemptId) <= 120 && $questionId !== '' && strlen($questionId) <= 120
            ? '/question-report?attempt_id=' . rawurlencode($attemptId) . '&question_id=' . rawurlencode($questionId)
            : '/history';
        if (!CsrfTokenService::valid(Request::input('csrf_token'))) {
            \SessionService::flash('error', 'This form expired. Reopen it and try again.');
            Response::redirect($redirect, 303);
        }

        $rawType = Request::input('type');
        $rawDescription = Request::input('description', '');
        try {
            if (!is_scalar($rawType) || !is_scalar($rawDescription)) {
                throw new InvalidArgumentException('Choose a concern type and enter plain text only.');
            }
            $result = self::service()->submit(
                LearnerIdentityService::current(),
                $attemptId,
                $questionId,
                (string) $rawType,
                (string) $rawDescription
            );
            \SessionService::flash(
                $result['duplicate'] ? 'info' : 'success',
                $result['duplicate']
                    ? 'A recent report for this question and concern type is already recorded.'
                    : 'Your question concern was submitted for review. It did not change the question, answer key, or your score.'
            );
            Response::redirect('/question-reports', 303);
        } catch (InvalidArgumentException $exception) {
            \SessionService::flash('error', $exception->getMessage());
            Response::redirect($redirect, 303);
        } catch (Throwable) {
            \SessionService::flash('error', 'The report could not be saved right now. Your answer and result are unchanged. Please try again later.');
            Response::redirect($redirect, 303);
        }
    }

    public static function index(): void
    {
        try {
            $reports = self::service()->forLearner(LearnerIdentityService::current());
            $error = null;
        } catch (Throwable) {
            $reports = [];
            $error = 'Your question reports are temporarily unavailable.';
        }
        View::render('learning/question-reports', [
            'pageTitle' => 'My question reports | BoardPrep',
            'reports' => $reports,
            'error' => $error,
        ]);
    }

    private static function service(): QuestionIssueReportService
    {
        return new QuestionIssueReportService();
    }
}
