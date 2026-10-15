<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException('Batch 681 duplicate/cognitive regression failed: ' . $message);
    }
};
$root = dirname(__DIR__, 2);
$read = static function (string $path) use ($root): array {
    $decoded = json_decode((string) file_get_contents($root . '/' . $path), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($decoded)) {
        throw new RuntimeException('Expected a JSON collection: ' . $path);
    }
    return $decoded;
};
$index = static function (array $rows): array {
    $result = [];
    foreach ($rows as $row) {
        if (is_array($row) && isset($row['id'])) {
            $result[(string) $row['id']] = $row;
        }
    }
    return $result;
};

$questions = $read('storage/questions.json');
$duplicateReview = $read('docs/batches/batch-681-duplicate-review.json');
$materials = $index($read('storage/study-materials.json'));
$sources = $index($read('storage/sources.json'));
$links = $read('storage/study-question-links.json');
$eligibility = $read('storage/question-eligibility.json');
$questionsById = $index($questions);
$linksByQuestion = [];
foreach ($links as $link) {
    $linksByQuestion[(string) ($link['question_id'] ?? '')][] = $link;
}

$changedIds = [
    '5206', '6602',
    '5462', '5463', '5464', '5465', '5466', '5467', '5468',
    '5487', '5488', '5489', '5490', '5491', '5492',
];
$expectedAnswers = [
    '5206' => 'The officer checked the log and reported the discrepancy.',
    '6602' => 'The clerk checked the receipt and entered its number.',
    '5462' => 'separate',
    '5463' => 'maintenance',
    '5464' => 'privilege',
    '5465' => 'necessary',
    '5466' => 'occurrence',
    '5467' => 'supersede',
    '5468' => 'liaison',
    '5487' => 'The officer verified the form before filing it.',
    '5488' => 'Please attach the receipt and retain a copy.',
    '5489' => 'The records were transferred to the secure cabinet.',
    '5490' => 'The applicant received a reference number.',
    '5491' => 'The committee approved the revised procedure.',
    '5492' => 'The file is complete, accurate, and ready for review.',
];
$assert(count($questions) === 8962 && count($questionsById) === 8962, 'Canonical question count or ID uniqueness changed.');
$assert(($duplicateReview['summary']['groups_before'] ?? 0) === 88
    && ($duplicateReview['summary']['excess_records_before'] ?? 0) === 236
    && ($duplicateReview['summary']['classifications']['TRUE_DUPLICATE'] ?? 0) === 85
    && ($duplicateReview['summary']['classifications']['LEGITIMATE_VARIANT'] ?? 0) === 3,
    'The baseline forensic ledger does not classify all 88 groups and 236 excess records.');
$assert(count($duplicateReview['groups'] ?? []) === 88
    && ($duplicateReview['summary']['question_level_history_references_found'] ?? -1) === 0
    && ($duplicateReview['summary']['cross_board_duplicate_placements'] ?? -1) === 0,
    'Duplicate forensics omitted canonical groups, history checks, or cross-board placements.');
