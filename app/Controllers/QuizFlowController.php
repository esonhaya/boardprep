<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Services\Quiz\QuizResultActionService;
use App\Services\Quiz\ExamTimerService;
use App\Services\Quiz\ExamReviewFlagService;
use App\Services\Quiz\Start\QuizStartSessionWriter;
use App\Services\Quiz\Start\QuizStartInputNormalizer;
use App\Services\Board\BoardViewService;
use App\Services\Shared\CsrfTokenService;
use App\Services\Quiz\LET\LETVariantRegistry;
use App\Services\Study\StudyLibraryService;
use App\Services\Question\QuestionIdMigrationService;
use App\Services\Profile\LearnerPreferenceService;
use App\Services\Learning\NotesPracticeResultService;
use App\Services\Learning\LearningHistoryService;
use App\Services\Learning\MistakeRecoveryResultService;

final class QuizFlowController
{
    public static function handle(): void
    {
        $method = Request::method();
        $rawAction = $method === 'POST'
            ? Request::input('action', '')
            : Request::query('action', '');
        if (!is_scalar($rawAction)) {
            self::rejectAction();
        }

        $action = trim((string) $rawAction);

        if ($method === 'POST'
            && App::config('environment') === 'production'
            && !CsrfTokenService::valid(Request::input('csrf_token'))) {
            \SessionService::flash('error', 'Your quiz form expired. Please try again.');
            Response::redirect('/quiz');
        }

        $allowed = $method === 'POST'
            ? ['start', 'submit', 'next', 'previous', 'jump', 'finish', 'timer-check', 'review-flag']
            : ['', 'start', 'result', 'review'];

        if (!in_array($action, $allowed, true)) {
            self::rejectAction();
        }

        if ($action !== 'start') {
            ExamTimerService::enforceIfExpired();
        }

        switch ($action) {
            case 'start':
                if ($method === 'GET') {
                    self::settings();
                    return;
                }
                \QuizStartService::start();
                return;

            case 'submit':
                \QuizSubmissionService::submit();
                return;

            case 'next':
                \QuizNavigationService::next();
                return;

            case 'previous':
                \QuizNavigationService::previous();
                return;

            case 'jump':
                \QuizNavigationService::jump();
                return;

            case 'timer-check':
                \QuizNavigationService::renderCurrent();
                return;

            case 'review-flag':
                self::reviewFlag();
                return;

            case 'review':
                self::review();
                return;

            case 'finish':
                self::finish();
                return;

            case 'result':
                self::result();
                return;

            default:
                if ($action !== '') {
                    self::rejectAction();
                }
                $activeSession = self::activeSessionSummary();
                if ($activeSession !== null && self::hasConfigurationIntent()) {
                    self::settings($activeSession);
                    return;
                }
                if ($activeSession !== null) {
                    \QuizNavigationService::renderCurrent();
                    return;
                }
                self::settings();
                return;
        }
    }

    private static function rejectAction(): never
    {
        \SessionService::flash('error', 'That quiz action is invalid or expired. Please try again.');
        Response::redirect('/quiz');
    }

