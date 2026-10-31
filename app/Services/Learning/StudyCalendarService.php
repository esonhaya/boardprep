<?php

declare(strict_types=1);

namespace App\Services\Learning;

use App\Contracts\StorageInterface;
use App\Core\App;
use App\Services\Accounts\AccountLifecycleLock;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/** Owner-scoped, versioned calendar state with idempotent edits. */
final class StudyCalendarService
{
    public const COLLECTION = 'learner-study-plans';
    public const MAX_ACTIVE_TASKS = 28;

    private StorageInterface $storage;
    private string $learnerId;

    public function __construct(?StorageInterface $storage = null, ?string $learnerId = null)
    {
        $this->storage = $storage ?? App::storage();
        $this->learnerId = $learnerId ?? LearnerIdentityService::current();
        if (preg_match('/\A(?:learner|account-learner)-[a-f0-9]{32}\z/', $this->learnerId) !== 1) {
            throw new RuntimeException('The learner identity could not be verified.');
        }
    }

    /** @return array<string,mixed>|null */
    public function current(): ?array
    {
        $record = $this->storage->find(self::COLLECTION, self::recordId($this->learnerId));
        if (!is_array($record) || ($record['user_id'] ?? null) !== $this->learnerId
            || ($record['id'] ?? null) !== self::recordId($this->learnerId)) {
            return null;
        }
        return $record;
    }

    /** @return array<string,mixed> */
    public function dashboardSummary(string $today): array
    {
        $plan = $this->current();
        if ($plan === null) return ['state' => 'NOT_CONFIGURED'];

        $sessions = is_array($plan['sessions'] ?? null) ? $plan['sessions'] : [];
        $completed = 0;
        $active = [];
        foreach ($sessions as $session) {
            if (!is_array($session)) continue;
            $status = (string) ($session['status'] ?? 'planned');
            if ($status === 'completed') $completed++;
            if (!in_array($status, ['planned', 'deferred'], true)) continue;
            $date = (string) ($session['scheduled_date'] ?? '');
            if ($date === '') continue;
            $active[] = $session;
        }
        usort($active, self::sessionOrder(...));
        $next = $active[0] ?? null;
        if (!is_array($next)) {
            return ['state' => 'NO_UPCOMING_SESSIONS', 'completed' => $completed, 'total' => count($sessions)];
        }
        $action = (string) ($next['action'] ?? '/study-calendar');
        if (!preg_match('#^/(?:quiz|flashcards/review|mistake-notebook/review)(?:\?|$)#', $action)) {
            $action = '/study-calendar';
        }
        return [
            'state' => (string) ($next['scheduled_date'] ?? '') < $today ? 'OVERDUE' : 'UPCOMING',
            'date' => (string) ($next['scheduled_date'] ?? ''),
            'subject' => (string) ($next['subject_name'] ?? $next['subject_id'] ?? ''),
            'kind' => (string) ($next['kind'] ?? 'PRACTICE'),
            'duration_minutes' => max(0, (int) ($next['duration_minutes'] ?? 0)),
            'completed' => $completed,
            'total' => count($sessions),
            'action' => $action,
        ];
    }

