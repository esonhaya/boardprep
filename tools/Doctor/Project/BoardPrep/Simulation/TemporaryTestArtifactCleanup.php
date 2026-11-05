<?php

declare(strict_types=1);

namespace Tools\Doctor\Project\BoardPrep\Simulation;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

/**
 * Explicitly prunes abandoned release-test fixtures. Simulator fixtures are
 * intentionally excluded because failures may preserve them for diagnosis.
 */
final class TemporaryTestArtifactCleanup
{
    public const MINIMUM_AGE_SECONDS = 86400;

    /** @return list<array<string, int|string>> */
    public static function inventory(string $temporaryRoot, ?int $now = null): array
    {
        $root = self::resolveTemporaryRoot($temporaryRoot);
        $now ??= time();
        $activeReferences = self::activeProcessReferences($root);
        $entries = [];

        foreach (glob($root . DIRECTORY_SEPARATOR . '*') ?: [] as $path) {
            $name = basename($path);
            $kind = self::artifactKind($name);
            if ($kind === null) {
                continue;
            }

            $entry = self::inspectCandidate($root, $path, $kind, $now, $activeReferences);
            $entries[] = $entry;
        }

        usort($entries, static fn(array $left, array $right): int => strcmp((string) $left['path'], (string) $right['path']));
        return $entries;
    }

    /** @return list<array<string, int|string>> */
    public static function prune(string $temporaryRoot, bool $apply, ?int $now = null): array
    {
        $root = self::resolveTemporaryRoot($temporaryRoot);
        $now ??= time();
        $results = [];

        foreach (self::inventory($root, $now) as $candidate) {
            if (($candidate['status'] ?? '') !== 'SAFE_REGENERABLE') {
                $results[] = $candidate + ['action' => 'PRESERVED'];
                continue;
            }
            if (!$apply) {
                $results[] = $candidate + ['action' => 'DRY_RUN'];
                continue;
            }

            // Recheck ownership, path shape, age, symlinks and live references
            // immediately before deletion to avoid acting on stale inventory.
            $references = self::activeProcessReferences($root);
            $fresh = self::inspectCandidate(
                $root,
                (string) $candidate['path'],
                (string) $candidate['kind'],
                $now,
                $references
            );
            if (($fresh['status'] ?? '') !== 'SAFE_REGENERABLE') {
                $results[] = $fresh + ['action' => 'PRESERVED_AFTER_RECHECK'];
                continue;
            }

            try {
                self::removeCandidateTree($root, (string) $fresh['path']);
                $results[] = $fresh + ['action' => 'REMOVED'];
            } catch (\Throwable $exception) {
                $fresh['status'] = 'FAIL';
                $fresh['reason'] = 'Cleanup stopped safely: ' . $exception->getMessage();
                $results[] = $fresh + ['action' => 'FAILED'];
            }
        }

        return $results;
    }

    private static function resolveTemporaryRoot(string $temporaryRoot): string
    {
        $root = realpath($temporaryRoot);
        if ($root === false || !is_dir($root) || is_link($temporaryRoot)) {
            throw new RuntimeException('Temporary artifact root must be an existing, real directory.');
        }
        $root = rtrim($root, DIRECTORY_SEPARATOR);
        $systemTemporaryRoot = rtrim(realpath(sys_get_temp_dir()) ?: sys_get_temp_dir(), DIRECTORY_SEPARATOR);
        if ($root !== $systemTemporaryRoot && !str_starts_with($root, $systemTemporaryRoot . DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('Temporary artifact root must remain inside the system temporary directory.');
        }
        return $root;
    }

    private static function artifactKind(string $name): ?string
    {
        if (preg_match('/^boardprep-release-test-storage-[a-f0-9]{12}$/D', $name) === 1) {
            return 'release_storage_fixture';
        }
        if (preg_match('/^boardprep-release-sessions-[a-f0-9]{32}$/D', $name) === 1) {
            return 'release_session_tree';
        }
        return null;
    }

    /** @param list<string>|null $activeReferences @return array<string, int|string> */
    private static function inspectCandidate(
        string $root,
        string $path,
        string $kind,
        int $now,
        ?array $activeReferences
    ): array {
        $name = basename($path);
        $result = [
            'kind' => $kind,
            'path' => $path,
            'status' => 'REQUIRES_OWNER_REVIEW',
            'reason' => 'Candidate did not meet all cleanup safety checks.',
            'bytes' => 0,
            'files' => 0,
            'age_seconds' => 0,
        ];

        if (self::artifactKind($name) !== $kind || dirname($path) !== $root) {
            $result['reason'] = 'Path is outside the exact generated namespace.';
            return $result;
        }
        $rootStat = @lstat($root);
        $candidateStat = @lstat($path);
        if (!is_array($rootStat) || !is_array($candidateStat) || is_link($path) || !is_dir($path)) {
            $result['reason'] = 'Candidate root is missing, not a directory, or is a symbolic link.';
            return $result;
        }
        if ((int) $candidateStat['uid'] !== (int) $rootStat['uid']) {
            $result['reason'] = 'Candidate owner differs from the temporary root owner.';
            return $result;
        }
        if (((int) $candidateStat['mode'] & 0077) !== 0 || (int) $candidateStat['dev'] !== (int) $rootStat['dev']) {
            $result['reason'] = 'Candidate is not private or is on a different filesystem device.';
            return $result;
        }

        $age = $now - (int) $candidateStat['mtime'];
        $result['age_seconds'] = max(0, $age);
        if ($age < self::MINIMUM_AGE_SECONDS) {
            $result['reason'] = 'Candidate is newer than the 24-hour stale-artifact window.';
            return $result;
        }
        if ($activeReferences === null) {
            $result['reason'] = 'Unable to inspect process references on this platform.';
            return $result;
        }
        foreach ($activeReferences as $referencedPath) {
            if ($referencedPath === $path || str_starts_with($referencedPath, $path . DIRECTORY_SEPARATOR)) {
                $result['reason'] = 'A live process references this candidate.';
                return $result;
            }
        }

        $bytes = 0;
        $files = 0;
        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );
            foreach ($iterator as $item) {
                if ($item->isLink()) {
                    $result['reason'] = 'Candidate contains a symbolic link.';
                    return $result;
                }
                $itemStat = @lstat($item->getPathname());
                if (!is_array($itemStat) || (int) $itemStat['uid'] !== (int) $rootStat['uid']) {
                    $result['reason'] = 'Candidate contains a missing or differently owned entry.';
                    return $result;
                }
                if ($item->isFile()) {
                    $bytes += (int) $itemStat['size'];
                    $files++;
                }
            }
        } catch (\Throwable) {
            $result['reason'] = 'Candidate tree could not be inspected safely.';
            return $result;
        }