    private static function settings(?array $activeSession = null): void
    {
        $query = Request::query();
        $query = is_array($query) ? $query : [];
        $query = LearnerPreferenceService::applyToQuizInput($query);
        $rawTimerMode = is_scalar($query['timer_mode'] ?? null) ? strtolower(trim((string) $query['timer_mode'])) : 'untimed';
        $rawTimerMinutes = is_scalar($query['time_limit_minutes'] ?? null) ? trim((string) $query['time_limit_minutes']) : '';
        try {
            $settings = QuizStartInputNormalizer::normalize($query);
        } catch (\InvalidArgumentException $exception) {
            if (\SessionService::get('flash') === null) {
                \SessionService::flash('error', $exception->getMessage());
            }
            // Keep a safely-renderable form while retaining the learner's
            // invalid timer value in the input for correction.
            $fallback = $query;
            $variantInput = [];
            foreach (['let_level', 'let_specialization', 'civil_service_level'] as $variantField) {
                if (is_scalar($query[$variantField] ?? null)) {
                    $variantInput[$variantField] = substr(trim((string) $query[$variantField]), 0, 160);
                }
            }
            $fallback['time_limit_minutes'] = '60';
            unset($fallback['let_level'], $fallback['let_specialization'], $fallback['civil_service_level']);
            $settings = QuizStartInputNormalizer::normalize($fallback);
            if (array_key_exists('let_level', $variantInput)) {
                $settings['let_level'] = strtoupper($variantInput['let_level']);
            }
            if (array_key_exists('let_specialization', $variantInput)) {
                $settings['let_specialization'] = $variantInput['let_specialization'];
            }
            if (array_key_exists('civil_service_level', $variantInput)) {
                $settings['civil_service_level'] = strtoupper($variantInput['civil_service_level']);
            }
            if (strtolower((string) ($query['mode'] ?? '')) === 'exam' && $rawTimerMode === 'timed') {
                $settings['timer_mode'] = 'timed';
                $settings['time_limit_minutes_input'] = $rawTimerMinutes;
            }
        }
        if (!isset($settings['time_limit_minutes_input'])) {
            $settings['time_limit_minutes_input'] = (string) ($settings['time_limit_minutes'] ?? 60);
        }
        $boards = BoardViewService::all();
        $requestedExam = is_scalar($query['exam'] ?? null) ? strtoupper(trim((string) $query['exam'])) : '';
        $boardContext = null;
        foreach ($boards as $candidate) {
            if (strtoupper((string) ($candidate['code'] ?? '')) === $requestedExam) {
                $boardContext = $candidate;
                break;
            }
        }
        $rawSubject = is_scalar($query['subject'] ?? null) ? strtolower(trim((string) $query['subject'])) : '';
        $subjectContext = $rawSubject === '*';
        if ($boardContext !== null && $rawSubject !== '' && $rawSubject !== '*') {
            foreach ((array) ($boardContext['practice_subjects'] ?? []) as $candidateSubject) {
                if (strtolower((string) ($candidateSubject['id'] ?? '')) === $rawSubject) {
                    $subjectContext = true;
                    break;
                }
            }
        }
        $examBlueprintPreviews = [];
        foreach ($boards as $candidate) {
            $boardCode = strtolower(trim((string) ($candidate['code'] ?? '')));
            if ($boardCode === '') {
                continue;
            }
            $examBlueprintPreviews[$boardCode] = \ExamBlueprintPreviewService::forSetup(
                (string) ($candidate['id'] ?? $candidate['code']),
                '*',
                (int) ($settings['count'] ?? 0)
            );
        }
        $selectedPreviewKey = strtolower(trim((string) ($settings['board'] ?? 'let')));
        $examBlueprintPreviews[$selectedPreviewKey] = \ExamBlueprintPreviewService::forSetup(
            (string) ($settings['board'] ?? 'let'),
            (string) ($settings['subject'] ?? '*'),
            (int) ($settings['count'] ?? 0)
        );
        View::render(
            'quiz/settings',
            [
                'pageTitle' => 'Quiz',
                'settings' => $settings,
                'boards' => $boards,
                'boardContext' => $boardContext,
                'subjectContext' => $subjectContext,
                'examBlueprintPreviews' => $examBlueprintPreviews,
                'preferences' => LearnerPreferenceService::get(),
                'letSpecializations' => LETVariantRegistry::specializations(),
                'activeSession' => $activeSession ?? self::activeSessionSummary(),
            ]
        );
    }