    /** @return array{plan:array<string,mixed>,retained_user_edits:int,duplicate:bool} */
    public function saveConfiguration(array $input, array $boards, array $evidence, string $today): array
    {
        $configuration = StudyCalendarPlanner::validateConfiguration($input, $boards, $today);
        $requestId = self::requestId($input['request_id'] ?? null);
        $expectedRevision = self::revision($input['expected_revision'] ?? null);
        $requestHash = self::hash(['configuration' => $configuration, 'operation' => 'configure']);
        $built = StudyCalendarPlanner::generate($configuration, $evidence, $today);
        $userId = $this->learnerId;

        return AccountLifecycleLock::withWritableLearner($userId, function () use (
            $configuration, $built, $today, $requestId, $expectedRevision, $requestHash, $userId
        ): array {
            $id = self::recordId($userId);
            $existing = $this->storage->find(self::COLLECTION, $id);
            if (is_array($existing) && ($existing['user_id'] ?? null) !== $userId) {
                throw new RuntimeException('This calendar is unavailable to the current learner.');
            }
            if (is_array($existing) && ($existing['last_request_id'] ?? null) === $requestId) {
                if (($existing['last_request_hash'] ?? null) !== $requestHash) {
                    throw new InvalidArgumentException('This calendar request was already used with different settings.');
                }
                return ['plan' => $existing, 'retained_user_edits' => (int) ($existing['retained_user_edits'] ?? 0), 'duplicate' => true];
            }
            $actualRevision = (int) ($existing['revision'] ?? 0);
            if ($actualRevision !== $expectedRevision) {
                throw new InvalidArgumentException('Your study calendar changed in another request. Reload it before saving these settings.');
            }

            $merge = self::mergeGeneratedSessions(
                is_array($existing['sessions'] ?? null) ? $existing['sessions'] : [],
                $built['sessions'],
                $today
            );
            $now = gmdate(DATE_ATOM);
            $record = [
                'id' => $id,
                'user_id' => $userId,
                'schema_version' => 1,
                'revision' => $actualRevision + 1,
                'configuration' => $configuration,
                'sessions' => $merge['sessions'],
                'window_end_date' => $built['window_end_date'],
                'performance_status' => $built['performance_status'],
                'explanations' => $built['explanations'],
                'retained_user_edits' => $merge['retained_user_edits'],
                'last_request_id' => $requestId,
                'last_request_hash' => $requestHash,
                'created_at' => is_string($existing['created_at'] ?? null) ? $existing['created_at'] : $now,
                'updated_at' => $now,
            ];

            if (!is_array($existing)) {
                try {
                    $saved = $this->storage->create(self::COLLECTION, $record);
                } catch (Throwable $exception) {
                    $raced = $this->storage->find(self::COLLECTION, $id);
                    if (is_array($raced) && ($raced['user_id'] ?? null) === $userId
                        && ($raced['last_request_id'] ?? null) === $requestId
                        && ($raced['last_request_hash'] ?? null) === $requestHash) {
                        return ['plan' => $raced, 'retained_user_edits' => (int) ($raced['retained_user_edits'] ?? 0), 'duplicate' => true];
                    }
                    throw $exception;
                }
                return ['plan' => $saved, 'retained_user_edits' => $merge['retained_user_edits'], 'duplicate' => false];
            }

            $saved = null;
            $duplicate = false;
            $this->storage->updateBatch(self::COLLECTION, [$id], function (array $current) use (
                $userId, $expectedRevision, $requestId, $requestHash, $record, &$saved, &$duplicate
            ): array {
                if (($current['user_id'] ?? null) !== $userId) {
                    throw new RuntimeException('This calendar is unavailable to the current learner.');
                }
                if (($current['last_request_id'] ?? null) === $requestId) {
                    if (($current['last_request_hash'] ?? null) !== $requestHash) {
                        throw new InvalidArgumentException('This calendar request was already used with different settings.');
                    }
                    $saved = $current;
                    $duplicate = true;
                    return $current;
                }
                if ((int) ($current['revision'] ?? 0) !== $expectedRevision) {
                    throw new InvalidArgumentException('Your study calendar changed in another request. Reload it before saving these settings.');
                }
                $saved = $record;
                return $record;
            });
            if (!is_array($saved)) throw new RuntimeException('The study calendar could not be saved.');
            return [
                'plan' => $saved,
                'retained_user_edits' => (int) ($saved['retained_user_edits'] ?? 0),
                'duplicate' => $duplicate,
            ];
        });
    }

