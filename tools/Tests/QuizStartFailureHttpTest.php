<?php

declare(strict_types=1);

require_once dirname(__DIR__) . "/Doctor/Project/BoardPrep/Simulation/HttpSimulator.php";

use Tools\Doctor\Project\BoardPrep\Simulation\HttpSimulator;

$simulator = new HttpSimulator(dirname(__DIR__, 2) . '/public/index.php');
$cookies = ['PHPSESSID' => 'batch670-failed-start-preserves-active-v2'];

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

if (!$failed['success'] || $failed['status'] !== 302 || $failed['location'] !== '/quiz') {
    throw new RuntimeException('empty HTTP generation did not use the safe recovery redirect');
}

$recovery = $simulator->request('GET', '/quiz', [], [], [], $cookies);
if (!$recovery['success'] || !preg_match('/name="question_id"\s+value="([^"]+)"/', $recovery['output'], $resumedQuestion)
    || $resumedQuestion[1] !== $activeQuestion[1]
    || !str_contains($recovery['output'], 'No questions matched those quiz settings')) {
    throw new RuntimeException('a failed start discarded the learner\'s active question or hid its recovery message');
}

echo "[PASS] Failed quiz generation reports the error while preserving the active learner session.\n";
