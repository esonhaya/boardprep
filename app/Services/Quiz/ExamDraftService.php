<?php

declare(strict_types=1);

namespace App\Services\Quiz;

use App\Contracts\StorageInterface;
use App\Core\App;
use App\Exceptions\ExamDraftConflictException;
use App\Exceptions\ExamDraftUnavailableException;
use App\Repositories\AttemptRepository;
use App\Repositories\ExamDraftRepository;
use App\Services\Accounts\AccountFeatureGate;
use App\Services\Accounts\AccountLifecycleLock;
use App\Services\Accounts\AccountSessionService;
use App\Services\Quiz\Session\QuizSessionQuestion;
use RuntimeException;

/**
 * Durable unfinished exam state for authenticated preview accounts.
 * Anonymous quiz state intentionally remains PHP-session-backed.
 */
final class ExamDraftService
{
    private ExamDraftRepository $drafts;
    private AttemptRepository $attempts;

    public function __construct(?StorageInterface $storage = null)
    {
        $storage ??= App::storage();
        $this->drafts = new ExamDraftRepository($storage);
        $this->attempts = new AttemptRepository($storage);
    }

    public function availableForCurrentLearner(): bool
    {
        return $this->currentLearnerId() !== null;
    }

    /** @param array<int,array<string,mixed>> $questions */
    public function createFromCurrentSession(
        array $quizSession,
        array $questions,
        array $answers = [],
        int $currentQuestion = 0,
        array $reviewFlags = []
    ): array {
        $learnerId = $this->requireCurrentLearnerId();
        return AccountLifecycleLock::withWritableLearner($learnerId, function () use (
            $learnerId,
            $quizSession,
            $questions,
            $answers,
            $currentQuestion,
            $reviewFlags
        ): array {
            if (($quizSession['mode'] ?? null) !== 'exam') {
                throw new ExamDraftUnavailableException('Only an exam simulation can be resumed.');
            }
            $sessionId = self::text($quizSession['id'] ?? null);
            if ($sessionId === '') {
                throw new ExamDraftUnavailableException('The exam session is unavailable for recovery.');
            }
            $questionIds = self::questionIds($questions);
            if ($questionIds === [] || count($questionIds) !== count($questions)) {
                throw new ExamDraftUnavailableException('The selected exam questions could not be saved for recovery.');
            }
            if (($quizSession['question_ids'] ?? null) !== $questionIds
                || ($quizSession['question_count'] ?? null) !== count($questionIds)) {
                throw new ExamDraftUnavailableException('The selected exam questions could not be saved for recovery.');
            }
            self::validateTimer($quizSession, time(), false);
            $state = self::normalizeState($questions, $questionIds, $answers, $currentQuestion, $reviewFlags);

            if ($this->drafts->byLearner($learnerId) !== []) {
                throw new ExamDraftConflictException('An unfinished exam is already available for this account.');
            }

            $draftId = 'exam-draft-' . bin2hex(random_bytes(16));
            $sessionData = self::safeConfigurationSnapshot($quizSession);
            $now = gmdate(DATE_ATOM);
            $draft = [
                'id' => $draftId,
                'schema_version' => 1,
                'state' => 'in_progress',
                'user_id' => $learnerId,
                'quiz_session' => $sessionData,
                'active_quiz_session_id' => $sessionId,
                'question_ids' => $questionIds,
                'choice_counts' => self::choiceCounts($questions),
                'answers' => $state['answers'],
                'current_question' => $state['current_question'],
                'review_flags' => $state['review_flags'],
                'exam_answer_revision' => max(0, (int) \SessionService::get(
                    'exam_answer_revision',
                    $quizSession['exam_answer_revision'] ?? 0
                )),
                'revision' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $saved = $this->drafts->create($draft);
            \SessionService::set('quiz_session', array_replace($quizSession, [
                'durable_draft_id' => $draftId,
                'durable_draft_revision' => 0,
            ]));
            return $saved;
        });
    }

    /** Return a non-secret account-owned summary for a resume card. */
    public function currentSummary(): ?array
    {
        $learnerId = $this->currentLearnerId();
        if ($learnerId === null) {
            return null;
        }
        return AccountLifecycleLock::withWritableLearner($learnerId, function () use ($learnerId): ?array {
            try {
                $draft = $this->singleActiveFor($learnerId);
            } catch (ExamDraftUnavailableException) {
                $owned = $this->drafts->byLearner($learnerId);
                $id = self::text($owned[0]['id'] ?? null);
                return ['status' => 'unavailable', 'id' => strlen($id) <= 128 ? $id : ''];
            }
            if ($draft === null) {
                return null;
            }
            try {
                $this->validateRecord($draft, $learnerId);
            } catch (ExamDraftUnavailableException) {
                $id = self::text($draft['id'] ?? null);
                return [
                    'status' => 'unavailable',
                    'id' => strlen($id) <= 128 ? $id : '',
                ];
            }
            $completed = $this->attempts->bySessionId((string) $draft['id'], $learnerId);
            if (is_array($completed) && ($completed['completed'] ?? false) === true) {
                $this->drafts->delete((string) $draft['id']);
                return [
                    'status' => 'completed',
                    'attempt_id' => (string) ($completed['id'] ?? ''),
                ];
            }
            return [
                'status' => self::isExpired($draft['quiz_session'], time()) ? 'expired' : 'in_progress',
                'id' => (string) $draft['id'],
                'board' => self::text($draft['quiz_session']['board'] ?? ''),
                'subject' => self::text($draft['quiz_session']['subject'] ?? ''),
                'question_count' => count($draft['question_ids']),
                'answered_count' => count($draft['answers']),
                'timer_mode' => self::text($draft['quiz_session']['timer_mode'] ?? 'untimed'),
                'time_limit_minutes' => (int) ($draft['quiz_session']['time_limit_minutes'] ?? 0),
                'deadline_epoch' => $draft['quiz_session']['deadline_epoch'] ?? null,
                'created_at' => (string) $draft['created_at'],
            ];
        });
    }

    /** Claim the one owned in-progress draft for a fresh authenticated PHP session. */
    public function recoverCurrentAccountDraft(): array
    {
        $learnerId = $this->requireCurrentLearnerId();
        return AccountLifecycleLock::withWritableLearner($learnerId, function () use ($learnerId): array {
            $draft = $this->singleActiveFor($learnerId);
            if ($draft === null) {
                throw new ExamDraftUnavailableException('No resumable exam is available for this account.');
            }
            $this->validateRecord($draft, $learnerId);

            $completed = $this->attempts->bySessionId((string) $draft['id'], $learnerId);
            if (is_array($completed) && ($completed['completed'] ?? false) === true) {
                $this->drafts->delete((string) $draft['id']);
                return ['status' => 'completed', 'attempt_id' => (string) ($completed['id'] ?? '')];
            }

            $this->validateTimer($draft['quiz_session'], time(), true);
            $revision = (int) $draft['revision'];
            if ($revision >= PHP_INT_MAX - 1) {
                throw new ExamDraftUnavailableException('This exam draft is no longer recoverable.');
            }
            $newSessionId = 'quiz-' . bin2hex(random_bytes(16));
            $updated = $this->drafts->update((string) $draft['id'], [
                'active_quiz_session_id' => $newSessionId,
                'revision' => $revision + 1,
                'updated_at' => gmdate(DATE_ATOM),
            ]);
            if (!is_array($updated)) {
                throw new ExamDraftUnavailableException('No resumable exam is available for this account.');
            }
            $updated['status'] = self::isExpired($updated['quiz_session'], time()) ? 'expired' : 'resumed';
            return $updated;
        });
    }

    /** Persist the server session's current answer/progress snapshot at its expected revision. */
    public function saveCurrentSessionState(int $expectedRevision, string $activeQuizSessionId): array
    {
        $learnerId = $this->requireCurrentLearnerId();
        return AccountLifecycleLock::withWritableLearner($learnerId, function () use (
            $learnerId,
            $expectedRevision,
            $activeQuizSessionId
        ): array {
            $quizSession = \SessionService::get('quiz_session', []);
            $questions = \SessionService::get('questions', []);
            $answers = \SessionService::get('answers', []);
            $currentQuestion = \SessionService::get('currentQuestion', 0);
            $reviewFlags = \SessionService::get('exam_review_flags', []);
            if (!is_array($quizSession) || !is_array($questions) || !is_array($answers)
                || !is_array($reviewFlags) || ($quizSession['mode'] ?? null) !== 'exam') {
                throw new ExamDraftUnavailableException('The current exam could not be saved for recovery.');
            }
            $draftId = self::text($quizSession['durable_draft_id'] ?? null);
            $draft = $draftId === '' ? null : $this->drafts->findForLearner($draftId, $learnerId);
            if ($draft === null) {
                throw new ExamDraftUnavailableException('The current exam could not be saved for recovery.');
            }
            $this->validateRecord($draft, $learnerId);
            if (!hash_equals((string) $draft['active_quiz_session_id'], $activeQuizSessionId)
                || !hash_equals((string) $draft['active_quiz_session_id'], self::text($quizSession['id'] ?? null))) {
                throw new ExamDraftConflictException('This exam was resumed in another session. Reload the current exam before continuing.');
            }
            self::validateTimer($draft['quiz_session'], time(), false);
            $questionIds = self::questionIds($questions);
            if ($questionIds !== $draft['question_ids']) {
                throw new ExamDraftUnavailableException('The selected exam questions are no longer available.');
            }
            $state = self::normalizeState(
                $questions,
                $questionIds,
                $answers,
                is_int($currentQuestion) ? $currentQuestion : (int) $currentQuestion,
                $reviewFlags
            );

            $revision = (int) $draft['revision'];
            $sameState = self::stateMatches($draft, $state);
            if ($expectedRevision !== $revision) {
                if ($sameState) {
                    \SessionService::set('quiz_session', array_replace($quizSession, [
                        'durable_draft_revision' => $revision,
                    ]));
                    return $draft;
                }
                throw new ExamDraftConflictException('This exam changed in another request. The saved answer was kept; reload before changing it again.');
            }
            if ($sameState) {
                \SessionService::set('quiz_session', array_replace($quizSession, [
                    'durable_draft_revision' => $revision,
                ]));
                return $draft;
            }
            if ($revision >= PHP_INT_MAX - 1) {
                throw new ExamDraftUnavailableException('This exam draft is no longer recoverable.');
            }
            $updated = $this->drafts->update((string) $draft['id'], [
                'answers' => $state['answers'],
                'current_question' => $state['current_question'],
                'review_flags' => $state['review_flags'],
                'exam_answer_revision' => max(0, (int) \SessionService::get(
                    'exam_answer_revision',
                    $quizSession['exam_answer_revision'] ?? 0
                )),
                'revision' => $revision + 1,
                'updated_at' => gmdate(DATE_ATOM),
            ]);
            if (!is_array($updated)) {
                throw new ExamDraftUnavailableException('The current exam could not be saved for recovery.');
            }
            \SessionService::set('quiz_session', array_replace($quizSession, [
                'durable_draft_revision' => (int) $updated['revision'],
            ]));
            return $updated;
        });
    }

    /** Serialize scoring and result persistence with answer saves and recovery. */
    public function withCurrentDraftFinalization(callable $finalize): mixed
    {
        $quizSession = \SessionService::get('quiz_session', []);
        if (!is_array($quizSession) || self::text($quizSession['durable_draft_id'] ?? null) === ''
            || \SessionService::has('attempt_persisted')) {
            return $finalize();
        }

        $learnerId = $this->requireCurrentLearnerId();
        return AccountLifecycleLock::withWritableLearner($learnerId, function () use (
            $learnerId,
            $quizSession,
            $finalize
        ): mixed {
            $draftId = self::text($quizSession['durable_draft_id'] ?? null);
            $draft = $draftId === '' ? null : $this->drafts->findForLearner($draftId, $learnerId);
            if ($draft === null) {
                throw new ExamDraftUnavailableException('The saved exam could not be verified before submission.');
            }
            $this->validateRecord($draft, $learnerId);
            if (!hash_equals((string) ($draft['active_quiz_session_id'] ?? ''), self::text($quizSession['id'] ?? null))) {
                throw new ExamDraftConflictException('This exam was resumed in another session. Reload before submitting.');
            }

            $questions = \SessionService::get('questions', []);
            $answers = \SessionService::get('answers', []);
            $flags = \SessionService::get('exam_review_flags', []);
            $current = \SessionService::get('currentQuestion', 0);
            if (!is_array($questions) || self::questionIds($questions) !== $draft['question_ids']
                || !is_array($answers) || !is_array($flags)) {
                throw new ExamDraftUnavailableException('The saved exam could not be verified before submission.');
            }
            $state = self::normalizeState(
                $questions,
                $draft['question_ids'],
                $answers,
                is_int($current) ? $current : (int) $current,
                $flags
            );
            if ((int) ($quizSession['durable_draft_revision'] ?? -1) !== (int) $draft['revision']
                || !self::stateMatches($draft, $state)
                || max(0, (int) \SessionService::get('exam_answer_revision', 0)) !== (int) $draft['exam_answer_revision']) {
                throw new ExamDraftConflictException('The exam changed in another request. The latest saved answers were kept; reload before submitting.');
            }

            return $finalize();
        });
    }

    /** Check whether this PHP session still owns the draft's current resume epoch. */
    public function currentSessionIsAuthoritative(): bool
    {
        $quizSession = \SessionService::get('quiz_session', []);
        if (!is_array($quizSession) || self::text($quizSession['durable_draft_id'] ?? null) === '') {
            return true;
        }
        if (\SessionService::has('attempt_persisted')) {
            return true;
        }
        $learnerId = $this->currentLearnerId();
        if ($learnerId === null) {
            return false;
        }
        $draftId = self::text($quizSession['durable_draft_id'] ?? null);
        $sessionId = self::text($quizSession['id'] ?? null);
        if ($draftId === '' || $sessionId === '') {
            return false;
        }
        $draft = $this->drafts->findForLearner($draftId, $learnerId);
        return is_array($draft)
            && ($draft['state'] ?? null) === 'in_progress'
            && is_string($draft['active_quiz_session_id'] ?? null)
            && hash_equals($draft['active_quiz_session_id'], $sessionId);
    }

    /** Restore only server-persisted answers after an interrupted/stale save. */
    public function restoreAuthoritativeSessionState(): bool
    {
        $quizSession = \SessionService::get('quiz_session', []);
        if (!is_array($quizSession)) {
            return false;
        }
        $draftId = self::text($quizSession['durable_draft_id'] ?? null);
        $sessionId = self::text($quizSession['id'] ?? null);
        $learnerId = $this->currentLearnerId();
        if ($draftId === '' || $sessionId === '' || $learnerId === null) {
            return false;
        }
        return AccountLifecycleLock::withWritableLearner($learnerId, function () use (
            $draftId,
            $sessionId,
            $learnerId,
            $quizSession
        ): bool {
            $draft = $this->drafts->findForLearner($draftId, $learnerId);
            if (!is_array($draft) || ($draft['state'] ?? null) !== 'in_progress'
                || ($draft['active_quiz_session_id'] ?? null) !== $sessionId) {
                return false;
            }
            $this->validateRecord($draft, $learnerId);
            $questions = \SessionService::get('questions', []);
            if (!is_array($questions) || self::questionIds($questions) !== $draft['question_ids']) {
                return false;
            }
            \SessionService::set('answers', $draft['answers']);
            \SessionService::set('currentQuestion', $draft['current_question']);
            \SessionService::set('exam_review_flags', array_fill_keys($draft['review_flags'], true));
            \SessionService::set('exam_review_flag_versions', []);
            \SessionService::set('exam_review_flag_session_id', $sessionId);
            \SessionService::set('exam_answer_revision', $draft['exam_answer_revision']);
            if (\SessionService::get('exam_reviewed_revision') !== $draft['exam_answer_revision']) {
                \SessionService::remove('exam_reviewed_revision');
            }
            \SessionService::set('quiz_session', array_replace($quizSession, [
                'durable_draft_revision' => $draft['revision'],
            ]));
            return true;
        });
    }

    /** Delete only this learner's draft after its completed attempt is durable. */
    public function deleteAfterFinalization(string $draftId, string $attemptId): void
    {
        $learnerId = $this->requireCurrentLearnerId();
        AccountLifecycleLock::withWritableLearner($learnerId, function () use ($learnerId, $draftId, $attemptId): void {
            $draft = $this->drafts->findForLearner($draftId, $learnerId);
            if ($draft === null) {
                return;
            }
            $attempt = $this->attempts->findForLearner($attemptId, $learnerId);
            if (!is_array($attempt)
                || ($attempt['completed'] ?? false) !== true
                || ($attempt['session_id'] ?? null) !== $draftId) {
                throw new ExamDraftUnavailableException('The completed exam could not be verified.');
            }
            $this->drafts->delete($draftId);
        });
    }

    /** Explicitly abandon only the current authenticated learner's draft. */
    public function discardOwnedDraft(string $draftId): bool
    {
        $learnerId = $this->requireCurrentLearnerId();
        return AccountLifecycleLock::withWritableLearner($learnerId, function () use ($learnerId, $draftId): bool {
            $draft = $this->drafts->findForLearner($draftId, $learnerId);
            if ($draft === null) {
                return false;
            }
            return $this->drafts->delete($draftId);
        });
    }

    private function currentLearnerId(): ?string
    {
        if (!AccountFeatureGate::enabled()) {
            return null;
        }
        $account = AccountSessionService::current();
        $learnerId = is_array($account) ? self::text($account['learner_id'] ?? null) : '';
        return preg_match('/\Aaccount-learner-[a-f0-9]{32}\z/', $learnerId) === 1 ? $learnerId : null;
    }

    private function requireCurrentLearnerId(): string
    {
        $learnerId = $this->currentLearnerId();
        if ($learnerId === null) {
            throw new ExamDraftUnavailableException('Sign in to resume an exam.');
        }
        return $learnerId;
    }

    private function singleActiveFor(string $learnerId): ?array
    {
        $owned = $this->drafts->byLearner($learnerId);
        if (count($owned) > 1) {
            throw new ExamDraftUnavailableException('The saved exam state is inconsistent.');
        }
        $draft = $owned[0] ?? null;
        if ($draft !== null && ($draft['state'] ?? null) !== 'in_progress') {
            throw new ExamDraftUnavailableException('The saved exam state is inconsistent.');
        }
        return $draft;
    }

    private function validateRecord(array $draft, string $learnerId): void
    {
        $allowedFields = [
            'id', 'schema_version', 'state', 'user_id', 'quiz_session', 'active_quiz_session_id',
            'question_ids', 'choice_counts', 'answers', 'current_question', 'review_flags',
            'exam_answer_revision', 'revision', 'created_at', 'updated_at',
        ];
        if (array_diff(array_keys($draft), $allowedFields) !== []
            || ($draft['schema_version'] ?? null) !== 1
            || ($draft['state'] ?? null) !== 'in_progress'
            || ($draft['user_id'] ?? null) !== $learnerId
            || !preg_match('/\Aexam-draft-[a-f0-9]{32}\z/', self::text($draft['id'] ?? null))
            || !is_array($draft['quiz_session'] ?? null)
            || ($draft['quiz_session']['mode'] ?? null) !== 'exam'
            || !is_string($draft['active_quiz_session_id'] ?? null)
            || ($draft['active_quiz_session_id'] !== ''
                && preg_match('/\Aquiz-(?:[a-f0-9]{16}|[a-f0-9]{32})\z/', $draft['active_quiz_session_id']) !== 1)
            || !is_array($draft['question_ids'] ?? null)
            || !array_is_list($draft['question_ids'])
            || $draft['question_ids'] === []
            || !is_array($draft['choice_counts'] ?? null)
            || !is_array($draft['answers'] ?? null)
            || !is_array($draft['review_flags'] ?? null)
            || !is_int($draft['revision'] ?? null)
            || $draft['revision'] < 0
            || !is_int($draft['exam_answer_revision'] ?? null)
            || $draft['exam_answer_revision'] < 0
            || !is_string($draft['created_at'] ?? null)
            || strtotime($draft['created_at']) === false
            || !is_string($draft['updated_at'] ?? null)
            || strtotime($draft['updated_at']) === false
            || !self::safeDraftConfiguration($draft['quiz_session'])) {
            throw new ExamDraftUnavailableException('The saved exam state is inconsistent.');
        }
        $ids = $draft['question_ids'];
        if (count(array_unique(array_map('strval', $ids))) !== count($ids)) {
            throw new ExamDraftUnavailableException('The saved exam state is inconsistent.');
        }
        self::validateTimer($draft['quiz_session'], time(), true);
        self::normalizeStoredState($draft);
    }

    private static function safeDraftConfiguration(array $session): bool
    {
        $allowed = [
            'board', 'subject', 'domain', 'topics', 'mode', 'session_type', 'timer_mode',
            'time_limit_minutes', 'time_limit_seconds', 'deadline_epoch', 'time_expired',
            'let_variant', 'let_level', 'let_specialization_id', 'civil_service_level',
            'difficulty', 'requested_question_count', 'question_count', 'started_at', 'selection_notice',
        ];
        return array_diff(array_keys($session), $allowed) === []
            && ($session['mode'] ?? null) === 'exam'
            && is_string($session['board'] ?? null)
            && $session['board'] !== ''
            && is_string($session['subject'] ?? null)
            && is_string($session['started_at'] ?? null)
            && strtotime($session['started_at']) !== false
            && is_int($session['question_count'] ?? null)
            && $session['question_count'] > 0
            && $session['question_count'] <= 150
            && is_array($session['topics'] ?? null)
            && array_is_list($session['topics'])
            && (count($session['topics']) <= 30)
            && (is_array($session['let_variant'] ?? null) || ($session['let_variant'] ?? null) === null);
    }

    private static function safeConfigurationSnapshot(array $session): array
    {
        $fields = [
            'board', 'subject', 'domain', 'topics', 'mode', 'session_type', 'timer_mode',
            'time_limit_minutes', 'time_limit_seconds', 'deadline_epoch', 'time_expired',
            'let_variant', 'let_level', 'let_specialization_id', 'civil_service_level',
            'difficulty', 'requested_question_count', 'question_count', 'started_at', 'selection_notice',
        ];
        $snapshot = array_intersect_key($session, array_fill_keys($fields, true));
        if (!self::safeDraftConfiguration($snapshot)) {
            throw new ExamDraftUnavailableException('The exam configuration could not be saved safely.');
        }
        foreach ($snapshot['topics'] as $topic) {
            if (!is_string($topic) || strlen($topic) > 160) {
                throw new ExamDraftUnavailableException('The exam configuration could not be saved safely.');
            }
        }
        return $snapshot;
    }

    private static function validateTimer(array $session, int $now, bool $allowExpired): void
    {
        if (($session['mode'] ?? null) !== 'exam') {
            throw new ExamDraftUnavailableException('The saved exam state is inconsistent.');
        }
        if (($session['timer_mode'] ?? 'untimed') === 'timed') {
            $deadline = filter_var($session['deadline_epoch'] ?? null, FILTER_VALIDATE_INT);
            $seconds = filter_var($session['time_limit_seconds'] ?? null, FILTER_VALIDATE_INT);
            $minutes = filter_var($session['time_limit_minutes'] ?? null, FILTER_VALIDATE_INT);
            $startedAt = is_string($session['started_at'] ?? null)
                ? strtotime($session['started_at'])
                : false;
            if ($deadline === false || $deadline < 1 || $seconds === false || $seconds < 1
                || $minutes === false || $minutes < 1 || $minutes * 60 !== $seconds
                || $startedAt === false || abs(($startedAt + $seconds) - $deadline) > 2
                || ($session['time_expired'] ?? false) === true) {
                throw new ExamDraftUnavailableException('This timed exam draft is no longer recoverable.');
            }
            if (!$allowExpired && $deadline <= $now) {
                throw new ExamDraftUnavailableException('This exam time has ended.');
            }
        } elseif (($session['timer_mode'] ?? 'untimed') !== 'untimed'
            || ($session['deadline_epoch'] ?? null) !== null) {
            throw new ExamDraftUnavailableException('The saved exam state is inconsistent.');
        }
    }

    private static function isExpired(array $session, int $now): bool
    {
        return ($session['timer_mode'] ?? '') === 'timed'
            && (int) ($session['deadline_epoch'] ?? 0) <= $now;
    }

    /** @return list<string> */
    private static function questionIds(array $questions): array
    {
        if (!array_is_list($questions) || count($questions) > 150) {
            throw new ExamDraftUnavailableException('The selected exam questions could not be saved for recovery.');
        }
        $ids = [];
        foreach ($questions as $question) {
            if (!QuizSessionQuestion::isRenderable($question)) {
                throw new ExamDraftUnavailableException('The selected exam questions could not be saved for recovery.');
            }
            $id = self::text($question['id'] ?? null);
            if ($id === '' || in_array($id, $ids, true)) {
                throw new ExamDraftUnavailableException('The selected exam questions could not be saved for recovery.');
            }
            $ids[] = $id;
        }
        return $ids;
    }

    /** @return array<string,int> */
    private static function choiceCounts(array $questions): array
    {
        $counts = [];
        foreach ($questions as $question) {
            $counts[(string) $question['id']] = count($question['choices']);
        }
        return $counts;
    }

    /** @return array{answers:array<string,string>,current_question:int,review_flags:list<int>} */
    private static function normalizeState(
        array $questions,
        array $questionIds,
        array $answers,
        int $currentQuestion,
        array $reviewFlags
    ): array {
        if ($currentQuestion < 0 || $currentQuestion >= count($questionIds)) {
            throw new ExamDraftUnavailableException('The saved exam state is inconsistent.');
        }
        $choiceCounts = self::choiceCounts($questions);
        $normalizedAnswers = [];
        foreach ($answers as $questionId => $answer) {
            if (!is_string($questionId) && !is_int($questionId)) {
                throw new ExamDraftUnavailableException('The saved exam state is inconsistent.');
            }
            $id = (string) $questionId;
            if (!isset($choiceCounts[$id]) || !is_scalar($answer)) {
                throw new ExamDraftUnavailableException('The saved exam state is inconsistent.');
            }
            $value = strtoupper(trim((string) $answer));
            if (preg_match('/\A[A-Z]\z/', $value) !== 1
                || ord($value) - ord('A') >= $choiceCounts[$id]) {
                throw new ExamDraftUnavailableException('The saved exam state is inconsistent.');
            }
            $normalizedAnswers[$id] = $value;
        }
        $normalizedFlags = [];
        foreach ($reviewFlags as $index => $marked) {
            $index = filter_var($index, FILTER_VALIDATE_INT);
            if ($index === false || $index < 0 || $index >= count($questionIds) || $marked !== true) {
                throw new ExamDraftUnavailableException('The saved exam state is inconsistent.');
            }
            $normalizedFlags[] = $index;
        }
        sort($normalizedFlags, SORT_NUMERIC);
        return [
            'answers' => $normalizedAnswers,
            'current_question' => $currentQuestion,
            'review_flags' => array_values(array_unique($normalizedFlags)),
        ];
    }

    /** @return array{answers:array<string,string>,current_question:int,review_flags:list<int>} */
    private static function normalizeStoredState(array $draft): array
    {
        $ids = $draft['question_ids'];
        $counts = $draft['choice_counts'];
        if (count($counts) !== count($ids)) {
            throw new ExamDraftUnavailableException('The saved exam state is inconsistent.');
        }
        $questions = [];
        foreach ($ids as $id) {
            if (!is_string($id) || !is_int($counts[$id] ?? null) || $counts[$id] < 2 || $counts[$id] > 26) {
                throw new ExamDraftUnavailableException('The saved exam state is inconsistent.');
            }
            $questions[] = [
                'id' => $id,
                'question' => 'stored selection',
                'answer' => 'A',
                'choices' => array_fill(0, $counts[$id], 'choice'),
            ];
        }
        $current = filter_var($draft['current_question'] ?? null, FILTER_VALIDATE_INT);
        if ($current === false) {
            throw new ExamDraftUnavailableException('The saved exam state is inconsistent.');
        }
        return self::normalizeState(
            $questions,
            $ids,
            $draft['answers'],
            $current,
            array_fill_keys($draft['review_flags'], true)
        );
    }

    private static function stateMatches(array $draft, array $state): bool
    {
        $stored = self::normalizeStoredState($draft);
        return $stored['answers'] === $state['answers']
            && $stored['current_question'] === $state['current_question']
            && $stored['review_flags'] === $state['review_flags'];
    }

    private static function text(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }
}
