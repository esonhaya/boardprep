<?php

declare(strict_types=1);

putenv('APP_ENV=production');
putenv('APP_DEBUG=false');
putenv('APP_TIMEZONE=UTC');
putenv('DB_DRIVER=json');

require_once dirname(__DIR__) . '/Doctor/Project/BoardPrep/Simulation/HttpSimulator.php';
require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

use App\Core\App;
use App\Repositories\QuestionRepository;
use App\Services\Question\QuestionEligibilityService;
use App\Services\Study\StudyLibraryService;
use App\Storage\JsonStorage;
use Tools\Doctor\Project\BoardPrep\Simulation\HttpSimulator;

$root = dirname(__DIR__, 2);
$http = new HttpSimulator($root . '/public/index.php');
$storagePath = $http->storagePath();
putenv('APP_STORAGE_PATH=' . $storagePath);
putenv('APP_SESSION_PATH=' . $http->sessionPath());
App::boot();

$fail = static function (string $message): never {
    throw new RuntimeException($message);
};
$assert = static function (bool $condition, string $message) use ($fail): void {
    if (!$condition) $fail($message);
};

$lessons = json_decode((string) file_get_contents($root . '/storage/study-lessons.json'), true, 512, JSON_THROW_ON_ERROR);
$materials = json_decode((string) file_get_contents($root . '/storage/study-materials.json'), true, 512, JSON_THROW_ON_ERROR);
$sources = json_decode((string) file_get_contents($root . '/storage/sources.json'), true, 512, JSON_THROW_ON_ERROR);
$links = json_decode((string) file_get_contents($root . '/storage/study-question-links.json'), true, 512, JSON_THROW_ON_ERROR);
$sourceIds = array_fill_keys(array_map(static fn(array $source): string => (string) ($source['id'] ?? ''), $sources), true);
$materialById = [];
foreach ($materials as $material) if (is_array($material)) $materialById[(string) ($material['id'] ?? '')] = $material;
$lessonById = [];
foreach ($lessons as $lesson) if (is_array($lesson)) $lessonById[(string) ($lesson['id'] ?? '')] = $lesson;

