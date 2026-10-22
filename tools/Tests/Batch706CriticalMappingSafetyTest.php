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
use App\Core\View;
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

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) throw new RuntimeException($message);
};
$readJson = static function (string $path) use ($root): array {
    $decoded = json_decode((string) file_get_contents($root . '/' . $path), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($decoded)) throw new RuntimeException('Invalid JSON: ' . $path);
    return $decoded;
};
$questionById = [];
foreach ($readJson('storage/questions.json') as $question) {
    if (is_array($question) && isset($question['id'])) $questionById[(string) $question['id']] = $question;
}
$links = App::storage()->all('study-question-links');
$lessons = App::storage()->all('study-lessons');
$lessonById = [];
foreach ($lessons as $lesson) if (is_array($lesson)) $lessonById[(string) ($lesson['id'] ?? '')] = $lesson;
$exactNursing = [];
foreach ($links as $link) {
    if (!is_array($link) || empty($link['lesson_id']) || empty($link['section_id'])) continue;
    if (strtolower((string) ($lessonById[(string) $link['lesson_id']]['board_id'] ?? '')) === 'nursing') {
        $exactNursing[(string) ($link['question_id'] ?? '')] = $link;
    }
}
$assert(count($exactNursing) === 47, 'Expected 47 current Nursing exact mappings after two source-only quarantines.');

$eligibility = QuestionEligibilityService::index();
foreach (['1018', '1536', '1537'] as $questionId) {
    $question = $questionById[$questionId] ?? null;
    $assert(is_array($question), 'Canonical question missing: ' . $questionId);
    $assert(QuestionEligibilityService::forExam($question, 'nursing', $eligibility) !== null, 'Safety review changed Nursing exam eligibility: ' . $questionId);
}
$assert(QuestionEligibilityService::forExam($questionById['1536'], 'let', $eligibility) === null, 'Dose question leaked into LET eligibility.');
$assert(hash_file('sha256', $root . '/storage/questions.json') === '73cdc775cd7240b57898868d67902d833db1171f947c8dcc89081ddaf46fb4be', 'Canonical question bytes or answer keys changed.');

$assert(isset($exactNursing['1018']), 'Q1018 exact section relationship was removed despite direct section support.');
$assert(($exactNursing['1018']['section_id'] ?? '') === 'b703-self-harm-statement-and-safety', 'Q1018 exact safety section changed unexpectedly.');
$assert(($exactNursing['1018']['semantic_review_status'] ?? '') === 'SECTION_AND_SOURCE_CHECKED_PENDING_INDEPENDENT_ACADEMIC_REVIEW', 'Q1018 was promoted beyond independent review.');
$assert(($questionById['1018']['taxonomy']['concept_id'] ?? '') === 'nle-np-v-teamwork', 'Q1018 taxonomy was silently changed.');

foreach (['1536', '1537'] as $questionId) {
    $assert(!isset($exactNursing[$questionId]), 'Dose-basis-ambiguous question retains an exact Nursing teaching relationship: ' . $questionId);
    $sourceOnly = array_values(array_filter($links, static fn(mixed $link): bool => is_array($link)
        && (string) ($link['question_id'] ?? '') === $questionId
        && !empty($link['source_ids'])
        && empty($link['lesson_id']) && empty($link['section_id'])));
    $assert(count($sourceOnly) === 1, 'Source-only provenance was not preserved for ' . $questionId);
    $assert(count((array) ($sourceOnly[0]['source_ids'] ?? [])) === 2, 'Dose calculation references were not preserved for ' . $questionId);
    $assert(empty($sourceOnly[0]['review_guidance']), 'Source-only row retains misleading exact-section guidance for ' . $questionId);
}

$medication = StudyLibraryService::find('nle-pharmacology-safety');
$assert(is_array($medication), 'Medication safety material is unavailable.');
$dosePool = StudyLibraryService::practiceQuestionIds($medication, 'b705-medication-weight-based-dose');
$assert($dosePool === [], 'Quarantined dose-basis questions remain in the exact section practice pool.');
$assert(StudyLibraryService::practiceQuestionIds($medication, 'missing-stale-section') === [], 'Stale section produced a fabricated practice pool.');
$medicationPool = StudyLibraryService::practiceQuestionIds($medication);
$assert(!in_array('1536', $medicationPool, true) && !in_array('1537', $medicationPool, true), 'Quarantined dose questions remain in the medication lesson pool.');

$crisis = StudyLibraryService::find('nle-b625-np-v-clinical-reasoning');
$assert(is_array($crisis), 'Crisis lesson material is unavailable.');
$crisisPool = StudyLibraryService::practiceQuestionIds($crisis, 'b703-self-harm-statement-and-safety');
$assert(in_array('1018', array_map('strval', $crisisPool), true), 'Q1018 lost its technically supported exact section practice relationship.');
$assert(count($crisisPool) >= 1, 'Q1018 section practice pool unexpectedly became empty.');