        if ($kind === 'release_storage_fixture' && (!is_file($path . '/questions.json') || !is_file($path . '/boards.json'))) {
            $result['reason'] = 'Release storage fixture lacks its expected canonical fixture files.';
            return $result;
        }
        if ($kind === 'release_session_tree' && $files === 0) {
            $result['reason'] = 'Session tree is empty; its provenance cannot be confirmed from generated session data.';
            return $result;
        }

        $result['status'] = 'SAFE_REGENERABLE';
        $result['reason'] = 'Exact release-test namespace, same owner, stale, private, symlink-free and unreferenced.';
        $result['bytes'] = $bytes;
        $result['files'] = $files;
        return $result;
    }

    /** @return list<string>|null Null means process reference checking is unavailable. */
    private static function activeProcessReferences(string $root): ?array
    {
        $procRoot = '/proc';
        if (!is_dir($procRoot) || !is_readable($procRoot)) {
            return null;
        }

        $references = [];
        foreach (glob($procRoot . '/[0-9]*') ?: [] as $processPath) {
            foreach (['cwd', 'root'] as $linkName) {
                $target = @readlink($processPath . '/' . $linkName);
                if (is_string($target) && self::isWithinAnyCandidate($root, $target)) {
                    $references[] = $target;
                }
            }

            foreach (glob($processPath . '/fd/*') ?: [] as $descriptorPath) {
                $target = @readlink($descriptorPath);
                if (is_string($target) && self::isWithinAnyCandidate($root, $target)) {
                    $references[] = $target;
                }
            }

            foreach (['environ', 'cmdline'] as $fileName) {
                $contents = @file_get_contents($processPath . '/' . $fileName);
                if (!is_string($contents) || $contents === '') {
                    continue;
                }
                foreach (glob($root . '/boardprep-release-*') ?: [] as $candidatePath) {
                    if (str_contains($contents, $candidatePath)) {
                        $references[] = $candidatePath;
                    }
                }
            }
        }

        return array_values(array_unique($references));
    }

    private static function isWithinAnyCandidate(string $root, string $path): bool
    {
        if (!str_starts_with($path, $root . DIRECTORY_SEPARATOR)) {
            return false;
        }
        $relative = substr($path, strlen($root) + 1);
        $firstPart = explode(DIRECTORY_SEPARATOR, $relative, 2)[0];
        return self::artifactKind($firstPart) !== null;
    }

    private static function removeCandidateTree(string $root, string $path): void
    {
        if (dirname($path) !== $root || self::artifactKind(basename($path)) === null || is_link($path)) {
            throw new RuntimeException('Refusing to remove a path outside the exact release-test namespace.');
        }
        $resolved = realpath($path);
        if ($resolved === false || $resolved !== $path) {
            throw new RuntimeException('Refusing to remove a candidate whose real path changed.');
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            $itemPath = $item->getPathname();
            if (!str_starts_with($itemPath, $path . DIRECTORY_SEPARATOR)) {
                throw new RuntimeException('Refusing to remove an entry outside the candidate directory.');
            }
            if ($item->isLink()) {
                throw new RuntimeException('Refusing to remove a candidate containing a symbolic link.');
            }
            $removed = $item->isDir() ? @rmdir($itemPath) : @unlink($itemPath);
            if (!$removed) {
                throw new RuntimeException('Unable to remove a verified stale release-test artifact.');
            }
        }
        if (!@rmdir($path) || is_dir($path)) {
            throw new RuntimeException('Unable to remove a verified stale release-test artifact root.');
        }
    }
}
