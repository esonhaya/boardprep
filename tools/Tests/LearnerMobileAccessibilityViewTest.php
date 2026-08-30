<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$layout = file_get_contents($root . '/app/Views/layouts/main.php');
$settings = file_get_contents($root . '/app/Views/quiz/settings.php');
$quiz = file_get_contents($root . '/app/Views/quiz/index.php');
$result = file_get_contents($root . '/app/Views/quiz/result.php');
$css = file_get_contents($root . '/public/assets/css/style.css');

$checks = [
    'layout exposes a keyboard skip link' => str_contains($layout, 'href="#main-content"')
        && str_contains($layout, 'id="main-content"'),
    'primary navigation identifies landmark and current page' => str_contains($layout, 'aria-label="Primary navigation"')
        && str_contains($layout, 'aria-current="page"'),
    'document title is escaped' => str_contains($layout, 'htmlspecialchars((string) ($pageTitle'),
    'quiz settings labels are explicitly associated' => preg_match_all('/<label for="quiz-[^"]+">/', $settings) === 4
        && preg_match_all('/id="quiz-[^"]+"/', $settings) === 5,
    'answer choices use a labelled fieldset and full-size labels' => str_contains($quiz, '<fieldset class="quiz-choices">')
        && str_contains($quiz, '<legend>Choose one answer</legend>')
        && str_contains($quiz, 'class="quiz-choice" for="answer-'),
    'quiz feedback is announced' => str_contains($quiz, 'role="status" aria-live="polite"'),
    'review correctness has textual and structural state' => str_contains($result, 'Correct answer</strong>')
        && str_contains($result, 'Incorrect answer</strong>')
        && str_contains($result, 'is-correct'),
    'mobile navigation and controls meet touch sizing contract' => str_contains($css, 'grid-template-columns:repeat(2,minmax(0,1fr))')
        && substr_count($css, 'min-height:44px') >= 4,
    'long learner content can wrap safely' => str_contains($css, 'overflow-wrap:anywhere'),
    'keyboard focus remains visible' => str_contains($css, ':focus-visible'),
];

foreach ($checks as $label => $passed) {
    if (!$passed) {
        fwrite(STDERR, "[FAIL] {$label}\n");
        exit(1);
    }

    echo "[PASS] {$label}\n";
}

echo '[PASS] learner mobile accessibility view contracts verified. Assertions: '
    . count($checks) . "\n";
