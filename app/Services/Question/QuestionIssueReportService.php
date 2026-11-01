<?php

declare(strict_types=1);

namespace App\Services\Question;

use App\Contracts\StorageInterface;
use App\Core\App;
use App\Repositories\AttemptRepository;
use App\Services\Accounts\AccountLifecycleLock;
use InvalidArgumentException;
use RuntimeException;

/** Learner concerns are stored apart from canonical questions and academic decisions. */
final class QuestionIssueReportService
{
    public const COLLECTION = 'learner-question-reports';
    public const MAX_REPORTS = 5000;
    public const MAX_REPORTS_PER_DAY = 5;
    public const PAGE_SIZE = 25;

    private const TYPES = [
        'ANSWER_OR_EXPLANATION' => 'Answer or explanation may be incorrect',
        'UNCLEAR_WORDING' => 'Wording is unclear',
        'OUTDATED_INFORMATION' => 'Information may be outdated',
        'SOURCE_CONCERN' => 'Source or citation concern',
        'POSSIBLE_DUPLICATE' => 'Possible duplicate question',
        'OTHER' => 'Other question concern',
    ];

    private const STATUSES = ['SUBMITTED', 'UNDER_REVIEW', 'RESOLVED', 'REJECTED'];

    private StorageInterface $storage;
    private AttemptRepository $attempts;

    public function __construct(?StorageInterface $storage = null, ?AttemptRepository $attempts = null)
    {
        $this->storage = $storage ?? App::storage();
        $this->attempts = $attempts ?? new AttemptRepository($this->storage);
    }

    /** @return array<string,string> */
    public static function types(): array
    {
        return self::TYPES;
    }

    /** @return array{id:string,question:string,archived:bool}|null Context is derived from an owned completed result snapshot. */
    public function questionContextForAttempt(string $learnerId, string $attemptId, string $questionId): ?array
    {
        $resolved = $this->ownedAttemptQuestion($learnerId, $attemptId, $questionId);
        if ($resolved === null) return null;
        $question = $resolved['question'];
        $canonical = $this->storage->find('questions', $questionId);
        $archived = !is_array($canonical)
            || self::questionVersion($canonical) !== self::questionVersion($question);
        return [
            'id' => $questionId,
            'question' => is_scalar($question['question'] ?? null) ? (string) $question['question'] : '',
            'archived' => $archived,
        ];
    }

    /** @return array{report:array<string,mixed>,duplicate:bool} */
    public function submit(string $learnerId, string $attemptId, string $questionId, string $type, string $description = ''): array
    {
        if (preg_match('/\A(?:learner|account-learner)-[a-f0-9]{32}\z/', $learnerId) !== 1) {
            throw new InvalidArgumentException('Your learner session could not be verified. Refresh the page and try again.');
        }
        $type = strtoupper(trim($type));
        if (!isset(self::TYPES[$type])) {
            throw new InvalidArgumentException('Choose one of the available concern types.');
        }
        $questionId = self::questionId($questionId);
        $description = self::description($description);
        $attemptId = self::attemptId($attemptId);

        // Serialize duplicate/rate-limit checks for anonymous learners too; the
        // nested writable-learner guard additionally fences account deletion.
        return AccountLifecycleLock::withLock(function () use (
            $learnerId, $attemptId, $questionId, $type, $description
        ) {
            return AccountLifecycleLock::withWritableLearner($learnerId, function () use (
                $learnerId, $attemptId, $questionId, $type, $description
            ): array {
                $resolved = $this->ownedAttemptQuestion($learnerId, $attemptId, $questionId);
                if ($resolved === null) {
                    throw new InvalidArgumentException('That question is unavailable in a completed quiz result owned by this learner.');
                }
                $question = $resolved['question'];
                $boardId = $resolved['board_id'];
                $subjectId = $resolved['subject_id'];
                return $this->persistReport($learnerId, $attemptId, $questionId, $type, $description, $question, $boardId, $subjectId);
            });
        });
    }