    /** @return array<string,mixed> */
    public function updateTask(array $input, string $today): array
    {
        $taskId = is_scalar($input['task_id'] ?? null) ? trim((string) $input['task_id']) : '';
        if (preg_match('/\Atask-[a-f0-9]{24}\z/', $taskId) !== 1) {
            throw new InvalidArgumentException('Choose a valid study session.');
        }
        $operation = is_scalar($input['operation'] ?? null) ? strtolower(trim((string) $input['operation'])) : '';
        if (!in_array($operation, ['complete', 'skip', 'defer', 'reschedule'], true)) {
            throw new InvalidArgumentException('Choose complete, skip, defer, or reschedule.');
        }
        $requestId = self::requestId($input['request_id'] ?? null);
        $expectedRevision = self::revision($input['expected_revision'] ?? null);
        $scheduledDate = is_scalar($input['scheduled_date'] ?? null) ? trim((string) $input['scheduled_date']) : '';
        $requestHash = self::hash([
            'operation' => $operation,
            'scheduled_date' => $scheduledDate,
            'task_id' => $taskId,
        ]);
        $userId = $this->learnerId;

        return AccountLifecycleLock::withWritableLearner($userId, function () use (
            $userId, $taskId, $operation, $requestId, $expectedRevision, $scheduledDate, $requestHash, $today
        ): array {
            $id = self::recordId($userId);
            $saved = null;
            $this->storage->updateBatch(self::COLLECTION, [$id], function (array $record) use (
                $userId, $taskId, $operation, $requestId, $expectedRevision, $scheduledDate, $requestHash, $today, &$saved
            ): array {
                if (($record['user_id'] ?? null) !== $userId) {
                    throw new RuntimeException('This calendar is unavailable to the current learner.');
                }
                if (($record['last_request_id'] ?? null) === $requestId) {
                    if (($record['last_request_hash'] ?? null) !== $requestHash) {
                        throw new InvalidArgumentException('This session request was already used for a different action.');
                    }
                    $saved = $record;
                    return $record;
                }
                if ((int) ($record['revision'] ?? 0) !== $expectedRevision) {
                    throw new InvalidArgumentException('Your study calendar changed in another request. Reload it before updating a session.');
                }

                $configuration = is_array($record['configuration'] ?? null) ? $record['configuration'] : [];
                $sessions = is_array($record['sessions'] ?? null) ? $record['sessions'] : [];
                $found = false;
                foreach ($sessions as $index => $session) {
                    if (!is_array($session) || ($session['task_id'] ?? null) !== $taskId) continue;
                    $found = true;
                    $status = strtolower((string) ($session['status'] ?? 'planned'));
                    if (!in_array($status, ['planned', 'deferred'], true)) {
                        throw new InvalidArgumentException('This session is already complete or skipped.');
                    }
                    if ($operation === 'complete' || $operation === 'skip') {
                        $sessions[$index]['status'] = $operation === 'complete' ? 'completed' : 'skipped';
                        $sessions[$index]['completed_at'] = $operation === 'complete' ? gmdate(DATE_ATOM) : null;
                        $sessions[$index]['user_modified'] = true;
                    } else {
                        if (!StudyCalendarPlanner::validScheduledDate($scheduledDate, $configuration, $today)) {
                            throw new InvalidArgumentException('Choose a future available weekday that is not marked as a rest day.');
                        }
                        if ($scheduledDate === (string) ($session['scheduled_date'] ?? '')) {
                            throw new InvalidArgumentException('Choose a different date for a deferred or rescheduled session.');
                        }
                        if ($operation === 'defer' && $scheduledDate <= (string) ($session['scheduled_date'] ?? '')) {
                            throw new InvalidArgumentException('A deferred session must move to a later date.');
                        }
                        foreach ($sessions as $otherIndex => $other) {
                            if ($otherIndex === $index || !is_array($other)
                                || in_array(($other['status'] ?? ''), ['completed', 'skipped'], true)) continue;
                            if (($other['scheduled_date'] ?? null) === $scheduledDate) {
                                throw new InvalidArgumentException('Another active study session is already scheduled for that day.');
                            }
                        }
                        $sessions[$index]['scheduled_date'] = $scheduledDate;
                        $sessions[$index]['status'] = $operation === 'defer' ? 'deferred' : 'planned';
                        $sessions[$index]['user_modified'] = true;
                    }
                    break;
                }
                if (!$found) throw new InvalidArgumentException('That study session is no longer in this calendar.');

                usort($sessions, self::sessionOrder(...));
                $updated = array_merge($record, [
                    'sessions' => $sessions,
                    'revision' => $expectedRevision + 1,
                    'last_request_id' => $requestId,
                    'last_request_hash' => $requestHash,
                    'updated_at' => gmdate(DATE_ATOM),
                ]);
                $saved = $updated;
                return $updated;
            });
            if (!is_array($saved)) throw new InvalidArgumentException('Create a study calendar before editing a session.');
            return $saved;
        });
    }

