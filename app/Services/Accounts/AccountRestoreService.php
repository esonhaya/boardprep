<?php

declare(strict_types=1);

namespace App\Services\Accounts;

use JsonException;
use RuntimeException;
use Throwable;

/** Restores only into a new, private JSON destination using staged directory commit. */
final class AccountRestoreService
{
    private const COLLECTIONS = [
        'quiz_attempts' => 'attempts',
        'exam_drafts' => 'exam-drafts',
        'weakness_summaries' => 'weakness',
        'flashcard_reviews' => 'flashcard_state',
        'learner_notes' => 'learner_notes',
        'question_issue_reports' => 'learner-question-reports',
        'study_calendar' => 'learner-study-plans',
    ];

    /** @param null|callable(string):void $afterFile Test-only interruption point. */
    public function __construct(private $afterFile = null)
    {
    }

    /** @return array{dry_run:bool,destination:string,record_counts:array<string,int>,schema_version:int} */
    public function restore(string $archivePath, string $destination, string $passphrase, bool $dryRun, bool $explicitIsolatedRestore = false): array
    {
        if (!$dryRun && !$explicitIsolatedRestore) {
            throw new RuntimeException('RESTORE_CONFIRMATION_REQUIRED: isolated restore requires explicit operator confirmation.');
        }
        $archive = AccountBackupService::readPrivateFile($archivePath);
        $payload = AccountBackupCrypto::decrypt($archive, $passphrase);
        self::validatePayload($payload);
        $destination = self::validateDestination($destination);
        return AccountLifecycleLock::withLock(function () use ($payload, $destination, $dryRun): array {
            return $this->restoreValidatedPayload($payload, $destination, $dryRun);
        });
    }

