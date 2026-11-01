<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Services\Flashcard\FlashcardService;
use App\Services\Learning\LearningHistoryService;
use App\Services\Learning\StudyCalendarEvidenceService;
use App\Services\Learning\StudyCalendarPlanner;
use App\Services\Learning\StudyCalendarService;
use App\Services\Learning\StudyDashboardService;
use App\Services\Board\BoardViewService;
use App\Services\Profile\LearnerPreferenceService;
use App\Services\Shared\CsrfTokenService;
use DateTimeImmutable;
use InvalidArgumentException;
use Throwable;

final class StudyCalendarController
{
    public static function index(): void
    {
        $error = null;
        $boards = [];
        $plan = null;
        try {
            $boards = BoardViewService::all();
            $plan = (new StudyCalendarService())->current();
        } catch (Throwable) {
            $error = 'The study calendar is temporarily unavailable. Your saved learning history was not changed.';
        }
        View::render('study/calendar', [
            'pageTitle' => 'Study Calendar | BoardPrep',
            'boards' => $boards,
            'plan' => $plan,
            'preferences' => LearnerPreferenceService::get(),
            'csrfToken' => CsrfTokenService::token(),
            'notice' => \SessionService::consumeFlash(),
            'error' => $error,
            'today' => (new DateTimeImmutable('today'))->format('Y-m-d'),
        ]);
    }

    public static function save(): void
    {
        if (!CsrfTokenService::valid(Request::input('csrf_token'))) {
            \SessionService::flash('error', 'This calendar form expired. Reload it before saving.');
            Response::redirect('/study-calendar', 303);
        }
        try {
            $boards = BoardViewService::all();
            $today = (new DateTimeImmutable('today'))->format('Y-m-d');
            $configuration = StudyCalendarPlanner::validateConfiguration(Request::input(), $boards, $today);
            $attempts = LearningHistoryService::all();
            $dashboard = StudyDashboardService::buildFromOrdered(
                $attempts,
                LearnerPreferenceService::get(),
                false
            );
            $boardAliases = array_fill_keys(array_map('strtolower', array_filter([
                (string) ($configuration['board_id'] ?? ''),
                (string) ($configuration['board_code'] ?? ''),
                (string) ($configuration['board_name'] ?? ''),
            ])), true);
            $boardAttemptCount = 0;
            foreach ($attempts as $attempt) {
                if (!is_array($attempt)) continue;
                $learningContext = is_array($attempt['learning_context'] ?? null) ? $attempt['learning_context'] : [];
                $attemptBoard = strtolower(trim((string) ($attempt['board'] ?? $attempt['exam'] ?? $learningContext['board'] ?? '')));
                if ($attemptBoard !== '' && isset($boardAliases[$attemptBoard])) $boardAttemptCount++;
            }
            $mistakeSubjectCounts = StudyCalendarEvidenceService::mistakeSubjectCounts($attempts, $configuration);
            $mistakeCount = array_sum($mistakeSubjectCounts);
            $dueCount = 0;
            $dueSubjectCounts = [];
            $dueStatus = 'AVAILABLE';
            try {
                $flashcards = App::container()->get(FlashcardService::class);
                $selection = $flashcards->dueSelection([
                    'board' => $configuration['board_id'],
                    'include_new' => false,
                ], 1);
                $dueCount = max(0, (int) ($selection['due_count'] ?? 0));
                $dueSubjectCounts = is_array($selection['due_count_by_subject'] ?? null)
                    ? $selection['due_count_by_subject']
                    : [];
            } catch (Throwable) {
                $dueStatus = 'UNAVAILABLE';
            }
            $result = (new StudyCalendarService())->saveConfiguration(Request::input(), $boards, [
                'attempt_count' => $boardAttemptCount,
                'focus_topics' => is_array($dashboard['focusTopics'] ?? null) ? $dashboard['focusTopics'] : [],
                'due_flashcard_count' => $dueCount,
                'due_flashcard_subject_counts' => $dueSubjectCounts,
                'due_flashcard_status' => $dueStatus,
                'mistake_count' => $mistakeCount,
                'mistake_subject_counts' => $mistakeSubjectCounts,
            ], $today);
            \SessionService::flash(
                'success',
                $result['duplicate']
                    ? 'These calendar settings were already saved.'
                    : 'Your study calendar was saved. Completed work and your edited sessions were preserved.'
            );
        } catch (InvalidArgumentException $exception) {
            \SessionService::flash('error', $exception->getMessage());
        } catch (Throwable) {
            \SessionService::flash('error', 'The study calendar could not be saved. Your current plan and learning history were kept.');
        }
        Response::redirect('/study-calendar', 303);
    }

    public static function updateTask(): void
    {
        if (!CsrfTokenService::valid(Request::input('csrf_token'))) {
            \SessionService::flash('error', 'This session form expired. Reload the calendar before changing it.');
            Response::redirect('/study-calendar', 303);
        }
        try {
            $plan = (new StudyCalendarService())->updateTask(
                Request::input(),
                (new DateTimeImmutable('today'))->format('Y-m-d')
            );
            \SessionService::flash('success', 'Study session updated. The calendar now shows revision ' . (int) ($plan['revision'] ?? 0) . '.');
        } catch (InvalidArgumentException $exception) {
            \SessionService::flash('error', $exception->getMessage());
        } catch (Throwable) {
            \SessionService::flash('error', 'The session could not be updated. Your saved calendar was kept.');
        }
        Response::redirect('/study-calendar', 303);
    }
}
