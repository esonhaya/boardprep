<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

use App\Core\Environment;
use App\Services\Operations\ProductionReadinessService;

$root = dirname(__DIR__, 2);
$profile = json_decode((string) file_get_contents($root . '/config/deployment-profile.json'), true, 512, JSON_THROW_ON_ERROR);
$requiredExtensions = array_map('strval', (array) ($profile['required_extensions'] ?? []));
$databaseModes = array_map('strval', (array) ($profile['database_modes'] ?? []));

if (!in_array('mbstring', $requiredExtensions, true)) {
    exit("[FAIL] deployment profile does not require mbstring.\n");
}
if ($databaseModes !== ['json', 'sqlite']) {
    exit("[FAIL] production database modes do not match the v1.0.1 release contract.\n");
}

$environment = [
    'APP_ENV' => 'production',
    'APP_DEBUG' => 'false',
    'APP_TIMEZONE' => 'UTC',
    'DB_DRIVER' => 'json',
    'APP_STORAGE_PATH' => $root . '/storage',
    'APP_PUBLIC_PATH' => $root . '/public',
];
foreach ($environment as $key => $value) {
    $_ENV[$key] = $value;
    putenv($key . '=' . $value);
}

$isolations = static function () use ($root): array {
    $report = ProductionReadinessService::preflight($root);
    return (array) ($report['checks']['PUBLIC_DATA_ISOLATION'] ?? []);
};

$privateStorage = $isolations();
if (($privateStorage['status'] ?? '') !== 'PASS') {
    exit("[FAIL] private storage did not pass document-root isolation.\n");
}

$_ENV['APP_STORAGE_PATH'] = $root . '/public';
putenv('APP_STORAGE_PATH=' . $root . '/public');
$publicStorage = $isolations();
if (($publicStorage['status'] ?? '') !== 'FAIL'
    || !in_array('storage', (array) ($publicStorage['evidence']['affected'] ?? []), true)) {
    exit("[FAIL] storage inside the document root was not rejected.\n");
}

$_ENV['APP_STORAGE_PATH'] = $root . '/storage';
putenv('APP_STORAGE_PATH=' . $root . '/storage');
$_ENV['APP_STORAGE_PATH'] = sys_get_temp_dir() . '/boardprep-private-state-fixture';
putenv('APP_STORAGE_PATH=' . sys_get_temp_dir() . '/boardprep-private-state-fixture');
$_ENV['APP_PUBLIC_PATH'] = $root;
putenv('APP_PUBLIC_PATH=' . $root);
$publicApplicationRoot = $isolations();
if (($publicApplicationRoot['status'] ?? '') !== 'FAIL'
    || !in_array('application_root', (array) ($publicApplicationRoot['evidence']['affected'] ?? []), true)) {
    exit("[FAIL] a public document root containing the package was not rejected.\n");
}

$_ENV['APP_STORAGE_PATH'] = $root . '/storage';
putenv('APP_STORAGE_PATH=' . $root . '/storage');
$_ENV['APP_PUBLIC_PATH'] = $root . '/public';
putenv('APP_PUBLIC_PATH=' . $root . '/public');
$_ENV['DB_DRIVER'] = 'sqlite';
putenv('DB_DRIVER=sqlite');
$_ENV['DB_SQLITE_PATH'] = $root . '/public/preflight-fixture.sqlite';
putenv('DB_SQLITE_PATH=' . $root . '/public/preflight-fixture.sqlite');
$publicSqlite = $isolations();
if (($publicSqlite['status'] ?? '') !== 'FAIL'
    || !in_array('sqlite', (array) ($publicSqlite['evidence']['affected'] ?? []), true)) {
    exit("[FAIL] SQLite inside the document root was not rejected.\n");
}

$alternateDocumentRoot = sys_get_temp_dir() . '/boardprep-preflight-root-' . bin2hex(random_bytes(5));
if (!mkdir($alternateDocumentRoot, 0700, true)) {
    exit("[FAIL] unable to create temporary document-root fixture.\n");
}
$_ENV['DB_DRIVER'] = 'json';
putenv('DB_DRIVER=json');
$_ENV['APP_PUBLIC_PATH'] = $alternateDocumentRoot;
putenv('APP_PUBLIC_PATH=' . $alternateDocumentRoot);
$alternateRoot = $isolations();
rmdir($alternateDocumentRoot);
if (($alternateRoot['status'] ?? '') !== 'PASS') {
    exit("[FAIL] separate public_html-style document root was not accepted.\n");
}

echo "deployment_preflight_isolation=PASS\n";