    /** @return array{report:array<string,mixed>,duplicate:bool} */
    private function persistReport(string $learnerId, string $attemptId, string $questionId, string $type, string $description, array $question, string $boardId, string $subjectId): array
    {
        $records = $this->storage->all(self::COLLECTION);
        if (count($records) >= self::MAX_REPORTS) {
            throw new RuntimeException('Question reports are temporarily at capacity. Your quiz result and answer are unchanged.');
        }

        $now = time();
        $recentCutoff = $now - 86400;
        $recentCount = 0;
        foreach ($records as $record) {
            if (!is_array($record) || ($record['user_id'] ?? null) !== $learnerId) {
                continue;
            }
            $created = is_string($record['created_at'] ?? null) ? strtotime($record['created_at']) : false;
            if (!is_int($created) || $created < $recentCutoff) {
                continue;
            }
            $recentCount++;
            if (($record['question_id'] ?? null) === $questionId && ($record['type'] ?? null) === $type) {
                return ['report' => self::learnerView($record), 'duplicate' => true];
            }
        }
        if ($recentCount >= self::MAX_REPORTS_PER_DAY) {
            throw new RuntimeException('You have reached the daily question-report limit. Please try again later.');
        }

        try {
            $versionPayload = json_encode($question, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new RuntimeException('The question could not be checked safely. No report was saved.', 0, $exception);
        }
        $id = 'qir-' . bin2hex(random_bytes(16));
        $timestamp = gmdate(DATE_ATOM, $now);
        $record = [
            'id' => $id,
            'user_id' => $learnerId,
            'attempt_id' => $attemptId,
            'question_id' => $questionId,
            'question_version_sha256' => hash('sha256', $versionPayload),
            'board_id' => $boardId,
            'subject_id' => $subjectId,
            'type' => $type,
            'description' => $description,
            'status' => 'SUBMITTED',
            'created_at' => $timestamp,
            'status_changed_at' => $timestamp,
            'status_history' => [['status' => 'SUBMITTED', 'at' => $timestamp]],
        ];
        $this->storage->create(self::COLLECTION, $record);

        return ['report' => self::learnerView($record), 'duplicate' => false];
    }

    /** @return list<array<string,mixed>> */
    public function forLearner(string $learnerId): array
    {
        $records = array_values(array_filter(
            $this->storage->all(self::COLLECTION),
            static fn(mixed $record): bool => is_array($record) && ($record['user_id'] ?? null) === $learnerId
        ));
        usort($records, static fn(array $a, array $b): int => strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? '')));
        return array_map(self::learnerView(...), array_slice($records, 0, 50));
    }

    /** @param list<array{board_id:string,subject_id:string,capabilities:list<string>}> $scopes
     * @return array{items:list<array<string,mixed>>,total:int,page:int,pages:int,status:string}
     */
    public function reviewerPage(array $scopes, string $status = 'OPEN', int $page = 1): array
    {
        if ($scopes === []) {
            throw new InvalidArgumentException('An active question-review scope is required.');
        }
        $allowed = [];
        foreach ($scopes as $scope) {
            if (!is_array($scope) || !is_array($scope['capabilities'] ?? null)
                || !in_array('REVIEW', array_map('strtoupper', $scope['capabilities']), true)) {
                continue;
            }
            $board = self::scalarText($scope['board_id'] ?? '');
            $subject = self::scalarText($scope['subject_id'] ?? '');
            if ($board !== '' && $subject !== '') {
                $allowed[$board . "\0" . $subject] = true;
            }
        }
        if ($allowed === []) {
            throw new InvalidArgumentException('An active question-review scope is required.');
        }
        $status = strtoupper(trim($status));
        if (!in_array($status, ['OPEN', ...self::STATUSES], true)) {
            $status = 'OPEN';
        }
        $items = [];
        foreach ($this->storage->all(self::COLLECTION) as $record) {
            if (!is_array($record)
                || !isset($allowed[(string) ($record['board_id'] ?? '') . "\0" . (string) ($record['subject_id'] ?? '')])) {
                continue;
            }
            $currentStatus = strtoupper((string) ($record['status'] ?? ''));
            if ($status === 'OPEN' && !in_array($currentStatus, ['SUBMITTED', 'UNDER_REVIEW'], true)) {
                continue;
            }
            if ($status !== 'OPEN' && $currentStatus !== $status) {
                continue;
            }
            $items[] = self::reviewerView($record);
        }
        usort($items, static fn(array $a, array $b): int => strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? '')));
        $total = count($items);
        $pages = max(1, (int) ceil($total / self::PAGE_SIZE));
        $page = min(max(1, $page), $pages);
        return [
            'items' => array_slice($items, ($page - 1) * self::PAGE_SIZE, self::PAGE_SIZE),
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'status' => $status,
        ];
    }

    public function transition(string $id, string $status, string $accountId, \App\Services\Question\AcademicReviewerAuthorizationService $access): bool
    {
        $id = trim($id);
        $status = strtoupper(trim($status));
        if (preg_match('/\Aqir-[a-f0-9]{32}\z/', $id) !== 1 || !in_array($status, ['UNDER_REVIEW', 'RESOLVED', 'REJECTED'], true)) {
            throw new InvalidArgumentException('The report update was invalid.');
        }
        $allowed = [
            'SUBMITTED' => ['UNDER_REVIEW', 'REJECTED'],
            'UNDER_REVIEW' => ['RESOLVED', 'REJECTED'],
        ];
        $found = false;
        $authorized = false;
        $changed = false;
        $this->storage->updateBatch(self::COLLECTION, [$id], static function (array $record) use (
            $status, $accountId, $access, $allowed, &$found, &$authorized, &$changed
        ): array {
            $found = true;
            if (!$access->allows(
                $accountId,
                (string) ($record['board_id'] ?? ''),
                (string) ($record['subject_id'] ?? ''),
                'REVIEW'
            )) {
                return $record;
            }
            $authorized = true;
            $current = strtoupper((string) ($record['status'] ?? ''));
            if ($current === $status) {
                $changed = true;
                return $record;
            }
            if (!in_array($status, $allowed[$current] ?? [], true)) {
                throw new InvalidArgumentException('That report cannot move from its current status to the requested status.');
            }
            $timestamp = gmdate(DATE_ATOM);
            $history = self::validStatusHistory($record['status_history'] ?? null);
            $history[] = ['from' => $current, 'to' => $status, 'at' => $timestamp];
            $record['status'] = $status;
            $record['status_changed_at'] = $timestamp;
            $record['status_history'] = array_slice($history, -10);
            $changed = true;
            return $record;
        });
        if (!$found || !$authorized) return false;
        return $changed;
    }

    /** @return array{attempt:array<string,mixed>,question:array<string,mixed>,board_id:string,subject_id:string}|null */
    private function ownedAttemptQuestion(string $learnerId, string $attemptId, string $questionId): ?array
    {
        if (preg_match('/\A(?:learner|account-learner)-[a-f0-9]{32}\z/', $learnerId) !== 1) return null;
        $attemptId = self::attemptId($attemptId);
        $questionId = self::questionId($questionId);
        $attempt = $this->attempts->findForLearner($attemptId, $learnerId);
        if (!is_array($attempt) || ($attempt['completed'] ?? false) !== true) return null;

        foreach (is_array($attempt['review'] ?? null) ? $attempt['review'] : [] as $item) {
            if (!is_array($item) || !is_array($item['question'] ?? null)) continue;
            $question = $item['question'];
            if (!is_scalar($question['id'] ?? null) || trim((string) $question['id']) !== $questionId) continue;
            $taxonomy = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
            $learningContext = is_array($attempt['learning_context'] ?? null) ? $attempt['learning_context'] : [];
            $boardId = self::scalarText($taxonomy['board_id'] ?? $question['board'] ?? $attempt['board'] ?? $learningContext['board'] ?? '');
            $subjectId = self::scalarText($taxonomy['subject_id'] ?? $question['subject'] ?? $attempt['subject'] ?? $learningContext['subject'] ?? '');
            if ($boardId === '' || $subjectId === '') return null;
            return [
                'attempt' => $attempt,
                'question' => $question,
                'board_id' => $boardId,
                'subject_id' => $subjectId,
            ];
        }
        return null;
    }

    private static function attemptId(string $attemptId): string
    {
        $attemptId = trim($attemptId);
        if ($attemptId === '' || strlen($attemptId) > 120
            || preg_match('/\A[A-Za-z0-9][A-Za-z0-9._:-]*\z/', $attemptId) !== 1) {
            throw new InvalidArgumentException('Choose a valid completed quiz result.');
        }
        return $attemptId;
    }

    private static function questionVersion(array $question): string
    {
        try {
            return hash('sha256', json_encode($question, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        } catch (\JsonException $exception) {
            throw new RuntimeException('The question snapshot could not be checked safely.', 0, $exception);
        }
    }

    /** @return list<array{from:string,to:string,at:string}> */
    private static function validStatusHistory(mixed $history): array
    {
        if (!is_array($history)) return [];
        $valid = [];
        foreach ($history as $entry) {
            if (!is_array($entry) || !is_string($entry['at'] ?? null) || trim($entry['at']) === '') continue;
            $from = strtoupper(trim((string) ($entry['from'] ?? '')));
            $to = strtoupper(trim((string) ($entry['to'] ?? $entry['status'] ?? '')));
            if (!in_array($to, self::STATUSES, true)
                || ($from !== '' && !in_array($from, self::STATUSES, true))) continue;
            $valid[] = ['from' => $from, 'to' => $to, 'at' => $entry['at']];
        }
        return array_slice($valid, -9);
    }

    private static function questionId(string $questionId): string
    {
        $questionId = trim($questionId);
        if ($questionId === '' || strlen($questionId) > 120 || preg_match('/\A[A-Za-z0-9][A-Za-z0-9._:-]*\z/', $questionId) !== 1) {
            throw new InvalidArgumentException('Choose a valid question before sending a report.');
        }
        return $questionId;
    }

    private static function description(string $description): string
    {
        if (!preg_match('//u', $description)) {
            throw new InvalidArgumentException('Use valid text in the optional note.');
        }
        $description = trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $description) ?? '');
        preg_match_all('/./us', $description, $characters);
        if (count($characters[0] ?? []) > 500) {
            throw new InvalidArgumentException('Keep the optional note to 500 characters or fewer.');
        }
        return $description;
    }

    private static function scalarText(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }

    /** @return array<string,mixed> */
    private static function learnerView(array $record): array
    {
        return [
            'id' => (string) ($record['id'] ?? ''),
            'question_id' => (string) ($record['question_id'] ?? ''),
            'type' => (string) ($record['type'] ?? ''),
            'type_label' => self::TYPES[(string) ($record['type'] ?? '')] ?? 'Question concern',
            'description' => (string) ($record['description'] ?? ''),
            'status' => in_array(($record['status'] ?? null), self::STATUSES, true) ? (string) $record['status'] : 'SUBMITTED',
            'created_at' => (string) ($record['created_at'] ?? ''),
            'status_changed_at' => (string) ($record['status_changed_at'] ?? $record['created_at'] ?? ''),
        ];
    }

    /** @return array<string,mixed> */
    private static function reviewerView(array $record): array
    {
        return [
            'id' => (string) ($record['id'] ?? ''),
            'question_id' => (string) ($record['question_id'] ?? ''),
            'attempt_id' => (string) ($record['attempt_id'] ?? ''),
            'question_version_sha256' => (string) ($record['question_version_sha256'] ?? ''),
            'board_id' => (string) ($record['board_id'] ?? ''),
            'subject_id' => (string) ($record['subject_id'] ?? ''),
            'type' => (string) ($record['type'] ?? ''),
            'type_label' => self::TYPES[(string) ($record['type'] ?? '')] ?? 'Question concern',
            'description' => (string) ($record['description'] ?? ''),
            'status' => (string) ($record['status'] ?? 'SUBMITTED'),
            'created_at' => (string) ($record['created_at'] ?? ''),
            'status_changed_at' => (string) ($record['status_changed_at'] ?? $record['created_at'] ?? ''),
            'status_history' => self::validStatusHistory($record['status_history'] ?? null),
        ];
    }
}
