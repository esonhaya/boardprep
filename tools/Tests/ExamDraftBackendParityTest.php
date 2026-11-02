<?php

declare(strict_types=1);

$script = __DIR__ . '/ExamDraftPrototypeTest.php';
foreach (['json', 'sqlite'] as $backend) {
    if ($backend === 'sqlite' && !extension_loaded('pdo_sqlite')) {
        fwrite(STDERR, "SQLite exam draft verification requires pdo_sqlite.\n");
        exit(2);
    }
    $process = proc_open([PHP_BINARY, $script, $backend], [
        0 => ['file', '/dev/null', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ], $pipes);
    if (!is_resource($process)) {
        fwrite(STDERR, "Could not start the {$backend} exam draft prototype.\n");
        exit(2);
    }
    $stdout = (string) stream_get_contents($pipes[1]);
    $stderr = (string) stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $status = proc_close($process);
    echo $stdout;
    if ($status !== 0) {
        fwrite(STDERR, $stderr);
        exit($status > 0 ? $status : 2);
    }
}