    /** @return array<string, int|string>|null */
    private static function activeSessionSummary(): ?array
    {
        $quizSession = \SessionService::get('quiz_session', []);
        $questions = \SessionService::get('questions', []);
        if (!is_array($quizSession) || !is_array($questions) || $questions === []
            || \SessionService::get('attempt_persisted') === true) {
            return null;
        }

        $answers = \SessionService::get('answers', []);
        $answers = is_array($answers) ? $answers : [];
        $answered = 0;
        foreach ($questions as $question) {
            $id = is_array($question) && is_scalar($question['id'] ?? null)
                ? (string) $question['id']
                : '';
            if ($id !== '' && is_scalar($answers[$id] ?? null)) {
                $answered++;
            }
        }

        $board = is_scalar($quizSession['board'] ?? $quizSession['exam'] ?? null)
            ? (string) ($quizSession['board'] ?? $quizSession['exam'])
            : '';
        $subject = is_scalar($quizSession['subject'] ?? null)
            ? (string) $quizSession['subject']
            : '';
        $mode = is_scalar($quizSession['mode'] ?? null)
            ? (string) $quizSession['mode']
            : 'practice';
        $current = \QuizNavigationService::current();

        return [
            'board' => $board,
            'board_label' => \App\Services\Shared\TaxonomyStorageService::label('boards', $board),
            'subject' => $subject,
            'subject_label' => \App\Services\Shared\TaxonomyStorageService::label('subjects', $subject),
            'mode_label' => $mode === 'exam' ? 'Exam simulation' : ucfirst($mode),
            'current_question' => min(max(0, $current), count($questions) - 1) + 1,
            'question_count' => count($questions),
            'answered_count' => $answered,
        ];
    }

    private static function hasConfigurationIntent(): bool
    {
        $query = Request::query();
        if (!is_array($query)) {
            return false;
        }

        foreach ([
            'exam', 'subject', 'topic', 'mode', 'difficulty', 'count', 'adaptive',
            'let_level', 'let_specialization', 'civil_service_level', 'timer_mode', 'time_limit_minutes',
        ] as $key) {
            if (is_scalar($query[$key] ?? null) && trim((string) $query[$key]) !== '') {
                return true;
            }
        }

        return false;
    }

    private static function finish(): void
    {
        if (\SessionService::get('mode', 'practice') === 'exam') {
            $revision = \SessionService::get('exam_answer_revision', 0);
            $reviewedRevision = \SessionService::get('exam_reviewed_revision', null);
            if (!is_int($revision) || $reviewedRevision !== $revision) {
                \SessionService::flash('error', 'Review your answers before submitting the exam simulation.');
                Response::redirect('/quiz?action=review', 303);
            }
        }

        $resultInput = \QuizResultSessionReader::read();
        $questions = $resultInput['questions'];

        if (empty($questions)) {
            QuizStartSessionWriter::clear();
            \SessionService::flash('error', 'That quiz session was stale or invalid. Please start a new quiz.');
            Response::redirect('/quiz');
            return;
        }

        \QuizResultService::build();
        Response::redirect('/quiz?action=result', 303);
    }

    private static function result(): void
    {
        if (!\SessionService::has('attempt_persisted')) {
            \SessionService::flash('error', 'That quiz result is unavailable or expired.');
            Response::redirect('/quiz');
        }

        $result = \QuizResultService::build();

        $summary = $result['summary'] ?? [];
        $review = $result['review'] ?? [];
        $session = \SessionService::get('quiz_session', []);
        $review = QuestionIdMigrationService::canonicalizeReview(
            is_array($review) ? $review : [],
            is_array($session) ? $session : []
        );
        $markedQuestionIds = \App\Services\Quiz\HistoricalQuizResultService::markedQuestionIds(
            ['review_flag_snapshot' => $summary['review_flag_snapshot'] ?? null],
            $review
        );
        $reviewFilter = Request::queryString('review_filter', 'all');
        $validFilters = ['all', 'incorrect', 'unanswered', 'correct'];
        if ($markedQuestionIds !== null) {
            $validFilters[] = 'marked';
        }
        if (!in_array($reviewFilter, $validFilters, true)) {
            $reviewFilter = 'all';
        }
        $studyReferencesByQuestion = StudyLibraryService::forQuestions(array_map(
            static fn(mixed $item): mixed => is_array($item) && is_array($item['question'] ?? null)
                ? ($item['question']['id'] ?? null)
                : null,
            $review
        ),
            is_scalar($session['board'] ?? null) ? (string) $session['board'] : null,
            is_scalar($session['subject'] ?? null) ? (string) $session['subject'] : null
        );
        $notesContext = is_array($session['notes_practice'] ?? null) ? $session['notes_practice'] : null;
        $notesPracticeReview = is_array($notesContext)
            ? NotesPracticeResultService::create($notesContext, is_array($review) ? $review : [])
            : null;
        $mistakeRecovery = is_array($session['mistake_recovery'] ?? null)
            ? MistakeRecoveryResultService::create(
                $session['mistake_recovery'],
                is_array($review) ? $review : [],
                LearningHistoryService::all()
            )
            : null;

        View::render(
            'quiz/result',
            [
                'summary' => $summary,
                'review' => $review,
                'reviewFilter' => $reviewFilter,
                'markedQuestionIds' => $markedQuestionIds ?? [],
                'markedReviewAvailable' => $markedQuestionIds !== null,
                'reviewFilterBaseUrl' => '/quiz?action=result',
                'quizSession' => is_array($session) ? $session : [],
                'studyReferencesByQuestion' => $studyReferencesByQuestion,
                'notesPracticeReview' => $notesPracticeReview,
                'mistakeRecovery' => $mistakeRecovery,
                'actions' => QuizResultActionService::build(
                    is_array($session) ? $session : [],
                    $summary,
                    $result['review'] ?? []
                ),
            ]
        );
    }

