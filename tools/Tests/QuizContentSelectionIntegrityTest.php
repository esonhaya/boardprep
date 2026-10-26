<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
require_once dirname(__DIR__) . '/Doctor/Project/BoardPrep/Simulation/HttpSimulator.php';
\App\Core\Autoloader::register();

$http = new \Tools\Doctor\Project\BoardPrep\Simulation\HttpSimulator(
    dirname(__DIR__, 2) . '/public/index.php'
);
$sessionCookie = 'batch676-content-selection-' . bin2hex(random_bytes(8));
$settings = $http->request(
    'GET',
    '/quiz',
    [],
    [],
    [],
    ['PHPSESSID' => $sessionCookie]
);
if (!$settings['success'] || $settings['status'] !== 200
    || !preg_match('/name="csrf_token"\s+value="([^"]+)"/', $settings['output'], $csrfMatch)) {
    throw new RuntimeException('HTTP quiz settings did not provide a session CSRF token');
}
$start = $http->request(
    'POST',
    '/quiz',
    [],
    ['action' => 'start', 'exam' => 'LET', 'subject' => 'English', 'topic' => 'Grammar', 'difficulty' => 'mixed', 'mode' => 'practice', 'count' => 1, 'csrf_token' => $csrfMatch[1]],
    [],
    ['PHPSESSID' => $sessionCookie]
);
if (!$start['success'] || $start['status'] !== 200 || !str_contains($start['output'], 'Question 1 / 1') ||
    !preg_match('/name="question_id"\s+value="([^"]+)"/', $start['output'])) {
    throw new RuntimeException('HTTP quiz start did not render a session-consistent question');
}

$request = new \SelectionRequest(
    subject: 'English',
    domain: 'Grammar',
    difficultyDistribution: ['easy' => 2],
    questionCount: 2,
    topic: 'Nouns'
);

$questions = [
    null,
    'legacy scalar record',
    ['id' => ['not' => 'an id'], 'subject' => 'English', 'domain' => 'Grammar', 'topic' => 'Nouns'],
    ['id' => '', 'subject' => 'English', 'domain' => 'Grammar', 'topic' => 'Nouns'],
    ['id' => 'bad', 'subject' => 'English', 'domain' => 'Grammar', 'topic' => 'Nouns', 'difficulty' => 'easy', 'question' => 'Broken answer', 'choices' => ['A', 'B'], 'answer' => 'C', 'explanation' => 'The answer is unavailable.'],
    ['id' => 'q1', 'subject' => 'English', 'domain' => 'Grammar', 'topic' => 'Nouns', 'difficulty' => 'easy', 'question' => 'Which is a noun?', 'choices' => ['Teacher', 'Quickly'], 'answer' => 'Teacher', 'explanation' => 'Teacher names a person.'],
    ['id' => 'q1', 'subject' => 'English', 'domain' => 'Grammar', 'topic' => 'Nouns', 'difficulty' => 'easy', 'question' => 'Duplicate ID question?', 'choices' => ['One', 'Two'], 'answer' => 'One', 'explanation' => 'This duplicates the identifier.'],
    ['id' => 'q2', 'subject' => 'English', 'domain' => 'Grammar', 'topic' => 'Nouns', 'difficulty' => 'easy', 'question' => '  WHICH   is a noun? ', 'choices' => ['School', 'Slowly'], 'answer' => 'School', 'explanation' => 'This duplicates normalized text.'],
    ['id' => 'q3', 'subject' => 'English', 'domain' => 'Grammar', 'topic' => 'Nouns', 'difficulty' => 'easy', 'question' => 'Which names a place?', 'choices' => ['School', 'Slowly'], 'answer' => ' school ', 'explanation' => 'School names a place.'],
];

$result = \QuestionSelectionService::fulfillRequest($questions, $request);

if (count($result->questions) !== 2 ||
    array_map(static fn(array $question): string => (string) $question['id'], $result->questions) !== ['q1', 'q3'] &&
    array_map(static fn(array $question): string => (string) $question['id'], $result->questions) !== ['q3', 'q1'] ||
    !$result->fulfilled) {
    throw new RuntimeException('malformed and duplicate candidates were not isolated');
}

$deduplicated = \SelectionDeduplicator::unique([
    ['id' => ['invalid' => true]],
    ['id' => 'q3'],
    ['id' => 'q3'],
    ['id' => 'q4'],
]);

if (array_map(static fn(array $question): string => (string) $question['id'], $deduplicated) !== ['q3', 'q4']) {
    throw new RuntimeException('deduplicator did not reject malformed IDs or duplicate IDs');
}

$executorResult = \BlueprintRequestExecutor::execute($questions, [$request]);
if (count($executorResult) !== 2) {
    throw new RuntimeException('blueprint execution did not isolate malformed candidates');
}

echo "[PASS] Quiz content selection isolates malformed and duplicate candidates.\n";
