<?php

declare(strict_types=1);

namespace App\Services\Operations;

use stdClass;

final class BrowserEvidenceService
{
    public const WORKFLOW_PATH = '.github/workflows/browser-acceptance.yml';
    public const REPOSITORY = 'esonhaya/boardprep';

    /**
     * Evaluate the append-only GitHub workflow register against the assessed checkout.
     * Local Playwright output is deliberately not consulted here: ignored artifacts
     * cannot silently change a release decision.
     */
    public static function evaluate(array $registry, string $head, string $root, array $uncommittedPaths = []): array
    {
        $records = array_values(array_filter($registry['records'] ?? [], 'is_array'));
        $evaluated = [];
        foreach ($records as $record) {
            $evaluated[] = self::evaluateRecord($record, $head, $root);
        }

        $exact = array_values(array_filter($evaluated, static fn(array $item): bool => ($item['relation'] ?? '') === 'EXACT'));
        $documentationDescendants = array_values(array_filter(
            $evaluated,
            static fn(array $item): bool => ($item['relation'] ?? '') === 'DOCUMENTATION_ONLY_DESCENDANT'
        ));
        $candidates = $exact !== [] ? $exact : $documentationDescendants;
        usort($candidates, static function (array $left, array $right): int {
            $runOrder = (int) ($right['run_id'] ?? 0) <=> (int) ($left['run_id'] ?? 0);
            return $runOrder !== 0
                ? $runOrder
                : (int) ($right['run_attempt'] ?? 0) <=> (int) ($left['run_attempt'] ?? 0);
        });

        if ($candidates !== []) {
            $selected = $candidates[0];
        } elseif ($evaluated !== []) {
            usort($evaluated, static fn(array $left, array $right): int =>
                (int) ($right['run_id'] ?? 0) <=> (int) ($left['run_id'] ?? 0));
            $selected = $evaluated[0];
        } else {
            $selected = [
                'status' => 'MISSING',
                'reason' => 'No GitHub Chromium workflow run is registered.',
                'relation' => 'MISSING',
                'record' => null,
                'run_id' => null,
                'application_commit' => null,
            ];
        }

        $dirtyApplicationPaths = self::applicationAffectingPaths($uncommittedPaths, $root);
        if (($selected['status'] ?? '') === 'PASS' && $dirtyApplicationPaths !== []) {
            $selected['status'] = 'UNVERIFIED';
            $selected['relation'] = 'DIRTY_WORKTREE';
            $selected['reason'] = 'The assessed checkout has uncommitted application-affecting changes: '
                . implode(', ', array_slice($dirtyApplicationPaths, 0, 5))
                . (count($dirtyApplicationPaths) > 5 ? ', …' : '')
                . '. The registered workflow ran against committed files only.';
        }

        return $selected + [
            'records_count' => count($records),
            'uncommitted_application_paths' => $dirtyApplicationPaths,
            'registered_runs' => array_map(static fn(array $item): array => [
                'run_id' => $item['run_id'] ?? null,
                'application_commit' => $item['application_commit'] ?? null,
                'conclusion' => $item['conclusion'] ?? null,
                'status' => $item['status'] ?? 'INVALID',
                'relation' => $item['relation'] ?? 'INVALID',
            ], $evaluated),
        ];
    }

    /** Discover tracked and untracked checkout changes that affect the browser-tested product. */
    public static function applicationAffectingWorkingTreePaths(string $root): array
    {
        $tracked = self::git($root, ['diff', 'HEAD', '--name-only']);
        $untracked = self::git($root, ['ls-files', '--others', '--exclude-standard']);
        if ($tracked['exit'] !== 0 || $untracked['exit'] !== 0) {
            return ['<working tree status unavailable>'];
        }
        return self::applicationAffectingPaths(array_merge(
            explode("\n", trim($tracked['output'])),
            explode("\n", trim($untracked['output']))
        ), $root);
    }