    /** @param list<array<string,mixed>> $previous @param list<array<string,mixed>> $generated
     * @return array{sessions:list<array<string,mixed>>,retained_user_edits:int}
     */
    private static function mergeGeneratedSessions(array $previous, array $generated, string $today): array
    {
        $history = [];
        $edited = [];
        foreach ($previous as $session) {
            if (!is_array($session) || preg_match('/\Atask-[a-f0-9]{24}\z/', (string) ($session['task_id'] ?? '')) !== 1) continue;
            $status = (string) ($session['status'] ?? 'planned');
            if (in_array($status, ['completed', 'skipped'], true)) {
                $history[] = $session;
            } elseif (($session['user_modified'] ?? false) === true
                && in_array($status, ['planned', 'deferred'], true)
                && is_string($session['scheduled_date'] ?? null)
                && $session['scheduled_date'] >= $today) {
                $edited[] = $session;
            }
        }
        usort($history, static fn(array $a, array $b): int => strcmp(
            (string) ($b['completed_at'] ?? $b['scheduled_date'] ?? ''),
            (string) ($a['completed_at'] ?? $a['scheduled_date'] ?? '')
        ));
        $history = array_slice($history, 0, StudyCalendarPlanner::MAX_COMPLETED_HISTORY);
        usort($edited, self::sessionOrder(...));

        $occupiedDates = [];
        foreach ([...$history, ...$edited] as $session) {
            if (is_string($session['scheduled_date'] ?? null) && $session['scheduled_date'] >= $today) {
                $occupiedDates[$session['scheduled_date']] = true;
            }
        }
        $future = [];
        foreach ($generated as $session) {
            if (!is_array($session) || isset($occupiedDates[$session['scheduled_date'] ?? ''])) continue;
            $occupiedDates[$session['scheduled_date']] = true;
            $future[] = $session;
            if (count($future) >= self::MAX_ACTIVE_TASKS - count($edited)) break;
        }
        $sessions = [...$history, ...$edited, ...$future];
        usort($sessions, self::sessionOrder(...));
        return ['sessions' => $sessions, 'retained_user_edits' => count($edited)];
    }

    private static function sessionOrder(array $left, array $right): int
    {
        $date = strcmp((string) ($left['scheduled_date'] ?? ''), (string) ($right['scheduled_date'] ?? ''));
        if ($date !== 0) return $date;
        return strcmp((string) ($left['task_id'] ?? ''), (string) ($right['task_id'] ?? ''));
    }

    private static function recordId(string $userId): string
    {
        return 'study-plan-' . substr(hash('sha256', $userId), 0, 32);
    }

    private static function requestId(mixed $value): string
    {
        $id = is_scalar($value) ? trim((string) $value) : '';
        if (preg_match('/\A[a-f0-9]{32}\z/', $id) !== 1) {
            throw new InvalidArgumentException('This study calendar form is out of date. Reload it and try again.');
        }
        return $id;
    }

    private static function revision(mixed $value): int
    {
        $revision = is_scalar($value) ? filter_var((string) $value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]) : false;
        if ($revision === false) throw new InvalidArgumentException('This study calendar form is out of date. Reload it and try again.');
        return $revision;
    }

    private static function hash(array $value): string
    {
        return hash('sha256', json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }
}
