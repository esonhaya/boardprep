<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Doctor/Project/BoardPrep/Simulation/HttpSimulator.php';
require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

use App\Storage\JsonStorage;
use App\Repositories\QuestionRepository;
use Tools\Doctor\Project\BoardPrep\Simulation\HttpSimulator;

$root = dirname(__DIR__, 2);
putenv('APP_ENV=production');
$http = new HttpSimulator($root . '/public/index.php');
$cookies = ['PHPSESSID' => 'guided-learning-' . bin2hex(random_bytes(6))];
$materials = json_decode((string) file_get_contents($root . '/storage/study-materials.json'), true, 512, JSON_THROW_ON_ERROR);
$lessons = json_decode((string) file_get_contents($root . '/storage/study-lessons.json'), true, 512, JSON_THROW_ON_ERROR);
$links = json_decode((string) file_get_contents($root . '/storage/study-question-links.json'), true, 512, JSON_THROW_ON_ERROR);

$lesson = null;
$material = null;
foreach ($lessons as $candidate) {
    if (is_array($candidate) && ($candidate['id'] ?? '') === 'let-english-reading-language-and-composition'
        && ($candidate['status'] ?? '') === 'published') {
        $lesson = $candidate;
        break;
    }
}
foreach ($materials as $candidate) {
    if (is_array($candidate) && ($candidate['id'] ?? '') === ($lesson['material_id'] ?? null)
        && ($candidate['status'] ?? 'published') === 'published') {
        $material = $candidate;
        break;
    }
}
if (!is_array($lesson) || !is_array($material)) {
    throw new RuntimeException('Published LET English lesson fixture is unavailable.');
}
$practiceIds = array_fill_keys(array_map('strval', (array) ($lesson['practice_question_ids'] ?? [])), true);
$sectionId = '';
$allowedSectionIds = [];
foreach ((array) ($lesson['sections'] ?? []) as $section) {
    if (!is_array($section) || !is_scalar($section['id'] ?? null)) continue;
    $sectionQuestionIds = [];
    foreach ($links as $link) {
        if (is_array($link) && ($link['material_id'] ?? '') === ($material['id'] ?? '')
            && (string) ($link['section_id'] ?? $link['lesson_section_id'] ?? '') === (string) $section['id']) {
            $questionId = is_scalar($link['question_id'] ?? null) ? (string) $link['question_id'] : '';
            if ($questionId !== '' && isset($practiceIds[$questionId])) $sectionQuestionIds[$questionId] = true;
        }
    }
    if ($sectionQuestionIds !== []) {
        $sectionId = (string) $section['id'];
        $allowedSectionIds = $sectionQuestionIds;
        break;
    }
}
if ($sectionId === '') {
    throw new RuntimeException('LET English lesson has no verified section-to-question mapping.');
}
$questionById = (new QuestionRepository(new JsonStorage($http->storagePath())))->findMany(array_keys($practiceIds));

$requireSuccess = static function (array $response, string $name): void {
    if (!$response['success'] || $response['status'] !== 200) {
        throw new RuntimeException($name . ' failed: HTTP ' . $response['status'] . ', exit ' . $response['exitCode']
            . ', ' . substr(preg_replace('/\s+/', ' ', strip_tags((string) $response['output'])) ?? '', 0, 300)
            . ', ' . substr(trim((string) $response['stderr']), -500));
    }
};

$board = $http->request('GET', '/exams', ['exam' => 'let'], [], [], $cookies);
$requireSuccess($board, 'Board to subject landing');
if (!str_contains($board['output'], 'Explore this subject’s study materials')
    || !str_contains($board['output'], 'subject=english')) {
    throw new RuntimeException('Board page does not offer subject-specific study materials.');
}

$subjectPage = $http->request('GET', '/study-library', ['exam' => 'let', 'subject' => 'english'], [], [], $cookies);
$requireSuccess($subjectPage, 'Scoped LET English study page');
if (!str_contains($subjectPage['output'], 'English study materials')
    || !str_contains($subjectPage['output'], 'let-english-reading-language-and-composition')
    || str_contains($subjectPage['output'], 'let-values-ethical-reasoning')) {
    throw new RuntimeException('Subject study page did not preserve its exact board and subject scope.');
}

