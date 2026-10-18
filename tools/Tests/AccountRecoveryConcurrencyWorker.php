<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

$storage = (string) ($argv[1] ?? '');
$driver = (string) ($argv[2] ?? 'json');
$username = (string) ($argv[3] ?? '');
$recoveryCode = fgets(STDIN, 257);
$newPassword = fgets(STDIN, 1026);
if ($storage === '' || !in_array($driver, ['json', 'sqlite'], true)
    || $username === '' || !is_string($recoveryCode) || !is_string($newPassword)) {
    fwrite(STDERR, "Invalid isolated recovery fixture input.\n");
    exit(2);
}
$recoveryCode = rtrim($recoveryCode, "\r\n");
$newPassword = rtrim($newPassword, "\r\n");
foreach ([
    'APP_ENV' => 'testing',
    'APP_DEBUG' => 'false',
    'BOARDPREP_ACCOUNTS_ENABLED' => 'true',
    'DB_DRIVER' => $driver,
    'APP_STORAGE_PATH' => $storage,
    'DB_SQLITE_PATH' => dirname($storage) . '/runtime.sqlite',
] as $key => $value) {
    putenv($key . '=' . $value);
    $_ENV[$key] = $value;
}
try {
    \App\Core\App::boot();
    $won = (new \App\Services\Accounts\AccountService())->recover($username, $recoveryCode, $newPassword);
    if ($recoveryCode !== '' && function_exists('sodium_memzero')) sodium_memzero($recoveryCode);
    if ($newPassword !== '' && function_exists('sodium_memzero')) sodium_memzero($newPassword);
    fwrite(STDOUT, $won ? "WON\n" : "LOST\n");
    exit(0);
} catch (Throwable) {
    fwrite(STDERR, "Recovery worker failed.\n");
    exit(3);
}
