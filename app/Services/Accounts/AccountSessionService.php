<?php

declare(strict_types=1);

namespace App\Services\Accounts;

use App\Services\Learning\LearnerIdentityService;
use App\Services\Shared\CsrfTokenService;

final class AccountSessionService
{
    private const SESSION_KEY = 'boardprep_account_auth';
    private const IDLE_SECONDS = 1800;
    private const ABSOLUTE_SECONDS = 43200;

    /** Check idle/absolute expiry, account status, and password-change epoch. */
    public static function enforce(): void
    {
        if (!AccountFeatureGate::enabled()) {
            if (isset($_SESSION[self::SESSION_KEY])) {
                self::logout();
            }
            return;
        }
        self::resolve(true);
    }

    public static function current(): ?array
    {
        return self::resolve(true);
    }

    public static function isAuthenticated(): bool
    {
        return self::current() !== null;
    }

    public static function login(array $account): void
    {
        $anonymousId = $_SESSION['anonymous_learner_identity'] ?? $_SESSION['learner_identity'] ?? null;
        if (!is_string($anonymousId) || preg_match('/\Alearner-[a-f0-9]{32}\z/', $anonymousId) !== 1) {
            unset($_SESSION['learner_identity']);
            $anonymousId = LearnerIdentityService::current();
        }
        $_SESSION['anonymous_learner_identity'] = $anonymousId;

        if (is_array($_SESSION['learner_preferences'] ?? null)) {
            $_SESSION['anonymous_learner_preferences'] = $_SESSION['learner_preferences'];
        }
        unset($_SESSION['learner_preferences']);

        $quizKeys = [
            'questions', 'answers', 'feedback', 'mode', 'currentQuestion',
            'quiz_session', 'attempt_persisted', 'quiz_completed', 'quiz_result',
            'exam_answer_revision', 'exam_reviewed_revision', 'flashcard_missed_scope',
        ];
        $anonymousQuiz = [];
        foreach ($quizKeys as $key) {
            if (array_key_exists($key, $_SESSION)) {
                $anonymousQuiz[$key] = $_SESSION[$key];
                unset($_SESSION[$key]);
            }
        }
        if ($anonymousQuiz !== []) {
            $_SESSION['anonymous_pending_quiz'] = $anonymousQuiz;
        }

        self::rotateId();
        $now = time();
        $_SESSION[self::SESSION_KEY] = [
            'account_id' => (string) $account['account_id'],
            'learner_id' => (string) $account['learner_id'],
            'auth_version' => (int) ($account['auth_version'] ?? 1),
            'started_at' => $now,
            'last_activity' => $now,
        ];
        $_SESSION['learner_identity'] = (string) $account['learner_id'];
        CsrfTokenService::rotate();
    }

    /** Rotate the active session after an authenticated security change. */
    public static function refresh(array $account): void
    {
        $auth = $_SESSION[self::SESSION_KEY] ?? null;
        if (!is_array($auth)
            || ($auth['account_id'] ?? null) !== ($account['account_id'] ?? null)) {
            throw new \RuntimeException('The authenticated account session is unavailable.');
        }
        self::rotateId();
        $auth['auth_version'] = (int) ($account['auth_version'] ?? 1);
        $auth['last_activity'] = time();
        $_SESSION[self::SESSION_KEY] = $auth;
        $_SESSION['learner_identity'] = (string) $account['learner_id'];
        CsrfTokenService::rotate();
    }

