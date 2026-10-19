<?php

declare(strict_types=1);

namespace App\Services\Operations;

use App\Core\App;
use App\Core\Autoloader;
use App\Core\Environment;
use App\Core\ReleaseIdentity;
use JsonException;
use PDO;
use Throwable;

/**
 * Small deployment-facing projection over the existing application/runtime.
 * It is intentionally independent from the expensive RepositoryHealthEngine.
 */
final class ProductionReadinessService
{
    /** @return array<string,mixed> */
    public static function preflight(?string $root = null): array
    {
        $root ??= dirname(__DIR__, 3);
        $checks = [];
        $profile = self::loadProfile($root, $checks);
        self::configureMemoryLimit($profile);
        self::checkEnvironmentFile($root, $checks);
        self::checkRuntimeVersion($profile, $checks);
        self::checkMemoryLimit($profile, $checks);
        self::checkRequiredExtensions($profile, $checks);
        $environment = self::checkDeploymentMode($checks);
        $storagePath = self::checkStoragePaths($root, $profile, $checks);
        self::checkCanonicalContent($profile, $storagePath, $checks);
        self::checkCanonicalJson($profile, $storagePath, $checks);
        self::checkMutableJson($profile, $storagePath, $checks);
        $driver = self::checkDatabaseRuntime($root, $profile, $checks);
        self::checkSessionStorage($checks);
        $release = self::checkReleaseIdentity($root, $profile, $checks);
        self::checkOperationalSafety($root, $checks);

        return self::preflightSummary($root, $release, $environment, $driver, $storagePath, $checks);
    }

    /** @param array<string,mixed> $profile */
    private static function configureMemoryLimit(array $profile): void
    {
        $configured = trim((string) (Environment::get('APP_MEMORY_LIMIT', (string) ($profile['php_memory_limit'] ?? '512M')) ?? '512M'));
        if ($configured !== '') {
            @ini_set('memory_limit', $configured);
        }
    }

    /** @param array<string,mixed> $checks */
    private static function checkEnvironmentFile(string $root, array &$checks): void
    {
        try {
            Environment::load($root . '/.env');
        } catch (Throwable) {
            self::check($checks, 'ENVIRONMENT_FILE', 'FAIL', 'Environment configuration could not be read.', []);
            return;
        }
        if (!isset($checks['ENVIRONMENT_FILE'])) {
            self::check($checks, 'ENVIRONMENT_FILE', 'PASS', 'Environment file is optional; process environment and .env values are readable.', []);
        }
    }

    /** @param array<string,mixed> $profile @param array<string,mixed> $checks */
    private static function checkRuntimeVersion(array $profile, array &$checks): void
    {
        $minimum = (string) ($profile['php_min_version'] ?? '8.1.0');
        $supported = version_compare(PHP_VERSION, $minimum, '>=');
        self::check(
            $checks,
            'PHP_VERSION',
            $supported ? 'PASS' : 'FAIL',
            $supported ? 'PHP runtime meets the deployment minimum.' : 'PHP runtime is below the documented deployment minimum.',
            ['running' => PHP_VERSION, 'required' => $minimum]
        );
    }

    /** @param array<string,mixed> $profile @param array<string,mixed> $checks */
    private static function checkMemoryLimit(array $profile, array &$checks): void
    {
        $required = (string) ($profile['php_memory_limit'] ?? '512M');
        $currentBytes = self::memoryLimitBytes((string) ini_get('memory_limit'));
        $requiredBytes = self::memoryLimitBytes($required);
        $supported = $currentBytes === -1 || $currentBytes >= $requiredBytes;
        self::check(
            $checks,
            'PHP_MEMORY_LIMIT',
            $supported ? 'PASS' : 'FAIL',
            $supported ? 'PHP memory limit meets the documented production floor.' : 'PHP memory limit is below the documented production floor.',
            ['running' => (string) ini_get('memory_limit'), 'required' => $required]
        );
    }

    /** @param array<string,mixed> $profile @param array<string,mixed> $checks */
    private static function checkRequiredExtensions(array $profile, array &$checks): void
    {
        $missing = [];
        $required = array_values(array_unique(array_merge(['mbstring'], (array) ($profile['required_extensions'] ?? []))));
        foreach ($required as $extension) {
            $extension = (string) $extension;
            if ($extension !== '' && !extension_loaded($extension)) $missing[] = $extension;
        }
        self::check(
            $checks,
            'REQUIRED_EXTENSIONS',
            $missing === [] ? 'PASS' : 'FAIL',
            $missing === [] ? 'Required PHP extensions are loaded.' : 'Required PHP extensions are missing.',
            ['missing' => $missing]
        );
    }

