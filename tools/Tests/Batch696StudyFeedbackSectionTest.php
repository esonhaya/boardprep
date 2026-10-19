<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

putenv('APP_ENV=testing');
putenv('APP_DEBUG=false');
\App\Core\App::boot();

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$questionId = '1162';
$reference = null;
foreach (\App\Services\Study\StudyLibraryService::forQuestion($questionId) as $candidate) {
    if (is_array($candidate) && trim((string) ($candidate['section_id'] ?? '')) !== '') {
        $reference = $candidate;
        break;
    }
}
$assert(is_array($reference), 'The selected question has no section-specific study reference.');
$url = (string) ($reference['url'] ?? '');
$materialId = (string) ($reference['material_id'] ?? '');
$fragment = parse_url($url, PHP_URL_FRAGMENT);
$assert($materialId !== '' && is_string($fragment) && $fragment !== '', 'The study reference does not include a lesson section fragment.');

$material = \App\Services\Study\StudyLibraryService::find($materialId);
$assert(is_array($material) && is_array($material['lesson'] ?? null), 'The linked published lesson could not be loaded.');
$lesson = $material['lesson'];
$assert((string) ($lesson['id'] ?? '') === (string) ($reference['lesson_id'] ?? ''), 'The study reference points to a different lesson than its published material.');
$scopedReferences = \App\Services\Study\StudyLibraryService::forQuestion(
    $questionId,
    (string) ($lesson['board_id'] ?? ''),
    (string) ($lesson['subject_id'] ?? '')
);
$assert(in_array($materialId, array_column($scopedReferences, 'material_id'), true), 'The eligible board/subject scope lost its supporting lesson.');
$wrongScopeReferences = \App\Services\Study\StudyLibraryService::forQuestion(
    $questionId,
    (string) ($lesson['board_id'] ?? ''),
    'ce-structural'
);
$assert(!in_array($materialId, array_column($wrongScopeReferences, 'material_id'), true), 'A question lesson leaked into a different subject scope.');
$matchingSection = false;
foreach ((array) ($lesson['sections'] ?? []) as $section) {
    if (is_array($section) && (string) ($section['id'] ?? '') === (string) ($reference['section_id'] ?? '')) {
        $matchingSection = true;
        break;
    }
}
$assert($matchingSection, 'The linked lesson section does not exist.');

ob_start();
\App\Core\View::render('study/library', [
    'materials' => [$material],
    'materialRequested' => true,
]);
$lessonHtml = (string) ob_get_clean();
$assert(str_contains($lessonHtml, 'id="' . htmlspecialchars($fragment, ENT_QUOTES, 'UTF-8') . '"'), 'The lesson reader does not render the linked section anchor.');

$question = null;
foreach (\App\Core\App::storage()->all('questions') as $candidate) {
    if (is_array($candidate) && (string) ($candidate['id'] ?? '') === $questionId) {
        $question = $candidate;
        break;
    }
}
$assert(is_array($question), 'The deterministic linked question is unavailable.');
ob_start();
\App\Core\View::render('quiz/result', [
    'summary' => ['score' => 0, 'total' => 1, 'percentage' => 0],
    'review' => [[
        'question' => $question,
        'userAnswer' => 'incorrect test response',
        'correct' => false,
        'answered' => true,
    ]],
    'studyReferencesByQuestion' => [$questionId => [$reference]],
]);
$reviewHtml = (string) ob_get_clean();
$assert(str_contains($reviewHtml, 'href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"'), 'Answer review does not link directly to the verified supporting lesson section.');

echo "[PASS] Practice answer review routes question {$questionId} to the rendered lesson section {$fragment}.\n";
