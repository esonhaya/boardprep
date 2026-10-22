<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Doctor/Project/BoardPrep/Simulation/HttpSimulator.php';
require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

use App\Services\Question\QuestionEligibilityIndex;
use App\Services\Question\QuestionEligibilityService;
use App\Services\Question\QuestionReuseEligibilityResolver;
use App\Storage\JsonStorage;
use Tools\Doctor\Project\BoardPrep\Simulation\HttpSimulator;

$root = dirname(__DIR__, 2);
$fixturePath = sys_get_temp_dir() . '/boardprep-ten-board-' . bin2hex(random_bytes(6));
$source = new JsonStorage($root . '/storage');
$sourcesById = [];
foreach ($source->all('sources') as $sourceRecord) {
    if (is_array($sourceRecord) && is_scalar($sourceRecord['id'] ?? null)) {
        $sourcesById[(string) $sourceRecord['id']] = $sourceRecord;
    }
}
$boards = $source->all('boards');
$questions = $source->all('questions');
$eligibilityRows = $source->all('question-eligibility');
$blueprints = $source->all('blueprints');
$overrides = $source->all('question-reuse-overrides');
$questionLinks = $source->all('study-question-links');
$materials = $source->all('study-materials');
$materialsById = [];
foreach ($materials as $material) {
    if (is_array($material) && is_scalar($material['id'] ?? null)) {
        $materialsById[(string) $material['id']] = $material;
    }
}
$lessonsByMaterial = [];
foreach ($source->all('study-lessons') as $lesson) {
    if (is_array($lesson) && ($lesson['status'] ?? '') === 'published') {
        $materialId = is_scalar($lesson['material_id'] ?? null) ? (string) $lesson['material_id'] : '';
        if ($materialId !== '') $lessonsByMaterial[$materialId] ??= $lesson;
    }
}
$linksByQuestion = [];
foreach ($questionLinks as $link) {
    if (is_array($link) && is_scalar($link['question_id'] ?? null)) {
        $material = $materialsById[(string) ($link['material_id'] ?? '')] ?? null;
        if (is_array($material) && ($material['status'] ?? 'published') === 'published') {
            $linksByQuestion[(string) $link['question_id']][] = $link;
        }
    }
}
$eligibilityIndex = new QuestionEligibilityIndex(
    $eligibilityRows,
    $blueprints,
    QuestionReuseEligibilityResolver::productionDerivedRelationships(),
    $overrides
);
$selectedQuestions = [];
$selectedById = [];
$selectedQuestionRecords = [];
$selectedByBoard = [];

foreach ($boards as $board) {
    if (!is_array($board)) {
        continue;
    }
    $boardId = (string) ($board['id'] ?? '');
    $selected = null;
    foreach ($questions as $question) {
        if (!is_array($question)) {
            continue;
        }
        $questionId = trim((string) ($question['id'] ?? ''));
        if ($questionId === '' || isset($selectedById[$questionId])
            || !isset($linksByQuestion[$questionId])
            || !is_array($question['choices'] ?? null)
            || count($question['choices']) < 2
            || !is_scalar($question['answer'] ?? null)
            || !is_scalar($question['explanation'] ?? null)) {
            continue;
        }
        $placement = QuestionEligibilityService::forExam($question, $boardId, $eligibilityIndex);
        if (!is_array($placement) || trim((string) ($placement['subject_id'] ?? '')) === '') {
            continue;
        }
        $hasContextualStudyReference = false;
        foreach ($linksByQuestion[$questionId] ?? [] as $link) {
            $materialId = (string) ($link['material_id'] ?? '');
            $material = $materialsById[$materialId] ?? null;
            if (!is_array($material)) continue;
            $lesson = $lessonsByMaterial[$materialId] ?? null;
            if (is_array($lesson)) {
                $hasContextualStudyReference = strtolower((string) ($lesson['board_id'] ?? '')) === strtolower($boardId)
                    && strtolower((string) ($lesson['subject_id'] ?? '')) === strtolower((string) ($placement['subject_id'] ?? ''));
            } else {
                foreach ((array) ($material['exams'] ?? []) as $exam) {
                    if (!is_scalar($exam)) continue;
                    $normalizedExam = strtolower(trim((string) $exam));
                    $normalizedExam = ['cse'=>'civil-service','cle'=>'criminologist','nle'=>'nursing','ple'=>'psychometrician'][$normalizedExam] ?? $normalizedExam;
                    if ($normalizedExam === strtolower($boardId)) {
                        $hasContextualStudyReference = true;
                        break;
                    }
                }
            }
            if ($hasContextualStudyReference) break;
        }
        if (!$hasContextualStudyReference) continue;
        $selected = $question;
        $selectedQuestions[] = $question;
        $selectedById[$questionId] = true;
        $selectedQuestionRecords[$questionId] = $question;
        $selectedByBoard[$boardId] = $question;
        break;
    }
    if (!is_array($selected)) {
        throw new RuntimeException('Unable to select a distinct source-linked eligible question for ' . $boardId . '.');
    }
}