    /** @param list<string> $paths @return list<string> */
    public static function applicationAffectingPaths(array $paths, string $root): array
    {
        $affected = [];
        foreach ($paths as $path) {
            if (!is_string($path) || $path === '' || self::isUserDataOrDocumentationPath($path)) {
                continue;
            }
            if ($path === 'storage/questions.json' && self::questionsDifferOnlyByUsage($root)) {
                continue;
            }
            $affected[] = $path;
        }
        $affected = array_values(array_unique($affected));
        sort($affected);
        return $affected;
    }

    private static function isUserDataOrDocumentationPath(string $path): bool
    {
        if (str_starts_with($path, 'docs/')
            || str_starts_with($path, 'storage/doctor/')
            || str_starts_with($path, 'storage/recovery/')
            || str_starts_with($path, 'storage/runtime/')
            || str_starts_with($path, 'public/assets/me/')
            || str_starts_with($path, '.commandcode/')) {
            return true;
        }

        return in_array($path, [
            'tools/Batch596Author.php',
            'storage/attempts.json',
            'storage/question-statistics.json',
            'storage/weakness.json',
            'storage/progress.json',
            'storage/history.json',
            'storage/flashcard-state.json',
            'storage/flashcard_state.json',
        ], true) || preg_match('/\Astorage\/doctor-[^\/]+\.json\z/', $path) === 1;
    }

    /** Learner counters can share the canonical question file; ignore those counters only. */
    private static function questionsDifferOnlyByUsage(string $root): bool
    {
        $temporary = tempnam(sys_get_temp_dir(), 'boardprep-question-baseline-');
        if (!is_string($temporary)) {
            return false;
        }
        $previousMemoryLimit = ini_get('memory_limit');
        @ini_set('memory_limit', '512M');
        try {
            $command = 'git -C ' . escapeshellarg($root)
                . ' show HEAD:storage/questions.json > ' . escapeshellarg($temporary);
            $output = [];
            exec($command, $output, $exit);
            if ($exit !== 0) {
                return false;
            }

            $baselineJson = file_get_contents($temporary);
            $baseline = $baselineJson === false
                ? null
                : json_decode($baselineJson, false, 512, JSON_THROW_ON_ERROR);
            unset($baselineJson);
            $baselineHash = self::canonicalQuestionHash($baseline);
            unset($baseline);
            gc_collect_cycles();

            $currentJson = file_get_contents($root . '/storage/questions.json');
            $current = $currentJson === false
                ? null
                : json_decode($currentJson, false, 512, JSON_THROW_ON_ERROR);
            unset($currentJson);
            $currentHash = self::canonicalQuestionHash($current);
            return $baselineHash !== null && $currentHash !== null && hash_equals($baselineHash, $currentHash);
        } catch (\Throwable) {
            return false;
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
            if (is_string($previousMemoryLimit) && $previousMemoryLimit !== '') {
                @ini_set('memory_limit', $previousMemoryLimit);
            }
        }
    }

    private static function canonicalQuestionHash(mixed $questions): ?string
    {
        if (!is_array($questions) || !array_is_list($questions)) {
            return null;
        }
        $hash = hash_init('sha256');
        foreach ($questions as $question) {
            if (!$question instanceof stdClass) {
                return null;
            }
            unset($question->timesUsed, $question->timesCorrect, $question->timesIncorrect, $question->updatedAt);
            $json = self::canonicalJson($question);
            hash_update($hash, strlen($json) . ':' . $json . "\n");
        }
        return hash_final($hash);
    }