foreach ($expectedAnswers as $id => $answer) {
    $question = $questionsById[$id] ?? null;
    $assert(is_array($question), 'Canonical ID was removed: ' . $id);
    $assert(($question['answer'] ?? null) === $answer, 'Answer identity changed: ' . $id);
    $assert(in_array($answer, (array) ($question['choices'] ?? []), true), 'Correct answer is absent from the choices: ' . $id);
    $assert(($question['cognitive_intent'] ?? '') === 'apply', 'The reviewed task lacks its supported APPLICATION label: ' . $id);
    $assert(($question['multistep'] ?? null) === false, 'A one-decision item is still marked multi-step: ' . $id);
    $assert(in_array('purdue-owl-proofreading-errors', (array) ($question['source_ids'] ?? []), true), 'Question lacks the exact proofreading source: ' . $id);
    $questionLinks = $linksByQuestion[$id] ?? [];
    $assert(count($questionLinks) === 1, 'Question does not have one exact material link: ' . $id);
    $materialId = (string) ($questionLinks[0]['material_id'] ?? '');
    $assert(isset($materials[$materialId]), 'Linked study material is missing: ' . $id . '/' . $materialId);
    foreach ((array) ($questionLinks[0]['source_ids'] ?? []) as $sourceId) {
        $assert(isset($sources[(string) $sourceId]), 'Study link cites an unregistered source: ' . $id . '/' . $sourceId);
        $assert(in_array($sourceId, (array) ($materials[$materialId]['source_ids'] ?? []), true), 'Study link source is absent from its material: ' . $id . '/' . $sourceId);
    }
}
$assert(str_contains((string) $questionsById['5489']['question'], 'The approved log reads, “The records were transferred to the secure cabinet.”'), 'The plural-cabinet ambiguity lacks its quoted source sentence.');
$assert(str_contains((string) $questionsById['5492']['question'], 'The approved review note reads, “The file is complete, accurate, and ready for review.”'), 'The added-word ambiguity lacks its quoted source sentence.');
$assert(count(array_unique(array_map(
    static fn(string $id): string => strtolower(preg_replace('/\s+/u', ' ', trim((string) $questionsById[$id]['question'])) ?? ''),
    $changedIds
))) === count($changedIds), 'A rewritten Civil Service variant still shares a normalized stem with another edited item.');

$assert(str_contains((string) $questionsById['22']['explanation'], 'A = 3 ÷ 4 = 0.75')
    && str_contains((string) $questionsById['22']['explanation'], 'B = 5 ÷ 6 ≈ 0.83')
    && str_contains((string) $questionsById['22']['explanation'], 'C = 4 ÷ 5 = 0.80'), 'LET unit-rate explanation does not calculate each row of its structured table.');
$assert(in_array('openstax-prealgebra-proportions-chapter6-5', (array) ($questionsById['22']['source_ids'] ?? []), true), 'LET unit-rate item lacks its exact proportion reference.');
$reeExplanation = (string) ($questionsById['1569']['explanation'] ?? '');
$assert(str_contains($reeExplanation, '(2x + y) + (x − y) = 11 + 1')
    && str_contains($reeExplanation, '3x = 12')
    && ($questionsById['1569']['answer'] ?? '') === '4 A', 'REE elimination rationale is not arithmetically valid or changed its answer.');
$assert(($questionsById['1569']['content_blocks'][0]['fallback'] ?? '') === $reeExplanation, 'REE equation fallback still exposes the invalid operation.');
$assert(str_contains((string) ($questionsById['1282']['question'] ?? ''), '100% of the acquiree')
    && str_contains((string) ($questionsById['1282']['explanation'] ?? ''), 'P140,000')
    && in_array('ifrs-ifrs-3-business-combinations', (array) ($questionsById['1282']['source_ids'] ?? []), true), 'CPA goodwill example omits its simplifying conditions or IFRS 3 link.');
$assert(str_contains((string) ($questionsById['1779']['question'] ?? ''), 'late-afternoon direct sun')
    && str_contains((string) ($questionsById['1779']['explanation'] ?? ''), 'shaded edge'), 'Architecture solar-orientation item lacks the facts needed to support its choice.');
$assert(($questionsById['1782']['taxonomy']['concept_id'] ?? '') === 'b633-arch-site-circulation'
    && str_contains((string) ($questionsById['1782']['explanation'] ?? ''), 'continuous, unobstructed path'), 'Architecture accessibility item is not classified and explained as route reasoning.');
$assert(($questionsById['1525']['source_ids'] ?? []) === ['cdc-field-epi-outbreak-investigation'], 'Nursing outbreak item lacks its exact CDC field-investigation source.');
$assert(isset($sources['cdc-field-epi-outbreak-investigation']), 'CDC field-investigation source was not registered.');
$verifiedLegacyLabels = ['268' => 'apply', '323' => 'recall', '324' => 'understand', '332' => 'analyze'];
foreach ($verifiedLegacyLabels as $id => $intent) {
    $assert(($questionsById[$id]['cognitive_intent'] ?? '') === $intent, 'A manually reviewed legacy LET label was not normalized conservatively: ' . $id);
}
$assert(($linksByQuestion['1782'][0]['material_id'] ?? '') === 'architecture-4-2-foundation'
    && in_array('bp344-2024-irr', (array) ($linksByQuestion['1782'][0]['source_ids'] ?? []), true), 'Architecture route item does not open the exact circulation and accessibility resource.');