$expected = [
    '1011' => ['nursing-prioritization-and-team-handoff', 'nle-v-prioritize-changing-risk'],
    '1012' => ['nursing-prioritization-and-team-handoff', 'nle-v-report-change-and-reassess'],
    '4094' => ['nursing-medication-safety-and-calculation', 'nle-medication-order-and-product-verification'],
    '4096' => ['nursing-medication-safety-and-calculation', 'nle-medication-order-and-product-verification'],
    '1517' => ['nursing-medication-safety-and-calculation', 'nle-medication-order-and-product-verification'],
    '4097' => ['nursing-medication-safety-and-calculation', 'nle-medication-concentration-calculation'],
    '4102' => ['nursing-medication-safety-and-calculation', 'nle-medication-weight-based-rate-units'],
    '5104' => ['nursing-medication-safety-and-calculation', 'nle-medication-reconcile-at-transition'],
    '5187' => ['nursing-medication-safety-and-calculation', 'nle-medication-change-teachback'],
];
$questions = (new QuestionRepository(new JsonStorage($storagePath)))->findMany(array_keys($expected));
$eligibility = QuestionEligibilityService::index();
$materialByLesson = [];
$expectedBySection = [];
foreach ($expected as $questionId => [$lessonId, $sectionId]) {
    $questionId = (string) $questionId;
    $lesson = $lessonById[$lessonId] ?? null;
    $assert(is_array($lesson) && ($lesson['status'] ?? '') === 'published', "Published lesson missing for question {$questionId}.");
    $materialId = (string) ($lesson['material_id'] ?? '');
    $material = $materialById[$materialId] ?? null;
    $assert(is_array($material) && ($material['status'] ?? '') === 'published', "Published material missing for question {$questionId}.");
    $materialByLesson[$lessonId] = StudyLibraryService::find($materialId);
    $assert(is_array($materialByLesson[$lessonId]), "Production study service cannot resolve {$materialId}.");
    $assert(in_array('nursing', array_map('strtolower', array_map('strval', (array) ($material['exams'] ?? []))), true), "Material {$materialId} is not Nursing scoped.");
    $assert(($materialByLesson[$lessonId]['pdf_url'] ?? null) === '/study-resources/' . (string) $lesson['pdf_file'], "Published PDF route is missing for {$lessonId}.");

    $section = null;
    foreach ((array) ($lesson['sections'] ?? []) as $candidate) {
        if (is_array($candidate) && ($candidate['id'] ?? '') === $sectionId) $section = $candidate;
    }
    $assert(is_array($section), "Stable section {$sectionId} is missing.");
    $assert(in_array($questionId, array_map('strval', StudyLibraryService::practiceQuestionIds($materialByLesson[$lessonId], $sectionId)), true), "Section practice pool excludes exact linked question {$questionId}.");
    $assert(in_array($questionId, array_map('strval', (array) ($lesson['practice_question_ids'] ?? [])), true), "Lesson practice pool excludes exact linked question {$questionId}.");
    $expectedBySection[$lessonId . "\0" . $sectionId]['material_id'] = $materialId;
    $expectedBySection[$lessonId . "\0" . $sectionId]['lesson_id'] = $lessonId;
    $expectedBySection[$lessonId . "\0" . $sectionId]['section_id'] = $sectionId;
    $expectedBySection[$lessonId . "\0" . $sectionId]['question_ids'][] = $questionId;

    $matching = array_values(array_filter($links, static fn(mixed $link): bool => is_array($link)
        && (string) ($link['material_id'] ?? '') === $materialId
        && (string) ($link['question_id'] ?? '') === $questionId
        && (string) ($link['lesson_id'] ?? '') === $lessonId
        && (string) ($link['section_id'] ?? '') === $sectionId));
    $assert(count($matching) === 1, "Question {$questionId} does not have exactly one selected section mapping.");
    $link = $matching[0];
    $assert(($link['semantic_review_status'] ?? '') === 'SECTION_AND_SOURCE_CHECKED_PENDING_INDEPENDENT_ACADEMIC_REVIEW', "Question {$questionId} was incorrectly marked academically approved.");
    foreach ((array) ($link['source_ids'] ?? []) as $sourceId) $assert(isset($sourceIds[(string) $sourceId]), "Question {$questionId} has an unregistered source {$sourceId}.");

    $question = $questions[$questionId] ?? null;
    $assert(is_array($question), "Canonical question {$questionId} is unavailable to production repository.");
    $assert((string) ($question['taxonomy']['board_id'] ?? '') === 'nursing', "Question {$questionId} no longer has canonical Nursing identity.");
    $placement = $eligibility->forExam($question, 'nursing');
    $assert(is_array($placement) && (string) ($placement['exam_id'] ?? '') === 'nursing', "Question {$questionId} is not eligible for Nursing.");
}
foreach ($expectedBySection as $scope) {
    $lessonId = $scope['lesson_id'];
    $actualIds = array_map('strval', StudyLibraryService::practiceQuestionIds($materialByLesson[$lessonId], $scope['section_id']));
    $expectedIds = array_values(array_unique($scope['question_ids']));
    sort($actualIds);
    sort($expectedIds);
    $assert($actualIds === $expectedIds, "Section {$scope['section_id']} exposes a question outside its exact map set.");
}

$references = StudyLibraryService::forQuestions(array_keys($expected), 'nursing', 'nursing-foundations');
foreach ($expected as $questionId => [$lessonId, $sectionId]) {
    $found = false;
    foreach ((array) ($references[$questionId] ?? []) as $reference) {
        if (($reference['lesson_id'] ?? '') === $lessonId
            && ($reference['section_id'] ?? '') === $sectionId
            && str_contains((string) ($reference['url'] ?? ''), rawurlencode(StudyLibraryService::lessonSectionAnchorId($lessonId, $sectionId)))) {
            $found = true;
            break;
        }
    }
    $assert($found, "Answer review reference for {$questionId} does not point back to the mapped section.");
}

$cookies = ['PHPSESSID' => 'batch702-nursing-' . bin2hex(random_bytes(6))];
$requireSuccess = static function (array $response, string $label) use ($fail): void {
    if (!$response['success'] || $response['status'] !== 200) {
        $fail($label . ' failed: HTTP ' . $response['status'] . ', ' . substr(preg_replace('/\\s+/', ' ', strip_tags((string) $response['output'])) ?? '', 0, 250));
    }
};
foreach (['nursing-prioritization-and-team-handoff', 'nursing-medication-safety-and-calculation'] as $lessonId) {
    $lesson = $lessonById[$lessonId];
    $materialId = (string) $lesson['material_id'];
    $detail = $http->request('GET', '/study-library', ['exam' => 'nursing', 'subject' => 'nursing-foundations', 'material' => $materialId], [], [], $cookies);
    $requireSuccess($detail, "Nursing lesson reader {$lessonId}");
    $assert(str_contains($detail['output'], 'What you will learn') && str_contains($detail['output'], 'aria-label="Lesson sections"'), "Lesson {$lessonId} lacks visible objectives or section navigation.");
    $assert(str_contains($detail['output'], 'Download searchable study PDF') && str_contains($detail['output'], '/flashcards?board=nursing&amp;subject=nursing-foundations') && str_contains($detail['output'], 'Continue your study plan'), "Lesson {$lessonId} lacks the established PDF, flashcard, or continue-learning path.");
    foreach ((array) $lesson['sections'] as $section) {
        $anchor = StudyLibraryService::lessonSectionAnchorId($lessonId, (string) $section['id']);
        $assert(str_contains($detail['output'], 'id="' . $anchor . '"'), "Lesson section anchor does not render: {$anchor}.");
    }
}

