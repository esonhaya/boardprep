<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();
putenv('APP_ENV=testing');
putenv('APP_DEBUG=false');
\App\Core\App::boot();

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException('Batch 680 content-quality regression failed: ' . $message);
    }
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

$questions = $index(\App\Core\App::storage()->all('questions'));
$materials = $index(\App\Core\App::storage()->all('study-materials'));
$sources = $index(\App\Core\App::storage()->all('sources'));
$links = \App\Core\App::storage()->all('study-question-links');
$assert(count($questions) === 8962, 'Canonical question count or ID uniqueness changed.');

$expectedAnswers = [
    '1' => 'Participation rose during both changes, but the report cannot isolate either cause.',
    '11' => 'Tumaas ang pagdalo kasabay ng dalawang pagbabago; hindi matitiyak ang hiwalay na epekto.',
    '21' => 'Reading, by 45 minutes.',
    '41' => 'Compare dates, context, and other records before resolving the discrepancy.',
    '81' => 'The pilot group improved more, but the report does not prove feedback was the only cause.',
    '91' => 'May pagsusulit bukas.',
    '2868' => 'Document significant fabric, retain character-defining elements, and add reversible access improvements.',
    '6976' => '40.5 kN',
    '5286' => '20%',
    '7596' => 'Select evidence and procedures that address the stated assertion and risk, and assess reliability rather than treating all evidence as equivalent.',
    '3905' => 'A process for screening, respiratory hygiene, separation when indicated, and referral of unstable persons.',
];
foreach ($expectedAnswers as $id => $answer) {
    $question = $questions[$id] ?? null;
    $assert(is_array($question), 'Improved question ID is missing: ' . $id);
    $assert(($question['answer'] ?? null) === $answer, 'Canonical answer identity changed for question ' . $id);
    $assert(in_array($answer, (array) ($question['choices'] ?? []), true), 'Correct answer is no longer one of the choices for question ' . $id);
}

$cseMetadataLeaks = array_filter($questions, static function (array $question): bool {
    return (string) ($question['taxonomy']['board_id'] ?? '') === 'civil-service'
        && preg_match('/\bThe competency focus is\b|\([^)]*review item[^)]*\)/i', (string) ($question['question'] ?? '')) === 1;
});
$ceScenarioPrefixes = array_filter($questions, static function (array $question): bool {
    return (string) ($question['taxonomy']['board_id'] ?? '') === 'civil-engineering'
        && preg_match('/\AScenario\s+\d+\s+—/', (string) ($question['question'] ?? '')) === 1;
});
$assert($cseMetadataLeaks === [], 'Civil Service authoring labels leaked into learner-facing question stems.');
$assert($ceScenarioPrefixes === [], 'Standalone Civil Engineering questions regained contextless scenario numbering.');
$assert(str_contains((string) ($questions['5286']['question'] ?? ''), 'What percentage met it?')
    && !str_contains((string) ($questions['5286']['question'] ?? ''), 'competency focus'), 'CSE percentage item is not a clean learner-facing stem.');
$assert(str_contains((string) ($questions['6976']['explanation'] ?? ''), '81 kN × 0.5 = 40.5 kN'), 'CE member-force rationale no longer shows the calculation.');
$assert(($questions['91']['question'] ?? '') === 'Aling pangungusap ang wastong gumagamit ng “may” bago ang pangngalang kasunod?', 'Filipino grammar item still asks an unrelated question.');
$assert(($questions['41']['cognitive_intent'] ?? '') === 'analyze', 'Reviewed source-comparison item lacks its supported cognitive-intent label.');

$linkByQuestion = [];
foreach ($links as $link) {
    if (is_array($link)) {
        $linkByQuestion[(string) ($link['question_id'] ?? '')] = $link;
    }
}
$expectedMaterials = [
    '2868' => 'architecture-1-3-foundation',
    '6976' => 'ce-489--tructural-echanics-and-esign',
    '3905' => 'nle-np-i-prevention',
];
foreach ($expectedMaterials as $questionId => $materialId) {
    $link = $linkByQuestion[$questionId] ?? [];
    $material = $materials[$materialId] ?? [];
    $assert(($link['material_id'] ?? '') === $materialId, 'Question ' . $questionId . ' does not link to its reviewed concept material.');
    $assert($material !== [], 'Reviewed study material is missing: ' . $materialId);
    foreach ((array) ($link['source_ids'] ?? []) as $sourceId) {
        $assert(isset($sources[(string) $sourceId]), 'Question ' . $questionId . ' cites an unregistered source: ' . $sourceId);
        $assert(in_array($sourceId, (array) ($material['source_ids'] ?? []), true), 'Question ' . $questionId . ' source is absent from its study material: ' . $sourceId);
    }
}
$assert(in_array('bp344-2024-irr', (array) ($materials['architecture-1-3-foundation']['source_ids'] ?? []), true), 'Architecture conservation material lacks the current BP 344 source.');
$assert(in_array('kwf-balarila-pambansa', (array) ($materials['let-filipino-communication-review']['source_ids'] ?? []), true), 'Filipino grammar material lacks its KWF reference.');

$audit = \Tools\Doctor\Project\BoardPrep\Checks\QuestionEditorialEvidenceAudit::analyze(array_values($questions));
$badEditorialFindings = array_filter(
    $audit['findings'],
    static fn(array $finding): bool => in_array(($finding['code'] ?? ''), ['EDITORIAL_METADATA_LEAK_IN_STEM', 'GENERIC_EXPLANATION_TEMPLATE'], true)
);
$assert($badEditorialFindings === [], 'Doctor still finds a repaired stem or generic rationale in the live bank.');

echo '[PASS] Canonical IDs and answer identities remain stable across 11 reviewed items.' . PHP_EOL;
echo '[PASS] 300 CSE metadata suffixes and 620 standalone CE scenario labels are absent.' . PHP_EOL;
echo '[PASS] Six-board content repairs retain exact board-specific study and source links.' . PHP_EOL;
