<?php

declare(strict_types=1);

namespace App\Services\Acceptance;

use App\Core\Environment;
use JsonException;
use RuntimeException;

final class AcceptanceSessionStore
{
    public const RESULTS = ['PASS', 'FAIL', 'BLOCKED', 'NOT_APPLICABLE', 'NOT_RUN'];
    public const CATEGORIES = [
        'BUG', 'MISSING_FEATURE', 'IMPROVEMENT', 'CONFUSING_UX', 'CONTENT_ISSUE',
        'ACCESSIBILITY', 'PERFORMANCE', 'OTHER',
    ];
    public const PRIORITIES = ['LOW', 'MEDIUM', 'HIGH', 'CRITICAL', 'UNSURE'];

    private string $directory;
    private string $ownersDirectory;
    private string $sessionsDirectory;
    private string $root;

    public function __construct(?string $directory = null, ?string $root = null)
    {
        $this->root = realpath($root ?? dirname(__DIR__, 3)) ?: ($root ?? dirname(__DIR__, 3));
        $directory ??= trim((string) Environment::get('BOARDPREP_ACCEPTANCE_STORAGE_PATH', ''));
        if ($directory === '' || $directory[0] !== DIRECTORY_SEPARATOR) {
            throw new RuntimeException('Acceptance Center private storage is not configured with an absolute path.');
        }
        $prospective = $this->prospectivePath($directory);
        if ($this->isInsideRepository($prospective)) {
            throw new RuntimeException('Acceptance Center storage must be outside the application repository.');
        }
        if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('Unable to create private Acceptance Center storage.');
        }
        @chmod($directory, 0700);
        $resolved = realpath($directory);
        if ($resolved === false || $this->isInsideRepository($resolved)) {
            throw new RuntimeException('Acceptance Center storage must be outside the application repository.');
        }
        $this->directory = $resolved;
        $this->ownersDirectory = $resolved . '/owners';
        $this->sessionsDirectory = $resolved . '/sessions';
        foreach ([$this->ownersDirectory, $this->sessionsDirectory] as $privateDirectory) {
            if (!is_dir($privateDirectory) && !@mkdir($privateDirectory, 0700, true) && !is_dir($privateDirectory)) {
                throw new RuntimeException('Unable to initialize private Acceptance Center storage.');
            }
            @chmod($privateDirectory, 0700);
        }
    }

    /** @return array{csrf_token:string,session_id:?string,sessions:list<array<string,mixed>>} */
    public function ownerOverview(string $ownerHash): array
    {
        $index = $this->mutateOwner($ownerHash, static fn(array $current): array => [$current, $current]);
        $sessions = [];
        foreach (array_keys($index['sessions'] ?? []) as $sessionId) {
            $record = $this->readSessionFile((string) $sessionId);
            if ($record === null) {
                continue;
            }
            $sessions[] = [
                'session_id' => $record['session_id'],
                'created_at' => $record['created_at'],
                'updated_at' => $record['updated_at'],
                'build_sha' => $record['build_sha'],
                'tests_recorded' => count(array_filter(
                    (array) $record['cases'],
                    static fn(array $case): bool => ($case['result'] ?? 'NOT_RUN') !== 'NOT_RUN'
                )),
                'current' => ($index['current_session_id'] ?? null) === $sessionId,
            ];
        }
        usort($sessions, static fn(array $left, array $right): int => strcmp((string) $right['updated_at'], (string) $left['updated_at']));
        return [
            'csrf_token' => (string) $index['csrf_token'],
            'session_id' => isset($index['current_session_id']) ? (string) $index['current_session_id'] : null,
            'sessions' => $sessions,
        ];
    }

    /** @return array<string,mixed> */
    public function createSession(string $ownerHash, string $buildSha): array
    {
        if (preg_match('/^[a-f0-9]{40}$/', $buildSha) !== 1) {
            throw new RuntimeException('The current committed build SHA is unavailable.');
        }
        $cases = [];
        foreach (AcceptanceScenarioCatalog::all($this->root) as $scenario) {
            $cases[$scenario['case_id']] = [
                'result' => 'NOT_RUN',
                'pass_confirmed' => false,
                'tested_routes' => [],
                'observations' => [],
                'draft' => self::emptyObservation(),
            ];
        }
        $sessionId = bin2hex(random_bytes(16));
        $now = gmdate('c');
        $record = [
            'schema_version' => 1,
            'session_id' => $sessionId,
            'build_sha' => strtolower($buildSha),
            'test_date' => gmdate('Y-m-d'),
            'environment' => 'local-isolated-preview',
            'tester_alias' => '',
            'device' => '',
            'operating_system' => '',
            'browser' => '',
            'viewport' => '',
            'active_case' => 'MVP-01',
            'created_at' => $now,
            'updated_at' => $now,
            'cases' => $cases,
            'general_feedback' => [],
            'general_draft' => self::emptyObservation(),
        ];

        $this->mutateOwner($ownerHash, function (array $index) use ($sessionId, $record): array {
            if (count((array) ($index['sessions'] ?? [])) >= 200) {
                throw new RuntimeException('This browser already has 200 saved test sessions. Export an earlier report before creating another.');
            }
            $this->writeSessionFile($sessionId, $record);
            $index['sessions'][$sessionId] = $record['created_at'];
            $index['current_session_id'] = $sessionId;
            return [$index, $index];
        });

        return $record;
    }

    public function selectSession(string $ownerHash, string $sessionId): void
    {
        $this->assertSessionId($sessionId);
        $this->mutateOwner($ownerHash, function (array $index) use ($sessionId): array {
            if (!isset($index['sessions'][$sessionId]) || $this->readSessionFile($sessionId) === null) {
                throw new RuntimeException('That saved test session is not available in this browser.');
            }
            $index['current_session_id'] = $sessionId;
            return [$index, null];
        });
    }

    /** @return array<string,mixed> */
    public function getSession(string $ownerHash, string $sessionId): array
    {
        $this->assertSessionId($sessionId);
        $index = $this->mutateOwner($ownerHash, static fn(array $current): array => [$current, $current]);
        if (!isset($index['sessions'][$sessionId])) {
            throw new RuntimeException('That saved test session is not available in this browser.');
        }
        $record = $this->readSessionFile($sessionId);
        if ($record === null || ($record['session_id'] ?? '') !== $sessionId) {
            throw new RuntimeException('The saved test session could not be read.');
        }
        return $record;
    }

    /** @param array<string,mixed> $metadata */
    public function saveMetadata(string $ownerHash, string $sessionId, array $metadata): array
    {
        $allowed = [
            'tester_alias' => 100,
            'device' => 180,
            'operating_system' => 120,
            'browser' => 180,
            'viewport' => 32,
        ];
        $clean = [];
        foreach ($allowed as $field => $limit) {
            $value = $metadata[$field] ?? '';
            if (!is_string($value) || strlen($value) > $limit) {
                throw new RuntimeException('One of the session details is too long or invalid.');
            }
            $clean[$field] = $value;
        }
        return $this->updateSession($ownerHash, $sessionId, static function (array $record) use ($clean): array {
            foreach ($clean as $field => $value) {
                $record[$field] = $value;
            }
            return $record;
        });
    }

    /** @param array<string,mixed> $draft */
    public function saveDraft(string $ownerHash, string $sessionId, string $caseId, array $draft): array
    {
        $this->assertCaseId($caseId);
        $clean = self::cleanObservation($draft);
        return $this->updateSession($ownerHash, $sessionId, static function (array $record) use ($caseId, $clean): array {
            $record['cases'][$caseId]['draft'] = $clean;
            $record['active_case'] = $caseId;
            return $record;
        });
    }

    public function saveGeneralDraft(string $ownerHash, string $sessionId, array $draft): array
    {
        $clean = self::cleanObservation($draft);
        return $this->updateSession($ownerHash, $sessionId, static function (array $record) use ($clean): array {
            $record['general_draft'] = $clean;
            return $record;
        });
    }

    public function setOutcome(string $ownerHash, string $sessionId, string $caseId, string $result, bool $passConfirmed): array
    {
        $this->assertCaseId($caseId);
        if (!in_array($result, self::RESULTS, true)) {
            throw new RuntimeException('Choose one of the listed test outcomes.');
        }
        if ($result === 'PASS' && !$passConfirmed) {
            throw new RuntimeException('Confirm that you performed the test and personally observed the success conditions before recording PASS.');
        }
        return $this->updateSession($ownerHash, $sessionId, static function (array $record) use ($caseId, $result, $passConfirmed): array {
            $record['cases'][$caseId]['result'] = $result;
            $record['cases'][$caseId]['pass_confirmed'] = $result === 'PASS' && $passConfirmed;
            $record['active_case'] = $caseId;
            return $record;
        });
    }

    public function setActiveCase(string $ownerHash, string $sessionId, string $caseId): array
    {
        $this->assertCaseId($caseId);
        return $this->updateSession($ownerHash, $sessionId, static function (array $record) use ($caseId): array {
            $record['active_case'] = $caseId;
            return $record;
        });
    }

    /** @param array<string,mixed> $observation */
    public function addObservation(string $ownerHash, string $sessionId, string $caseId, array $observation): array
    {
        $this->assertCaseId($caseId);
        $clean = self::cleanObservation($observation);
        self::assertObservationHasContent($clean);
        return $this->updateSession($ownerHash, $sessionId, static function (array $record) use ($caseId, $clean): array {
            if (count($record['cases'][$caseId]['observations']) >= 100) {
                throw new RuntimeException('This test has reached 100 saved observations.');
            }
            $clean['observation_id'] = bin2hex(random_bytes(12));
            $clean['created_at'] = gmdate('c');
            $record['cases'][$caseId]['observations'][] = $clean;
            $record['cases'][$caseId]['draft'] = self::emptyObservation();
            $record['active_case'] = $caseId;
            return $record;
        });
    }

    public function addGeneralFeedback(string $ownerHash, string $sessionId, array $observation): array
    {
        $clean = self::cleanObservation($observation);
        self::assertObservationHasContent($clean);
        return $this->updateSession($ownerHash, $sessionId, static function (array $record) use ($clean): array {
            if (count($record['general_feedback']) >= 500) {
                throw new RuntimeException('This session has reached 500 general observations.');
            }
            $clean['observation_id'] = bin2hex(random_bytes(12));
            $clean['created_at'] = gmdate('c');
            $record['general_feedback'][] = $clean;
            $record['general_draft'] = self::emptyObservation();
            return $record;
        });
    }

    /** @param array<string,mixed> $observation */
    public function editObservation(string $ownerHash, string $sessionId, string $caseId, string $observationId, array $observation): array
    {
        $this->assertCaseId($caseId);
        $this->assertObservationId($observationId);
        $clean = self::cleanObservation($observation);
        self::assertObservationHasContent($clean);
        return $this->updateSession($ownerHash, $sessionId, static function (array $record) use ($caseId, $observationId, $clean): array {
            $record['cases'][$caseId]['observations'] = self::editObservationList(
                $record['cases'][$caseId]['observations'],
                $observationId,
                $clean
            );
            return $record;
        });
    }

    public function editGeneralFeedback(string $ownerHash, string $sessionId, string $observationId, array $observation): array
    {
        $this->assertObservationId($observationId);
        $clean = self::cleanObservation($observation);
        self::assertObservationHasContent($clean);
        return $this->updateSession($ownerHash, $sessionId, static function (array $record) use ($observationId, $clean): array {
            $record['general_feedback'] = self::editObservationList($record['general_feedback'], $observationId, $clean);
            return $record;
        });
    }

    public function deleteObservation(string $ownerHash, string $sessionId, string $caseId, string $observationId): array
    {
        $this->assertCaseId($caseId);
        $this->assertObservationId($observationId);
        return $this->updateSession($ownerHash, $sessionId, static function (array $record) use ($caseId, $observationId): array {
            $record['cases'][$caseId]['observations'] = self::deleteObservationList($record['cases'][$caseId]['observations'], $observationId);
            return $record;
        });
    }

    public function deleteGeneralFeedback(string $ownerHash, string $sessionId, string $observationId): array
    {
        $this->assertObservationId($observationId);
        return $this->updateSession($ownerHash, $sessionId, static function (array $record) use ($observationId): array {
            $record['general_feedback'] = self::deleteObservationList($record['general_feedback'], $observationId);
            return $record;
        });
    }

    public function moveObservation(string $ownerHash, string $sessionId, string $caseId, string $observationId, string $direction): array
    {
        $this->assertCaseId($caseId);
        $this->assertObservationId($observationId);
        if (!in_array($direction, ['up', 'down'], true)) {
            throw new RuntimeException('Choose a valid observation order.');
        }
        return $this->updateSession($ownerHash, $sessionId, static function (array $record) use ($caseId, $observationId, $direction): array {
            $record['cases'][$caseId]['observations'] = self::moveObservationList(
                $record['cases'][$caseId]['observations'],
                $observationId,
                $direction
            );
            return $record;
        });
    }

    public function moveGeneralFeedback(string $ownerHash, string $sessionId, string $observationId, string $direction): array
    {
        $this->assertObservationId($observationId);
        if (!in_array($direction, ['up', 'down'], true)) {
            throw new RuntimeException('Choose a valid observation order.');
        }
        return $this->updateSession($ownerHash, $sessionId, static function (array $record) use ($observationId, $direction): array {
            $record['general_feedback'] = self::moveObservationList($record['general_feedback'], $observationId, $direction);
            return $record;
        });
    }

    /** @return array<string,mixed> */
    public function recordLaunch(string $ownerHash, string $sessionId, string $caseId, string $path): array
    {
        $scenario = AcceptanceScenarioCatalog::find($caseId, $this->root);
        $allowedPaths = array_column((array) ($scenario['routes'] ?? []), 'path');
        if ($scenario === null || !in_array($path, $allowedPaths, true)) {
            throw new RuntimeException('That route is not part of this test guide.');
        }
        return $this->updateSession($ownerHash, $sessionId, static function (array $record) use ($caseId, $path): array {
            if (!in_array($path, $record['cases'][$caseId]['tested_routes'], true)) {
                $record['cases'][$caseId]['tested_routes'][] = $path;
            }
            $record['active_case'] = $caseId;
            return $record;
        });
    }

    public static function emptyObservation(): array
    {
        return [
            'category' => 'OTHER',
            'priority' => 'UNSURE',
            'comments' => '',
            'expected_behavior' => '',
            'actual_behavior' => '',
            'suggested_improvement' => '',
            'reproduction_steps' => '',
            'evidence_reference' => '',
        ];
    }

    /** @param array<string,mixed> $input */
    public static function cleanObservation(array $input): array
    {
        $limits = [
            'comments' => 20000,
            'expected_behavior' => 8000,
            'actual_behavior' => 8000,
            'suggested_improvement' => 8000,
            'reproduction_steps' => 12000,
            'evidence_reference' => 2000,
        ];
        $category = $input['category'] ?? 'OTHER';
        $priority = $input['priority'] ?? 'UNSURE';
        if (!is_string($category) || !in_array($category, self::CATEGORIES, true)
            || !is_string($priority) || !in_array($priority, self::PRIORITIES, true)) {
            throw new RuntimeException('Choose a listed feedback category and priority.');
        }
        $result = ['category' => $category, 'priority' => $priority];
        foreach ($limits as $field => $limit) {
            $value = $input[$field] ?? '';
            if (!is_string($value) || strlen($value) > $limit) {
                throw new RuntimeException('One of the feedback fields is too long or invalid.');
            }
            $result[$field] = $value;
        }
        return $result;
    }

    private static function assertObservationHasContent(array $observation): void
    {
        foreach (['comments', 'expected_behavior', 'actual_behavior', 'suggested_improvement', 'reproduction_steps', 'evidence_reference'] as $field) {
            if (trim((string) $observation[$field]) !== '') {
                return;
            }
        }
        throw new RuntimeException('Add a comment or another detail before saving this observation.');
    }

    private static function editObservationList(array $observations, string $observationId, array $clean): array
    {
        foreach ($observations as $index => $observation) {
            if (($observation['observation_id'] ?? '') === $observationId) {
                $clean['observation_id'] = $observationId;
                $clean['created_at'] = $observation['created_at'] ?? gmdate('c');
                $observations[$index] = $clean;
                return array_values($observations);
            }
        }
        throw new RuntimeException('That observation no longer exists.');
    }

    private static function deleteObservationList(array $observations, string $observationId): array
    {
        $filtered = array_values(array_filter(
            $observations,
            static fn(array $observation): bool => ($observation['observation_id'] ?? '') !== $observationId
        ));
        if (count($filtered) === count($observations)) {
            throw new RuntimeException('That observation no longer exists.');
        }
        return $filtered;
    }

    private static function moveObservationList(array $observations, string $observationId, string $direction): array
    {
        $index = null;
        foreach ($observations as $position => $observation) {
            if (($observation['observation_id'] ?? '') === $observationId) {
                $index = $position;
                break;
            }
        }
        if ($index === null) {
            throw new RuntimeException('That observation no longer exists.');
        }
        $target = $direction === 'up' ? $index - 1 : $index + 1;
        if (!isset($observations[$target])) {
            return $observations;
        }
        [$observations[$index], $observations[$target]] = [$observations[$target], $observations[$index]];
        return array_values($observations);
    }

    private function updateSession(string $ownerHash, string $sessionId, callable $mutator): array
    {
        $this->assertSessionId($sessionId);
        $index = $this->mutateOwner($ownerHash, static fn(array $current): array => [$current, $current]);
        if (!isset($index['sessions'][$sessionId])) {
            throw new RuntimeException('That saved test session is not available in this browser.');
        }
        $path = $this->sessionPath($sessionId);
        return $this->withLock($path . '.lock', function () use ($path, $sessionId, $mutator): array {
            $record = $this->readJsonFile($path);
            if (($record['session_id'] ?? '') !== $sessionId || !is_array($record['cases'] ?? null)) {
                throw new RuntimeException('The saved test session could not be read.');
            }
            $record = $mutator($record);
            $record['updated_at'] = gmdate('c');
            $this->writeJsonFile($path, $record);
            return $record;
        });
    }

    private function mutateOwner(string $ownerHash, callable $mutator): mixed
    {
        $this->assertOwnerHash($ownerHash);
        $path = $this->ownerPath($ownerHash);
        return $this->withLock($path . '.lock', function () use ($path, $mutator): mixed {
            $index = is_file($path)
                ? $this->readJsonFile($path)
                : [
                    'schema_version' => 1,
                    'csrf_token' => bin2hex(random_bytes(32)),
                    'sessions' => [],
                    'current_session_id' => null,
                ];
            if ((int) ($index['schema_version'] ?? 0) !== 1
                || !is_string($index['csrf_token'] ?? null)
                || !is_array($index['sessions'] ?? null)) {
                throw new RuntimeException('The private Acceptance Center session index is invalid.');
            }
            [$next, $result] = $mutator($index);
            $this->writeJsonFile($path, $next);
            return $result;
        });
    }

    private function withLock(string $lockPath, callable $callback): mixed
    {
        $handle = @fopen($lockPath, 'c');
        if ($handle === false || !flock($handle, LOCK_EX)) {
            if (is_resource($handle)) {
                fclose($handle);
            }
            throw new RuntimeException('Unable to lock private Acceptance Center data.');
        }
        @chmod($lockPath, 0600);
        try {
            return $callback();
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private function readSessionFile(string $sessionId): ?array
    {
        $path = $this->sessionPath($sessionId);
        if (!is_file($path)) {
            return null;
        }
        return $this->readJsonFile($path);
    }

    private function readJsonFile(string $path): array
    {
        $contents = @file_get_contents($path);
        if (!is_string($contents)) {
            throw new RuntimeException('Unable to read private Acceptance Center data.');
        }
        try {
            $value = json_decode($contents, true, 128, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new RuntimeException('Private Acceptance Center data is malformed.');
        }
        if (!is_array($value)) {
            throw new RuntimeException('Private Acceptance Center data has an invalid structure.');
        }
        return $value;
    }

    private function writeSessionFile(string $sessionId, array $record): void
    {
        $this->assertSessionId($sessionId);
        $path = $this->sessionPath($sessionId);
        $this->withLock($path . '.lock', function () use ($path, $record): void {
            $this->writeJsonFile($path, $record);
        });
    }

    private function writeJsonFile(string $path, array $data): void
    {
        try {
            $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
        } catch (JsonException) {
            throw new RuntimeException('Unable to encode private Acceptance Center data.');
        }
        $temporary = $path . '.' . bin2hex(random_bytes(6)) . '.tmp';
        if (file_put_contents($temporary, $json, LOCK_EX) !== strlen($json)) {
            @unlink($temporary);
            throw new RuntimeException('Unable to save private Acceptance Center data.');
        }
        @chmod($temporary, 0600);
        if (!@rename($temporary, $path)) {
            @unlink($temporary);
            throw new RuntimeException('Unable to finalize private Acceptance Center data.');
        }
        @chmod($path, 0600);
    }

    private function ownerPath(string $ownerHash): string
    {
        $this->assertOwnerHash($ownerHash);
        return $this->ownersDirectory . '/' . $ownerHash . '.json';
    }

    private function sessionPath(string $sessionId): string
    {
        $this->assertSessionId($sessionId);
        return $this->sessionsDirectory . '/' . $sessionId . '.json';
    }

    private function assertOwnerHash(string $ownerHash): void
    {
        if (preg_match('/^[a-f0-9]{64}$/', $ownerHash) !== 1) {
            throw new RuntimeException('The local tester identity is invalid.');
        }
    }

    private function assertSessionId(string $sessionId): void
    {
        if (preg_match('/^[a-f0-9]{32}$/', $sessionId) !== 1) {
            throw new RuntimeException('The test session ID is invalid.');
        }
    }

    private function assertCaseId(string $caseId): void
    {
        if (AcceptanceScenarioCatalog::find($caseId, $this->root) === null) {
            throw new RuntimeException('That case is not in the canonical MVP acceptance list.');
        }
    }

    private function assertObservationId(string $observationId): void
    {
        if (preg_match('/^[a-f0-9]{24}$/', $observationId) !== 1) {
            throw new RuntimeException('The observation ID is invalid.');
        }
    }

    private function prospectivePath(string $directory): string
    {
        $probe = rtrim($directory, DIRECTORY_SEPARATOR);
        $missing = [];
        while ($probe !== '' && !file_exists($probe)) {
            $part = basename($probe);
            if ($part === '' || $part === '.' || $part === '..') {
                break;
            }
            array_unshift($missing, $part);
            $parent = dirname($probe);
            if ($parent === $probe) {
                break;
            }
            $probe = $parent;
        }
        $resolvedParent = realpath($probe);
        if ($resolvedParent === false) {
            throw new RuntimeException('Unable to resolve the private Acceptance Center storage location.');
        }
        $candidate = $resolvedParent;
        if ($missing !== []) {
            $candidate .= DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $missing);
        }
        $normalized = realpath($candidate);
        return $normalized !== false ? $normalized : $candidate;
    }

    private function isInsideRepository(string $candidate): bool
    {
        return $candidate === $this->root
            || str_starts_with(rtrim($candidate, DIRECTORY_SEPARATOR), rtrim($this->root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR);
    }
}
