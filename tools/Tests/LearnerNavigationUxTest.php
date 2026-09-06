<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);

$read = static function (string $path) use ($root): string {
    $contents = file_get_contents($root . '/' . $path);
    if ($contents === false) {
        fwrite(STDERR, "[FAIL] Cannot read {$path}.\n");
        exit(1);
    }

    return $contents;
};

$dashboard = $read('app/Views/dashboard/index.php');
$study = $read('app/Views/study/index.php');
$library = $read('app/Views/study/library.php');
$progress = $read('app/Views/progress/index.php');
$flashcards = $read('app/Views/flashcards/index.php');
$review = $read('app/Views/flashcards/review.php');
$quiz = $read('app/Views/quiz/index.php');
$exam = $read('app/Views/home/exam.php');

$checks = [
    'dashboard keeps weak-topic review discoverable' =>
        str_contains($dashboard, '/flashcards/review?mode=weakness'),
    'study empty states offer practice' =>
        substr_count($study, 'href="/quiz">Start a practice quiz</a>') >= 5,
    'study library offers practice and flashcards' =>
        str_contains($library, 'href="/quiz">Practice now</a>')
        && str_contains($library, 'href="/flashcards">Review flashcards</a>'),
    'progress exposes quiz next step' =>
        str_contains($progress, 'No completed quizzes yet.')
        && str_contains($progress, 'href="/quiz">Practice now</a>'),
    'flashcard empty state has return actions' =>
        str_contains($flashcards, 'href="/study">Return to study</a>')
        && str_contains($flashcards, 'href="/dashboard">Dashboard</a>'),
    'flashcard review has contextual return actions' =>
        str_contains($review, 'href="/progress">Progress</a>')
        && str_contains($review, 'href="/dashboard">Dashboard</a>'),
    'quiz no-question state returns to setup' =>
        str_contains($quiz, 'href="/quiz">Return to practice setup</a>'),
    'exam empty state offers recovery actions' =>
        str_contains($exam, 'href="/exams">Choose an exam</a>')
        && str_contains($exam, 'href="/dashboard">Dashboard</a>'),
];

foreach ($checks as $label => $passed) {
    if (!$passed) {
        fwrite(STDERR, "[FAIL] {$label}\n");
        exit(1);
    }

    echo "[PASS] {$label}\n";
}

echo '[PASS] learner navigation UX contracts verified. Assertions: '
    . count($checks) . "\n";
