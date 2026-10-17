<?php

declare(strict_types=1);

namespace Tools\Doctor\Project\BoardPrep\Simulation;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Give every PHP simulator process a private copy of repository content and a
 * private session namespace. Mutable learner state and cached reports are
 * never copied into the default simulator fixture.
 */
final class SimulationStorageFixture
{
    private static ?string $storagePath = null;
    private static ?string $sessionPath = null;
    private static ?string $ownedRoot = null;
    private static bool $cleanupRegistered = false;

    public static function activate(string $repositoryRoot): void
    {
        if (self::$storagePath !== null) {
            return;
        }

        $configuredStorage = trim((string) (getenv('APP_STORAGE_PATH') ?: ''));
        $configuredSession = trim((string) (getenv('APP_SESSION_PATH') ?: ''));
        $repositoryStorage = rtrim($repositoryRoot, DIRECTORY_SEPARATOR) . '/storage';
        $isTestStorage = self::isWithinTemporaryDirectory($configuredStorage)
            && realpath($configuredStorage) !== realpath($repositoryStorage);

        if ($isTestStorage) {
            self::$storagePath = rtrim($configuredStorage, DIRECTORY_SEPARATOR);
        } else {
            $temporaryRoot = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
                . '/boardprep-simulator-'
                . getmypid()
                . '-'
                . bin2hex(random_bytes(8));
            self::$ownedRoot = $temporaryRoot;
            self::$storagePath = $temporaryRoot . '/storage';
            self::registerCleanup();
            self::copyRepositoryStorage($repositoryStorage, self::$storagePath);
            self::setEnvironment('APP_STORAGE_PATH', self::$storagePath);
        }

        if (
            $configuredSession !== ''
            && self::isWithinTemporaryDirectory($configuredSession)
        ) {
            self::$sessionPath = rtrim($configuredSession, DIRECTORY_SEPARATOR)
                . '/simulator-'
                . bin2hex(random_bytes(8));
        } else {
            self::$sessionPath = self::$storagePath
                . '/doctor/simulation-sessions/test-'
                . bin2hex(random_bytes(8));
        }
        self::setEnvironment('DB_DRIVER', 'json');
        self::setEnvironment('DB_SQLITE_PATH', self::$storagePath . '/runtime/boardprep.sqlite');
        self::setEnvironment('APP_SESSION_PATH', self::$sessionPath);
        self::registerCleanup();

        if (!is_dir(self::$sessionPath) && !mkdir(self::$sessionPath, 0700, true) && !is_dir(self::$sessionPath)) {
            throw new \RuntimeException('Unable to create isolated simulator session storage.');
        }
    }

    public static function storagePath(): string
    {
        if (self::$storagePath === null) {
            throw new \LogicException('Simulator storage fixture is not active.');
        }
        return self::$storagePath;
    }

    public static function sessionPath(): string
    {
        if (self::$sessionPath === null) {
            throw new \LogicException('Simulator session fixture is not active.');
        }
        return self::$sessionPath;
    }

    private static function isWithinTemporaryDirectory(string $path): bool
    {
        if ($path === '') {
            return false;
        }
        $temporaryDirectory = rtrim(realpath(sys_get_temp_dir()) ?: sys_get_temp_dir(), DIRECTORY_SEPARATOR);
        $resolvedPath = realpath($path);
        $candidate = rtrim($resolvedPath === false ? $path : $resolvedPath, DIRECTORY_SEPARATOR);
        return $candidate === $temporaryDirectory
            || str_starts_with($candidate, $temporaryDirectory . DIRECTORY_SEPARATOR);
    }

    private static function copyRepositoryStorage(string $source, string $destination): void
    {
        if (!is_dir($source) || !mkdir($destination, 0700, true)) {
            throw new \RuntimeException('Unable to create simulator storage fixture.');
        }

        $excludedFiles = [
            'attempts.json',
            'results.json',
            'history.json',
            'progress.json',
            'weakness.json',
            'question-statistics.json',
            'flashcard_state.json',
            'flashcard-state.json',
            'repository-health.json',
            'doctor-report.json',
            'release-readiness.json',
            '.storage.lock',
        ];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $entry) {
            if (!$entry instanceof SplFileInfo) {
                continue;
            }
            $relative = substr($entry->getPathname(), strlen(rtrim($source, DIRECTORY_SEPARATOR)) + 1);
            $basename = $entry->getBasename();
            $firstPathPart = explode(DIRECTORY_SEPARATOR, $relative, 2)[0];
            if (
                in_array($basename, $excludedFiles, true)
                || in_array($firstPathPart, ['doctor', 'private', 'recovery', 'runtime'], true)
                || preg_match('/^batch\d.*\.json$/i', $basename) === 1
                || preg_match('/^(profile|progress|learner-state|flashcard-state).*\.json$/i', $basename) === 1
            ) {
                continue;
            }

            $target = $destination . DIRECTORY_SEPARATOR . $relative;
            if ($entry->isDir()) {
                if (!is_dir($target) && !mkdir($target, 0700, true) && !is_dir($target)) {
                    throw new \RuntimeException('Unable to prepare simulator content directory.');
                }
                continue;
            }
            if (!$entry->isFile()) {
                continue;
            }
            $parent = dirname($target);
            if (!is_dir($parent) && !mkdir($parent, 0700, true) && !is_dir($parent)) {
                throw new \RuntimeException('Unable to prepare simulator content directory.');
            }
            if (!copy($entry->getPathname(), $target)) {
                throw new \RuntimeException('Unable to copy repository content into simulator storage.');
            }
        }