    private static function canonicalJson(mixed $value): string
    {
        if ($value instanceof stdClass) {
            $properties = get_object_vars($value);
            ksort($properties, SORT_STRING);
            $pairs = [];
            foreach ($properties as $key => $item) {
                $pairs[] = json_encode((string) $key, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
                    . ':' . self::canonicalJson($item);
            }
            return '{' . implode(',', $pairs) . '}';
        }
        if (is_array($value)) {
            return '[' . implode(',', array_map(self::canonicalJson(...), $value)) . ']';
        }
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
    }

    /** Validate the durable attestation before treating a workflow result as evidence. */
    public static function validateRecord(array $record): array
    {
        $required = [
            'run_id', 'run_attempt', 'repository', 'workflow_id', 'workflow_path',
            'head_sha', 'run_status', 'workflow_conclusion', 'jobs', 'registered_at',
            'verification_status', 'verification_source', 'api_response_sha256', 'run_url',
        ];
        $missing = array_values(array_diff($required, array_keys($record)));
        if ($missing !== []) {
            return ['valid' => false, 'reason' => 'Workflow record is missing: ' . implode(', ', $missing) . '.'];
        }

        if ((int) $record['run_id'] < 1
            || (int) $record['run_attempt'] < 1
            || $record['repository'] !== self::REPOSITORY
            || $record['workflow_path'] !== self::WORKFLOW_PATH
            || !preg_match('/\A[0-9a-f]{40}\z/i', (string) $record['head_sha'])) {
            return ['valid' => false, 'reason' => 'Workflow identity, repository, path, or commit SHA is invalid.'];
        }

        if (($record['verification_status'] ?? '') !== 'VERIFIED'
            || ($record['verification_source'] ?? '') !== 'GITHUB_ACTIONS_API'
            || !preg_match('/\A[0-9a-f]{64}\z/i', (string) $record['api_response_sha256'])) {
            return ['valid' => false, 'reason' => 'Workflow result has no valid GitHub API verification attestation.'];
        }

        if (($record['run_status'] ?? '') !== 'completed'
            || !in_array((string) ($record['workflow_conclusion'] ?? ''), [
                'success', 'failure', 'cancelled', 'timed_out', 'action_required', 'neutral', 'stale', 'skipped',
            ], true)) {
            return ['valid' => false, 'reason' => 'Workflow has not completed with a recognized conclusion.'];
        }

        $jobs = array_values(array_filter($record['jobs'] ?? [], 'is_array'));
        if ($jobs === []) {
            return ['valid' => false, 'reason' => 'GitHub returned no workflow jobs.'];
        }
        foreach ($jobs as $job) {
            if (($job['status'] ?? '') !== 'completed'
                || !in_array((string) ($job['conclusion'] ?? ''), [
                    'success', 'failure', 'cancelled', 'timed_out', 'action_required', 'neutral', 'stale', 'skipped',
                ], true)) {
                return ['valid' => false, 'reason' => 'At least one workflow job is incomplete or has an unknown conclusion.'];
            }
        }

        if (!filter_var((string) ($record['run_url'] ?? ''), FILTER_VALIDATE_URL)
            || !str_starts_with((string) $record['run_url'], 'https://github.com/' . self::REPOSITORY . '/actions/runs/')) {
            return ['valid' => false, 'reason' => 'Workflow URL does not identify this repository.'];
        }

        $success = ($record['workflow_conclusion'] ?? '') === 'success';
        foreach ($jobs as $job) {
            $success = $success && ($job['conclusion'] ?? '') === 'success';
        }

        return [
            'valid' => true,
            'passed' => $success,
            'reason' => $success
                ? 'GitHub reports a completed successful browser workflow and every job succeeded.'
                : 'The verified browser workflow or one of its jobs did not conclude successfully.',
        ];
    }

    /** Classify the relationship between browser-tested application code and assessed HEAD. */
    public static function classifyRelation(bool $isAncestor, array $pathsSinceRun): string
    {
        if (!$isAncestor) {
            return 'MISMATCH';
        }
        foreach ($pathsSinceRun as $path) {
            if (!is_string($path) || !str_starts_with($path, 'docs/')) {
                return 'STALE';
            }
        }
        return 'DOCUMENTATION_ONLY_DESCENDANT';
    }

    private static function evaluateRecord(array $record, string $head, string $root): array
    {
        $validation = self::validateRecord($record);
        $sha = strtolower((string) ($record['head_sha'] ?? ''));
        $head = strtolower($head);
        $relation = 'MISMATCH';
        if (preg_match('/\A[0-9a-f]{40}\z/', $sha) && preg_match('/\A[0-9a-f]{40}\z/', $head)) {
            if (hash_equals($sha, $head)) {
                $relation = 'EXACT';
            } else {
                $evidenceCommit = self::git($root, ['cat-file', '-e', $sha . '^{commit}']);
                $ancestry = $evidenceCommit['exit'] === 0
                    ? self::git($root, ['merge-base', '--is-ancestor', $sha, $head])
                    : ['exit' => 128, 'output' => ''];
                if ($ancestry['exit'] === 0) {
                    $paths = self::changedPathsBetween($root, $sha, $head);
                    $relation = self::classifyRelation(true, $paths);
                } elseif ($evidenceCommit['exit'] !== 0
                    || $ancestry['exit'] !== 1
                    || self::hasIncompleteHistory($root)) {
                    // A missing object, shallow boundary, or Git error cannot
                    // prove either ancestry or a genuine unrelated commit.
                    $relation = 'UNVERIFIED';
                }
            }
        }

        if (!$validation['valid']) {
            $status = 'UNVERIFIED';
            $reason = $validation['reason'];
        } elseif (!$validation['passed']) {
            $status = 'FAIL';
            $reason = $validation['reason'];
        } elseif ($relation === 'EXACT') {
            $status = 'PASS';
            $reason = 'Verified successful Chromium workflow for the exact assessed commit.';
        } elseif ($relation === 'DOCUMENTATION_ONLY_DESCENDANT') {
            $status = 'PASS';
            $reason = 'Verified successful Chromium workflow for an ancestor; every descendant commit changes documentation files only.';
        } elseif ($relation === 'STALE') {
            $status = 'STALE';
            $reason = 'The browser-tested commit is an ancestor, but at least one descendant changes application or non-documentation files.';
        } elseif ($relation === 'UNVERIFIED') {
            $status = 'UNVERIFIED';
            $reason = 'The checkout does not contain enough verified Git history to establish the browser commit relationship.';
        } else {
            $status = 'MISMATCH';
            $reason = 'The browser-tested commit is not an ancestor of the assessed commit.';
        }

        return [
            'status' => $status,
            'reason' => $reason,
            'relation' => $relation,
            'record' => $record,
            'run_id' => (int) ($record['run_id'] ?? 0),
            'run_attempt' => (int) ($record['run_attempt'] ?? 0),
            'application_commit' => $sha,
            'conclusion' => (string) ($record['workflow_conclusion'] ?? 'unknown'),
        ];
    }

    private static function changedPathsBetween(string $root, string $from, string $to): array
    {
        $commits = self::git($root, ['rev-list', '--first-parent', $from . '..' . $to]);
        if ($commits['exit'] !== 0) {
            return ['<history unavailable>'];
        }
        $paths = [];
        foreach (array_filter(explode("\n", trim($commits['output']))) as $commit) {
            if (!preg_match('/\A[0-9a-f]{40}\z/i', $commit)) {
                return ['<invalid commit history>'];
            }
            $result = self::git($root, ['diff-tree', '--root', '--first-parent', '--no-commit-id', '--name-only', '-r', $commit]);
            if ($result['exit'] !== 0) {
                return ['<changed paths unavailable>'];
            }
            foreach (explode("\n", trim($result['output'])) as $path) {
                if ($path !== '') {
                    $paths[] = $path;
                }
            }
        }
        return $paths;
    }

    private static function git(string $root, array $arguments): array
    {
        $command = 'git -C ' . escapeshellarg($root) . ' ' . implode(' ', array_map('escapeshellarg', $arguments));
        $output = [];
        exec($command . ' 2>/dev/null', $output, $exit);
        return ['exit' => $exit, 'output' => implode("\n", $output)];
    }

    private static function hasIncompleteHistory(string $root): bool
    {
        $result = self::git($root, ['rev-parse', '--is-shallow-repository']);
        return $result['exit'] !== 0 || trim($result['output']) !== 'false';
    }
}
