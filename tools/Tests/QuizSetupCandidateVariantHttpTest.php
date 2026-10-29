<?php

declare(strict_types=1);

$_ENV['APP_ENV'] = 'testing';
putenv('APP_ENV=testing');
require_once dirname(__DIR__) . '/Doctor/Project/BoardPrep/Simulation/HttpSimulator.php';

use Tools\Doctor\Project\BoardPrep\Simulation\HttpSimulator;

$simulator = new HttpSimulator(dirname(__DIR__, 2) . '/public/index.php');
if (realpath($simulator->storagePath()) === realpath(dirname(__DIR__, 2) . '/storage')) {
    throw new RuntimeException('candidate setup test did not use isolated storage.');
}
$attemptsPath = $simulator->storagePath() . '/attempts.json';
$attemptsBefore = hash_file('sha256', $attemptsPath);
$cookies = ['PHPSESSID' => 'batch725-variant-setup-' . bin2hex(random_bytes(6))];

$letSetup = $simulator->request('GET', '/quiz', ['exam' => 'LET'], [], [], $cookies);
if (!$letSetup['success'] || $letSetup['status'] !== 200
    || !preg_match('/<fieldset[^>]*id="quiz-let-variant-fields"[^>]*>/', $letSetup['output'], $letFieldset)
    || str_contains($letFieldset[0], ' hidden')
    || !str_contains($letSetup['output'], 'id="quiz-let-level" name="let_level"')
    || !str_contains($letSetup['output'], 'id="quiz-let-specialization" name="let_specialization"')
    || !str_contains($letSetup['output'], 'Secondary candidate must also choose a registered specialization')
    || !str_contains($letSetup['output'], 'data-level="SECONDARY"')
    || str_contains($letSetup['output'], '>Review Mode<')) {
    throw new RuntimeException('LET candidate variant controls were not rendered inline for first-use setup.');
}

$incompleteLetStart = $simulator->request('POST', '/quiz', [], [
    'action' => 'start', 'exam' => 'LET', 'subject' => '*', 'mode' => 'practice',
    'let_level' => 'SECONDARY', 'count' => 1,
], [], $cookies);
if (!$incompleteLetStart['success'] || $incompleteLetStart['status'] !== 302) {
    throw new RuntimeException('Server accepted a Secondary LET session without its required specialization.');
}
$letCorrection = $simulator->request('GET', '/quiz', [
    'exam' => 'LET', 'subject' => '*', 'mode' => 'practice', 'let_level' => 'SECONDARY',
], [], [], $cookies);
if (!$letCorrection['success'] || !str_contains($letCorrection['output'], 'A Secondary LET candidate must choose an official specialization.')
    || !preg_match('/<option value="SECONDARY" selected>/', $letCorrection['output'])) {
    throw new RuntimeException('Incomplete LET feedback did not preserve the selected level for correction.');
}

$cseSetup = $simulator->request('GET', '/quiz', ['exam' => 'CSE'], [], [], $cookies);
if (!$cseSetup['success'] || $cseSetup['status'] !== 200
    || !preg_match('/<fieldset[^>]*id="quiz-cse-variant-fields"[^>]*>/', $cseSetup['output'], $cseFieldset)
    || str_contains($cseFieldset[0], ' hidden')
    || !str_contains($cseSetup['output'], 'id="quiz-cse-level" name="civil_service_level"')) {
    throw new RuntimeException('Civil Service candidate level was not rendered inline for first-use setup.');
}

$missingVariant = $simulator->request('POST', '/quiz', [], [
    'action' => 'start', 'exam' => 'CSE', 'subject' => '*', 'mode' => 'practice', 'count' => 1,
], [], $cookies);
if (!$missingVariant['success'] || $missingVariant['status'] !== 302) {
    throw new RuntimeException('Server accepted a CSE session without its required candidate level.');
}
$variantError = $simulator->request('GET', '/quiz', ['exam' => 'CSE'], [], [], $cookies);
if (!$variantError['success'] || !str_contains($variantError['output'], 'Select Professional or Subprofessional')) {
    throw new RuntimeException('Missing CSE level did not return a learner-readable correction message.');
}

$validStart = $simulator->request('POST', '/quiz', [], [
    'action' => 'start', 'exam' => 'CSE', 'civil_service_level' => 'PROFESSIONAL',
    'subject' => '*', 'mode' => 'practice', 'count' => 1,
], [], $cookies);
if (!$validStart['success'] || $validStart['status'] !== 200
    || !str_contains($validStart['output'], 'Question 1 / 1')
    || !str_contains($validStart['output'], 'Professional')) {
    throw new RuntimeException('A first-time CSE learner could not start directly with the selected variant.');
}

if (hash_file('sha256', $attemptsPath) !== $attemptsBefore) {
    throw new RuntimeException('Starting the synthetic variant flow changed persisted attempt history.');
}

echo "[PASS] LET/CSE variant controls render inline, CSE server validation remains authoritative, and isolated setup does not write attempt history.\n";