        foreach ([
            'attempts.json' => "[]\n",
            'weakness.json' => "[]\n",
            'question-statistics.json' => "[]\n",
            'flashcard_state.json' => "[]\n",
        ] as $file => $contents) {
            if (file_put_contents($destination . '/' . $file, $contents, LOCK_EX) === false) {
                throw new \RuntimeException('Unable to initialize isolated learner-state fixtures.');
            }
        }

        self::removeQuestionUsageCounters($destination . '/questions.json');
    }

    private static function removeQuestionUsageCounters(string $questionsPath): void
    {
        if (!is_file($questionsPath)) {
            return;
        }
        $input = fopen($questionsPath, 'rb');
        $temporaryPath = $questionsPath . '.simulator';
        $output = fopen($temporaryPath, 'wb');
        if (!is_resource($input) || !is_resource($output)) {
            if (is_resource($input)) fclose($input);
            if (is_resource($output)) fclose($output);
            @unlink($temporaryPath);
            throw new \RuntimeException('Unable to open deterministic simulator question fixtures.');
        }

        $pendingLine = null;
        while (($line = fgets($input)) !== false) {
            if (preg_match('/^\s*"(?:timesUsed|timesCorrect|timesIncorrect|updatedAt)"\s*:/', $line) === 1) {
                continue;
            }
            if ($pendingLine !== null) {
                if (preg_match('/^\s*[}\]]/', $line) === 1) {
                    $pendingLine = preg_replace('/,(\s*)$/', '$1', $pendingLine) ?? $pendingLine;
                }
                fwrite($output, $pendingLine);
            }
            $pendingLine = $line;
        }
        if ($pendingLine !== null) fwrite($output, $pendingLine);
        fclose($input);
        fclose($output);

        if (!rename($temporaryPath, $questionsPath)) {
            @unlink($temporaryPath);
            throw new \RuntimeException('Unable to publish deterministic simulator question fixtures.');
        }

    }

    private static function setEnvironment(string $key, string $value): void
    {
        $_ENV[$key] = $value;
        putenv($key . '=' . $value);
    }

    private static function registerCleanup(): void
    {
        if (self::$cleanupRegistered) {
            return;
        }
        self::$cleanupRegistered = true;
        if (function_exists('pcntl_async_signals') && function_exists('pcntl_signal')) {
            pcntl_async_signals(true);
            foreach ([SIGINT, SIGTERM] as $signal) {
                pcntl_signal($signal, static function (int $receivedSignal): void {
                    self::cleanupOwnedPaths();
                    exit(128 + $receivedSignal);
                });
            }
        }
        register_shutdown_function(static function (): void {
            $lastError = error_get_last();
            $fatal = is_array($lastError) && in_array($lastError['type'] ?? null, [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true);
            $preserve = $fatal || getenv('BOARDPREP_KEEP_SIMULATION_FIXTURES') === '1';
            if ($preserve) {
                if (self::$ownedRoot !== null) {
                    fwrite(STDERR, 'SIMULATOR_FIXTURE_PRESERVED=' . self::$ownedRoot . PHP_EOL);
                } elseif (self::$sessionPath !== null) {
                    fwrite(STDERR, 'SIMULATOR_SESSION_FIXTURE_PRESERVED=' . self::$sessionPath . PHP_EOL);
                }
                return;
            }

            self::cleanupOwnedPaths();
        });
    }

    private static function cleanupOwnedPaths(): void
    {
        if (self::$ownedRoot !== null) {
            self::removeCreatedTree(self::$ownedRoot);
        } elseif (self::$sessionPath !== null) {
            self::removeCreatedTree(self::$sessionPath);
        }
    }

    private static function removeCreatedTree(string $path): void
    {
        $temporaryDirectory = rtrim(realpath(sys_get_temp_dir()) ?: sys_get_temp_dir(), DIRECTORY_SEPARATOR);
        $resolvedPath = realpath($path);
        if ($resolvedPath === false || !str_starts_with($resolvedPath, $temporaryDirectory . DIRECTORY_SEPARATOR)) {
            return;
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($resolvedPath, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $entry) {
            if ($entry->isDir() && !$entry->isLink()) {
                @rmdir($entry->getPathname());
            } else {
                @unlink($entry->getPathname());
            }
        }
        @rmdir($resolvedPath);
    }
}
