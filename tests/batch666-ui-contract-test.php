<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$read = static function (string $path) use ($root): string {
    $contents = file_get_contents($root . '/' . $path);
    if (!is_string($contents)) {
        throw new RuntimeException('Unable to read ' . $path);
    }
    return $contents;
};

$layout = $read('app/Views/layouts/main.php');
$settings = $read('app/Views/quiz/settings.php');
$quiz = $read('app/Views/quiz/index.php');
$result = $read('app/Views/quiz/result.php');
$history = $read('app/Views/history/index.php');
$privacy = $read('app/Views/data-handling/index.php');
$study = $read('app/Views/study/library.php');
$board = $read('app/Views/home/exam.php');
$css = $read('public/assets/css/rich-product.css');
$baseCss = $read('public/assets/css/style.css');
$shellJs = $read('public/assets/js/app-shell.js');
$browserJourney = $read('tests/browser/acceptance/boardprep.spec.cjs');
$browserVisual = $read('tests/browser/acceptance/visual-baseline.spec.cjs');

$checks = [
    'Learner navigation links to exams, study, practice, flashcards, progress, history, and privacy' => count(array_filter([
        str_contains($layout, 'href="/exams"'),
        str_contains($layout, 'href="/study-library"'),
        str_contains($layout, 'href="/quiz"'),
        str_contains($layout, 'href="/flashcards"'),
        str_contains($layout, 'href="/progress"'),
        str_contains($layout, 'href="/history"'),
        str_contains($layout, 'href="/privacy"'),
    ])) === 7,
    'Active navigation state and primary landmark are present' => str_contains($layout, 'aria-label="Primary navigation"') && str_contains($layout, 'aria-current="page"'),
    'Developer navigation remains environment-gated' => str_contains($layout, "App::config('environment') !== 'production'") && str_contains($layout, 'href="/developer"'),
    'Redundant hidden navigation announcement is removed' => !str_contains($layout, 'Learning Overview Recommended Next Step Subject Performance Recent Quiz History'),
    'Mobile navigation supports no-JavaScript visibility and JavaScript collapse' => str_contains($css, '.site-header nav.learner-navigation{display:flex') && str_contains($css, 'html.has-js .site-header nav.learner-navigation{display:none'),
    'Mobile navigation has narrow-screen layout rules' => str_contains($css, '@media(max-width:768px)') && str_contains($css, '@media(max-width:360px)'),
    'Keyboard menu behavior closes with Escape and restores focus' => str_contains($shellJs, "event.key !== 'Escape'") && str_contains($shellJs, 'button.focus()'),
    'Quiz setup preserves CSRF and supported field names' => str_contains($settings, 'name="csrf_token"') && str_contains($settings, 'name="exam"') && str_contains($settings, 'name="subject"') && str_contains($settings, 'name="count"') && str_contains($settings, 'name="difficulty"') && str_contains($settings, 'name="mode"'),
    'Exam-style mode is truthfully described as untimed' => str_contains($settings, 'Exam-style practice (untimed)') && str_contains($settings, 'does not reproduce every official board-exam format') && str_contains($board, 'Exam-style sessions are untimed'),
    'Question progress exposes a percentage progressbar' => str_contains($quiz, 'role="progressbar"') && str_contains($quiz, 'aria-valuenow="<?= $progressValue ?>"'),
    'Active quiz screen derives and labels board/subject context from its existing session' => str_contains($quiz, "\$quizSession['board']") && str_contains($quiz, "\$quizSession['subject']") && str_contains($quiz, 'aria-label="Selected study context"'),
    'Question form keeps CSRF, required radio answer, and submission route' => str_contains($quiz, 'name="csrf_token"') && str_contains($quiz, 'name="answer"') && str_contains($quiz, 'required') && str_contains($quiz, 'action="/quiz?action=submit"'),
    'Prepared browser locators use the updated practice action label' => str_contains($browserJourney, "name: 'Start practice'") && str_contains($browserVisual, "name: 'Start practice'") && !str_contains($browserJourney, "name: 'Start quiz'"),
    'Results retain labeled review and score summary regions' => str_contains($result, 'aria-label="Quiz score summary"') && str_contains($result, 'aria-labelledby="answer-review-heading"'),
    'Study library and board detail include breadcrumb landmarks' => str_contains($study, 'aria-label="Breadcrumb"') && str_contains($board, 'aria-label="Breadcrumb"'),
    'Empty history action leads to valid practice setup' => str_contains($history, 'class="button button-green" href="/quiz"'),
    'Privacy export, CSRF-protected deletion, and explicit confirmation remain available' => str_contains($privacy, 'href="/privacy/export"') && str_contains($privacy, 'action="/privacy/data/clear"') && str_contains($privacy, 'name="csrf_token"') && str_contains($privacy, 'name="confirm_clear"'),
    'Touch targets, focus, reduced motion, and long-content handling are styled' => str_contains($css, '.button,button{min-height:48px') && str_contains($baseCss, ':focus-visible') && str_contains($css, 'prefers-reduced-motion:reduce') && str_contains($css, '.question-table-wrap'),
    'CSS braces are balanced' => substr_count($css, '{') === substr_count($css, '}') && substr_count($baseCss, '{') === substr_count($baseCss, '}'),
];

$failed = 0;
foreach ($checks as $description => $passed) {
    printf("%s %s\n", $passed ? 'PASS' : 'FAIL', $description);
    $failed += $passed ? 0 : 1;
}

printf("RESULT %d/%d checks passed\n", count($checks) - $failed, count($checks));
exit($failed === 0 ? 0 : 1);