if (count($boards) !== 10 || count($selectedQuestions) !== 10) {
    throw new RuntimeException('The ten-board journey fixture is incomplete.');
}
if (!mkdir($fixturePath, 0700, true)) {
    throw new RuntimeException('Unable to create isolated ten-board fixture.');
}

$replacedCollections = [
    'questions', 'question-eligibility', 'question-reuse-overrides',
    'question-reuse-metadata', 'question-statistics', 'attempts',
    'weakness', 'flashcard_state',
];
foreach (glob($root . '/storage/*.json') ?: [] as $file) {
    $collection = basename($file, '.json');
    if (in_array($collection, $replacedCollections, true)) {
        continue;
    }
    if (!copy($file, $fixturePath . '/' . basename($file))) {
        throw new RuntimeException('Unable to seed fixture collection ' . $collection . '.');
    }
}

$writeCollection = static function (string $name, array $records) use ($fixturePath): void {
    $json = json_encode(array_values($records), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    if (file_put_contents($fixturePath . '/' . $name . '.json', $json . "\n", LOCK_EX) === false) {
        throw new RuntimeException('Unable to write isolated fixture collection ' . $name . '.');
    }
};

$selectedIds = array_fill_keys(array_keys($selectedById), true);
$filteredEligibility = [];
foreach ($eligibilityRows as $entry) {
    if (!is_array($entry)) {
        continue;
    }
    $questionIds = array_values(array_filter(
        (array) ($entry['question_ids'] ?? []),
        static fn(mixed $id): bool => is_scalar($id) && isset($selectedIds[(string) $id])
    ));
    if ($questionIds === []) {
        continue;
    }
    $entry['question_ids'] = $questionIds;
    if (is_array($entry['question_mappings'] ?? null)) {
        $entry['question_mappings'] = array_intersect_key($entry['question_mappings'], $selectedIds);
    }
    $filteredEligibility[] = $entry;
}
$filteredOverrides = array_values(array_filter(
    $overrides,
    static fn(mixed $override): bool => is_array($override)
        && isset($selectedIds[(string) ($override['question_id'] ?? '')])
));
$metadata = array_values(array_filter(
    $source->all('question-reuse-metadata'),
    static fn(mixed $record): bool => is_array($record)
        && isset($selectedIds[(string) ($record['question_id'] ?? '')])
));
$writeCollection('questions', $selectedQuestions);
$writeCollection('question-eligibility', $filteredEligibility);
$writeCollection('question-reuse-overrides', $filteredOverrides);
$writeCollection('question-reuse-metadata', $metadata);
foreach (['question-statistics', 'attempts', 'weakness', 'flashcard_state'] as $collection) {
    $writeCollection($collection, []);
}

putenv('APP_ENV=production');
putenv('APP_DEBUG=false');
putenv('APP_TIMEZONE=UTC');
putenv('DB_DRIVER=json');
putenv('APP_STORAGE_PATH=' . $fixturePath);
$http = new HttpSimulator($root . '/public/index.php');
$cookieId = 'batch663boards' . bin2hex(random_bytes(6));
$cookies = ['PHPSESSID' => $cookieId];
$fixtureStorage = new JsonStorage($fixturePath);
$boardResults = [];

try {
    $home = $http->request('GET', '/', [], [], [], $cookies);
    $listing = $http->request('GET', '/exams', [], [], [], $cookies);
    if (!$home['success'] || !$listing['success'] || substr_count($listing['output'], 'status-ready') !== 10) {
        throw new RuntimeException('Production learner home or ten-board discovery did not render.');
    }

    foreach ($boards as $board) {
        $boardId = (string) ($board['id'] ?? '');
        $boardCode = (string) ($board['code'] ?? '');
        $boardName = (string) ($board['name'] ?? $boardId);
        $question = $selectedByBoard[$boardId] ?? null;
        if (!is_array($question)) {
            throw new RuntimeException('Fixture question was not eligible for ' . $boardCode . '.');
        }
        $selectedQuestionId = (string) $question['id'];

        $detail = $http->request('GET', '/exams', ['exam' => $boardCode], [], [], $cookies);
        if (!$detail['success'] || !str_contains($detail['output'], htmlspecialchars($boardName, ENT_QUOTES, 'UTF-8'))
            || !str_contains($detail['output'], 'Configure practice')
            || !str_contains($detail['output'], 'Study materials')) {
            throw new RuntimeException('Board detail did not expose the expected learner routes for ' . $boardCode . '.');
        }
        if (!preg_match('~href="/quiz\?exam=' . preg_quote($boardCode, '~') . '&amp;subject=([^\"]+)"~', $detail['output'], $subjectLinkMatch)) {
            throw new RuntimeException('Board-specific subject practice link is missing for ' . $boardCode . '.');
        }
        parse_str((string) parse_url(html_entity_decode('/quiz?exam=' . $boardCode . '&amp;subject=' . $subjectLinkMatch[1], ENT_QUOTES | ENT_HTML5), PHP_URL_QUERY), $subjectQuery);
        $subjectId = (string) ($subjectQuery['subject'] ?? '');
        $boardHasSubjectQuestion = false;
        foreach ($selectedQuestions as $candidate) {
            $placement = QuestionEligibilityService::forExam($candidate, $boardId, $eligibilityIndex);
            if (is_array($placement) && strcasecmp((string) ($placement['subject_id'] ?? ''), $subjectId) === 0) {
                $boardHasSubjectQuestion = true;
                break;
            }
        }
        if (!$boardHasSubjectQuestion) {
            throw new RuntimeException('Board practice link does not target a fixture-eligible subject for ' . $boardCode . '.');
        }
        if (!str_contains($detail['output'], '/study-library?exam=' . $boardId . '&amp;subject=' . $subjectId)) {
            throw new RuntimeException('Board subject selection has no matching study-library destination for ' . $boardCode . '.');
        }
        $subjectStudy = $http->request('GET', '/study-library', [
            'exam' => $boardId,
            'subject' => $subjectId,
        ], [], [], $cookies);
        if (!$subjectStudy['success'] || $subjectStudy['status'] !== 200
            || str_contains($subjectStudy['output'], 'This board and subject combination is unavailable')
            || !str_contains($subjectStudy['output'], 'Published lesson availability is partial coverage')) {
            throw new RuntimeException('Subject-specific study discovery failed for ' . $boardCode . '/' . $subjectId
                . ': HTTP ' . $subjectStudy['status'] . ', location=' . ($subjectStudy['location'] ?? '')
                . ', body=' . substr(preg_replace('/\s+/', ' ', strip_tags((string) $subjectStudy['output'])) ?? '', 0, 280));
        }

        $study = $http->request('GET', '/study-library', ['exam' => $boardId], [], [], $cookies);
        if (!$study['success'] || !str_contains($study['output'], 'Study Library · ' . strtoupper($boardId))
            || str_contains($study['output'], 'No study materials are available yet')) {
            throw new RuntimeException('Board-specific study resources are not reachable for ' . $boardCode . '.');
        }

        $query = ['exam' => $boardCode, 'subject' => $subjectId];
        $postFields = ['exam' => $boardCode, 'subject' => $subjectId, 'count' => '1', 'mode' => 'practice', 'difficulty' => 'mixed'];
        if ($boardCode === 'CSE') {
            $query['civil_service_level'] = 'SUBPROFESSIONAL';
            $postFields['civil_service_level'] = 'SUBPROFESSIONAL';
        }
        $setup = $http->request('GET', '/quiz', $query, [], [], $cookies);
        if (!$setup['success'] || !str_contains($setup['output'], 'data-exam="' . htmlspecialchars($boardCode, ENT_QUOTES, 'UTF-8') . '"')
            || !preg_match('/name="csrf_token" value="([a-f0-9]{64})"/', $setup['output'], $csrfMatch)) {
            throw new RuntimeException('Practice configuration is missing board options or CSRF token for ' . $boardCode
                . ': HTTP ' . $setup['status'] . ' ' . ($setup['location'] ?? '') . ' '
                . substr(preg_replace('/\s+/', ' ', strip_tags($setup['output'])) ?? '', 0, 320));
        }
        $csrfToken = $csrfMatch[1];
        $postFields['action'] = 'start';
        $postFields['csrf_token'] = $csrfToken;
        $start = $http->request('POST', '/quiz', [], $postFields, [], $cookies);
        if (!$start['success'] || !str_contains($start['output'], 'Question 1 / 1')
            || !preg_match('/name="question_id"\s+value="([^"]+)"/', $start['output'], $questionIdMatch)
            || !isset($selectedQuestionRecords[$questionIdMatch[1]])) {
            throw new RuntimeException('Practice selected a wrong-board or wrong-subject question for ' . $boardCode . '.');
        }
        $questionId = $questionIdMatch[1];
        $question = $selectedQuestionRecords[$questionId];
        $actualPlacement = QuestionEligibilityService::forExam($question, $boardId, $eligibilityIndex);
        if (!is_array($actualPlacement)
            || strcasecmp((string) ($actualPlacement['subject_id'] ?? ''), $subjectId) !== 0
            || !str_contains($start['output'], htmlspecialchars((string) $question['question'], ENT_QUOTES, 'UTF-8'))) {
            throw new RuntimeException('Practice question did not preserve the requested board and subject for ' . $boardCode . '.');
        }

        $answerText = strtolower(trim((string) $question['answer']));
        $answerLetter = null;
        foreach (array_values($question['choices']) as $choiceIndex => $choice) {
            if (strtolower(trim((string) $choice)) === $answerText) {
                $answerLetter = chr(65 + $choiceIndex);
                break;
            }
        }
        if ($answerLetter === null) {
            throw new RuntimeException('Representative question has no matching answer choice for ' . $boardCode . '.');
        }
        $submission = $http->request('POST', '/quiz', [], [
            'action' => 'submit',
            'question_id' => $questionId,
            'answer' => $answerLetter,
            'csrf_token' => $csrfToken,
        ], [], $cookies);
        if (!$submission['success'] || !str_contains($submission['output'], 'Correct!')
            || !str_contains($submission['output'], htmlspecialchars((string) $question['explanation'], ENT_QUOTES, 'UTF-8'))
            || !str_contains($submission['output'], 'Related study materials')) {
            throw new RuntimeException('Practice feedback, explanation, or question study link failed for ' . $boardCode . '.');
        }
        if (preg_match('~href="/study-library\?material=([^\"]+)"~', $submission['output'], $materialMatch)) {
            $studyTarget = rawurldecode($materialMatch[1]);
            [$materialPath, $sectionFragment] = array_pad(explode('#', $studyTarget, 2), 2, '');
            $materialId = $materialPath;
            $materialPage = $http->request('GET', '/study-library', ['material' => $materialId], [], [], $cookies);
            $expectedMaterial = $materialsById[$materialId] ?? null;
            if (!$materialPage['success'] || !is_array($expectedMaterial)
                || !str_contains($materialPage['output'], htmlspecialchars((string) ($expectedMaterial['title'] ?? ''), ENT_QUOTES, 'UTF-8'))
                || !str_contains($materialPage['output'], 'Sources')) {
                throw new RuntimeException('Question study reference did not open its published source material for ' . $boardCode . '.');
            }
            if ($sectionFragment !== '' && !str_contains($materialPage['output'], 'id="' . htmlspecialchars($sectionFragment, ENT_QUOTES, 'UTF-8') . '"')) {
                throw new RuntimeException('Question study reference did not resolve its exact lesson section for ' . $boardCode . '.');
            }
        } else {
            // Source-only associations must stay visibly distinct from exact lesson links.
            $allowedSourceUrls = [];
            foreach ($linksByQuestion[$questionId] ?? [] as $referenceLink) {
                foreach ((array) ($referenceLink['source_ids'] ?? []) as $sourceId) {
                    $url = $sourcesById[(string) $sourceId]['url'] ?? null;
                    if (is_string($url) && trim($url) !== '') $allowedSourceUrls[] = $url;
                }
            }
            $hasDirectSource = false;
            foreach (array_unique($allowedSourceUrls) as $url) {
                if (str_contains($submission['output'], htmlspecialchars($url, ENT_QUOTES, 'UTF-8'))) {
                    $hasDirectSource = true;
                    break;
                }
            }
            if (!$hasDirectSource || str_contains($submission['output'], 'Open supporting lesson section:')) {
                throw new RuntimeException('Source-only question support was not rendered as a registered direct source for ' . $boardCode . '.');
            }
        }

        $finish = $http->request('POST', '/quiz', [], ['action' => 'finish', 'csrf_token' => $csrfToken], [], $cookies);
        $result = $http->request('GET', '/quiz', ['action' => 'result'], [], [], $cookies);
        if (!$finish['success'] || $finish['status'] !== 303 || !$result['success']
            || !str_contains($result['output'], 'Answer Review')
            || !str_contains($result['output'], '1 / 1')
            || !str_contains($result['output'], htmlspecialchars((string) $question['explanation'], ENT_QUOTES, 'UTF-8'))) {
            throw new RuntimeException('Scoring or result review failed for ' . $boardCode . '.');
        }
        if (!preg_match('~href="([^"]*/quiz\?[^\"]*action=start[^\"]*)"~', $result['output'], $resultActionMatch)) {
            throw new RuntimeException('Result page did not expose a usable retry action for ' . $boardCode . '.');
        }
        $resultActionUrl = html_entity_decode($resultActionMatch[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        parse_str((string) parse_url($resultActionUrl, PHP_URL_QUERY), $resultActionQuery);
        if (($resultActionQuery['exam'] ?? '') !== $boardCode) {
            throw new RuntimeException('Result retry action changed board context for ' . $boardCode . ': ' . json_encode($resultActionQuery));
        }
        $attempts = $fixtureStorage->all('attempts');
        $saved = $attempts[count($attempts) - 1] ?? null;
        if (!is_array($saved) || ($saved['board'] ?? '') !== $boardCode
            || array_map('strval', (array) ($saved['question_ids'] ?? [])) !== [$questionId]
            || ($saved['user_id'] ?? '') === '') {
            throw new RuntimeException('Attempt persistence or board identity failed for ' . $boardCode . '.');
        }

        $progress = $http->request('GET', '/progress', [], [], [], $cookies);
        $history = $http->request('GET', '/history', [], [], [], $cookies);
        if (!$progress['success'] || !$history['success']
            || !str_contains($history['output'], 'Quiz History')
            || !str_contains($history['output'], htmlspecialchars($boardName, ENT_QUOTES, 'UTF-8'))
            || !str_contains($progress['output'], htmlspecialchars($boardName, ENT_QUOTES, 'UTF-8'))) {
            throw new RuntimeException('Saved learner history or progress failed for ' . $boardCode . '.');
        }

        $retrySetup = $http->request('GET', '/quiz', $resultActionQuery, [], [], $cookies);
        if (!$retrySetup['success'] || !preg_match('/name="csrf_token" value="([a-f0-9]{64})"/', $retrySetup['output'], $retryCsrf)) {
            throw new RuntimeException('Retry setup failed for ' . $boardCode . '.');
        }
        $retryFields = array_merge($resultActionQuery, $postFields);
        $retryFields['csrf_token'] = $retryCsrf[1];
        $retry = $http->request('POST', '/quiz', [], $retryFields, [], $cookies);
        $stale = $http->request('POST', '/quiz', [], [
            'action' => 'submit',
            'question_id' => 'stale-question-id',
            'answer' => 'A',
            'csrf_token' => $retryCsrf[1],
        ], [], $cookies);
        if (!$retry['success'] || !str_contains($retry['output'], 'Question 1 / 1')
            || !preg_match('/name="question_id"\s+value="([^"]+)"/', $retry['output'], $retryQuestionMatch)
            || !$stale['success'] || !str_contains($stale['output'], 'no longer active')
            || count($fixtureStorage->all('attempts')) !== 2 * count($boardResults) + 1) {
            throw new RuntimeException('Retry or stale-action recovery failed for ' . $boardCode . '.');
        }
        $retryQuestionId = $retryQuestionMatch[1];
        $retryQuestion = $selectedQuestionRecords[$retryQuestionId] ?? null;
        if (!is_array($retryQuestion)) {
            throw new RuntimeException('Retry selected a question outside the board fixture for ' . $boardCode . '.');
        }
        $retryAnswerLetter = null;
        foreach (array_values($retryQuestion['choices'] ?? []) as $retryChoiceIndex => $retryChoice) {
            if (strtolower(trim((string) $retryChoice)) === strtolower(trim((string) ($retryQuestion['answer'] ?? '')))) {
                $retryAnswerLetter = chr(65 + $retryChoiceIndex);
                break;
            }
        }
        if ($retryAnswerLetter === null) {
            throw new RuntimeException('Retry question has no matching answer choice for ' . $boardCode . '.');
        }

        $retrySubmission = $http->request('POST', '/quiz', [], [
            'action' => 'submit',
            'question_id' => $retryQuestionId,
            'answer' => $retryAnswerLetter,
            'csrf_token' => $retryCsrf[1],
        ], [], $cookies);
        $retryFinish = $http->request('POST', '/quiz', [], [
            'action' => 'finish',
            'csrf_token' => $retryCsrf[1],
        ], [], $cookies);
        $retryResult = $http->request('GET', '/quiz', ['action' => 'result'], [], [], $cookies);
        if (!$retrySubmission['success'] || !str_contains($retrySubmission['output'], 'Correct!')
            || !$retryFinish['success'] || $retryFinish['status'] !== 303
            || !$retryResult['success'] || !str_contains($retryResult['output'], 'Quiz Result')
            || count($fixtureStorage->all('attempts')) !== 2 * (count($boardResults) + 1)) {
            throw new RuntimeException('A completed retry did not persist exactly one additional attempt for ' . $boardCode
                . ': submit=' . $retrySubmission['status'] . ' ' . ($retrySubmission['location'] ?? '')
                . ', finish=' . $retryFinish['status'] . ' ' . ($retryFinish['location'] ?? '')
                . ', result=' . ($retryResult['success'] ? 'PASS' : 'FAIL')
                . ', attempts=' . count($fixtureStorage->all('attempts'))
                . ', submit_text=' . substr(preg_replace('/\s+/', ' ', strip_tags($retrySubmission['output'])) ?? '', 0, 180));
        }

        $boardResults[$boardCode] = [
            'question_id' => $questionId,
            'subject_id' => $subjectId,
            'fixture_question_id' => $selectedQuestionId,
            'attempts' => count($fixtureStorage->all('attempts')),
            'study_material' => $materialId,
        ];
    }

    if (count($boardResults) !== 10) {
        throw new RuntimeException('The full board journey matrix did not complete.');
    }
    foreach ($fixtureStorage->all('attempts') as $attempt) {
        if (!is_array($attempt) || ($attempt['user_id'] ?? '') === '') {
            throw new RuntimeException('An attempt lost its anonymous learner ownership.');
        }
    }

    echo '[PASS] Production HTTP learner journeys completed for ' . implode(', ', array_keys($boardResults)) . ".\n";
    echo "[PASS] Each board verified detail, study, exact-subject practice, eligibility, answer, score, explanation, study link, history, progress, retry, stale-action recovery, and persistence.\n";
} finally {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($fixturePath, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $entry) {
        if ($entry->isDir()) {
            @rmdir($entry->getPathname());
        } else {
            @unlink($entry->getPathname());
        }
    }
    @rmdir($fixturePath);
    foreach (['APP_ENV', 'APP_DEBUG', 'APP_TIMEZONE', 'DB_DRIVER', 'APP_STORAGE_PATH'] as $environmentKey) {
        putenv($environmentKey);
    }
    $sessionDirectory = trim((string) (getenv('APP_SESSION_PATH') ?: ''));
    if ($sessionDirectory === '') {
        $sessionDirectory = $root . '/storage/doctor/simulation-sessions';
    }
    $sessionFile = rtrim($sessionDirectory, DIRECTORY_SEPARATOR) . '/sess-' . $cookieId;
    @unlink($sessionFile);
}
