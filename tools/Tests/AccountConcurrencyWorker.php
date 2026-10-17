<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

$directory = (string) ($argv[1] ?? '');
$suffix = (string) ($argv[2] ?? '');
$mode = (string) ($argv[3] ?? 'create');
if ($directory === '' || $suffix === '') {
    fwrite(STDERR, "Missing isolated concurrency fixture arguments.\n");
    exit(2);
}

$repository = new \App\Services\Accounts\AccountRepository($directory);
if ($mode === 'increment') {
    $account = $repository->findByUsername('concurrent-counter');
    if (!is_array($account)) {
        fwrite(STDERR, "Missing isolated concurrent update account.\n");
        exit(3);
    }
    for ($index = 0; $index < 20; $index++) {
        $updated = $repository->updateById((string) $account['account_id'], static function (array $current): array {
            $current['mutation_counter'] = (int) ($current['mutation_counter'] ?? 0) + 1;
            return $current;
        });
        if (!is_array($updated)) {
            fwrite(STDERR, "Concurrent account update failed.\n");
            exit(4);
        }
    }
    echo "UPDATED\n";
    exit(0);
}

$created = $repository->create([
    'account_id' => 'account-' . str_pad($suffix, 32, '0', STR_PAD_LEFT),
    'learner_id' => 'account-learner-' . str_pad($suffix, 32, '0', STR_PAD_LEFT),
    'username' => 'concurrent-learner',
    'password_hash' => password_hash('a fixture-only password', PASSWORD_DEFAULT),
    'created_at' => gmdate(DATE_ATOM),
    'updated_at' => gmdate(DATE_ATOM),
    'auth_version' => 1,
    'status' => 'active',
    'deletion_requested_at' => null,
    'preferences' => \App\Services\Accounts\AccountService::defaultPreferences(),
    'recovery_code_hashes' => [],
]);
echo $created ? "CREATED\n" : "DUPLICATE\n";
