<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

$question = [
    'id' => 'topic-priority-fixture',
    'status' => 'active',
    'question' => 'A reading report presents two different changes. Which inference is supported?',
    'choices' => ['The report proves one cause.', 'The report supports only the observed changes.'],
    'answer' => 'The report supports only the observed changes.',
    'explanation' => 'The report does not isolate a cause.',
    'difficulty' => 'medium',
    'subject' => 'Filipino',
    'domain' => 'Grammar',
    'topic' => 'Subject-Verb Agreement',
    'taxonomy' => [
        'board_id' => 'let',
        'subject_id' => 'english',
        'domain_id' => 'reading-comprehension',
        'topic_id' => 'critical-reading',
        'concept_id' => 'critical-reading-evidence-quality',
    ],
];
$request = new SelectionRequest('english', 'reading-comprehension', [], 1, 'critical-reading');
$filtered = QuestionPoolFilter::filter([$question], $request);
if (count($filtered) !== 1 || ($filtered[0]['id'] ?? '') !== 'topic-priority-fixture') {
    throw new RuntimeException('Topic selection did not prioritize the canonical taxonomy ID.');
}

$wrongLegacyRequest = new SelectionRequest('*', null, [], 1, 'Subject-Verb Agreement');
if (QuestionPoolFilter::filter([$question], $wrongLegacyRequest) !== []) {
    throw new RuntimeException('A stale legacy topic label still selects a question under the wrong concept.');
}
$wrongLegacyDomainRequest = new SelectionRequest('*', 'Grammar', [], 1);
if (QuestionPoolFilter::filter([$question], $wrongLegacyDomainRequest) !== []) {
    throw new RuntimeException('A stale legacy subject/domain label still selects a question under the wrong taxonomy.');
}

echo "[PASS] Practice selection uses canonical subject, domain, and topic IDs over conflicting legacy labels.\n";
