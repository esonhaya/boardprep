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

$subjectNames = array_column(\App\Services\Shared\TaxonomyStorageService::subjects(), 'name', 'id');
if (!is_string($subjectNames['mathematics'] ?? null)) {
    throw new RuntimeException('The subject-pool fixture requires the configured Mathematics subject.');
}
$englishDuplicate = $question;
$englishDuplicate['id'] = 'same-prompt-english';
$englishDuplicate['question'] = 'Shared prompt across two subject pools?';
$mathematicsQuestion = $englishDuplicate;
$mathematicsQuestion['id'] = 'same-prompt-mathematics';
$mathematicsQuestion['taxonomy']['subject_id'] = 'mathematics';
$mathematicsDuplicate = $mathematicsQuestion;
$mathematicsDuplicate['id'] = 'duplicate-prompt-mathematics';
$subjectPool = [$englishDuplicate, $mathematicsQuestion, $mathematicsDuplicate];
$subjectPoolRequest = new SelectionRequest('*', null, [], 1);
$batchedSubjectCounts = QuestionPoolFilter::countBySubject(
    $subjectPool,
    ['english' => 'English', 'mathematics' => $subjectNames['mathematics']],
    $subjectPoolRequest
);
$separateSubjectCounts = [
    'english' => count(QuestionPoolFilter::filter($subjectPool, new SelectionRequest('English', null, [], 1))),
    'mathematics' => count(QuestionPoolFilter::filter($subjectPool, new SelectionRequest($subjectNames['mathematics'], null, [], 1))),
];
if ($batchedSubjectCounts !== $separateSubjectCounts
    || $batchedSubjectCounts !== ['english' => 1, 'mathematics' => 1]) {
    throw new RuntimeException('Batched subject counts changed independent pool deduplication semantics.');
}

$wrongLegacyRequest = new SelectionRequest('*', null, [], 1, 'Subject-Verb Agreement');
if (QuestionPoolFilter::filter([$question], $wrongLegacyRequest) !== []) {
    throw new RuntimeException('A stale legacy topic label still selects a question under the wrong concept.');
}
$wrongLegacyDomainRequest = new SelectionRequest('*', 'Grammar', [], 1);
if (QuestionPoolFilter::filter([$question], $wrongLegacyDomainRequest) !== []) {
    throw new RuntimeException('A stale legacy subject/domain label still selects a question under the wrong taxonomy.');
}

$crossBoardQuestion = $question;
$crossBoardQuestion['id'] = 'cross-board-eligibility-fixture';
$crossBoardQuestion['taxonomy']['board_id'] = 'nursing';
$eligibilityIndex = new \App\Services\Question\QuestionEligibilityIndex([
    [
        'question_ids' => ['cross-board-eligibility-fixture'],
        'exam_ids' => ['LET'],
        'subject_ids' => ['let' => 'english'],
    ],
], [], [], []);
$crossBoardRequest = new SelectionRequest('*', null, [], 1, exam: 'LET');
$crossBoardFiltered = QuestionPoolFilter::filter([$crossBoardQuestion], $crossBoardRequest, $eligibilityIndex);
if (count($crossBoardFiltered) !== 1 || ($crossBoardFiltered[0]['id'] ?? '') !== 'cross-board-eligibility-fixture') {
    throw new RuntimeException('Candidate prefilter rejected a valid explicit cross-board eligibility placement.');
}
$batchedCrossBoardCounts = QuestionPoolFilter::countBySubject(
    [$crossBoardQuestion],
    ['english' => 'English'],
    $crossBoardRequest,
    $eligibilityIndex
);
$separateCrossBoardCount = count(QuestionPoolFilter::filter(
    [$crossBoardQuestion],
    new SelectionRequest('English', null, [], 1, exam: 'LET'),
    $eligibilityIndex
));
if ($batchedCrossBoardCounts !== ['english' => $separateCrossBoardCount]) {
    throw new RuntimeException('Batched subject counts changed explicit cross-board eligibility placement.');
}

$excludedIndex = new \App\Services\Question\QuestionEligibilityIndex([], [], [], [[
    'question_id' => 'topic-priority-fixture',
    'board_id' => 'LET',
    'override_type' => 'EXCLUDE',
]]);
$excludedRequest = new SelectionRequest('*', null, [], 1, exam: 'LET');
if (QuestionPoolFilter::filter([$question], $excludedRequest, $excludedIndex) !== []) {
    throw new RuntimeException('Candidate prefilter bypassed the exact exclusion resolver for canonical board content.');
}

$unrelatedQuestion = $question;
$unrelatedQuestion['id'] = 'unrelated-board-fixture';
$unrelatedQuestion['taxonomy']['board_id'] = 'nursing';
if (QuestionPoolFilter::filter([$unrelatedQuestion], $crossBoardRequest, $eligibilityIndex) !== []) {
    throw new RuntimeException('Candidate prefilter admitted unrelated board content without an eligibility record.');
}

$blankSameBoard = $question;
$blankSameBoard['id'] = 'blank-same-board-subject-fixture';
$blankIndex = new \App\Services\Question\QuestionEligibilityIndex([
    ['question_ids' => ['blank-same-board-subject-fixture'], 'exam_ids' => ['LET'], 'subject_ids' => ['let' => '']],
], [], [], []);
$blankEffective = \App\Services\Question\QuestionEligibilityService::forExam($blankSameBoard, 'LET', $blankIndex);
if (($blankEffective['subject_id'] ?? '') !== 'english') {
    throw new RuntimeException('A same-board legacy eligibility row did not resolve through canonical taxonomy.');
}
$blankCrossBoard = $question;
$blankCrossBoard['id'] = 'blank-cross-board-subject-fixture';
$blankCrossBoard['taxonomy']['board_id'] = 'nursing';
$blankCrossIndex = new \App\Services\Question\QuestionEligibilityIndex([
    ['question_ids' => ['blank-cross-board-subject-fixture'], 'exam_ids' => ['LET'], 'subject_ids' => ['let' => '']],
], [], [], []);
if (QuestionPoolFilter::filter([$blankCrossBoard], $crossBoardRequest, $blankCrossIndex) !== []) {
    throw new RuntimeException('A cross-board placement with no destination subject was allowed to reuse its source taxonomy.');
}

echo "[PASS] Practice selection uses canonical subject, domain, and topic IDs over conflicting legacy labels.\n";
echo "[PASS] Candidate prefilter preserves canonical placement, explicit cross-board eligibility, and exact exclusion behavior.\n";
echo "[PASS] Missing same-board subject metadata uses canonical taxonomy; missing cross-board destination placement fails closed.\n";
