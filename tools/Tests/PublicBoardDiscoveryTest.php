<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Doctor/Project/BoardPrep/Simulation/HttpSimulator.php';
require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

use Tools\Doctor\Project\BoardPrep\Simulation\HttpSimulator;

$root = dirname(__DIR__, 2);
$storage = sys_get_temp_dir() . '/boardprep-public-boards-' . bin2hex(random_bytes(6));
mkdir($storage, 0777, true);
foreach (glob($root . '/storage/*.json') ?: [] as $source) {
    copy($source, $storage . '/' . basename($source));
}

putenv('APP_ENV=production');
putenv('APP_DEBUG=false');
putenv('APP_TIMEZONE=UTC');
putenv('DB_DRIVER=json');
putenv('APP_STORAGE_PATH=' . $storage);

$http = new HttpSimulator($root . '/public/index.php');

try {
    $detail = $http->request('GET', '/exams', ['exam' => 'LET']);
    if (!$detail['success'] || !str_contains($detail['output'], 'LET examination')
        || !str_contains($detail['output'], 'Configure practice')) {
        throw new RuntimeException('public board detail did not resolve from discovery');
    }

    $architecture = $http->request('GET', '/exams', ['exam' => 'ARCH']);
    if (!$architecture['success'] || !str_contains($architecture['output'], 'ARCH examination')
        || !str_contains($architecture['output'], 'Configure practice')) {
        throw new RuntimeException('populated architecture board remained inaccessible');
    }

    $listing = $http->request('GET', '/exams');
    if (!$listing['success'] || substr_count($listing['output'], 'status-ready') !== 10
        || str_contains($listing['output'], 'Repository in progress')) {
        throw new RuntimeException('populated board discovery still exposes disabled coming-soon cards');
    }

    echo "[PASS] public board discovery resolves detail pages and exposes all populated boards.\n";
} finally {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($storage, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $file) {
        if ($file->isDir()) {
            @rmdir($file->getPathname());
        } else {
            @unlink($file->getPathname());
        }
    }
    @rmdir($storage);
}
