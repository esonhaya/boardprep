#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/Core/Autoloader.php';
\App\Core\Autoloader::register();

try {
    $database = \App\Core\App::database();
    if (!$database->usingSqlite()) {
        throw new RuntimeException('Set DB_DRIVER=sqlite before running db-migrate.php.');
    }
    $applied = $database->migrate();
    $imports = [];
    foreach (['attempts', 'weakness'] as $collection) {
        $imports[$collection] = $database->importLegacyCollection($collection);
    }
    echo 'Migrations applied: ' . count($applied) . "\n";
    foreach ($imports as $collection => $import) {
        echo ucfirst($collection) . ' imported: ' . $import['imported']
            . ', existing: ' . $import['existing'] . ', invalid: ' . $import['invalid'] . "\n";
    }
} catch (Throwable $exception) {
    fwrite(STDERR, '[FAIL] ' . $exception->getMessage() . "\n");
    exit(1);
}