    /** @param array<string,mixed> $checks */
    private static function checkDeploymentMode(array &$checks): string
    {
        $environment = strtolower((string) (Environment::get('APP_ENV', 'production') ?? 'production'));
        $debugRaw = (string) (Environment::get('APP_DEBUG', 'false') ?? 'false');
        $debug = filter_var($debugRaw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        self::check(
            $checks,
            'PRODUCTION_MODE',
            $environment === 'production' ? 'PASS' : 'FAIL',
            $environment === 'production' ? 'APP_ENV is production.' : 'Deployment preflight requires APP_ENV=production.',
            ['environment' => $environment]
        );
        self::check(
            $checks,
            'DEBUG_DISABLED',
            $debug === false ? 'PASS' : 'FAIL',
            $debug === false ? 'APP_DEBUG is disabled.' : 'APP_DEBUG must be false for production.',
            []
        );
        $timezone = (string) (Environment::get('APP_TIMEZONE', 'UTC') ?? 'UTC');
        $validTimezone = in_array($timezone, timezone_identifiers_list(), true);
        self::check(
            $checks,
            'TIMEZONE',
            $validTimezone ? 'PASS' : 'FAIL',
            $validTimezone ? 'Configured timezone is valid.' : 'APP_TIMEZONE is not a valid timezone identifier.',
            ['timezone' => $timezone]
        );
        return $environment;
    }

    /** @param array<string,mixed> $profile @param array<string,mixed> $checks */
    private static function checkStoragePaths(string $root, array $profile, array &$checks): string
    {
        $documentRoot = $root . '/' . ltrim((string) ($profile['document_root'] ?? 'public'), '/');
        $frontController = $root . '/' . ltrim((string) ($profile['front_controller'] ?? 'public/index.php'), '/');
        $documentRootPresent = is_dir($documentRoot) && is_file($frontController);
        self::check(
            $checks,
            'DOCUMENT_ROOT',
            $documentRootPresent ? 'PASS' : 'FAIL',
            $documentRootPresent ? 'public/ document root and front controller are present.' : 'Document root or front controller is missing.',
            []
        );

        $storagePath = self::configuredStoragePath($root);
        $storageUsable = is_dir($storagePath) && is_readable($storagePath) && is_writable($storagePath);
        self::check(
            $checks,
            'STORAGE_DIRECTORY',
            $storageUsable ? 'PASS' : 'FAIL',
            $storageUsable ? 'Configured storage is readable and writable.' : 'Configured storage must exist and be readable and writable.',
            ['configured' => self::pathLabel($storagePath, $root)]
        );
        self::checkPublicDataIsolation($root, $storagePath, $checks);
        return $storagePath;
    }

    /** @param array<string,mixed> $checks */
    private static function checkPublicDataIsolation(string $root, string $storagePath, array &$checks): void
    {
        $driver = strtolower((string) (Environment::get('DB_DRIVER', 'json') ?? 'json'));
        $configured = trim((string) (Environment::get('APP_PUBLIC_PATH', '') ?? ''));
        $publicPath = $configured !== '' ? self::absolutePath($configured, $root) : $root . '/public';
        $exposed = [];
        if (!is_dir($publicPath)) $exposed[] = 'public_root_missing';
        if (self::pathIsWithin($root, $publicPath)) $exposed[] = 'application_root';
        if (self::pathIsWithin($storagePath, $publicPath)) $exposed[] = 'storage';
        if ($driver === 'sqlite' && self::pathIsWithin(self::configuredSqlitePath($root), $publicPath)) $exposed[] = 'sqlite';
        $environmentFile = $root . '/.env';
        if (is_file($environmentFile) && self::pathIsWithin($environmentFile, $publicPath)) $exposed[] = 'environment_file';
        self::check(
            $checks,
            'PUBLIC_DATA_ISOLATION',
            $exposed === [] ? 'PASS' : 'FAIL',
            $exposed === []
                ? 'Writable state and configuration are outside the configured public document root.'
                : 'Writable state or configuration overlaps the configured public document root.',
            ['affected' => $exposed]
        );
    }

    /** @param array<string,mixed> $profile @param array<string,mixed> $checks */
    private static function checkCanonicalContent(array $profile, string $storagePath, array &$checks): void
    {
        $missing = [];
        foreach ((array) ($profile['canonical_content'] ?? []) as $relative) {
            $relative = ltrim((string) $relative, '/');
            $name = str_starts_with($relative, 'storage/') ? substr($relative, 8) : $relative;
            if (!is_file($storagePath . '/' . $name)) $missing[] = $relative;
        }
        self::check(
            $checks,
            'CANONICAL_CONTENT',
            $missing === [] ? 'PASS' : 'FAIL',
            $missing === [] ? 'Required canonical content files are present.' : 'Required canonical content files are missing from configured storage.',
            ['missing' => $missing]
        );
    }

    /** @param array<string,mixed> $profile @param array<string,mixed> $checks */
    private static function checkCanonicalJson(array $profile, string $storagePath, array &$checks): void
    {
        $errors = [];
        foreach ((array) ($profile['canonical_content'] ?? []) as $relative) {
            $relative = ltrim((string) $relative, '/');
            $name = str_starts_with($relative, 'storage/') ? substr($relative, 8) : $relative;
            $path = $storagePath . '/' . $name;
            if (!is_file($path)) continue;
            try {
                $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
                if (!is_array($data) || !array_is_list($data)) $errors[] = $relative . ':expected-list';
            } catch (JsonException) {
                $errors[] = $relative . ':malformed';
            }
        }
        self::check(
            $checks,
            'CANONICAL_JSON',
            $errors === [] ? 'PASS' : 'FAIL',
            $errors === [] ? 'Canonical JSON collections parse with the expected list shape.' : 'One or more canonical JSON collections are invalid.',
            ['errors' => $errors]
        );
    }

    /** @param array<string,mixed> $profile @param array<string,mixed> $checks */
    private static function checkMutableJson(array $profile, string $storagePath, array &$checks): void
    {
        $errors = [];
        $present = [];
        $stores = array_merge(
            (array) ($profile['json_mutable_stores'] ?? []),
            (array) ($profile['json_optional_mutable_stores'] ?? [])
        );
        foreach ($stores as $relative) {
            $relative = ltrim((string) $relative, '/');
            $path = $storagePath . '/' . $relative;
            if (!is_file($path)) continue;
            $present[] = $relative;
            try {
                $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
                if (!is_array($data) || !array_is_list($data)) $errors[] = $relative . ':expected-list';
            } catch (JsonException) {
                $errors[] = $relative . ':malformed';
            }
        }
        self::check(
            $checks,
            'MUTABLE_JSON',
            $errors === [] ? 'PASS' : 'FAIL',
            $errors === [] ? 'Present mutable JSON stores parse with the expected list shape.' : 'One or more present mutable JSON stores are invalid.',
            ['present' => $present, 'errors' => $errors]
        );
    }

    /** @param array<string,mixed> $profile @param array<string,mixed> $checks */
    private static function checkDatabaseRuntime(string $root, array $profile, array &$checks): string
    {
        $driver = strtolower((string) (Environment::get('DB_DRIVER', 'json') ?? 'json'));
        $profileDrivers = array_map('strtolower', array_map('strval', (array) ($profile['database_modes'] ?? ['json', 'sqlite'])));
        $allowedDrivers = array_values(array_intersect($profileDrivers, ['json', 'sqlite']));
        $supported = in_array($driver, $allowedDrivers, true);
        self::check(
            $checks,
            'DATABASE_DRIVER',
            $supported ? 'PASS' : 'FAIL',
            $supported ? 'Configured database mode is supported.' : 'DB_DRIVER is not supported by this release.',
            ['driver' => $driver]
        );
        self::databaseCheck($checks, $root, $driver);
        return $driver;
    }

    /** @param array<string,mixed> $checks */
    private static function checkSessionStorage(array &$checks): void
    {
        $sessionPath = (string) (session_save_path() ?: sys_get_temp_dir());
        if (str_contains($sessionPath, ';')) {
            $parts = explode(';', $sessionPath);
            $sessionPath = (string) end($parts);
        }
        $writable = is_dir($sessionPath) && is_writable($sessionPath);
        self::check(
            $checks,
            'SESSION_STORAGE',
            $writable ? 'PASS' : 'FAIL',
            $writable ? 'PHP session storage is writable.' : 'PHP session storage is not writable.',
            []
        );
    }

    /** @param array<string,mixed> $profile @param array<string,mixed> $checks @return array<string,mixed> */
    private static function checkReleaseIdentity(string $root, array $profile, array &$checks): array
    {
        $release = ReleaseIdentity::current($root);
        $hasBuildCommit = preg_match('/^[0-9a-f]{40}$/', $release['build_commit']) === 1;
        self::check(
            $checks,
            'RELEASE_IDENTITY',
            $hasBuildCommit ? 'PASS' : 'WARN',
            $hasBuildCommit ? 'Product and build identity are discoverable.' : 'Product identity is available but no committed build identity was found.',
            ['product_version' => $release['product_version'], 'release_tag' => $release['release_tag']]
        );
        $expectedVersion = trim((string) ($profile['product_version'] ?? ''));
        $expectedTag = trim((string) ($profile['release_tag'] ?? ''));
        $matches = ($expectedVersion === '' || $release['product_version'] === $expectedVersion)
            && ($expectedTag === '' || $release['release_tag'] === $expectedTag);
        self::check(
            $checks,
            'RELEASE_IDENTITY_CONTRACT',
            $matches ? 'PASS' : 'FAIL',
            $matches ? 'Product version and release tag match the deployment profile.' : 'Product version or release tag does not match the deployment profile.',
            [
                'expected_product_version' => $expectedVersion,
                'actual_product_version' => $release['product_version'],
                'expected_release_tag' => $expectedTag,
                'actual_release_tag' => $release['release_tag'],
            ]
        );
        return $release;
    }

    /** @param array<string,mixed> $checks */
    private static function checkOperationalSafety(string $root, array &$checks): void
    {
        self::checkRuntimeErrorSafety($root, $checks);
        $configurationPresent = is_file($root . '/.env.example');
        self::check(
            $checks,
            'CONFIG_TEMPLATE',
            $configurationPresent ? 'PASS' : 'FAIL',
            $configurationPresent ? 'Configuration template is present.' : 'Configuration template is missing.',
            []
        );
        $baselinePresent = is_file($root . '/config/deployment-profile.json') && is_file($root . '/docs/RELEASE_RUNBOOK_V1.md');
        self::check(
            $checks,
            'OPERATIONAL_BASELINE',
            $baselinePresent ? 'PASS' : 'FAIL',
            $baselinePresent ? 'Deployment profile and runbook are available.' : 'Deployment profile or runbook is missing.',
            []
        );
    }

    /** @param array<string,mixed> $checks */
    private static function checkRuntimeErrorSafety(string $root, array &$checks): void
    {
        $frontControllerText = is_file($root . '/public/index.php') ? (string) file_get_contents($root . '/public/index.php') : '';
        $frontControllerGuard = str_contains($frontControllerText, "ini_set('display_errors', '0')");
        $displayErrors = filter_var(ini_get('display_errors'), FILTER_VALIDATE_BOOLEAN);
        $dangerous = $displayErrors && !$frontControllerGuard ? ['display_errors'] : [];
        self::check(
            $checks,
            'RUNTIME_ERROR_SAFETY',
            $dangerous === [] ? 'PASS' : 'WARN',
            $dangerous === [] ? 'Production entrypoint disables runtime error display.' : 'Runtime error display is enabled without a front-controller guard.',
            ['cli_display_errors' => $displayErrors, 'front_controller_guard' => $frontControllerGuard]
        );
    }

    /** @param array<string,mixed> $checks @return array<string,mixed> */
    private static function preflightSummary(string $root, array $release, string $environment, string $driver, string $storagePath, array $checks): array
    {
        $failures = [];
        $warnings = [];
        foreach ($checks as $code => $check) {
            if (($check['status'] ?? '') === 'FAIL') $failures[] = $code;
            if (($check['status'] ?? '') === 'WARN') $warnings[] = $code;
        }
        return [
            'status' => $failures !== [] ? 'FAIL' : ($warnings !== [] ? 'WARN' : 'PASS'),
            'product' => $release['product'],
            'product_version' => $release['product_version'],
            'release_tag' => $release['release_tag'],
            'build_commit' => $release['build_commit'],
            'environment' => $environment,
            'database_driver' => $driver,
            'storage_path' => self::pathLabel($storagePath, $root),
            'checks' => $checks,
            'failures' => $failures,
            'warnings' => $warnings,
            'summary' => ['pass' => count($checks) - count($failures) - count($warnings), 'warn' => count($warnings), 'fail' => count($failures)],
        ];
    }

    /** @return array<string,mixed> */
    public static function smoke(?string $root = null): array
    {
        $root ??= dirname(__DIR__, 3);
        $started = hrtime(true);
        $checks = [];
        $preflight = self::preflight($root);
        self::check($checks, 'PREFLIGHT', $preflight['status'] === 'FAIL' ? 'FAIL' : 'PASS', 'Production preflight completed.', ['status' => $preflight['status']]);
        if ($preflight['status'] !== 'FAIL') {
            try {
                Autoloader::register();
                App::boot();
                $storage = App::storage();
                $boards = $storage->all('boards');
                $questions = $storage->all('questions');
                $eligibility = $storage->all('question-eligibility');
                self::check($checks, 'APPLICATION_BOOT', 'PASS', 'Application booted in the configured environment.', []);
                self::check($checks, 'CONTENT_LOAD', ($boards !== [] && $questions !== [] && $eligibility !== []) ? 'PASS' : 'FAIL', 'Boards, questions, and eligibility are readable.', ['boards' => count($boards), 'questions' => count($questions), 'eligibility' => count($eligibility)]);
                $selectionServiceLoaded = class_exists('QuestionSelectionService')
                    || class_exists('App\\Services\\Quiz\\QuestionSelectionService');
                self::check($checks, 'LEARNER_SERVICE_PATH', $selectionServiceLoaded ? 'PASS' : 'FAIL', 'Core learner selection service is loadable.', []);
                self::check($checks, 'PROGRESS_HISTORY_PATH', class_exists('App\\Services\\Learning\\LearningHistoryService') ? 'PASS' : 'FAIL', 'Progress/history services are loadable.', []);
            } catch (Throwable $exception) {
                self::check($checks, 'APPLICATION_BOOT', 'FAIL', 'Application bootstrap failed.', []);
            }
        }
        $failures = [];
        foreach ($checks as $code => $check) if (($check['status'] ?? '') === 'FAIL') $failures[] = $code;
        return [
            'status' => $failures === [] ? 'PASS' : 'FAIL',
            'checks' => $checks,
            'failures' => $failures,
            'release_identity' => ReleaseIdentity::current($root),
            'runtime_ms' => round((hrtime(true) - $started) / 1_000_000, 3),
        ];
    }

    /** @return array<string,mixed> */
    public static function publicHealth(?string $root = null): array
    {
        $root ??= dirname(__DIR__, 3);
        try {
            Environment::load($root . '/.env');
            $storagePath = self::configuredStoragePath($root);
            $ready = is_dir($storagePath)
                && is_readable($storagePath)
                && is_file($storagePath . '/boards.json')
                && is_file($storagePath . '/questions.json');
            return [
                'status' => $ready ? 'PASS' : 'FAIL',
                'liveness' => 'PASS',
                'readiness' => $ready ? 'PASS' : 'FAIL',
                'product' => ReleaseIdentity::current($root)['product'],
                'product_version' => ReleaseIdentity::current($root)['product_version'],
                'build_commit' => ReleaseIdentity::current($root)['build_commit'],
            ];
        } catch (Throwable) {
            return [
                'status' => 'FAIL',
                'liveness' => 'PASS',
                'readiness' => 'FAIL',
                'product' => 'BoardPrep',
                'product_version' => '1.0.1',
                'build_commit' => '',
            ];
        }
    }

    /** @param array<string,array<string,mixed>> $checks */
    private static function check(array &$checks, string $code, string $status, string $reason, array $evidence): void
    {
        $checks[$code] = ['status' => $status, 'reason' => $reason, 'evidence' => $evidence];
    }

    /** @param array<string,array<string,mixed>> $checks @return array<string,mixed> */
    private static function loadProfile(string $root, array &$checks): array
    {
        $path = $root . '/config/deployment-profile.json';
        if (!is_file($path)) {
            self::check($checks, 'DEPLOYMENT_PROFILE', 'FAIL', 'Deployment profile is missing.', []);
            return [];
        }
        try {
            $profile = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($profile)) throw new JsonException('Profile must be an object.');
            self::check($checks, 'DEPLOYMENT_PROFILE', 'PASS', 'Deployment profile is valid.', []);
            return $profile;
        } catch (JsonException) {
            self::check($checks, 'DEPLOYMENT_PROFILE', 'FAIL', 'Deployment profile is malformed.', []);
            return [];
        }
    }

