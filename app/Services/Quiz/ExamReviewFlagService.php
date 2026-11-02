<?php

declare(strict_types=1);

namespace App\Services\Quiz;

use App\Services\Quiz\Session\QuizSessionQuestion;

/** Server-session review marks for an active exam simulation. */
final class ExamReviewFlagService
{
    /**
     * Capture only marks that belong to the exact exam being finalized.
     * The returned additive snapshot is safe to store with that attempt.
     *
     * @return array{version:int,marked_question_ids:array<int,string>}|null
     */
    public static function snapshotForAttempt(array $session, array $questions): ?array
    {
        if (($session['mode'] ?? '') !== 'exam') {
            return null;
        }

        $sessionId = is_scalar($session['id'] ?? null) ? trim((string) $session['id']) : '';
        $currentSession = \SessionService::get('quiz_session', []);
        $currentSessionId = is_array($currentSession) && is_scalar($currentSession['id'] ?? null)
            ? trim((string) $currentSession['id'])
            : '';
        if ($sessionId === '' || $currentSessionId === ''
            || !hash_equals($currentSessionId, $sessionId)
            || !self::scopeMatchesCurrentSession()) {
            return null;
        }

        $questions = array_values($questions);
        $flags = self::readFlags($questions);
        $markedQuestionIds = [];
        foreach ($questions as $index => $question) {
            if (!isset($flags[$index]) || !is_array($question) || !is_scalar($question['id'] ?? null)) {
                continue;
            }
            $questionId = trim((string) $question['id']);
            if ($questionId !== '') {
                $markedQuestionIds[] = $questionId;
            }
        }

        return ['version' => 1, 'marked_question_ids' => array_values(array_unique($markedQuestionIds))];
    }

    /** @return array{marked: bool, version: int} */
    public static function state(int $index, array $questions): array
    {
        if (!self::scopeMatchesCurrentSession()) {
            return ['marked' => false, 'version' => 0];
        }
        $flags = self::readFlags($questions);
        $versions = self::readVersions($questions);

        return self::stateFrom($index, $flags, $versions);
    }

    /** @return array<int, array{marked: bool, version: int}> */
    public static function states(array $questions): array
    {
        if (!self::scopeMatchesCurrentSession()) {
            $empty = [];
            foreach (array_values($questions) as $index => $_question) $empty[$index] = ['marked' => false, 'version' => 0];
            return $empty;
        }
        $flags = self::readFlags($questions);
        $versions = self::readVersions($questions);
        $states = [];
        foreach (array_values($questions) as $index => $_question) {
            $states[$index] = self::stateFrom($index, $flags, $versions);
        }
        return $states;
    }

    /**
     * Set a mark only for a question in this learner's active exam session.
     * The version makes an old conflicting request harmless while an identical
     * retry remains idempotent after a lost response.
     *
     * @return array{status: string, marked: bool, version: int}
     */
    public static function set(int $index, string $questionId, bool $marked, int $expectedVersion): array
    {
        $empty = ['status' => 'invalid', 'marked' => false, 'version' => 0];
        if (\SessionService::get('mode', 'practice') !== 'exam'
            || \SessionService::get('quiz_completed', false) === true
            || \SessionService::has('attempt_persisted')) {
            return $empty;
        }

        $questions = \SessionService::get('questions', []);
        if (!is_array($questions)) {
            return $empty;
        }
        if (!self::initializeOrMatchCurrentScope()) {
            return $empty;
        }
        $questions = array_values($questions);
        $question = $questions[$index] ?? null;
        if ($index < 0 || !QuizSessionQuestion::isCurrent($question)
            || !is_scalar($question['id'] ?? null)
            || trim((string) $question['id']) === ''
            || trim((string) $question['id']) !== trim($questionId)) {
            return $empty;
        }

        $flags = self::readFlags($questions);
        $versions = self::readVersions($questions);
        $currentMarked = isset($flags[$index]);
        $currentVersion = $versions[$index] ?? 0;

        if ($currentMarked === $marked) {
            return ['status' => 'unchanged', 'marked' => $currentMarked, 'version' => $currentVersion];
        }
        if ($expectedVersion !== $currentVersion || $currentVersion >= PHP_INT_MAX - 1) {
            return ['status' => 'stale', 'marked' => $currentMarked, 'version' => $currentVersion];
        }

        if ($marked) {
            $flags[$index] = true;
        } else {
            unset($flags[$index]);
        }
        $versions[$index] = $currentVersion + 1;
        ksort($flags, SORT_NUMERIC);
        ksort($versions, SORT_NUMERIC);
        \SessionService::set('exam_review_flags', $flags);
        \SessionService::set('exam_review_flag_versions', $versions);

        return ['status' => 'updated', 'marked' => $marked, 'version' => $versions[$index]];
    }

