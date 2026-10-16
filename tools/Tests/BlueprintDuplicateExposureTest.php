<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

$makeQuestion = static function (
    string $id,
    string $subject,
    string $stem = 'Which value is supported by the stated calculation?',
    array $choices = ['42', '24']
): array {
    return [
        'id' => $id,
        'status' => 'active',
        'difficulty' => 'medium',
        'question' => $stem,
        'choices' => $choices,
        'answer' => $choices[0],
        'explanation' => 'The stated calculation supports this result.',
        'taxonomy' => [
            'board_id' => 'nursing',
            'subject_id' => $subject,
        ],
    ];
};

$cloneEnglish = $makeQuestion('clone-english', 'english');
$cloneMath = $makeQuestion('clone-math', 'math', '  WHICH value is supported by the stated calculation?  ', ['24', '42']);
$distinctMath = $makeQuestion('distinct-math', 'math', 'Which operation is shown by the second example?', ['Multiply', 'Divide']);

// Reproduce the cross-request leak with a real exact-duplicate pair from the
// current corpus. Each request asks for one item from the same blueprint area;
// an ID-only session can choose both canonical IDs even though the content is
// identical. The fixture reads the records but never mutates question data.
$canonicalQuestions = json_decode(
    (string) file_get_contents(dirname(__DIR__, 2) . '/storage/questions.json'),
    true,
    512,
    JSON_THROW_ON_ERROR
);
$canonicalById = [];
foreach ($canonicalQuestions as $canonicalQuestion) {
    if (is_array($canonicalQuestion) && isset($canonicalQuestion['id'])) {
        $canonicalById[(string) $canonicalQuestion['id']] = $canonicalQuestion;
    }
}
$realDuplicateIds = ['6973', '7517'];
$realDuplicatePool = array_values(array_filter(
    array_map(static fn(string $id): ?array => $canonicalById[$id] ?? null, $realDuplicateIds),
    'is_array'
));
if (count($realDuplicatePool) !== 2 || $realDuplicatePool[0]['question'] !== $realDuplicatePool[1]['question']) {
    throw new RuntimeException('The Batch 686 exact-duplicate fixture no longer identifies two equivalent canonical records.');
}
$realDistinctQuestion = $canonicalById['1137'] ?? null;
if (!is_array($realDistinctQuestion)
    || ($realDistinctQuestion['taxonomy']['subject_id'] ?? null) !== ($realDuplicatePool[0]['taxonomy']['subject_id'] ?? null)) {
    throw new RuntimeException('The real exact-duplicate regression fixture lost its distinct same-subject control item.');
}
$realDuplicatePool[] = $realDistinctQuestion;
$realDuplicateSubject = (string) ($realDuplicatePool[0]['taxonomy']['subject_id'] ?? '');
mt_srand(1);
$realPractice = BlueprintRequestExecutor::execute($realDuplicatePool, [
    new SelectionRequest($realDuplicateSubject, null, [], 1),
    new SelectionRequest($realDuplicateSubject, null, [], 1),
]);
$taskFingerprint = static function (array $question): string {
    $choices = array_map(static fn(mixed $choice): string => strtolower(trim((string) $choice)), $question['choices'] ?? []);
    sort($choices, SORT_STRING);
    return json_encode([strtolower(trim((string) ($question['question'] ?? ''))), $choices], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
};
if (count($realPractice) !== 2 || count(array_unique(array_map($taskFingerprint, $realPractice))) !== 2) {
    throw new RuntimeException('A real-corpus exact duplicate was selected despite sufficient distinct content in a multi-request practice attempt.');
}
$realExamBoard = (string) ($realDuplicatePool[0]['taxonomy']['board_id'] ?? '');
$realExamEligibility = new \App\Services\Question\QuestionEligibilityIndex([
    ['question_ids' => [...$realDuplicateIds, (string) $realDistinctQuestion['id']], 'exam_ids' => [$realExamBoard], 'subject_ids' => [$realExamBoard => $realDuplicateSubject]],
], [], [], []);
mt_srand(1);
$realExam = BlueprintRequestExecutor::execute($realDuplicatePool, [
    new SelectionRequest($realDuplicateSubject, null, [], 1, exam: $realExamBoard),
    new SelectionRequest($realDuplicateSubject, null, [], 1, exam: $realExamBoard),
], $realExamEligibility);
if (count($realExam) !== 2 || count(array_unique(array_map($taskFingerprint, $realExam))) !== 2) {
    throw new RuntimeException('A real-corpus exact duplicate was selected despite sufficient distinct content in an exam attempt.');
}

$session = new SelectionSession();
$session->reserve([$cloneEnglish]);
if (array_column($session->available([$cloneEnglish, $cloneMath, $distinctMath]), 'id') !== ['distinct-math']) {
    throw new RuntimeException('A reserved learner-facing task remained available under a second ID.');
}
$sameStemDifferentOptions = $makeQuestion('different-options', 'math', $cloneEnglish['question'], ['Yes', 'No']);
if (array_column($session->available([$sameStemDifferentOptions]), 'id') !== ['different-options']) {
    throw new RuntimeException('Content identity collapsed a same-stem item with a materially different option set.');
}
if (array_column($session->available([$cloneEnglish, $cloneMath, $distinctMath], true), 'id') !== ['clone-math', 'distinct-math']) {
    throw new RuntimeException('Shortage fallback did not expose unselected canonical IDs.');
}

$requests = [
    new SelectionRequest('english', null, [], 1),
    new SelectionRequest('math', null, [], 1),
];
mt_srand(1);
$practice = BlueprintRequestExecutor::execute([$cloneEnglish, $cloneMath, $distinctMath], $requests);
$practiceIds = array_column($practice, 'id');
if (count($practice) !== 2 || $practiceIds !== ['clone-english', 'distinct-math']) {
    throw new RuntimeException('Practice selection repeated equivalent content or left a distinct blueprint slot unused.');
}

$eligibility = new \App\Services\Question\QuestionEligibilityIndex([
    ['question_ids' => ['clone-english'], 'exam_ids' => ['nursing'], 'subject_ids' => ['nursing' => 'english']],
    ['question_ids' => ['clone-math'], 'exam_ids' => ['nursing'], 'subject_ids' => ['nursing' => 'math']],
    ['question_ids' => ['distinct-math'], 'exam_ids' => ['nursing'], 'subject_ids' => ['nursing' => 'math']],
], [], [], []);
$examRequests = [
    new SelectionRequest('english', null, [], 1, exam: 'nursing'),
    new SelectionRequest('math', null, [], 1, exam: 'nursing'),
];
mt_srand(1);
$exam = BlueprintRequestExecutor::execute([$cloneEnglish, $cloneMath, $distinctMath], $examRequests, $eligibility);
if (array_column($exam, 'id') !== ['clone-english', 'distinct-math']) {
    throw new RuntimeException('Exam blueprint selection did not avoid equivalent content across requests.');
}

$smallPool = [$cloneEnglish, $makeQuestion('clone-short-pool', 'english')];
mt_srand(1);
$short = BlueprintRequestExecutor::execute($smallPool, [new SelectionRequest('english', null, [], 2)]);
if (count($short) !== 2 || count(array_unique(array_column($short, 'id'))) !== 2) {
    throw new RuntimeException('A short duplicate-only pool was not transparently filled with distinct canonical IDs.');
}
$repeatCount = count(array_filter($short, static fn(array $question): bool => ($question['_selection_duplicate_content_fallback'] ?? false) === true));
if ($repeatCount !== 1) {
    throw new RuntimeException('Short-pool repeated content was not tagged for learner disclosure.');
}
$smallPoolSpecification = new QuizSpecification('nursing', 'english', null, [], [], 'mixed', 2, 'practice', false, false, null, null);
$sessionPayload = \App\Services\Quiz\Start\QuizStartSessionPayloadFactory::create($smallPoolSpecification, $short);
$notice = $sessionPayload['selection_notice'] ?? '';
if (!is_string($notice) || !str_contains($notice, 'too few distinct questions')) {
    throw new RuntimeException('The learner session payload did not disclose duplicate fallback.');
}

$shared = $makeQuestion('shared-cross-board', 'english');
$sharedEligibility = new \App\Services\Question\QuestionEligibilityIndex([
    ['question_ids' => ['shared-cross-board'], 'exam_ids' => ['nursing', 'civil-service'], 'subject_ids' => ['nursing' => 'english', 'civil-service' => 'english']],
], [], [], []);
foreach (['nursing', 'civil-service'] as $board) {
    $selection = BlueprintRequestExecutor::execute(
        [$shared],
        [new SelectionRequest('english', null, [], 1, exam: $board)],
        $sharedEligibility
    );
    if (array_column($selection, 'id') !== ['shared-cross-board']) {
        throw new RuntimeException('Content deduplication broke legitimate cross-board eligibility for ' . $board . '.');
    }
}

echo "[PASS] Equivalent tasks are reserved across practice and exam blueprint requests; short pools, IDs, and cross-board reuse remain safe.\n";
