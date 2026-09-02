<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$php = escapeshellarg(PHP_BINARY);
$autoload = var_export($root . '/app/Core/Autoloader.php', true);
$bootstrap = var_export($root . '/bootstrap/app.php', true);
$script = 'require ' . $autoload . '; App\\Core\\Autoloader::register(); require '
    . $bootstrap . '; echo App\\Core\\App::config("timezone");';

function runBootstrap(string $php, string $script, array $environment): array
{
    $prefix = '';
    foreach ($environment as $key => $value) {
        $prefix .= $key . '=' . escapeshellarg($value) . ' ';
    }

    $output = [];
    $status = 0;
    exec($prefix . $php . ' -r ' . escapeshellarg($script) . ' 2>&1', $output, $status);
    return [$status, implode("\n", $output)];
}

$storage = sys_get_temp_dir() . '/boardprep-runtime-' . bin2hex(random_bytes(6));
[$status, $output] = runBootstrap($php, $script, [
    'APP_ENV' => 'testing',
    'APP_TIMEZONE' => 'Asia/Manila',
    'DB_DRIVER' => 'json',
    'APP_STORAGE_PATH' => $storage,
]);

if ($status !== 0 || !str_ends_with($output, 'Asia/Manila') || !is_dir($storage)) {
    exit("[FAIL] clean-process JSON bootstrap failed: {$output}\n");
}

@rmdir($storage);

[$status, $output] = runBootstrap($php, $script, [
    'APP_ENV' => 'testing',
    'APP_TIMEZONE' => 'Not/A-Timezone',
    'DB_DRIVER' => 'json',
]);
if ($status === 0 || !str_contains($output, 'APP_TIMEZONE')) {
    exit("[FAIL] invalid timezone did not fail clearly.\n");
}

$sqlite = sys_get_temp_dir() . '/boardprep-runtime-' . bin2hex(random_bytes(6)) . '.sqlite';
[$status, $output] = runBootstrap($php, $script, [
    'APP_ENV' => 'testing',
    'APP_TIMEZONE' => 'UTC',
    'DB_DRIVER' => 'sqlite',
    'APP_STORAGE_PATH' => $storage,
    'DB_SQLITE_PATH' => $sqlite,
]);
if ($status !== 0 || !is_file($sqlite)) {
    exit("[FAIL] SQLite bootstrap failed: {$output}\n");
}
@unlink($sqlite);
@rmdir($storage);

[$status, $output] = runBootstrap($php, $script, [
    'APP_ENV' => 'production',
    'APP_DEBUG' => 'true',
    'APP_TIMEZONE' => 'UTC',
    'DB_DRIVER' => 'json',
]);
if ($status === 0 || !str_contains($output, 'APP_DEBUG')) {
    exit("[FAIL] production debug mode was accepted.\n");
}

echo "[PASS] runtime configuration and clean-process bootstrap verified.\n";