$assert(($linksByQuestion['1525'][0]['material_id'] ?? '') === 'nle-b625-np-i-clinical-reasoning'
    && in_array('cdc-field-epi-outbreak-investigation', (array) ($linksByQuestion['1525'][0]['source_ids'] ?? []), true), 'Nursing outbreak item does not open its CDC-supported study pathway.');

$audit = \Tools\Doctor\Project\BoardPrep\Checks\QuestionEditorialEvidenceAudit::analyze($questions, $links, $eligibility);
$duplicateGroups = (array) ($audit['duplicate_groups'] ?? []);
$assert(count($duplicateGroups) === 85, 'The 85 retained Civil Engineering duplicate groups are not reported exactly.');
$assert(($audit['duplicate_classification_counts'] ?? []) === ['TRUE_DUPLICATE' => 85], 'Live duplicate classification is not deterministic or exact.');
$duplicateExcess = array_sum(array_map(
    static fn(array $group): int => max(0, count(explode(',', (string) ($group['entity_id'] ?? ''))) - 1),
    $duplicateGroups
));
$assert($duplicateExcess === 224, 'Runtime duplicate exposure accounting changed unexpectedly.');
$duplicateIntentIds = [];
foreach ($duplicateGroups as $group) {
    $ids = explode(',', (string) ($group['entity_id'] ?? ''));
    $intents = [];
    foreach ($ids as $id) {
        $intent = (string) ($questionsById[$id]['cognitive_intent'] ?? '');
        $assert($intent !== '' && \Tools\Doctor\Project\BoardPrep\Checks\QuestionEditorialEvidenceAudit::cognitiveRubricClasses($intent) !== [], 'An exact Civil Engineering duplicate still lacks a rubric-supported label: ' . $id);
        $duplicateIntentIds[$id] = true;
        $intents[$intent] = true;
    }
    $assert(count($intents) === 1, 'Equivalent duplicate copies carry different cognitive labels: ' . (string) ($group['entity_id'] ?? ''));
}
$missingIntentCount = count(array_filter($questions, static fn(array $question): bool => trim((string) ($question['cognitive_intent'] ?? '')) === ''));
$assert(count($duplicateIntentIds) === 309 && $missingIntentCount === 2416, 'Cognitive intent coverage did not improve by 324 conservative, individually reviewed labels.');
$unmappedLiveIntents = array_filter(
    $audit['findings'],
    static fn(array $finding): bool => ($finding['code'] ?? '') === 'UNMAPPED_COGNITIVE_INTENT'
);
$assert($unmappedLiveIntents === [], 'A live cognitive-intent label still falls outside the documented rubric.');
$firstDuplicate = explode(',', (string) ($duplicateGroups[0]['entity_id'] ?? ''));
$runtimeDuplicates = \QuestionPoolFilter::filter(
    [$questionsById[$firstDuplicate[0]], $questionsById[$firstDuplicate[1]]],
    new \SelectionRequest('*', null, [], 10)
);
$assert(count($runtimeDuplicates) === 1, 'Runtime practice selection exposes more than one copy of the same verified task.');
$metadataConflicts = array_filter(
    $audit['findings'],
    static fn(array $finding): bool => ($finding['code'] ?? '') === 'DUPLICATE_TASK_METADATA_CONFLICT'
        && in_array('difficulty', (array) ($finding['differing_fields'] ?? []), true)
);
$assert(count($metadataConflicts) === 5, 'Difficulty conflicts among exact Civil Engineering duplicates are not identified.');