$foreignScope = $http->request('GET', '/study-library', ['exam' => 'let', 'subject' => 'english', 'material' => 'nle-pharmacology-safety'], [], [], $cookies);
$requireSuccess($foreignScope, 'Cross-board material rejection');
$assert(!str_contains($foreignScope['output'], 'Medication Safety: Verify, Calculate, Reconcile, and Explain'), 'Nursing lesson leaked into LET English scope.');

$completeMappedPractice = static function (string $questionId) use ($http, $cookies, $questions, $expected, $materialByLesson, $requireSuccess, $assert): void {
    [$lessonId, $sectionId] = $expected[$questionId];
    $materialId = (string) ($materialByLesson[$lessonId]['id'] ?? '');
    if ($materialId === '') throw new RuntimeException('Missing mapped material for production practice simulation.');
    $start = $http->request('GET', '/study-library/practice', ['material' => $materialId, 'section' => $sectionId], [], [], $cookies);
    $requireSuccess($start, "Mapped practice start {$questionId}");
    if (!preg_match('/name="question_id"\s+value="([^"]+)"/', $start['output'], $idMatch)
        || !preg_match('/name="csrf_token"\s+value="([a-f0-9]{64})"/', $start['output'], $csrfMatch)) {
        throw new RuntimeException("Mapped practice {$questionId} did not start a question session.");
    }
    $selectedId = $idMatch[1];
    $assert($selectedId === $questionId, "Section {$sectionId} selected unrelated question {$selectedId}.");
    $question = $questions[$selectedId];
    $answerLetter = null;
    foreach (array_values((array) ($question['choices'] ?? [])) as $index => $choice) {
        if (strcasecmp(trim((string) $choice), trim((string) ($question['answer'] ?? ''))) === 0) $answerLetter = chr(65 + $index);
    }
    if ($answerLetter === null) throw new RuntimeException("Question {$selectedId} answer is not represented by a choice.");
    $submission = $http->request('POST', '/quiz', [], ['action' => 'submit', 'question_id' => $selectedId, 'answer' => $answerLetter, 'csrf_token' => $csrfMatch[1]], [], $cookies);
    $requireSuccess($submission, "Answer feedback {$questionId}");
    if (!str_contains($submission['output'], 'Correct!')) throw new RuntimeException("Answer feedback was not rendered for {$questionId}.");
    $finish = $http->request('POST', '/quiz', [], ['action' => 'finish', 'csrf_token' => $csrfMatch[1]], [], $cookies);
    if (!$finish['success'] || $finish['status'] !== 303) throw new RuntimeException("Mapped practice did not finish for {$questionId}.");
    $review = $http->request('GET', '/quiz', ['action' => 'result'], [], [], $cookies);
    $requireSuccess($review, "Answer review {$questionId}");
    $anchor = StudyLibraryService::lessonSectionAnchorId($lessonId, $sectionId);
    $assert(str_contains($review['output'], 'Open supporting lesson section:') && str_contains($review['output'], rawurlencode($anchor)) && str_contains($review['output'], 'Return to the supporting lesson'), "Answer review {$questionId} lost its precise return-to-section navigation.");
};

$completeMappedPractice('1011');
$completeMappedPractice('4097');

echo "[PASS] Two Nursing lessons render under the correct subject, with stable anchors, source links, PDFs, and continuation actions.\n";
echo "[PASS] Nine exact-section mappings resolve through production study and Nursing eligibility services; review remains pending and question identity is preserved.\n";
echo "[PASS] Nursing practice, correct-answer review, and return-to-section navigation work for prioritization and dosage calculation.\n";
echo "[PASS] Nursing lesson materials are excluded from LET English scope. Learner mutations stayed in the simulator fixture.\n";