    private static function review(): void
    {
        if (\SessionService::get('mode', 'practice') !== 'exam') {
            \SessionService::flash('error', 'Answer review is available in an exam simulation.');
            Response::redirect('/quiz?action=start', 303);
        }

        $questions = \SessionService::get('questions', []);
        $answers = \SessionService::get('answers', []);
        if (!is_array($questions) || $questions === [] || !is_array($answers)) {
            \App\Services\Quiz\Session\QuizSessionRecoveryService::abandonInvalidSession();
        }
        $questions = array_values($questions);
        foreach ($questions as $question) {
            if (!\App\Services\Quiz\Session\QuizSessionQuestion::isCurrent($question)) {
                \App\Services\Quiz\Session\QuizSessionRecoveryService::abandonInvalidSession();
            }
        }

        $revision = \SessionService::get('exam_answer_revision', 0);
        $revision = is_int($revision) && $revision >= 0 ? $revision : 0;
        \SessionService::set('exam_reviewed_revision', $revision);
        View::render('quiz/review', [
            'questions' => $questions,
            'answers' => $answers,
            'current' => \QuizNavigationService::current(),
        ]);
    }

    private static function reviewFlag(): void
    {
        $rawIndex = Request::input('index');
        $rawQuestionId = Request::input('question_id');
        $rawMarked = Request::input('marked');
        $rawVersion = Request::input('version');
        $returnTo = Request::input('return_to', 'current');
        $valid = is_scalar($rawIndex) && preg_match('/\A\d{1,4}\z/', (string) $rawIndex) === 1
            && is_scalar($rawQuestionId) && trim((string) $rawQuestionId) !== ''
            && is_scalar($rawMarked) && in_array((string) $rawMarked, ['0', '1'], true)
            && is_scalar($rawVersion) && preg_match('/\A\d{1,10}\z/', (string) $rawVersion) === 1;

        if (!$valid) {
            \SessionService::flash('error', 'That review mark could not be changed. Your saved exam answers were kept.');
        } else {
            $result = ExamReviewFlagService::set(
                (int) $rawIndex,
                trim((string) $rawQuestionId),
                (string) $rawMarked === '1',
                (int) $rawVersion
            );
            $questionNumber = (int) $rawIndex + 1;
            if ($result['status'] === 'updated') {
                \SessionService::flash('success', $result['marked']
                    ? "Question {$questionNumber} marked for review."
                    : "Question {$questionNumber} removed from review.");
            } elseif ($result['status'] === 'unchanged') {
                \SessionService::flash('success', $result['marked']
                    ? "Question {$questionNumber} is marked for review."
                    : "Question {$questionNumber} is not marked for review.");
            } elseif ($result['status'] === 'stale') {
                \SessionService::flash('error', 'This review mark changed in another request. The current saved state was kept; refresh the review list before changing it again.');
            } else {
                \SessionService::flash('error', 'This exam question is no longer available for review. Your saved answers were kept.');
            }
        }

        Response::redirect($returnTo === 'review' ? '/quiz?action=review' : '/quiz', 303);
    }
}