$fixture = static function (string $id, string $answer, array $choices, string $explanation, string $difficulty = 'medium'): array {
    return [
        'id' => $id,
        'status' => 'active',
        'difficulty' => $difficulty,
        'question' => 'Choose the correctly proofread sentence.',
        'choices' => $choices,
        'answer' => $answer,
        'explanation' => $explanation,
        'source_ids' => ['fixture-proofreading-source'],
        'cognitive_intent' => 'apply',
        'multistep' => false,
        'taxonomy' => [
            'board_id' => 'civil-service',
            'subject_id' => 'clerical-ability',
            'domain_id' => 'spelling-and-word-forms',
            'topic_id' => 'spelling-and-word-forms',
            'concept_id' => 'cse-proofreading',
        ],
    ];
};
$fixtureLinks = static fn(array $rows): array => array_map(
    static fn(array $row): array => [
        'id' => 'fixture-link-' . $row['id'],
        'question_id' => $row['id'],
        'material_id' => 'fixture-proofreading-material',
        'source_ids' => ['fixture-proofreading-source'],
    ],
    $rows
);
$fixtureEligibility = static fn(array $rows): array => array_map(
    static fn(array $row): array => ['question_ids' => [$row['id']], 'exam_ids' => ['civil-service']],
    $rows
);
$classifications = static function (array $rows, array $reviews = []) use ($fixtureLinks, $fixtureEligibility): array {
    $result = \Tools\Doctor\Project\BoardPrep\Checks\QuestionEditorialEvidenceAudit::analyze(
        $rows,
        $fixtureLinks($rows),
        $fixtureEligibility($rows),
        $reviews
    );
    return (array) ($result['duplicate_groups'][0] ?? []);
};
$sameChoices = ['The officer filed the report.', 'The officer file the report.'];
$sameA = $fixture('fixture-a', $sameChoices[0], $sameChoices, 'The finite verb agrees with the subject.');
$sameB = $sameA;
$sameB['id'] = 'fixture-b';
$sameB['difficulty'] = 'hard';
$sameFinding = $classifications([$sameA, $sameB]);
$assert(($sameFinding['classification'] ?? '') === 'TRUE_DUPLICATE', 'Difficulty metadata masked an otherwise exact duplicate.');
$assert(in_array('difficulty', (array) ($sameFinding['differing_fields'] ?? []), true) === false, 'Metadata conflict leaked into task-difference fields.');

$conflictB = $sameB;
$conflictB['answer'] = $sameChoices[1];
$conflictB['explanation'] = 'This key conflicts with the other copy.';
$conflictFinding = $classifications([$sameA, $conflictB]);
$assert(($conflictFinding['classification'] ?? '') === 'CONFLICTING_ANSWER', 'Identical options with different answer identities were not identified as a conflict.');
$differentTask = $fixture('fixture-different', 'A clerk checked the receipt.', ['A clerk checked the receipt.', 'A clerk checking the receipt.'], 'The first option is a complete past-tense clause.');
$taskFinding = $classifications([$sameA, $differentTask]);
$assert(($taskFinding['classification'] ?? '') === 'SAME_STEM_DIFFERENT_TASK', 'Different answer sets under one exact stem were collapsed as duplicates.');
$crossBoardEligibility = $fixtureEligibility([$sameA, $sameB]);
$crossBoardEligibility[1]['exam_ids'][] = 'nursing';
$crossBoardAudit = \Tools\Doctor\Project\BoardPrep\Checks\QuestionEditorialEvidenceAudit::analyze(
    [$sameA, $sameB],
    $fixtureLinks([$sameA, $sameB]),
    $crossBoardEligibility
);
$crossBoardFinding = $crossBoardAudit['duplicate_groups'][0] ?? [];
$assert(($crossBoardFinding['classification'] ?? '') === 'SAME_STEM_DIFFERENT_TASK'
    && in_array('eligible_boards', (array) ($crossBoardFinding['differing_fields'] ?? []), true), 'Different cross-board placements were hidden by a duplicate match.');