$references = StudyLibraryService::forQuestions(['1018', '1536', '1537'], 'nursing', 'nursing-foundations');
foreach (['1536', '1537'] as $questionId) {
    $rows = (array) ($references[$questionId] ?? []);
    $assert($rows !== [], 'Source-only answer-review references disappeared for ' . $questionId);
    foreach ($rows as $reference) {
        $assert(($reference['lesson_id'] ?? '') === '' && ($reference['section_id'] ?? '') === '', 'Quarantined question still resolves an exact answer-review anchor: ' . $questionId);
        $assert(!str_contains((string) ($reference['url'] ?? ''), '#'), 'Source-only review reference contains a lesson anchor: ' . $questionId);
        $assert(($reference['source_references'] ?? []) !== [], 'Source-only review reference has no resolvable source: ' . $questionId);
    }
}
$q1018Anchor = StudyLibraryService::lessonSectionAnchorId('nursing-therapeutic-communication-and-crisis', 'b703-self-harm-statement-and-safety');
$assert(count(array_filter((array) ($references['1018'] ?? []), static fn(array $row): bool => ($row['section_id'] ?? '') === 'b703-self-harm-statement-and-safety' && str_contains((string) ($row['url'] ?? ''), $q1018Anchor))) === 1, 'Q1018 answer review no longer points to its exact teaching section.');

foreach (['1536', '1537'] as $questionId) {
    $_SESSION = [];
    ob_start();
    View::render('quiz/result', [
        'summary' => ['score' => 0, 'total' => 1, 'percentage' => 0],
        'review' => [[
            'question' => $questionById[$questionId],
            'userAnswer' => 'A',
            'correctAnswer' => (string) $questionById[$questionId]['answer'],
            'correct' => false,
            'answered' => true,
        ]],
        'studyReferencesByQuestion' => [$questionId => $references[$questionId] ?? []],
    ]);
    $reviewHtml = (string) ob_get_clean();
    $assert(str_contains($reviewHtml, 'This source association has no exact lesson section link.'), 'Dose answer review did not explain source-only status: ' . $questionId);
    $assert(!str_contains($reviewHtml, 'Open supporting lesson section:'), 'Dose answer review presents an unverified exact section: ' . $questionId);
}
$_SESSION = [];
ob_start();
View::render('quiz/result', [
    'summary' => ['score' => 1, 'total' => 1, 'percentage' => 100],
    'review' => [[
        'question' => $questionById['1018'],
        'userAnswer' => 'A',
        'correctAnswer' => (string) $questionById['1018']['answer'],
        'correct' => true,
        'answered' => true,
    ]],
    'studyReferencesByQuestion' => ['1018' => $references['1018'] ?? []],
]);
$q1018ReviewHtml = (string) ob_get_clean();
$assert(str_contains($q1018ReviewHtml, 'Open supporting lesson section:') && str_contains($q1018ReviewHtml, $q1018Anchor), 'Q1018 answer review lost its exact supporting section.');

$cookies = ['PHPSESSID' => 'batch706-nursing-' . bin2hex(random_bytes(6))];
$require = static function (array $response, int $status, string $description): void {
    if (!$response['success'] || $response['status'] !== $status) {
        throw new RuntimeException($description . ' failed: HTTP ' . $response['status'] . ', ' . substr(trim((string) ($response['stderr'] ?? '')), -400));
    }
};
$detail = $http->request('GET', '/study-library', [
    'exam' => 'nursing', 'subject' => 'nursing-foundations', 'material' => 'nle-pharmacology-safety',
], [], [], $cookies);
$require($detail, 200, 'Nursing medication lesson route');
$doseAnchor = StudyLibraryService::lessonSectionAnchorId('nursing-medication-safety-and-calculation', 'b705-medication-weight-based-dose');
$assert(str_contains($detail['output'], $doseAnchor), 'Dose calculation section is missing from the learner route.');
$assert(!str_contains($detail['output'], 'section=b705-medication-weight-based-dose'), 'Quarantined section still exposes an exact practice action.');
$emptyPractice = $http->request('GET', '/study-library/practice', [
    'material' => 'nle-pharmacology-safety', 'section' => 'b705-medication-weight-based-dose',
], [], [], $cookies);
$require($emptyPractice, 303, 'Quarantined dose section empty practice');
$assert(str_contains((string) ($emptyPractice['location'] ?? ''), 'nle-pharmacology-safety'), 'Empty exact practice did not return to the medication lesson.');
$stalePractice = $http->request('GET', '/study-library/practice', [
    'material' => 'nle-pharmacology-safety', 'section' => 'stale-section-anchor',
], [], [], $cookies);
$require($stalePractice, 303, 'Stale Nursing section recovery');

$crossBoard = $http->request('GET', '/study-library', [
    'exam' => 'let', 'subject' => 'nursing-foundations', 'material' => 'nle-pharmacology-safety',
], [], [], $cookies);
$require($crossBoard, 200, 'Cross-board scope rejection route');
$assert(!str_contains($crossBoard['output'], 'nle-pharmacology-safety'), 'Nursing material leaked into a LET subject route.');

