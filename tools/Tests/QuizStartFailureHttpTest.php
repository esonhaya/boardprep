<?php

declare(strict_types=1);

require_once dirname(__DIR__) . "/Doctor/Project/BoardPrep/Simulation/HttpSimulator.php";

use Tools\Doctor\Project\BoardPrep\Simulation\HttpSimulator;

$simulator = new HttpSimulator(dirname(__DIR__, 2) . '/public/index.php');
$cookies = ['PHPSESSID' => 'batch676-failed-start-' . bin2hex(random_bytes(8))];

$valid = $simulator->request(
    'POST',
    '/quiz',
    [],
    [
        'action' => 'start',
        'exam' => 'LET',
        'subject' => 'English',
        'mode' => 'practice',
        'difficulty' => 'mixed',
        'count' => 5,
    ],
    [],
    $cookies
);

if (!$valid['success'] || !preg_match('/Question 1 \/ [1-9]\d*/', $valid['output'])
    || !preg_match('/name="question_id"\s+value="([^"]+)"/', $valid['output'], $activeQuestion)) {
    $diagnostic = $simulator->request('GET', '/quiz', ['action' => 'start'], [], [], $cookies);
    throw new RuntimeException('valid HTTP quiz start did not create an active quiz: ' . json_encode([
        'status' => $valid['status'] ?? null,
        'location' => $valid['location'] ?? null,
        'output' => substr((string) ($valid['output'] ?? ''), 0, 800),
        'stderr' => substr((string) ($valid['stderr'] ?? ''), 0, 800),
        'diagnostic_alert' => preg_match('/<p class="alert alert-danger"[^>]*>(.*?)<\\/p>/s', (string) ($diagnostic['output'] ?? ''), $alert)
            ? trim(strip_tags($alert[1]))
            : substr((string) ($diagnostic['output'] ?? ''), -500),
    ]));
}

$answeredActive = $simulator->request('POST', '/quiz', [], [
    'action' => 'submit',
    'question_id' => $activeQuestion[1],
    'answer' => 'A',
], [], $cookies);
if (!$answeredActive['success'] || !str_contains($answeredActive['output'], 'quiz-feedback')) {
    throw new RuntimeException('could not save an answer before testing active-session recovery');
}

$newBoardSetup = $simulator->request('GET', '/quiz', [
    'exam' => 'CPA',
    'subject' => 'cpa-financial-accounting',
], [], [], $cookies);
if (!$newBoardSetup['success'] || !str_contains($newBoardSetup['output'], 'active-session-warning')
    || !str_contains($newBoardSetup['output'], 'Resume current session')
    || !str_contains($newBoardSetup['output'], 'Licensure Examination for Certified Public Accountants')) {
    throw new RuntimeException('requesting a different board did not offer a safe active-session choice');
}

$failed = $simulator->request(
    'POST',
    '/quiz',
    [],
    [
        'action' => 'start',
        'exam' => 'LET',
        'subject' => 'Not A Subject',
        'topic' => 'Not A Topic',
        'mode' => 'practice',
        'difficulty' => 'hard',
        'count' => 20,
    ],
    [],
    $cookies
);

if (!$failed['success'] || $failed['status'] !== 302 || !str_starts_with((string) $failed['location'], '/quiz?')) {
    throw new RuntimeException('empty HTTP generation did not use the safe recovery redirect');
}

$redirectQuery = [];
parse_str((string) parse_url((string) $failed['location'], PHP_URL_QUERY), $redirectQuery);
foreach ([
    'exam' => 'LET',
    'subject' => 'Not A Subject',
    'topic' => 'Not A Topic',
    'difficulty' => 'hard',
    'count' => '20',
    'mode' => 'practice',
] as $key => $expected) {
    if ((string) ($redirectQuery[$key] ?? '') !== $expected) {
        throw new RuntimeException('failed start redirect discarded the learner\'s submitted ' . $key . ' context');
    }
}

$recoverySettings = $simulator->request('GET', '/quiz', $redirectQuery, [], [], $cookies);
if (!$recoverySettings['success']
    || !str_contains($recoverySettings['output'], 'No questions matched those quiz settings')
    || !str_contains($recoverySettings['output'], 'active-session-warning')
    || !str_contains($recoverySettings['output'], 'Resume current session')) {
    throw new RuntimeException('failed start did not show the preserved settings error and safe active-session option');
}

$recovery = $simulator->request('GET', '/quiz', [], [], [], $cookies);
if (!$recovery['success'] || !preg_match('/name="question_id"\s+value="([^"]+)"/', $recovery['output'], $resumedQuestion)
    || $resumedQuestion[1] !== $activeQuestion[1]
    || !preg_match('/name="answer"\s+value="A"\s+required\s+checked/', $recovery['output'])) {
    throw new RuntimeException('a failed start discarded the learner\'s active question or selected answer');
}

$replacement = $simulator->request('POST', '/quiz', [], [
    'action' => 'start',
    'exam' => 'CPA',
    'subject' => 'cpa-financial-accounting',
    'mode' => 'practice',
    'difficulty' => 'mixed',
    'count' => 1,
], [], $cookies);
if (!$replacement['success']
    || !str_contains($replacement['output'], 'Licensure Examination for Certified Public Accountants')
    || !preg_match('/name="question_id"\s+value="([^\"]+)"/', $replacement['output'], $replacementQuestion)
    || $replacementQuestion[1] === $activeQuestion[1]) {
    throw new RuntimeException('an explicit new-session start did not replace the active board context safely');
}

echo "[PASS] Active sessions resume on refresh, offer a warned board switch, and survive invalid generation before an explicit replacement.\n";