$variantA = $fixture('fixture-variant-a', 'The officer filed the report.', ['The officer filed the report.', 'The officer file the report.'], 'The past-tense form is correct.');
$variantB = $fixture('fixture-variant-b', 'The clerk checked the receipt.', ['The clerk checked the receipt.', 'The clerk checking the receipt.'], 'The completed action uses a past-tense verb.');
$variantRows = [$variantA, $variantB];
$variantLinks = $fixtureLinks($variantRows);
$variantEligibility = $fixtureEligibility($variantRows);
$fingerprints = [];
foreach ($variantRows as $index => $row) {
    $fingerprints[$row['id']] = \Tools\Doctor\Project\BoardPrep\Checks\QuestionEditorialEvidenceAudit::taskFingerprint(
        $row,
        [$variantLinks[$index]],
        [$variantEligibility[$index]]
    );
}
$variantReview = [[
    'board_id' => 'civil-service',
    'normalized_stem' => 'choose the correctly proofread sentence.',
    'question_ids' => ['fixture-variant-a', 'fixture-variant-b'],
    'classification' => 'LEGITIMATE_VARIANT',
    'reason' => 'Each option set supplies a separate complete sentence and a distinct proofreading task.',
    'fingerprints' => $fingerprints,
]];
$variantFinding = $classifications($variantRows, $variantReview);
$assert(($variantFinding['classification'] ?? '') === 'LEGITIMATE_VARIANT', 'A fingerprint-verified reviewed variant was not retained as legitimate.');
$variantRows[1]['explanation'] .= ' Changed after review.';
$staleFinding = $classifications($variantRows, $variantReview);
$assert(($staleFinding['classification'] ?? '') !== 'LEGITIMATE_VARIANT', 'Stale variant evidence silently continued to pass.');

$missing = $sameB;
$missing['id'] = 'fixture-missing-evidence';
$missing['answer'] = '';
$incompleteFinding = $classifications([$sameA, $missing]);
$assert(($incompleteFinding['classification'] ?? '') === 'INSUFFICIENT_EVIDENCE', 'An incomplete key was given a confident duplicate classification.');
$assert(\Tools\Doctor\Project\BoardPrep\Checks\QuestionEditorialEvidenceAudit::cognitiveRubricClasses('application and interpretation') === ['APPLICATION', 'UNDERSTANDING'], 'The documented rubric does not normalize a supported compound label.');
$assert(\Tools\Doctor\Project\BoardPrep\Checks\QuestionEditorialEvidenceAudit::cognitiveRubricClasses('context') === [], 'An ambiguous legacy label was assigned a fabricated rubric level.');
$cognitiveAudit = \Tools\Doctor\Project\BoardPrep\Checks\QuestionEditorialEvidenceAudit::analyze([
    ['id' => 'unknown-intent', 'status' => 'active', 'difficulty' => 'easy', 'question' => 'Choose.', 'cognitive_intent' => 'context', 'taxonomy' => ['board_id' => 'civil-service', 'concept_id' => 'fixture']],
    ['id' => 'contradictory-intent', 'status' => 'active', 'difficulty' => 'hard', 'question' => 'Recall one fact.', 'cognitive_intent' => 'recall', 'multistep' => true, 'taxonomy' => ['board_id' => 'civil-service', 'concept_id' => 'fixture']],
]);
$cognitiveCodes = array_column((array) ($cognitiveAudit['findings'] ?? []), 'code');
$assert(in_array('UNMAPPED_COGNITIVE_INTENT', $cognitiveCodes, true), 'An unmapped cognitive label is not reported for review.');
$assert(in_array('CONTRADICTORY_COGNITIVE_INTENT', $cognitiveCodes, true), 'A RECALL label contradicted by multi-step metadata is not reported.');

echo "[PASS] 85 exact duplicate groups are classified without collapsing the three reviewed Civil Service variant sets.\n";
echo "[PASS] Duplicate conflicts, stale reviews, missing evidence, difficulty drift, and cross-board distinctions have focused fixtures.\n";
echo "[PASS] 15 source-linked Civil Service questions retain IDs and answers, resolve ambiguous choices, and carry supported application labels.\n";