    /** @param array<string,mixed> $payload @return array{dry_run:bool,destination:string,record_counts:array<string,int>,schema_version:int} */
    private function restoreValidatedPayload(array $payload, string $destination, bool $dryRun): array
    {
        $existing = (new AccountRepository())->findById((string) $payload['account']['account_id']);
        if (is_array($existing) && ($existing['status'] ?? null) === 'deletion_completed') {
            throw new RuntimeException('RESTORE_DELETED_ACCOUNT: a completed account deletion permanently blocks restoration of these credentials.');
        }
        $report = [
            'dry_run' => $dryRun,
            'destination' => $destination,
            'record_counts' => $payload['manifest']['record_counts'],
            'schema_version' => (int) $payload['payload_schema_version'],
        ];
        if ($dryRun) {
            return $report;
        }

        $lockPath = dirname($destination) . DIRECTORY_SEPARATOR . '.' . basename($destination) . '.boardprep-restore.lock';
        if (is_link($lockPath)) {
            throw new RuntimeException('RESTORE_PATH_UNSAFE: destination lock is an unsafe symbolic link.');
        }
        $lock = fopen($lockPath, 'c+b');
        if ($lock === false) {
            throw new RuntimeException('RESTORE_LOCK_FAILED: private destination lock could not be opened.');
        }
        @chmod($lockPath, 0600);
        $lockPermissions = @fileperms($lockPath);
        if (!is_int($lockPermissions) || ($lockPermissions & 0077) !== 0) {
            fclose($lock);
            throw new RuntimeException('RESTORE_PERMISSIONS_UNSAFE: destination lock is not private.');
        }

        $stage = dirname($destination) . DIRECTORY_SEPARATOR . '.boardprep-restore-' . bin2hex(random_bytes(12));
        $createdDirectories = [];
        $createdFiles = [];
        try {
            if (!flock($lock, LOCK_EX)) {
                throw new RuntimeException('RESTORE_LOCK_FAILED: private destination is busy.');
            }
            if (file_exists($destination) || is_link($destination)) {
                throw new RuntimeException('RESTORE_DESTINATION_CONFLICT: destination already exists and was not changed.');
            }
            if (!mkdir($stage, 0700)) {
                throw new RuntimeException('RESTORE_STAGE_FAILED: private staging directory could not be created.');
            }
            $createdDirectories[] = $stage;
            $privateDirectory = $stage . DIRECTORY_SEPARATOR . 'private';
            $accountDirectory = $privateDirectory . DIRECTORY_SEPARATOR . 'learner-accounts';
            if (!mkdir($privateDirectory, 0700)) {
                throw new RuntimeException('RESTORE_STAGE_FAILED: private credential directories could not be created.');
            }
            $createdDirectories[] = $privateDirectory;
            if (!mkdir($accountDirectory, 0700)) {
                throw new RuntimeException('RESTORE_STAGE_FAILED: private credential directories could not be created.');
            }
            $createdDirectories[] = $accountDirectory;

            $username = (string) $payload['account']['username'];
            self::writeJson($accountDirectory . DIRECTORY_SEPARATOR . 'accounts.json', [
                'schema_version' => 1,
                'accounts' => [$username => $payload['account']],
                'rate_limits' => [],
            ], $createdFiles);
            $this->interruptAfter('credential_profile');

            foreach (self::COLLECTIONS as $exportName => $storageName) {
                if (!array_key_exists($exportName, $payload['collections'])) {
                    continue;
                }
                $records = $payload['collections'][$exportName];
                if ($exportName === 'question_issue_reports') {
                    foreach ($records as &$record) {
                        $record = self::resetQuestionReportForRestoredCopy($record);
                    }
                    unset($record);
                }
                if ($exportName === 'exam_drafts') {
                    foreach ($records as &$record) {
                        // A restored draft must be claimed again by an authenticated session.
                        $record['active_quiz_session_id'] = '';
                    }
                    unset($record);
                }
                self::writeJson($stage . DIRECTORY_SEPARATOR . $storageName . '.json', $records, $createdFiles);
                $this->interruptAfter($exportName);
            }
            self::writeJson($stage . DIRECTORY_SEPARATOR . 'restore-report.json', [
                'format' => 'boardprep-account-restore-report-v1',
                'restored_at' => gmdate(DATE_ATOM),
                'schema_version' => (int) $payload['payload_schema_version'],
                'record_counts' => $payload['manifest']['record_counts'],
                'mode' => 'new isolated private JSON destination; not activated',
            ], $createdFiles);
            $this->interruptAfter('restore_report');

            if (file_exists($destination) || is_link($destination) || !rename($stage, $destination)) {
                throw new RuntimeException('RESTORE_COMMIT_FAILED: destination changed or atomic commit failed; source state is unchanged.');
            }
            $createdDirectories = [];
            $createdFiles = [];
            $report['dry_run'] = false;
            return $report;
        } catch (Throwable $exception) {
            self::removeStaging($createdFiles, $createdDirectories);
            throw $exception;
        } finally {
            @flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** @param array<string,mixed> $payload */
    public static function validatePayload(array $payload): void
    {
        if (array_diff(['payload_schema_version', 'manifest', 'account', 'collections'], array_keys($payload)) !== []
            || count($payload) !== 4
            || ($payload['payload_schema_version'] ?? null) !== 1
            || !is_array($payload['manifest'] ?? null)
            || !is_array($payload['account'] ?? null)
            || !is_array($payload['collections'] ?? null)) {
            throw new RuntimeException('RESTORE_SCHEMA_UNSUPPORTED: required account backup collections or schema are missing.');
        }

        $manifest = $payload['manifest'];
        $legacyNames = ['quiz_attempts', 'weakness_summaries', 'flashcard_reviews'];
        $previousCurrentNames = [...$legacyNames, 'learner_notes'];
        $previousLatestNames = [...$previousCurrentNames, 'question_issue_reports'];
        $previousCurrentWithCalendarNames = [...$previousLatestNames, 'study_calendar'];
        $currentNames = [
            'quiz_attempts',
            'exam_drafts',
            ...array_slice($previousCurrentWithCalendarNames, 1),
        ];
        $collectionNames = is_array($payload['collections'] ?? null) ? array_keys($payload['collections']) : [];
        $payloadNames = $collectionNames === $legacyNames ? $legacyNames
            : ($collectionNames === $previousCurrentNames ? $previousCurrentNames
                    : ($collectionNames === $previousLatestNames ? $previousLatestNames
                    : ($collectionNames === $previousCurrentWithCalendarNames ? $previousCurrentWithCalendarNames
                        : ($collectionNames === $currentNames ? $currentNames : []))));
        $expectedNames = [...$payloadNames, 'credential_profile'];
        if (($manifest['format'] ?? null) !== 'boardprep-account-backup-payload-v1'
            || !is_string($manifest['created_at'] ?? null)
            || ($manifest['included_collections'] ?? null) !== $expectedNames
            || !is_array($manifest['record_counts'] ?? null)
            || !is_array($manifest['collection_schema_versions'] ?? null)
            || $payloadNames === []
            || $manifest['collection_schema_versions'] !== array_fill_keys($expectedNames, 1)) {
            throw new RuntimeException('RESTORE_SCHEMA_UNSUPPORTED: encrypted backup manifest is incomplete or unsupported.');
        }
        $account = $payload['account'];
        $expectedAccountFields = [
            'account_id', 'learner_id', 'username', 'password_hash', 'created_at', 'updated_at',
            'auth_version', 'status', 'preferences', 'recovery_code_hashes',
        ];
        if (array_diff($expectedAccountFields, array_keys($account)) !== []
            || count($account) !== count($expectedAccountFields)
            || preg_match('/\Aaccount-[a-f0-9]{32}\z/', (string) ($account['account_id'] ?? '')) !== 1
            || preg_match('/\Aaccount-learner-[a-f0-9]{32}\z/', (string) ($account['learner_id'] ?? '')) !== 1
            || AccountService::normalizeUsername((string) ($account['username'] ?? '')) !== ($account['username'] ?? null)
            || !is_string($account['password_hash'] ?? null)
            || (password_get_info((string) ($account['password_hash'] ?? ''))['algoName'] ?? 'unknown') === 'unknown'
            || ($account['status'] ?? null) !== 'active'
            || !is_int($account['auth_version'] ?? null)
            || $account['auth_version'] < 1
            || !is_array($account['preferences'] ?? null)
            || !is_array($account['recovery_code_hashes'] ?? null)
            || count($account['recovery_code_hashes']) > 8) {
            throw new RuntimeException('RESTORE_CREDENTIAL_INVALID: account credentials or profile failed validation.');
        }
        $recoverySeen = [];
        foreach ($account['recovery_code_hashes'] as $hash) {
            if (!is_string($hash) || preg_match('/\A[a-f0-9]{64}\z/', $hash) !== 1 || isset($recoverySeen[$hash])) {
                throw new RuntimeException('RESTORE_CREDENTIAL_INVALID: recovery-code digests failed validation.');
            }
            $recoverySeen[$hash] = true;
        }

        if ($payloadNames === [] || array_keys($payload['collections']) !== $payloadNames) {
            throw new RuntimeException('RESTORE_SCHEMA_UNSUPPORTED: required learner data collections are missing or unexpected.');
        }
        foreach (array_intersect_key(self::COLLECTIONS, $payload['collections']) as $exportName => $_storageName) {
            $records = $payload['collections'][$exportName];
            if (!is_array($records) || !array_is_list($records)) {
                throw new RuntimeException('RESTORE_SCHEMA_UNSUPPORTED: a learner collection is not a record list.');
            }
            if (($manifest['record_counts'][$exportName] ?? null) !== count($records)) {
                throw new RuntimeException('RESTORE_MANIFEST_MISMATCH: collection count differs from the encrypted manifest.');
            }
            $seen = [];
            foreach ($records as $record) {
                if (!is_array($record)
                    || ($record['user_id'] ?? null) !== $account['learner_id']
                    || array_key_exists('session_id', $record)
                    || array_key_exists('active_quiz_session_id', $record)
                    || (!is_string($record['id'] ?? null) && !is_int($record['id'] ?? null))
                    || trim((string) ($record['id'] ?? '')) === ''
                    || isset($seen[(string) $record['id']])) {
                    throw new RuntimeException('RESTORE_OWNERSHIP_INVALID: learner records have mismatched or duplicate ownership references.');
                }
                if ($exportName === 'exam_drafts') {
                    self::validateExamDraftRecord($record, $account['learner_id']);
                }
                $seen[(string) $record['id']] = true;
            }
        }
        if (($manifest['record_counts']['credential_profile'] ?? null) !== 1
            || count($manifest['record_counts']) !== count($expectedNames)) {
            throw new RuntimeException('RESTORE_MANIFEST_MISMATCH: account profile count is invalid.');
        }
    }

    /** @param array<string,mixed> $record */
    private static function validateExamDraftRecord(array $record, string $learnerId): void
    {
        $session = $record['quiz_session'] ?? null;
        $questionIds = $record['question_ids'] ?? null;
        $choiceCounts = $record['choice_counts'] ?? null;
        $answers = $record['answers'] ?? null;
        $flags = $record['review_flags'] ?? null;
        $allowedRecordFields = [
            'id', 'schema_version', 'state', 'user_id', 'quiz_session', 'question_ids',
            'choice_counts', 'answers', 'current_question', 'review_flags',
            'exam_answer_revision', 'revision', 'created_at', 'updated_at',
        ];
        $allowedSessionFields = [
            'board', 'subject', 'domain', 'topics', 'mode', 'session_type', 'timer_mode',
            'time_limit_minutes', 'time_limit_seconds', 'deadline_epoch', 'time_expired',
            'let_variant', 'let_level', 'let_specialization_id', 'civil_service_level',
            'difficulty', 'requested_question_count', 'question_count', 'started_at', 'selection_notice',
        ];
        if (array_diff(array_keys($record), $allowedRecordFields) !== []
            || preg_match('/\Aexam-draft-[a-f0-9]{32}\z/', (string) ($record['id'] ?? '')) !== 1
            || ($record['schema_version'] ?? null) !== 1
            || ($record['state'] ?? null) !== 'in_progress'
            || ($record['user_id'] ?? null) !== $learnerId
            || !is_array($session) || ($session['mode'] ?? null) !== 'exam'
            || array_diff(array_keys($session), $allowedSessionFields) !== []
            || array_key_exists('answer', $record) || array_key_exists('questions', $record)
            || array_key_exists('explanations', $record)
            || !is_array($questionIds) || !array_is_list($questionIds)
            || $questionIds === [] || count($questionIds) > 150
            || !is_array($choiceCounts) || !is_array($answers) || !is_array($flags)
            || !array_is_list($flags)
            || !is_int($record['revision'] ?? null) || $record['revision'] < 0
            || !is_int($record['exam_answer_revision'] ?? null) || $record['exam_answer_revision'] < 0
            || !is_int($record['current_question'] ?? null) || $record['current_question'] < 0
            || $record['current_question'] >= count($questionIds)
            || !is_string($record['created_at'] ?? null) || strtotime($record['created_at']) === false
            || !is_string($record['updated_at'] ?? null) || strtotime($record['updated_at']) === false
            || !is_string($session['board'] ?? null) || $session['board'] === ''
            || !is_string($session['subject'] ?? null)
            || !is_array($session['topics'] ?? null) || !array_is_list($session['topics'])
            || count($session['topics']) > 30
            || !is_int($session['question_count'] ?? null)
            || $session['question_count'] !== count($questionIds)
            || !is_string($session['started_at'] ?? null) || strtotime($session['started_at']) === false
            || ($session['time_expired'] ?? false) !== false) {
            throw new RuntimeException('RESTORE_DRAFT_INVALID: an exam draft failed its safe data-shape check.');
        }
        foreach ($session['topics'] as $topic) {
            if (!is_string($topic) || strlen($topic) > 160) {
                throw new RuntimeException('RESTORE_DRAFT_INVALID: exam topic metadata is invalid.');
            }
        }
        if (($session['timer_mode'] ?? null) === 'timed') {
            $deadline = filter_var($session['deadline_epoch'] ?? null, FILTER_VALIDATE_INT);
            $seconds = filter_var($session['time_limit_seconds'] ?? null, FILTER_VALIDATE_INT);
            $minutes = filter_var($session['time_limit_minutes'] ?? null, FILTER_VALIDATE_INT);
            $startedAt = strtotime($session['started_at']);
            if ($deadline === false || $seconds === false || $minutes === false
                || $deadline < 1 || $seconds < 1 || $minutes < 1 || $minutes * 60 !== $seconds
                || abs(($startedAt + $seconds) - $deadline) > 2) {
                throw new RuntimeException('RESTORE_DRAFT_INVALID: exam deadline metadata is invalid.');
            }
        } elseif (($session['timer_mode'] ?? null) !== 'untimed'
            || ($session['deadline_epoch'] ?? null) !== null) {
            throw new RuntimeException('RESTORE_DRAFT_INVALID: exam timer metadata is invalid.');
        }
        $seenQuestions = [];
        foreach ($questionIds as $questionId) {
            if (!is_string($questionId) || trim($questionId) === '' || isset($seenQuestions[$questionId])) {
                throw new RuntimeException('RESTORE_DRAFT_INVALID: selected question identifiers are invalid.');
            }
            $seenQuestions[$questionId] = true;
            if (!is_int($choiceCounts[$questionId] ?? null)
                || $choiceCounts[$questionId] < 2 || $choiceCounts[$questionId] > 26) {
                throw new RuntimeException('RESTORE_DRAFT_INVALID: selected question choice metadata is invalid.');
            }
        }
        foreach ($answers as $questionId => $answer) {
            if ((!is_string($questionId) && !is_int($questionId)) || !isset($seenQuestions[(string) $questionId])
                || !is_string($answer) || preg_match('/\A[A-Z]\z/', $answer) !== 1
                || ord($answer) - ord('A') >= $choiceCounts[(string) $questionId]) {
                throw new RuntimeException('RESTORE_DRAFT_INVALID: learner answer data is invalid.');
            }
        }
        foreach ($flags as $index) {
            if (!is_int($index) || $index < 0 || $index >= count($questionIds)) {
                throw new RuntimeException('RESTORE_DRAFT_INVALID: review marks are invalid.');
            }
        }
        if (count(array_unique($flags)) !== count($flags)) {
            throw new RuntimeException('RESTORE_DRAFT_INVALID: duplicate review marks are invalid.');
        }
        if (array_key_exists('active_quiz_session_id', $record)) {
            throw new RuntimeException('RESTORE_DRAFT_INVALID: active session bindings are not restorable.');
        }
    }

    /** A restored report is submitted again for review in the isolated destination. */
    public static function resetQuestionReportForRestoredCopy(array $record, ?string $timestamp = null): array
    {
        $timestamp = $timestamp !== null && trim($timestamp) !== '' ? $timestamp : gmdate(DATE_ATOM);
        $record['status'] = 'SUBMITTED';
        $record['status_changed_at'] = $timestamp;
        $record['status_history'] = [['status' => 'SUBMITTED', 'at' => $timestamp]];
        return $record;
    }

    private static function validateDestination(string $path): string
    {
        if (!str_starts_with($path, DIRECTORY_SEPARATOR)
            || preg_match('#(?:^|/)\.\.?(/|$)#', $path) === 1
            || str_contains($path, "\0")) {
            throw new RuntimeException('RESTORE_PATH_UNSAFE: use an absolute destination path without traversal segments.');
        }
        $parent = dirname($path);
        self::rejectSymlinkComponents($parent);
        $resolvedParent = realpath($parent);
        if (!is_string($resolvedParent) || !is_dir($resolvedParent)) {
            throw new RuntimeException('RESTORE_PATH_UNSAFE: private destination parent does not exist.');
        }
        self::assertOutsideProtectedRoots($resolvedParent);
        $permissions = @fileperms($resolvedParent);
        if (!is_int($permissions) || ($permissions & 0077) !== 0 || !is_writable($resolvedParent)) {
            throw new RuntimeException('RESTORE_PERMISSIONS_UNSAFE: destination parent must be writable and owner-only.');
        }
        $target = rtrim($resolvedParent, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . basename($path);
        if (file_exists($target) || is_link($target)) {
            throw new RuntimeException('RESTORE_DESTINATION_CONFLICT: destination already exists and was not changed.');
        }
        return $target;
    }

    private static function writeJson(string $path, array $data, array &$createdFiles): void
    {
        try {
            $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
        } catch (JsonException $exception) {
            throw new RuntimeException('RESTORE_DATA_INVALID: a validated collection could not be encoded.', 0, $exception);
        }
        $handle = fopen($path, 'x');
        if ($handle === false) {
            throw new RuntimeException('RESTORE_STAGE_FAILED: private staged file could not be created.');
        }
        $createdFiles[] = $path;
        @chmod($path, 0600);
        try {
            $offset = 0;
            $length = strlen($json);
            while ($offset < $length) {
                $written = fwrite($handle, substr($json, $offset, 65_536));
                if (!is_int($written) || $written < 1) {
                    throw new RuntimeException('RESTORE_STAGE_FAILED: private staged file could not be written completely.');
                }
                $offset += $written;
            }
            if (!fflush($handle)) {
                throw new RuntimeException('RESTORE_STAGE_FAILED: staged file could not be flushed.');
            }
            if (function_exists('fsync') && !fsync($handle)) {
                throw new RuntimeException('RESTORE_STAGE_FAILED: staged file could not be synchronized.');
            }
        } finally {
            fclose($handle);
        }
        $permissions = @fileperms($path);
        if (!is_int($permissions) || ($permissions & 0077) !== 0) {
            throw new RuntimeException('RESTORE_PERMISSIONS_UNSAFE: staged file permissions are not private.');
        }
    }

    private function interruptAfter(string $name): void
    {
        if (is_callable($this->afterFile)) {
            ($this->afterFile)($name);
        }
    }

    private static function removeStaging(array $files, array $directories): void
    {
        foreach (array_reverse($files) as $file) {
            if (is_file($file) && !is_link($file)) {
                @unlink($file);
            }
        }
        foreach (array_reverse($directories) as $directory) {
            if (is_dir($directory) && !is_link($directory)) {
                @rmdir($directory);
            }
        }
    }

    private static function assertOutsideProtectedRoots(string $path): void
    {
        $repository = realpath(dirname(__DIR__, 3));
        $documentRoot = realpath(dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'public');
        foreach ([$repository, $documentRoot] as $protected) {
            if (is_string($protected)
                && ($path === $protected || str_starts_with($path, rtrim($protected, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR))) {
                throw new RuntimeException('RESTORE_PATH_UNSAFE: restore destinations must be outside the repository and web root.');
            }
        }
    }

    private static function rejectSymlinkComponents(string $path): void
    {
        $current = '';
        foreach (array_filter(explode(DIRECTORY_SEPARATOR, $path), static fn(string $part): bool => $part !== '') as $part) {
            $current = rtrim($current, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $part;
            if (is_link($current)) {
                throw new RuntimeException('RESTORE_PATH_UNSAFE: symbolic-link path components are not allowed.');
            }
        }
    }
}
