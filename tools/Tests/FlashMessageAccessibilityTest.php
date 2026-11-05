<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

$storagePath = sys_get_temp_dir() . '/boardprep-flash-a11y-' . bin2hex(random_bytes(6));
if (!mkdir($storagePath, 0700, true)) {
    throw new RuntimeException('Unable to create isolated flash-message storage.');
}

putenv('APP_ENV=testing');
putenv('APP_DEBUG=false');
putenv('DB_DRIVER=json');
putenv('APP_STORAGE_PATH=' . $storagePath);

function flashA11yRender(string $type, string $message): string
{
    $_SERVER['REQUEST_URI'] = '/flashcards/review';
    $pageTitle = 'Synthetic flash-message test';
    $content = '<p>synthetic content</p>';
    \SessionService::flash($type, $message);
    ob_start();
    require dirname(__DIR__, 2) . '/app/Views/layouts/main.php';
    return (string) ob_get_clean();
}

try {
    \App\Core\App::boot();

    $success = flashA11yRender('success', 'Review saved.');
    if (!str_contains($success, 'flash-success')
        || !str_contains($success, 'role="status"')
        || !str_contains($success, 'aria-live="polite"')) {
        throw new RuntimeException('Success feedback should be a polite status message.');
    }

    $error = flashA11yRender('error', 'The review was not saved.');
    if (!str_contains($error, 'flash-error')
        || !str_contains($error, 'role="alert"')
        || !str_contains($error, 'aria-live="assertive"')) {
        throw new RuntimeException('Error feedback should remain an assertive alert.');
    }

    echo "PASS: routine flash feedback is polite and errors remain assertive.\n";
} finally {
    putenv('APP_ENV');
    putenv('APP_DEBUG');
    putenv('DB_DRIVER');
    putenv('APP_STORAGE_PATH');
    $real = realpath($storagePath);
    $temporaryRoot = realpath(sys_get_temp_dir());
    if (is_string($real) && is_string($temporaryRoot)
        && str_starts_with($real, $temporaryRoot . DIRECTORY_SEPARATOR)
        && str_starts_with(basename($real), 'boardprep-flash-a11y-')) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($real, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $entry) {
            if ($entry->isDir() && !$entry->isLink()) @rmdir($entry->getPathname());
            else @unlink($entry->getPathname());
        }
        @rmdir($real);
    }
}