    /** @param array<string,array<string,mixed>> $checks */
    private static function databaseCheck(array &$checks, string $root, string $driver): void
    {
        if ($driver === 'sqlite') {
            if (!extension_loaded('pdo_sqlite')) {
                self::check($checks, 'SQLITE', 'FAIL', 'pdo_sqlite is required for SQLite mode.', []);
                return;
            }
            $path = self::configuredSqlitePath($root);
            if (!is_file($path)) {
                $parent = dirname($path);
                self::check($checks, 'SQLITE', is_dir($parent) && is_writable($parent) ? 'WARN' : 'FAIL', is_dir($parent) && is_writable($parent) ? 'SQLite database will be initialized by the migration command.' : 'SQLite database path is unavailable.', []);
                return;
            }
            try {
                $pdo = new PDO('sqlite:' . $path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $result = (string) $pdo->query('PRAGMA integrity_check')->fetchColumn();
                self::check($checks, 'SQLITE', strtolower($result) === 'ok' ? 'PASS' : 'FAIL', strtolower($result) === 'ok' ? 'SQLite integrity check passed.' : 'SQLite integrity check failed.', []);
            } catch (Throwable) {
                self::check($checks, 'SQLITE', 'FAIL', 'SQLite database could not be opened safely.', []);
            }
            return;
        }
        if ($driver === 'mysql') {
            $configured = trim((string) (Environment::get('DB_HOST', '') ?? '')) !== ''
                && trim((string) (Environment::get('DB_DATABASE', '') ?? '')) !== ''
                && trim((string) (Environment::get('DB_USERNAME', '') ?? '')) !== '';
            self::check($checks, 'MYSQL', $configured && extension_loaded('pdo_mysql') ? 'WARN' : 'FAIL', $configured && extension_loaded('pdo_mysql') ? 'MySQL configuration is present; network connectivity is deferred to deployment.' : 'MySQL mode requires pdo_mysql and non-empty host/database/username settings.', []);
            return;
        }
        self::check($checks, 'SQLITE', 'PASS', 'SQLite is optional for JSON mode.', []);
    }

    private static function configuredStoragePath(string $root): string
    {
        $configured = trim((string) (Environment::get('APP_STORAGE_PATH', '') ?? ''));
        if ($configured === '') return $root . '/storage';
        return self::absolutePath($configured, $root);
    }

    private static function configuredSqlitePath(string $root): string
    {
        $configured = trim((string) (Environment::get('DB_SQLITE_PATH', '') ?? ''));
        return self::absolutePath($configured !== '' ? $configured : 'storage/runtime/boardprep.sqlite', $root);
    }

    private static function absolutePath(string $path, string $root): string
    {
        if ($path === '' || $path[0] === '/') return $path;
        return rtrim($root, '/') . '/' . ltrim($path, '/');
    }

    private static function pathIsWithin(string $path, string $directory): bool
    {
        $resolvedPath = self::resolvedPath($path);
        $resolvedDirectory = self::resolvedPath($directory);
        if ($resolvedPath === $resolvedDirectory) return true;
        if ($resolvedDirectory === DIRECTORY_SEPARATOR) return str_starts_with($resolvedPath, DIRECTORY_SEPARATOR);
        return str_starts_with($resolvedPath, rtrim($resolvedDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR);
    }

    private static function resolvedPath(string $path): string
    {
        $resolved = realpath($path);
        if ($resolved !== false) return $resolved;
        $parent = realpath(dirname($path));
        $candidate = ($parent !== false ? $parent : dirname($path)) . DIRECTORY_SEPARATOR . basename($path);
        return rtrim($candidate, DIRECTORY_SEPARATOR) ?: DIRECTORY_SEPARATOR;
    }

    private static function pathLabel(string $path, string $root): string
    {
        $prefix = rtrim($root, '/') . '/';
        return str_starts_with($path, $prefix) ? substr($path, strlen($prefix)) : '[external]';
    }

    private static function memoryLimitBytes(string $value): int
    {
        $value = trim($value);
        if ($value === '-1') return -1;
        if (preg_match('/^([0-9]+(?:\.[0-9]+)?)\s*([kmgt]?)/i', $value, $matches) !== 1) return 0;
        $number = (float) $matches[1];
        $unit = strtolower($matches[2] ?? '');
        $multipliers = ['' => 1, 'k' => 1024, 'm' => 1048576, 'g' => 1073741824, 't' => 1099511627776];
        return (int) round($number * ($multipliers[$unit] ?? 1));
    }
}
