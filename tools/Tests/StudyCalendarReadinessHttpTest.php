<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

$root = dirname(__DIR__, 2);
$mode = strtolower((string) ($argv[1] ?? 'testing'));
if (!in_array($mode, ['testing', 'production'], true)) throw new RuntimeException('Use testing or production mode.');
$storagePath = sys_get_temp_dir() . '/boardprep-calendar-readiness-http-' . bin2hex(random_bytes(8));
putenv('APP_ENV=' . $mode);
putenv('APP_DEBUG=false');
putenv('DB_DRIVER=json');
putenv('APP_STORAGE_PATH=' . $storagePath);
$_SESSION = [];
\App\Core\App::boot();
$router = new \App\Core\Router();
require $root . '/routes/web.php';

function calendarHttpAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

try {
    if ($mode === 'production') {
        $notFound = false;
        try {
            $router->dispatch('GET', '/developer/readiness');
        } catch (RuntimeException $exception) {
            $notFound = $exception->getCode() === 404;
        }
        calendarHttpAssert($notFound, 'Developer readiness route was registered in production.');
        echo "[PASS] Developer readiness route returns an empty 404 response in production.\n";
        return;
    }

    $today = (new DateTimeImmutable('today'))->format('Y-m-d');
    $board = [[
        'id' => 'let', 'code' => 'LET', 'name' => 'Synthetic LET',
        'practice_subjects' => [['id' => 'english', 'name' => 'English', 'available_questions' => 8]],
    ]];
    $input = [
        'board_id' => 'let', 'exam_date' => (new DateTimeImmutable($today))->modify('+14 days')->format('Y-m-d'),
        'start_date' => $today, 'weekdays' => [1, 2, 3, 4, 5, 6, 7], 'daily_minutes' => 30,
        'review_intensity' => 'balanced', 'rest_dates' => [], 'subject_priorities' => ['let' => ['english' => 'normal']],
        'expected_revision' => 0, 'request_id' => str_repeat('7', 32),
    ];
    $learner = \App\Services\Learning\LearnerIdentityService::current();
    (new \App\Services\Learning\StudyCalendarService(\App\Core\App::storage(), $learner))
        ->saveConfiguration($input, $board, [], $today);

    $_SERVER['REQUEST_URI'] = '/dashboard';
    $_SERVER['REQUEST_METHOD'] = 'GET';
    ob_start();
    $router->dispatch('GET', '/dashboard');
    $dashboard = (string) ob_get_clean();
    calendarHttpAssert(str_contains($dashboard, 'dashboard-calendar-heading')
        && str_contains($dashboard, 'Open next session')
        && str_contains($dashboard, 'href="/study-calendar"'), 'Learner dashboard did not show its own next calendar task.');

    $_SERVER['REQUEST_URI'] = '/study-calendar';
    ob_start();
    $router->dispatch('GET', '/study-calendar');
    $calendar = (string) ob_get_clean();
    calendarHttpAssert(str_contains($calendar, '<h1>Study Calendar</h1>')
        && str_contains($calendar, 'Mark complete')
        && str_contains($calendar, 'Move session to an available weekday'), 'Study calendar page did not render task controls and accessible navigation.');

    $_SERVER['REQUEST_URI'] = '/developer/readiness';
    http_response_code(200);
    ob_start();
    $router->dispatch('GET', '/developer/readiness');
    $readiness = (string) ob_get_clean();
    calendarHttpAssert(str_contains($readiness, 'Readiness Overview')
        && str_contains($readiness, 'NOT AUTHORIZED')
        && str_contains($readiness, 'NOT_EXECUTED'), 'Readiness dashboard failed to display separate browser and public release states.');
    echo "[PASS] Learner dashboard/calendar routes render owned study state; readiness route keeps browser and public-release gates separate.\n";
} finally {
    if (is_dir($storagePath)) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($storagePath, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $entry) {
            if ($entry->isDir() && !$entry->isLink()) @rmdir($entry->getPathname());
            else @unlink($entry->getPathname());
        }
        @rmdir($storagePath);
    }
}