$invalidSubject = $http->request('GET', '/study-library', ['exam' => 'architecture', 'subject' => 'english'], [], [], $cookies);
$requireSuccess($invalidSubject, 'Invalid board/subject recovery');
if (!str_contains($invalidSubject['output'], 'This board and subject combination is unavailable')
    || str_contains($invalidSubject['output'], 'LET English study materials')) {
    throw new RuntimeException('Invalid board/subject navigation was not rejected without unrelated material leakage.');
}
$invalidTopic = $http->request('GET', '/study-library', ['exam' => 'let', 'subject' => 'english', 'topic' => 'retired-section'], [], [], $cookies);
$requireSuccess($invalidTopic, 'Stale topic recovery');
if (!str_contains($invalidTopic['output'], 'This topic is no longer available in the selected subject')
    || str_contains($invalidTopic['output'], 'let-values-ethical-reasoning')) {
    throw new RuntimeException('Stale topic navigation expanded or leaked another subject.');
}

$detail = $http->request('GET', '/study-library', [
    'exam' => 'let', 'subject' => 'english', 'material' => (string) $material['id'],
], [], [], $cookies);
$requireSuccess($detail, 'Lesson reader');
$sectionAnchor = \App\Services\Study\StudyLibraryService::lessonSectionAnchorId((string) $lesson['id'], $sectionId);
if (!str_contains($detail['output'], 'What you will learn')
    || !str_contains($detail['output'], 'aria-label="Lesson sections"')
    || !str_contains($detail['output'], 'id="' . $sectionAnchor . '"')
    || !str_contains($detail['output'], 'Practice this concept')
    || !str_contains($detail['output'], 'Download searchable study PDF')
    || !str_contains($detail['output'], '/flashcards?board=let&amp;subject=english')) {
    throw new RuntimeException('Lesson reader is missing objectives, stable section navigation, scoped practice, PDF, or flashcard actions.');
}

$completeMappedPractice = static function (string $answerMode) use ($http, $cookies, $material, $sectionId, $sectionAnchor, $allowedSectionIds, $questionById, $requireSuccess): array {
    $start = $http->request('GET', '/study-library/practice', [
        'material' => (string) $material['id'], 'section' => $sectionId,
    ], [], [], $cookies);
    $requireSuccess($start, 'Lesson section practice start');
    if (!preg_match('/Question 1 \/ (\d+)/', $start['output'], $totalMatch)
        || !preg_match('/name="question_id"\s+value="([^"]+)"/', $start['output'], $idMatch)
        || !preg_match('/name="csrf_token"\s+value="([a-f0-9]{64})"/', $start['output'], $csrfMatch)) {
        throw new RuntimeException('Mapped lesson practice did not start a quiz session.');
    }
    $questionId = $idMatch[1];
    if (!isset($allowedSectionIds[$questionId], $questionById[$questionId])) {
        throw new RuntimeException('Lesson practice selected a question outside the exact mapped section pool.');
    }
    $question = $questionById[$questionId];
    $correct = null;
    foreach (array_values((array) ($question['choices'] ?? [])) as $index => $choice) {
        if (strcasecmp(trim((string) $choice), trim((string) ($question['answer'] ?? ''))) === 0) {
            $correct = chr(65 + $index);
            break;
        }
    }
    if ($correct === null) throw new RuntimeException('Mapped practice question has no valid answer choice.');
    $answer = $answerMode === 'correct' ? $correct : ($correct === 'A' ? 'B' : 'A');
    $submission = $http->request('POST', '/quiz', [], [
        'action' => 'submit', 'question_id' => $questionId, 'answer' => $answer, 'csrf_token' => $csrfMatch[1],
    ], [], $cookies);
    $requireSuccess($submission, ucfirst($answerMode) . ' lesson answer submission');
    $feedback = $answerMode === 'correct' ? 'Correct!' : 'Incorrect';
    if (!str_contains($submission['output'], $feedback)) {
        throw new RuntimeException($answerMode . ' answer feedback was not preserved.');
    }
    $finish = $http->request('POST', '/quiz', [], ['action' => 'finish', 'csrf_token' => $csrfMatch[1]], [], $cookies);
    if (!$finish['success'] || $finish['status'] !== 303) {
        throw new RuntimeException('Lesson practice did not finish cleanly: HTTP ' . $finish['status']);
    }
    $result = $http->request('GET', '/quiz', ['action' => 'result'], [], [], $cookies);
    $requireSuccess($result, 'Lesson practice answer review');
    if (!str_contains($result['output'], 'Open supporting lesson section:')
        || !str_contains($result['output'], $sectionAnchor)
        || !str_contains($result['output'], 'Return to the supporting lesson')) {
        throw new RuntimeException('Answer review lost its exact lesson section or return-to-study action.');
    }
    return $result;
};

$sectionAnchor = \App\Services\Study\StudyLibraryService::lessonSectionAnchorId((string) $lesson['id'], $sectionId);
$completeMappedPractice('correct');
$completeMappedPractice('incorrect');

