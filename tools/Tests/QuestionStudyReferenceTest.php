<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

$root = dirname(__DIR__, 2);
$storage = sys_get_temp_dir() . '/boardprep-question-study-' . bin2hex(random_bytes(6));
if (!mkdir($storage, 0700, true)) {
    throw new RuntimeException('Unable to create isolated question-study fixture.');
}
foreach (['study-materials.json', 'study-question-links.json'] as $file) {
    if (!copy($root . '/storage/' . $file, $storage . '/' . $file)) {
        throw new RuntimeException('Unable to seed question-study fixture.');
    }
}

putenv('APP_ENV=production');
putenv('APP_DEBUG=false');
putenv('APP_TIMEZONE=UTC');
putenv('APP_STORAGE_PATH=' . $storage);
putenv('DB_DRIVER=json');
\App\Core\App::boot();

$questions = json_decode((string) file_get_contents($root . '/storage/questions.json'), true, 512, JSON_THROW_ON_ERROR);
$question = $questions[0] ?? null;
if (!is_array($question) || (string) ($question['id'] ?? '') !== '1') {
    throw new RuntimeException('The deterministic linked-question fixture is unavailable.');
}

$_SESSION = [];
$referencesByQuestion = \App\Services\Study\StudyLibraryService::forQuestions(['1']);
$questionReferences = \App\Services\Study\StudyLibraryService::forQuestion('1');

try {
    ob_start();
    \App\Core\View::render('quiz/index', [
        'question' => $question,
        'current' => 0,
        'total' => 1,
        'mode' => 'practice',
        'feedback' => ['correct' => false],
        'studyReferences' => $questionReferences,
    ]);
    $practiceHtml = (string) ob_get_clean();
    if (!str_contains($practiceHtml, '/study-library?material=let-english-communication-review')
        || !str_contains($practiceHtml, 'English Communication Essentials')) {
        throw new RuntimeException('Practice feedback did not render the question-linked study material.');
    }

    ob_start();
    \App\Core\View::render('quiz/result', [
        'summary' => ['score' => 0, 'total' => 1, 'percentage' => 0],
        'review' => [[
            'question' => $question,
            'userAnswer' => 'B',
            'correctAnswer' => 'A',
            'correct' => false,
            'answered' => true,
        ]],
        'studyReferencesByQuestion' => $referencesByQuestion,
    ]);
    $resultHtml = (string) ob_get_clean();
    if (!str_contains($resultHtml, '/study-library?material=let-english-communication-review')
        || !str_contains($resultHtml, 'Open source reference: English Communication Essentials')
        || !str_contains($resultHtml, 'it is not a BoardPrep lesson mapping')) {
        throw new RuntimeException('Result review did not distinguish the question-linked source reference from lesson instruction.');
    }

    echo "[PASS] Practice explanations and result review link to the exact published study materials for linked questions.\n";
} finally {
    foreach (glob($storage . '/*') ?: [] as $file) {
        if (is_file($file)) {
            @unlink($file);
        }
    }
    @rmdir($storage);
    putenv('APP_ENV');
    putenv('APP_DEBUG');
    putenv('APP_TIMEZONE');
    putenv('APP_STORAGE_PATH');
    putenv('DB_DRIVER');
}