    /** @return array<int, true> */
    private static function readFlags(array $questions): array
    {
        $stored = \SessionService::get('exam_review_flags', []);
        if (!is_array($stored)) {
            return [];
        }

        $flags = [];
        foreach ($stored as $rawIndex => $enabled) {
            $index = self::validIndex($rawIndex, count($questions));
            if ($index !== null && $enabled === true && QuizSessionQuestion::isRenderable($questions[$index] ?? null)) {
                $flags[$index] = true;
            }
        }
        return $flags;
    }

    /** @return array<int, int> */
    private static function readVersions(array $questions): array
    {
        $stored = \SessionService::get('exam_review_flag_versions', []);
        if (!is_array($stored)) {
            return [];
        }

        $versions = [];
        foreach ($stored as $rawIndex => $rawVersion) {
            $index = self::validIndex($rawIndex, count($questions));
            if ($index === null || !QuizSessionQuestion::isRenderable($questions[$index] ?? null)
                || !is_int($rawVersion) || $rawVersion < 0) {
                continue;
            }
            $versions[$index] = $rawVersion;
        }
        return $versions;
    }

    private static function validIndex(mixed $rawIndex, int $count): ?int
    {
        if (!is_int($rawIndex) && (!is_string($rawIndex) || preg_match('/\A\d{1,4}\z/', $rawIndex) !== 1)) {
            return null;
        }
        $index = (int) $rawIndex;
        return $index >= 0 && $index < $count ? $index : null;
    }

    private static function scopeMatchesCurrentSession(): bool
    {
        $session = \SessionService::get('quiz_session', []);
        $sessionId = is_array($session) && is_scalar($session['id'] ?? null)
            ? trim((string) $session['id'])
            : '';
        $stored = \SessionService::get('exam_review_flag_session_id');
        return $sessionId !== '' && is_scalar($stored) && hash_equals($sessionId, (string) $stored);
    }

    /** Old active sessions have no marks; initialize them without carrying stale state forward. */
    private static function initializeOrMatchCurrentScope(): bool
    {
        $session = \SessionService::get('quiz_session', []);
        $sessionId = is_array($session) && is_scalar($session['id'] ?? null)
            ? trim((string) $session['id'])
            : '';
        if (preg_match('/\Aquiz-(?:[a-f0-9]{16}|[a-f0-9]{32})\z/', $sessionId) !== 1) return false;

        $stored = \SessionService::get('exam_review_flag_session_id');
        if (is_scalar($stored) && (string) $stored === $sessionId) return true;
        if (is_scalar($stored) && trim((string) $stored) !== '') return false;

        \SessionService::set('exam_review_flags', []);
        \SessionService::set('exam_review_flag_versions', []);
        \SessionService::set('exam_review_flag_session_id', $sessionId);
        return true;
    }

    /** @param array<int, true> $flags @param array<int, int> $versions */
    private static function stateFrom(int $index, array $flags, array $versions): array
    {
        return ['marked' => isset($flags[$index]), 'version' => $versions[$index] ?? 0];
    }
}
