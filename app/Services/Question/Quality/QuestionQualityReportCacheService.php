<?php

declare(strict_types=1);

namespace App\Services\Question\Quality;

use App\Core\App;
use App\Services\RepositoryHealth\DTO\HealthReport;
use App\Services\RepositoryHealth\Engine\RepositoryHealthEngine;
use App\Services\RepositoryHealth\Engine\RepositoryHealthEvidenceService;
use App\Services\RepositoryHealth\Engine\RepositoryHealthReportHydrator;

/** Current-checkout cache for the expensive developer quality report. */
final class QuestionQualityReportCacheService
{
    /** @return array{0:HealthReport,1:string} */
    public static function currentReport(): array
    {
        $root = dirname(__DIR__, 4);
        if (!self::canUsePersistentCache($root)) {
            return [RepositoryHealthEngine::analyze(), 'FRESH_ANALYSIS'];
        }

        $cachePath = self::cachePath($root);
        $lock = @fopen($cachePath . '.lock', 'c');
        if (!is_resource($lock) || !flock($lock, LOCK_EX)) {
            if (is_resource($lock)) {
                fclose($lock);
            }
            return [RepositoryHealthEngine::analyze(), 'FRESH_ANALYSIS'];
        }

        try {
            $classification = RepositoryHealthEvidenceService::classifyPersistedSnapshot($cachePath, $root);
            if (($classification['state'] ?? '') === RepositoryHealthEvidenceService::CACHED) {
                $cached = self::readCache($cachePath);
                $report = $cached === null ? null : RepositoryHealthReportHydrator::hydrate($cached);
                if ($report !== null) {
                    return [$report, 'CACHED_CURRENT'];
                }
            }

            $report = RepositoryHealthEngine::analyze();
            self::writeCache($cachePath, $report->toArray(), $root);
            return [$report, 'FRESH_ANALYSIS'];
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private static function canUsePersistentCache(string $root): bool
    {
        if ((string) App::config('environment', '') === 'testing') {
            return false;
        }

        $database = App::config('database', []);
        $storagePath = is_array($database) ? ($database['path'] ?? null) : null;
        if (!is_string($storagePath) || trim($storagePath) === '') {
            return false;
        }

        $configuredStorage = realpath($storagePath);
        $repositoryStorage = realpath($root . DIRECTORY_SEPARATOR . 'storage');
        return $configuredStorage !== false
            && $repositoryStorage !== false
            && $configuredStorage === $repositoryStorage;
    }

    private static function cachePath(string $root): string
    {
        return rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . 'boardprep-question-quality-'
            . substr(hash('sha256', $root), 0, 20)
            . '.json';
    }

    /** @return array<string, mixed>|null */
    private static function readCache(string $path): ?array
    {
        $contents = @file_get_contents($path);
        if (!is_string($contents)) {
            return null;
        }
        $payload = json_decode($contents, true);
        return is_array($payload) ? $payload : null;
    }

    /** @param array<string, mixed> $report */
    private static function writeCache(string $path, array $report, string $root): void
    {
        try {
            $payload = RepositoryHealthEvidenceService::preparePersistedSnapshot($report, $root);
            $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
        } catch (\Throwable) {
            return;
        }

        $temporaryPath = @tempnam(dirname($path), 'bp-question-quality-');
        if (!is_string($temporaryPath)) {
            return;
        }

        try {
            $written = @file_put_contents($temporaryPath, $json, LOCK_EX);
            if ($written !== strlen($json)) {
                return;
            }
            @chmod($temporaryPath, 0600);
            if (!@rename($temporaryPath, $path)) {
                return;
            }
            @chmod($path, 0600);
        } finally {
            if (is_file($temporaryPath)) {
                @unlink($temporaryPath);
            }
        }
    }
}
