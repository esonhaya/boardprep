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

        $configuredMemory = trim((string) (Environment::get('APP_MEMORY_LIMIT', (string) ($profile['php_memory_limit'] ?? '512M')) ?? '512M'));
        if ($configuredMemory !== '') {
            @ini_set('memory_limit', $configuredMemory);
        }

        try {
            Environment::load($root . '/.env');
            $environmentLoad = 'PASS';
        } catch (Throwable $exception) {
            $environmentLoad = 'FAIL';
            self::check($checks, 'ENVIRONMENT_FILE', 'FAIL', 'Environment configuration could not be read.', []);
        }
        if ($environmentLoad === 'PASS' && !isset($checks['ENVIRONMENT_FILE'])) {
            self::check($checks, 'ENVIRONMENT_FILE', 'PASS', 'Environment file is optional; process environment and .env values are readable.', []);
        }

        $minimumPhp = (string) ($profile['php_min_version'] ?? '8.1.0');
        self::check(
            $checks,
            'PHP_VERSION',
            version_compare(PHP_VERSION, $minimumPhp, '>=') ? 'PASS' : 'FAIL',
            version_compare(PHP_VERSION, $minimumPhp, '>=')
                ? 'PHP runtime meets the deployment minimum.'
                : 'PHP runtime is below the documented deployment minimum.',
            ['running' => PHP_VERSION, 'required' => $minimumPhp]
        );

        $requiredMemory = (string) ($profile['php_memory_limit'] ?? '512M');
        $currentMemoryBytes = self::memoryLimitBytes((string) ini_get('memory_limit'));
        $requiredMemoryBytes = self::memoryLimitBytes($requiredMemory);
        self::check(
            $checks,
            'PHP_MEMORY_LIMIT',
            $currentMemoryBytes === -1 || $currentMemoryBytes >= $requiredMemoryBytes ? 'PASS' : 'FAIL',
            $currentMemoryBytes === -1 || $currentMemoryBytes >= $requiredMemoryBytes ? 'PHP memory limit meets the documented production floor.' : 'PHP memory limit is below the documented production floor.',
            ['running' => (string) ini_get('memory_limit'), 'required' => $requiredMemory]
        );

        $missingExtensions = [];
        foreach ((array) ($profile['required_extensions'] ?? []) as $extension) {
            $extension = (string) $extension;
            if ($extension !== '' && !extension_loaded($extension)) {
                $missingExtensions[] = $extension;
            }
        }
        self::check(
            $checks,
            'REQUIRED_EXTENSIONS',
            $missingExtensions === [] ? 'PASS' : 'FAIL',
            $missingExtensions === [] ? 'Required PHP extensions are loaded.' : 'Required PHP extensions are missing.',
            ['missing' => $missingExtensions]
        );

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
        self::check(
            $checks,
            'TIMEZONE',
            in_array($timezone, timezone_identifiers_list(), true) ? 'PASS' : 'FAIL',
            in_array($timezone, timezone_identifiers_list(), true) ? 'Configured timezone is valid.' : 'APP_TIMEZONE is not a valid timezone identifier.',
            ['timezone' => $timezone]
        );

        $documentRoot = $root . '/' . ltrim((string) ($profile['document_root'] ?? 'public'), '/');
        $frontController = $root . '/' . ltrim((string) ($profile['front_controller'] ?? 'public/index.php'), '/');
        self::check(
            $checks,
            'DOCUMENT_ROOT',
            is_dir($documentRoot) && is_file($frontController) ? 'PASS' : 'FAIL',
            is_dir($documentRoot) && is_file($frontController) ? 'public/ document root and front controller are present.' : 'Document root or front controller is missing.',
            []
        );

        $storagePath = self::configuredStoragePath($root);
        self::check(
            $checks,
            'STORAGE_DIRECTORY',
            is_dir($storagePath) && is_readable($storagePath) && is_writable($storagePath) ? 'PASS' : 'FAIL',
            is_dir($storagePath) && is_readable($storagePath) && is_writable($storagePath)
                ? 'Configured storage is readable and writable.'
                : 'Configured storage must exist and be readable and writable.',
            ['configured' => self::pathLabel($storagePath, $root)]
        );

        $canonicalMissing = [];
        foreach ((array) ($profile['canonical_content'] ?? []) as $relative) {
            $relative = ltrim((string) $relative, '/');
            $name = str_starts_with($relative, 'storage/') ? substr($relative, 8) : $relative;
            if (!is_file($storagePath . '/' . $name)) {
                $canonicalMissing[] = $relative;
            }
        }
        self::check(
            $checks,
            'CANONICAL_CONTENT',
            $canonicalMissing === [] ? 'PASS' : 'FAIL',
            $canonicalMissing === [] ? 'Required canonical content files are present.' : 'Required canonical content files are missing from configured storage.',
            ['missing' => $canonicalMissing]
        );

        $jsonErrors = [];
        foreach ((array) ($profile['canonical_content'] ?? []) as $relative) {
            $relative = ltrim((string) $relative, '/');
            $name = str_starts_with($relative, 'storage/') ? substr($relative, 8) : $relative;
            $path = $storagePath . '/' . $name;
            if (!is_file($path)) continue;
            try {
                $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
                if (!is_array($data) || !array_is_list($data)) {
                    $jsonErrors[] = $relative . ':expected-list';
                }
            } catch (JsonException) {
                $jsonErrors[] = $relative . ':malformed';
            }
        }
        self::check(
            $checks,
            'CANONICAL_JSON',
            $jsonErrors === [] ? 'PASS' : 'FAIL',
            $jsonErrors === [] ? 'Canonical JSON collections parse with the expected list shape.' : 'One or more canonical JSON collections are invalid.',
            ['errors' => $jsonErrors]
        );

        $mutableJsonErrors = [];
        $mutableJsonPresent = [];
        foreach (array_merge(
            (array) ($profile['json_mutable_stores'] ?? []),
            (array) ($profile['json_optional_mutable_stores'] ?? [])
        ) as $relative) {
            $relative = ltrim((string) $relative, '/');
            $path = $storagePath . '/' . $relative;
            if (!is_file($path)) {
                continue;
            }
            $mutableJsonPresent[] = $relative;
            try {
                $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
                if (!is_array($data) || !array_is_list($data)) {
                    $mutableJsonErrors[] = $relative . ':expected-list';
                }
            } catch (JsonException) {
                $mutableJsonErrors[] = $relative . ':malformed';
            }
        }
        self::check(
            $checks,
            'MUTABLE_JSON',
            $mutableJsonErrors === [] ? 'PASS' : 'FAIL',
            $mutableJsonErrors === []
                ? 'Present mutable JSON stores parse with the expected list shape.'
                : 'One or more present mutable JSON stores are invalid.',
            ['present' => $mutableJsonPresent, 'errors' => $mutableJsonErrors]
        );

        $driver = strtolower((string) (Environment::get('DB_DRIVER', 'json') ?? 'json'));
        $allowedDrivers = array_map('strtolower', array_map('strval', (array) ($profile['database_modes'] ?? ['json', 'sqlite', 'mysql'])));
        self::check(
            $checks,
            'DATABASE_DRIVER',
            in_array($driver, $allowedDrivers, true) ? 'PASS' : 'FAIL',
            in_array($driver, $allowedDrivers, true) ? 'Configured database mode is supported.' : 'DB_DRIVER is not supported by this release.',
            ['driver' => $driver]
        );

        self::databaseCheck($checks, $root, $driver);

        $sessionPath = (string) (session_save_path() ?: sys_get_temp_dir());
        if (str_contains($sessionPath, ';')) {
            $parts = explode(';', $sessionPath);
            $sessionPath = (string) end($parts);
        }
        self::check(
            $checks,
            'SESSION_STORAGE',
            is_dir($sessionPath) && is_writable($sessionPath) ? 'PASS' : 'FAIL',
            is_dir($sessionPath) && is_writable($sessionPath) ? 'PHP session storage is writable.' : 'PHP session storage is not writable.',
            []
        );

        $release = ReleaseIdentity::current($root);
        self::check(
            $checks,
            'RELEASE_IDENTITY',
            preg_match('/^[0-9a-f]{40}$/', $release['build_commit']) === 1 ? 'PASS' : 'WARN',
            preg_match('/^[0-9a-f]{40}$/', $release['build_commit']) === 1 ? 'Product and build identity are discoverable.' : 'Product identity is available but no committed build identity was found.',
            ['product_version' => $release['product_version'], 'release_tag' => $release['release_tag']]
        );
        $expectedProductVersion = trim((string) ($profile['product_version'] ?? ''));
        $expectedReleaseTag = trim((string) ($profile['release_tag'] ?? ''));
        $identityContractPasses = ($expectedProductVersion === '' || $release['product_version'] === $expectedProductVersion)
            && ($expectedReleaseTag === '' || $release['release_tag'] === $expectedReleaseTag);
        self::check(
            $checks,
            'RELEASE_IDENTITY_CONTRACT',
            $identityContractPasses ? 'PASS' : 'FAIL',
            $identityContractPasses
                ? 'Product version and release tag match the deployment profile.'
                : 'Product version or release tag does not match the deployment profile.',
            [
                'expected_product_version' => $expectedProductVersion,
                'actual_product_version' => $release['product_version'],
                'expected_release_tag' => $expectedReleaseTag,
                'actual_release_tag' => $release['release_tag'],
            ]
        );

        $frontControllerText = is_file($root . '/public/index.php') ? (string) file_get_contents($root . '/public/index.php') : '';
        $frontControllerGuard = str_contains($frontControllerText, "ini_set('display_errors', '0')");
        $dangerous = filter_var(ini_get('display_errors'), FILTER_VALIDATE_BOOLEAN) && !$frontControllerGuard
            ? ['display_errors']
            : [];
        self::check(
            $checks,
            'RUNTIME_ERROR_SAFETY',
            $dangerous === [] ? 'PASS' : 'WARN',
            $dangerous === [] ? 'Production entrypoint disables runtime error display.' : 'Runtime error display is enabled without a front-controller guard.',
            ['cli_display_errors' => filter_var(ini_get('display_errors'), FILTER_VALIDATE_BOOLEAN), 'front_controller_guard' => $frontControllerGuard]
        );

        self::check(
            $checks,
            'CONFIG_TEMPLATE',
            is_file($root . '/.env.example') ? 'PASS' : 'FAIL',
            is_file($root . '/.env.example') ? 'Configuration template is present.' : 'Configuration template is missing.',
            []
        );

        self::check(
            $checks,
            'OPERATIONAL_BASELINE',
            is_file($root . '/config/deployment-profile.json') && is_file($root . '/docs/RELEASE_RUNBOOK_V1.md') ? 'PASS' : 'FAIL',
            is_file($root . '/config/deployment-profile.json') && is_file($root . '/docs/RELEASE_RUNBOOK_V1.md')
                ? 'Deployment profile and runbook are available.'
                : 'Deployment profile or runbook is missing.',
            []
        );

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
