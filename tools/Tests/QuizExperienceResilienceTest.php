<?php

declare(strict_types=1);

require_once dirname(__DIR__) . "/Doctor/Project/BoardPrep/Simulation/HttpSimulator.php";

use Tools\Doctor\Project\BoardPrep\Simulation\HttpSimulator;

$simulator = new HttpSimulator(dirname(__DIR__, 2) . "/public/index.php");
$cookies = ['PHPSESSID' => 'batch676-stale-navigation-' . bin2hex(random_bytes(8))];

$settings = $simulator->request('GET', '/quiz', [], [], [], $cookies);
if (!$settings['success'] || $settings['status'] !== 200
    || !preg_match('/name="csrf_token" value="([^"]+)"/', $settings['output'], $csrfMatch)) {
    throw new RuntimeException('could not load quiz settings and CSRF token for stale-navigation test');
}
$csrfToken = $csrfMatch[1];

$start = $simulator->request(
    'POST',
    '/quiz',
    [],
    [
        'action' => 'start',
        'exam' => 'LET',
        'subject' => 'English',
        'topic' => 'Subject-Verb Agreement',
        'mode' => 'practice',
        'difficulty' => 'mixed',
        'count' => 1,
        'csrf_token' => $csrfToken,
    ],
    [],
    $cookies
);

if (!$start['success'] || $start['status'] !== 200
    || !str_contains($start['output'], 'Question 1 / 1')
    || !preg_match('/name="question_id"\s+value="([^"]+)"/', $start['output'], $matches)) {
    throw new RuntimeException('could not render the active quiz for stale-navigation test');
}

$submit = $simulator->request(
    'POST',
    '/quiz',
    [],
    ['action' => 'submit', 'question_id' => $matches[1], 'answer' => 'A', 'csrf_token' => $csrfToken],
    [],
    $cookies
);

if (!$submit['success'] || $submit['status'] !== 200) {
    throw new RuntimeException('practice answer did not return to the active quiz');
}

$finish = $simulator->request('POST', '/quiz', [], ['action' => 'finish', 'csrf_token' => $csrfToken], [], $cookies);
$result = $simulator->request('GET', '/quiz', ['action' => 'result'], [], [], $cookies);
if (!$result['success'] || $result['status'] !== 200 || !str_contains($result['output'], 'Quiz Result')) {
    throw new RuntimeException('completed quiz did not render its result');
}

$staleNext = $simulator->request('POST', '/quiz', [], ['action' => 'next', 'csrf_token' => $csrfToken], [], $cookies);
if (!$staleNext['success'] || $staleNext['status'] !== 302 || $staleNext['output'] !== '') {
    throw new RuntimeException('completed quiz still accepted stale navigation');
}

echo "[PASS] completed quiz rejects stale navigation through HTTP.\n";