$crisisHttp = $http->request('GET', '/study-library/practice', [
    'material' => 'nle-b625-np-v-clinical-reasoning', 'section' => 'b703-self-harm-statement-and-safety',
], [], [], $cookies);
$require($crisisHttp, 200, 'Q1018 exact Nursing practice route');
$assert(preg_match('/Question 1 \/ (\d+)/', $crisisHttp['output'], $countMatch) === 1, 'Q1018 section did not start a practice journey.');
$total = (int) $countMatch[1];
$assert($total >= 1 && $total <= 10, 'Q1018 section practice count is not bounded.');
$expectedPool = array_fill_keys(array_map('strval', $crisisPool), true);
$httpQuestionIds = [];
$sawCorrect = false;
$sawIncorrect = false;
$current = $crisisHttp;
for ($position = 0; $position < $total; $position++) {
    if (preg_match('/name="question_id"\s+value="([^"]+)"/', $current['output'], $idMatch) !== 1
        || preg_match('/name="csrf_token"\s+value="([a-f0-9]{64})"/', $current['output'], $csrfMatch) !== 1) {
        throw new RuntimeException('Nursing section practice omitted its current question or CSRF token.');
    }
    $questionId = $idMatch[1];
    if (!isset($expectedPool[$questionId], $questionById[$questionId])) throw new RuntimeException('Nursing section practice leaked an unlinked question: ' . $questionId);
    $httpQuestionIds[$questionId] = true;
    $question = $questionById[$questionId];
    $answerLetter = null;
    foreach ((array) ($question['choices'] ?? []) as $index => $choice) {
        if (strcasecmp(trim((string) $choice), trim((string) ($question['answer'] ?? ''))) === 0) {
            $answerLetter = chr(65 + $index);
            break;
        }
    }
    if ($answerLetter === null) throw new RuntimeException('Nursing practice question has no answer choice: ' . $questionId);
    $correct = $questionId === '1018' || $sawIncorrect;
    $submitted = $correct ? $answerLetter : ($answerLetter === 'A' ? 'B' : 'A');
    $response = $http->request('POST', '/quiz', [], [
        'action' => 'submit', 'question_id' => $questionId, 'answer' => $submitted, 'csrf_token' => $csrfMatch[1],
    ], [], $cookies);
    $require($response, 200, 'Nursing learner answer submission');
    if ($correct && !str_contains($response['output'], 'Correct!')) throw new RuntimeException('Correct Nursing answer was not confirmed.');
    if (!$correct && !str_contains($response['output'], 'Incorrect')) throw new RuntimeException('Incorrect Nursing answer was not reviewed.');
    $sawCorrect = $sawCorrect || $correct;
    $sawIncorrect = $sawIncorrect || !$correct;
    $current = $response;
    if ($position + 1 < $total) {
        if (preg_match('/name="csrf_token"\s+value="([a-f0-9]{64})"/', $current['output'], $nextCsrf) !== 1) throw new RuntimeException('Nursing answer feedback omitted navigation token.');
        $current = $http->request('POST', '/quiz', [], ['action' => 'next', 'csrf_token' => $nextCsrf[1]], [], $cookies);
        $require($current, 200, 'Next Nursing practice item');
    }
}
$assert(isset($httpQuestionIds['1018']), 'HTTP practice journey did not include Q1018.');
$assert($sawCorrect && $sawIncorrect, 'Nursing HTTP journey did not verify both correct and incorrect answers.');
$assert(count($httpQuestionIds) === $total, 'Nursing practice repeated a question unexpectedly.');
$lastCsrf = null;
if (preg_match('/name="csrf_token"\s+value="([a-f0-9]{64})"/', $current['output'], $finishCsrf) === 1) $lastCsrf = $finishCsrf[1];
if ($lastCsrf === null) throw new RuntimeException('Final Nursing feedback omitted finish token.');
$finish = $http->request('POST', '/quiz', [], ['action' => 'finish', 'csrf_token' => $lastCsrf], [], $cookies);
$require($finish, 303, 'Finish Nursing practice');
$result = $http->request('GET', '/quiz', ['action' => 'result'], [], [], $cookies);
$require($result, 200, 'Nursing answer review route');
$assert(str_contains($result['output'], $q1018Anchor), 'Nursing answer review did not return to Q1018 exact instruction.');

$repeat = $http->request('GET', '/study-library/practice', [
    'material' => 'nle-b625-np-v-clinical-reasoning', 'section' => 'b703-self-harm-statement-and-safety',
], [], [], ['PHPSESSID' => 'batch706-repeat-' . bin2hex(random_bytes(6))]);
$require($repeat, 200, 'Repeated Q1018 section practice');

$afterHash = hash_file('sha256', $root . '/storage/questions.json');
$assert($afterHash === '73cdc775cd7240b57898868d67902d833db1171f947c8dcc89081ddaf46fb4be', 'Learner HTTP fixture modified canonical questions.');
echo "Batch706CriticalMappingSafetyTest PASS ($assertions assertions; Q1018 retained pending review; Q1536/Q1537 source-only; Nursing eligibility, practice, answer review, empty/stale pool, repeat, and cross-board routes verified in isolated storage)\n";
