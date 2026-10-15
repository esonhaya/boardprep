<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$storagePath = getenv('APP_STORAGE_PATH');
if (!is_string($storagePath) || trim($storagePath) === '') $storagePath = $root . '/storage';
$load = static function (string $name) use ($storagePath): array {
    $decoded = json_decode((string) file_get_contents(rtrim($storagePath, DIRECTORY_SEPARATOR) . '/' . $name . '.json'), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($decoded)) throw new RuntimeException("Invalid {$name}.json fixture.");
    return $decoded;
};
$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};
$questions = $load('questions');
$byId = [];
foreach ($questions as $question) $byId[(string) ($question['id'] ?? '')] = $question;

$momentCases = [
    '6975' => ['peer' => '7519', 'load' => 23.0, 'arm' => 3.5, 'answer' => '80.5 kN-m'],
    '7135' => ['peer' => null, 'load' => 19.0, 'arm' => 3.5, 'answer' => '66.5 kN-m'],
    '7143' => ['peer' => null, 'load' => 12.0, 'arm' => 3.5, 'answer' => '42 kN-m'],
    '7327' => ['peer' => null, 'load' => 21.0, 'arm' => 3.5, 'answer' => '73.5 kN-m'],
    '7335' => ['peer' => null, 'load' => 14.0, 'arm' => 3.5, 'answer' => '49 kN-m'],
];
foreach ($momentCases as $id => $case) {
    $question = $byId[$id] ?? null;
    $assert(is_array($question), "Missing corrected Civil Engineering question {$id}.");
    $inputs = (array) ($question['batch634']['calculation']['inputs'] ?? []);
    $expected = (float) $case['load'] * (float) $case['arm'];
    $assert(abs((float) ($inputs['load'] ?? NAN) - (float) $case['load']) < 0.00001, "Question {$id} load metadata disagrees with its stem.");
    $assert(abs((float) ($inputs['arm'] ?? NAN) - (float) $case['arm']) < 0.00001, "Question {$id} lever-arm metadata disagrees with its stem.");
    $assert(abs((float) ($question['batch634']['calculation']['expected_value'] ?? NAN) - $expected) < 0.00001, "Question {$id} expected value is not P times d.");
    $assert(($question['answer'] ?? null) === $case['answer'], "Question {$id} answer does not match the stated load and lever arm.");
    $assert(count((array) ($question['choices'] ?? [])) === 4 && count(array_filter($question['choices'], static fn($choice): bool => $choice === $case['answer'])) === 1, "Question {$id} does not offer exactly one corrected answer.");
    $assert(str_contains((string) ($question['explanation'] ?? ''), str_replace('kN-m', 'kN·m', $case['answer'])), "Question {$id} explanation omits its worked result.");
    if (is_string($case['peer'])) {
        $peer = $byId[$case['peer']] ?? null;
        $assert(is_array($peer) && ($peer['answer'] ?? null) === $case['answer'], 'The retained duplicate canonical ID does not share the verified answer.');
        $assert(($peer['choices'] ?? null) === ($question['choices'] ?? null) && ($peer['explanation'] ?? null) === ($question['explanation'] ?? null), 'The true-duplicate pair no longer has identical task content.');
    }
}

$explanationGroups = [
    ['6973', '7517'],
    ['6978', '7138', '7146', '7330', '7514'],
    ['6979', '7139', '7147', '7323', '7331', '7515'],
];
foreach ($explanationGroups as $ids) {
    $expectedExplanation = $byId[$ids[0]]['explanation'] ?? null;
    $assert(is_string($expectedExplanation) && strlen($expectedExplanation) > 160, 'A reviewed duplicate group still has a generic one-line rationale.');
    foreach ($ids as $id) {
        $question = $byId[$id] ?? null;
        $assert(is_array($question) && ($question['explanation'] ?? null) === $expectedExplanation, "Equivalent question {$id} has a divergent explanation.");
    }
}

$cognitiveExpected = [
    '51' => 'apply', '52' => 'apply', '53' => 'apply',
    '857' => 'apply', '858' => 'apply', '859' => 'apply',
    '5193' => 'understand', '5194' => 'understand', '5195' => 'understand',
];
foreach ($cognitiveExpected as $id => $intent) {
    $question = $byId[$id] ?? null;
    $assert(is_array($question) && ($question['cognitive_intent'] ?? null) === $intent, "Question {$id} is missing its reviewed cognitive-intent label.");
}

$sources = $load('sources');
$sourceById = [];
foreach ($sources as $source) $sourceById[(string) ($source['id'] ?? '')] = $source;
$sourceIds = ['openstax-static-equilibrium-12-1', 'mit-solid-mechanics-beam-reactions-316'];
$assert(($sourceById[$sourceIds[0]]['url'] ?? '') === 'https://openstax.org/books/university-physics-volume-1/pages/12-1-conditions-for-static-equilibrium', 'OpenStax static-equilibrium source reference is missing or imprecise.');
$assert(str_starts_with((string) ($sourceById[$sourceIds[1]]['url'] ?? ''), 'https://live.ocw.mit.edu/courses/1-050-solid-mechanics-fall-2004/'), 'MIT beam-reaction reference is missing or outside the official course source.');
$materials = $load('study-materials');
$material = array_values(array_filter($materials, static fn(array $row): bool => ($row['id'] ?? '') === 'ce-489--tructural-echanics-and-esign'))[0] ?? null;
$assert(is_array($material), 'Civil Engineering structural study material is missing.');
$assert(in_array($sourceIds[0], (array) ($material['source_ids'] ?? []), true) && in_array($sourceIds[1], (array) ($material['source_ids'] ?? []), true), 'Worked examples do not expose their precise source references.');
$materialText = json_encode($material['notes'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
$assert(str_contains($materialText, '80.5 kN·m') && str_contains($materialText, 'R_B L = 16(L/2)'), 'Study material is missing its source-supported worked calculations.');
$links = $load('study-question-links');
$linkedIds = [];
$requiredLinkIds = array_map('strval', array_merge(array_keys($momentCases), ['7519'], ...$explanationGroups));
foreach ($links as $link) {
    if (($link['material_id'] ?? '') !== 'ce-489--tructural-echanics-and-esign') continue;
    $id = (string) ($link['question_id'] ?? '');
    if (!in_array($id, $requiredLinkIds, true)) continue;
    $sourceList = (array) ($link['source_ids'] ?? []);
    $assert(in_array($sourceIds[0], $sourceList, true) && in_array($sourceIds[1], $sourceList, true), "Question {$id} lacks its exact source-to-study link.");
    $linkedIds[$id] = true;
}
foreach ($requiredLinkIds as $id) {
    $assert(isset($linkedIds[$id]), "Question {$id} has no precise study-material link.");
}

echo '[PASS] Corrected five independently wrong moment calculations and the retained duplicate pair; strengthened three duplicate-group explanations, verified nine cognitive labels, and checked exact source-study links.' . PHP_EOL;