    public static function logout(): void
    {
        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION[self::SESSION_KEY], $_SESSION['learner_preferences']);
        self::restoreAnonymousIdentityAndState();
        self::rotateId();
        CsrfTokenService::rotate();
        if (is_array($flash)) {
            $_SESSION['flash'] = $flash;
        }
    }

    public static function sessionDetails(): ?array
    {
        $auth = $_SESSION[self::SESSION_KEY] ?? null;
        if (!is_array($auth)) {
            return null;
        }
        return [
            'started_at' => gmdate(DATE_ATOM, (int) ($auth['started_at'] ?? 0)),
            'last_activity_at' => gmdate(DATE_ATOM, (int) ($auth['last_activity'] ?? 0)),
            'idle_expiry_minutes' => (int) (self::IDLE_SECONDS / 60),
            'absolute_expiry_hours' => (int) (self::ABSOLUTE_SECONDS / 3600),
        ];
    }

    private static function resolve(bool $touch): ?array
    {
        if (!AccountFeatureGate::enabled()) {
            if (isset($_SESSION[self::SESSION_KEY])) {
                self::logout();
            }
            return null;
        }
        $auth = $_SESSION[self::SESSION_KEY] ?? null;
        if (!is_array($auth)) {
            return null;
        }
        $accountId = $auth['account_id'] ?? null;
        $learnerId = $auth['learner_id'] ?? null;
        $startedAt = $auth['started_at'] ?? null;
        $lastActivity = $auth['last_activity'] ?? null;
        $version = $auth['auth_version'] ?? null;
        $now = time();
        if (!is_string($accountId)
            || preg_match('/\Aaccount-[a-f0-9]{32}\z/', $accountId) !== 1
            || !is_string($learnerId)
            || preg_match('/\Aaccount-learner-[a-f0-9]{32}\z/', $learnerId) !== 1
            || !is_int($startedAt)
            || !is_int($lastActivity)
            || !is_int($version)
            || $startedAt > $now
            || $lastActivity > $now
            || $now - $lastActivity >= self::IDLE_SECONDS
            || $now - $startedAt >= self::ABSOLUTE_SECONDS) {
            self::logout();
            return null;
        }

        $account = (new AccountRepository())->findById($accountId);
        if (!is_array($account)
            || ($account['status'] ?? '') !== 'active'
            || ($account['learner_id'] ?? '') !== $learnerId
            || (int) ($account['auth_version'] ?? 0) !== $version) {
            self::logout();
            return null;
        }

        $_SESSION['learner_identity'] = $learnerId;
        if ($touch) {
            $_SESSION[self::SESSION_KEY]['last_activity'] = $now;
        }

        return self::safeAccount($account);
    }

    private static function safeAccount(array $account): array
    {
        return [
            'account_id' => (string) $account['account_id'],
            'learner_id' => (string) $account['learner_id'],
            'username' => (string) $account['username'],
            'created_at' => (string) $account['created_at'],
            'updated_at' => (string) ($account['updated_at'] ?? $account['created_at']),
            'auth_version' => (int) ($account['auth_version'] ?? 1),
            'status' => (string) ($account['status'] ?? 'active'),
            'preferences' => is_array($account['preferences'] ?? null) ? $account['preferences'] : [],
            'deletion_requested_at' => $account['deletion_requested_at'] ?? null,
        ];
    }

    private static function restoreAnonymousIdentityAndState(): void
    {
        $anonymousId = $_SESSION['anonymous_learner_identity'] ?? null;
        unset($_SESSION['learner_identity']);
        if (is_string($anonymousId) && preg_match('/\Alearner-[a-f0-9]{32}\z/', $anonymousId) === 1) {
            $_SESSION['learner_identity'] = $anonymousId;
        } else {
            unset($_SESSION['anonymous_learner_identity']);
        }

        unset($_SESSION['learner_preferences']);
        if (is_array($_SESSION['anonymous_learner_preferences'] ?? null)) {
            $_SESSION['learner_preferences'] = $_SESSION['anonymous_learner_preferences'];
        }
        unset($_SESSION['anonymous_learner_preferences']);

        $quizKeys = [
            'questions', 'answers', 'feedback', 'mode', 'currentQuestion',
            'quiz_session', 'attempt_persisted', 'quiz_completed', 'quiz_result',
            'exam_answer_revision', 'exam_reviewed_revision', 'flashcard_missed_scope',
        ];
        foreach ($quizKeys as $key) {
            unset($_SESSION[$key]);
        }
        $pending = $_SESSION['anonymous_pending_quiz'] ?? null;
        if (is_array($pending)) {
            foreach ($pending as $key => $value) {
                if (in_array($key, $quizKeys, true)) {
                    $_SESSION[$key] = $value;
                }
            }
        }
        unset($_SESSION['anonymous_pending_quiz']);
    }

    private static function rotateId(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE && !session_regenerate_id(true)) {
            throw new \RuntimeException('The secure account session could not be rotated.');
        }
    }
}
