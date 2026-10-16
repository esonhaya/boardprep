#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

$arguments = array_slice($argv, 1);
if (count($arguments) > 1 || ($arguments !== [] && $arguments[0] !== '--check')) {
    fwrite(STDERR, "Usage: php tools/generate-study-pdfs.php [--check]\n");
    exit(2);
}

try {
    $result = \App\Services\Study\StudyPdfGenerator::build(dirname(__DIR__), $arguments === ['--check']);
    printf("STATUS=%s\nMODE=%s\nFILES=%d\n", $result['status'], $result['mode'], count($result['files']));
    foreach ($result['files'] as $entry) {
        printf("LESSON=%s PATH=%s PAGES=%d SHA256=%s\n", $entry['lesson_id'], $entry['path'], $entry['pages'], $entry['sha256']);
    }
} catch (Throwable $exception) {
    fwrite(STDERR, '[FAIL] ' . $exception->getMessage() . "\n");
    exit(1);
}
