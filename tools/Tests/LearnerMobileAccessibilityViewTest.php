<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$layout = file_get_contents($root . '/app/Views/layouts/main.php');
$settings = file_get_contents($root . '/app/Views/quiz/settings.php');
$quiz = file_get_contents($root . '/app/Views/quiz/index.php');
$result = file_get_contents($root . '/app/Views/quiz/result.php');
$review = file_get_contents($root . '/app/Views/quiz/review.php');
$studyLibrary = file_get_contents($root . '/app/Views/study/library.php');
$home = file_get_contents($root . '/app/Views/home/index.php');
$history = file_get_contents($root . '/app/Views/history/index.php');
$progress = file_get_contents($root . '/app/Views/progress/index.php');
$flashcardReview = file_get_contents($root . '/app/Views/flashcards/review.php');
$privacy = file_get_contents($root . '/app/Views/data-handling/index.php');
$focus = file_get_contents($root . '/public/assets/js/quiz-focus.js');
$css = file_get_contents($root . '/public/assets/css/style.css');

preg_match_all('/<label\b[^>]*\bfor="(quiz-[^"]+)"/is', $settings, $quizLabelMatches);
preg_match_all('/<(?:input|select|textarea)\b[^>]*\bid="(quiz-[^"]+)"/is', $settings, $quizControlMatches);
$quizLabelTargets = $quizLabelMatches[1] ?? [];
$quizControlIds = $quizControlMatches[1] ?? [];
sort($quizLabelTargets);
sort($quizControlIds);

$checks = [
    'layout exposes a keyboard skip link' => str_contains($layout, 'href="#main-content"')
        && str_contains($layout, 'id="main-content"'),
    'primary navigation identifies landmark and current page' => str_contains($layout, 'aria-label="Primary navigation"')
        && str_contains($layout, 'aria-current="page"'),
    'document title is escaped' => str_contains($layout, 'htmlspecialchars((string) ($pageTitle'),
    'quiz settings labels target their corresponding controls' => count($quizLabelTargets) === 8
        && $quizLabelTargets === $quizControlIds,
    'answer choices use a labelled fieldset and full-size labels' => str_contains($quiz, '<fieldset class="quiz-choices">')
        && str_contains($quiz, '<legend>Choose one answer</legend>')
        && str_contains($quiz, 'class="quiz-choice" for="answer-'),
    'quiz feedback is announced' => str_contains($quiz, 'role="status" aria-live="polite"'),
    'quiz, review, and result transitions move keyboard focus to the update' => str_contains($quiz, '/assets/js/quiz-focus.js')
        && str_contains($review, '/assets/js/quiz-focus.js')
        && str_contains($result, '/assets/js/quiz-focus.js')
        && str_contains($focus, "target.focus({ preventScroll: true })"),
    'active attempts disclose replacement and provide an explicit resume action' => str_contains($settings, 'class="active-session-warning card"')
        && str_contains($settings, 'Starting the session below will end this unfinished attempt and discard its answers.')
        && str_contains($settings, 'Resume current session'),
    'study-library search is labelled and note disclosures meet touch sizing' => str_contains($studyLibrary, 'role="search"')
        && str_contains($studyLibrary, 'label for="study-library-search"')
        && str_contains($studyLibrary, '<summary>Read study notes and sources</summary>')
        && str_contains($css, '.study-material-disclosure>summary{display:flex;align-items:center;min-height:44px'),
    'review correctness has textual and structural state' => str_contains($result, 'Correct answer</strong>')
        && str_contains($result, 'Incorrect answer</strong>')
        && str_contains($result, 'is-correct'),
    'results group answer, correctness, explanation, and study links' => str_contains($result, 'aria-labelledby="answer-review-heading"')
        && str_contains($result, 'class="answer-review-explanation"')
        && str_contains($result, 'class="quiz-study-support"'),
    'home entry and learner history/progress actions have named destinations' => str_contains($home, 'href="/exams">Start studying</a>')
        && str_contains($history, 'href="/quiz">Start practice</a>')
        && str_contains($history, 'No completed quizzes yet')
        && str_contains($progress, 'href="/flashcards">Review flashcards</a>')
        && str_contains($progress, 'href="/history">View all history</a>'),
    'flashcard navigation and ratings expose keyboard-operable named controls' => str_contains($flashcardReview, 'aria-label="Flashcard navigation"')
        && str_contains($flashcardReview, 'aria-labelledby="flashcard-answer-heading"')
        && substr_count($flashcardReview, 'class="flashcard-rating flashcard-rating-') === 1
        && substr_count($flashcardReview, '<button type="submit" class="flashcard-rating') === 1,
    'privacy export and session deletion are distinct, confirmed, and scoped in the disclosure' => str_contains($privacy, 'Download my saved learning data')
        && str_contains($privacy, 'name="confirm_clear" value="yes" required')
        && str_contains($privacy, 'Clear my learning data')
        && str_contains($css, '.privacy-delete-form .form-field>label{display:flex;align-items:flex-start;gap:10px;min-height:44px')
        && str_contains($css, '.privacy-delete-form input[type=checkbox]{flex:0 0 20px')
        && str_contains($privacy, 'Deleting app records does not erase hosting access/error logs')
        && str_contains($privacy, 'Clearing browser cookies alone does not delete server-side records'),
    'quiz setup errors use an alert and keep explanatory copy with the settings' => str_contains($settings, 'role="alert"')
        && str_contains($settings, 'class="form-help mode-disclosure"'),
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
