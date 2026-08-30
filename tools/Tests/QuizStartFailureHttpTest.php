<?php

declare(strict_types=1);

require_once dirname(__DIR__) . "/Doctor/Project/BoardPrep/Simulation/HttpSimulator.php";

use Tools\Doctor\Project\BoardPrep\Simulation\HttpSimulator;

$simulator = new HttpSimulator(dirname(__DIR__, 2) . '/public/index.php');
$cookies = ['PHPSESSID' => 'batch425-failed-start'];

$valid = $simulator->request(
    'GET',
    '/quiz',
    [
        'action' => 'start',
        'subject' => 'English',
        'topic' => 'Subject-Verb Agreement',
        'mode' => 'practice',
        'difficulty' => 'mixed',
        'count' => 20,
    ],
    [],
    [],
    $cookies
);

if (!$valid['success'] || !preg_match('/Question 1 \/ [1-9]\d*/', $valid['output'])) {
    throw new RuntimeException('valid HTTP quiz start did not create an active quiz');
}

$failed = $simulator->request(
    'GET',
    '/quiz',
    [
        'action' => 'start',
        'subject' => 'Not A Subject',
        'topic' => 'Not A Topic',
        'mode' => 'practice',
        'difficulty' => 'hard',
        'count' => 20,
    ],
    [],
    [],
    $cookies
);

if (!$failed['success'] || $failed['status'] !== 302) {
    throw new RuntimeException('empty HTTP generation did not use the safe recovery redirect');
}

$recovery = $simulator->request('GET', '/quiz', [], [], [], $cookies);
if (!$recovery['success'] || !str_contains($recovery['output'], 'No questions matched')) {
    throw new RuntimeException('failed HTTP generation did not render learner recovery');
}

$finish = $simulator->request(
    'GET',
    '/quiz',
    ['action' => 'finish'],
    [],
    [],
    $cookies
);
if (!$finish['success'] || $finish['status'] !== 302) {
    throw new RuntimeException('failed generation left stale quiz questions active');
}

echo "[PASS] failed HTTP quiz start clears stale session and renders recovery.\n";