$dynamicLesson = null;
foreach ($lessons as $candidate) {
    if (is_array($candidate) && ($candidate['id'] ?? '') === 'psychometrician-abnormal-psychology-classification-and-assessment'
        && ($candidate['status'] ?? '') === 'published') {
        $dynamicLesson = $candidate;
        break;
    }
}
if (!is_array($dynamicLesson) || empty($dynamicLesson['practice_question_ids'])) {
    throw new RuntimeException('Published PLE lesson with a dynamic exact-subject practice pool is unavailable.');
}
$dynamicPracticeIds = array_fill_keys(array_map('strval', (array) $dynamicLesson['practice_question_ids']), true);
$dynamicStart = $http->request('GET', '/study-library/practice', [
    'material' => (string) $dynamicLesson['material_id'],
], [], [], ['PHPSESSID' => 'guided-ple-' . bin2hex(random_bytes(6))]);
$requireSuccess($dynamicStart, 'Dynamic PLE lesson practice start');
if (!preg_match('/name="question_id"\s+value="([^"]+)"/', $dynamicStart['output'], $dynamicQuestionMatch)
    || !isset($dynamicPracticeIds[$dynamicQuestionMatch[1]])) {
    throw new RuntimeException('Dynamic PLE practice did not stay inside its published lesson question pool.');
}

$noPracticePage = $http->request('GET', '/study-library', [
    'exam' => 'let', 'subject' => 'filipino', 'material' => 'let-filipino-pagbasa-wika-at-pagsulat',
], [], [], $cookies);
$requireSuccess($noPracticePage, 'Lesson with no mapped practice');
if (!str_contains($noPracticePage['output'], 'No lesson-mapped practice questions are available yet')
    || str_contains($noPracticePage['output'], '/study-library/practice?material=let-filipino-pagbasa-wika-at-pagsulat')) {
    throw new RuntimeException('A lesson without a mapped question pool offered a misleading practice action.');
}
$noPoolStart = $http->request('GET', '/study-library/practice', ['material' => 'let-filipino-pagbasa-wika-at-pagsulat'], [], [], $cookies);
if (!$noPoolStart['success'] || $noPoolStart['status'] !== 303
    || !str_contains((string) ($noPoolStart['location'] ?? ''), 'material=let-filipino-pagbasa-wika-at-pagsulat')) {
    throw new RuntimeException('Unavailable lesson practice did not return to its lesson with an honest empty state.');
}

$flashcards = $http->request('GET', '/flashcards', ['board' => 'let', 'subject' => 'english'], [], [], $cookies);
$requireSuccess($flashcards, 'Contextual subject flashcards');
$history = $http->request('GET', '/history', [], [], [], $cookies);
$requireSuccess($history, 'Anonymous learner history restore');
$progress = $http->request('GET', '/progress', [], [], [], $cookies);
$requireSuccess($progress, 'Anonymous learner progress');
$studyPlan = $http->request('GET', '/study', [], [], [], $cookies);
$requireSuccess($studyPlan, 'Continue learning plan');
$otherLearner = $http->request('GET', '/history', [], [], [], ['PHPSESSID' => 'guided-other-' . bin2hex(random_bytes(6))]);
$requireSuccess($otherLearner, 'Separate anonymous learner history');
if (!str_contains($history['output'], 'Quiz History')
    || !str_contains($progress['output'], 'Progress')
    || !str_contains($studyPlan['output'], 'Study Dashboard')
    || !str_contains($otherLearner['output'], 'No completed quizzes yet')) {
    throw new RuntimeException('Practice history was not persisted or leaked across anonymous learner sessions.');
}
$attempts = (new JsonStorage($http->storagePath()))->all('attempts');
$completed = array_values(array_filter($attempts, static fn(mixed $attempt): bool => is_array($attempt)
    && strtolower((string) ($attempt['board'] ?? $attempt['exam'] ?? '')) === 'let'
    && strtolower((string) ($attempt['subject'] ?? '')) === 'english'));
$ownerValues = [];
foreach ($completed as $attempt) {
    $owner = trim((string) ($attempt['user_id'] ?? ''));
    if ($owner !== '') $ownerValues[$owner] = true;
}
$owners = array_keys($ownerValues);
if (count($completed) !== 2 || count($owners) !== 1 || $owners[0] === '') {
    throw new RuntimeException('Correct and incorrect practice attempts were not saved to isolated learner storage.');
}

echo "[PASS] Board, subject, lesson sections, PDF action, and scope rejection verified over production HTTP.\n";
echo "[PASS] Exact lesson-section practice, correct/incorrect review, return-to-lesson, scoped flashcards, and anonymous history isolation verified.\n";
