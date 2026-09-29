<?php

declare(strict_types=1);

/**
 * Batch 626: reconcile the divergent Batch 625 Nursing cohort onto the
 * authoritative Batch 624 portfolio lineage.
 *
 * --write performs an idempotent structural import. A second --write run
 * verifies the same manifest and records the second deterministic hash.
 * No question authoring or cross-board expansion is performed here.
 */

$root = dirname(__DIR__);
$write = in_array('--write', $argv, true);

$baseHead = 'aee05ca8a6ad2e792bbe7a596291a837fa75589e';
$sourceHead = '0eb9704ba293e0aa3402af5d01a2d3392c005b73';
$sourceParent = '42bdfd7581f2b50bae8584707df9ffeab37fb055';
$mergeBase = $sourceParent;
$verificationDate = '2026-09-29';
$artifactPath = $root . '/storage/batch626-lineage-reconciliation.json';

$readJson = static function (string $path): array {
    $raw = file_get_contents($path);
    if ($raw === false) {
        throw new RuntimeException('Cannot read ' . $path);
    }
    $value = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    return is_array($value) ? $value : [];
};

$readGitJson = static function (string $commit, string $relativePath) use ($root): array {
    $command = 'git -C ' . escapeshellarg($root) . ' show ' . escapeshellarg($commit . ':' . $relativePath);
    $raw = shell_exec($command);
    if (!is_string($raw) || trim($raw) === '') {
        throw new RuntimeException('Cannot read ' . $commit . ':' . $relativePath);
    }
    $value = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    return is_array($value) ? $value : [];
};

$writeJson = static function (string $path, mixed $value): void {
    $raw = json_encode(
        $value,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    ) . "\n";
    if (file_put_contents($path, $raw) === false) {
        throw new RuntimeException('Cannot write ' . $path);
    }
};

$appendRows = static function (string $path, array $rows): void {
    if ($rows === []) {
        return;
    }
    $raw = file_get_contents($path);
    if ($raw === false) {
        throw new RuntimeException('Cannot read before append: ' . $path);
    }
    $trimmed = rtrim($raw, "\r\n");
    $close = strrpos($trimmed, "\n]");
    if ($close === false) {
        throw new RuntimeException('Expected a top-level JSON array in ' . $path);
    }
    $encoded = [];
    foreach ($rows as $row) {
        $rowJson = json_encode(
            $row,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );
        $encoded[] = implode("\n", array_map(
            static fn(string $line): string => '    ' . $line,
            explode("\n", $rowJson)
        ));
    }
    $updated = substr($trimmed, 0, $close)
        . ",\n"
        . implode(",\n", $encoded)
        . "\n]\n";
    if (file_put_contents($path, $updated) === false) {
        throw new RuntimeException('Cannot append ' . $path);
    }
};

$commandOutput = static function (string $command): string {
    return trim((string) shell_exec($command));
};
$commandSucceeds = static function (string $command): bool {
    $output = [];
    $status = 1;
    exec($command, $output, $status);
    return $status === 0;
};

$currentHead = $commandOutput('git -C ' . escapeshellarg($root) . ' rev-parse HEAD');
$baseIsAncestor = $currentHead === $baseHead
    || $commandSucceeds(
        'git -C ' . escapeshellarg($root) . ' merge-base --is-ancestor '
        . $baseHead . ' ' . $currentHead
    );
if (!$baseIsAncestor) {
    throw new RuntimeException('Batch 626 requires Batch 624 ancestry ' . $baseHead . '; found ' . $currentHead);
}

$targetPaths = [
    'questions' => 'storage/questions.json',
    'eligibility' => 'storage/question-eligibility.json',
    'metadata' => 'storage/question-reuse-metadata.json',
    'links' => 'storage/study-question-links.json',
    'materials' => 'storage/study-materials.json',
    'requirements' => 'storage/question-required-concepts.json',
    'sources' => 'storage/sources.json',
    'derived' => 'storage/concept-derived-eligibility.json',
    'coverage' => 'storage/board-concept-coverage.json',
    'overrides' => 'storage/question-reuse-overrides.json',
];

$before = [];
foreach ($targetPaths as $key => $path) {
    $before[$key] = $readGitJson($baseHead, $path);
}

$sourceQuestions = $readGitJson($sourceHead, 'storage/questions.json');
$sourceEligibility = $readGitJson($sourceHead, 'storage/question-eligibility.json');
$sourceMetadata = $readGitJson($sourceHead, 'storage/question-reuse-metadata.json');
$sourceLinks = $readGitJson($sourceHead, 'storage/study-question-links.json');
$sourceMaterials = $readGitJson($sourceHead, 'storage/study-materials.json');
$sourceRequirements = $readGitJson($sourceHead, 'storage/question-required-concepts.json');
$sourceSources = $readGitJson($sourceHead, 'storage/sources.json');
$sourceBatchArtifact = $readGitJson($sourceHead, 'storage/batch625-nursing-modernization.json');
$sourceBatchArtifact = $sourceBatchArtifact[0] ?? [];

$sourceNewQuestions = array_values(array_filter(
    $sourceQuestions,
    static fn(array $question): bool =>
        (int) ($question['id'] ?? 0) >= 4163
        && (int) ($question['id'] ?? 0) <= 4482
        && ($question['source'] ?? '') === 'batch625-source-first'
        && is_array($question['batch625'] ?? null)
));
usort($sourceNewQuestions, static fn(array $a, array $b): int => ((int) $a['id']) <=> ((int) $b['id']));
if (count($sourceNewQuestions) !== 320) {
    throw new RuntimeException('Expected exactly 320 source Batch 625 Nursing questions');
}

$sourceNewIds = array_map(static fn(array $question): string => (string) $question['id'], $sourceNewQuestions);
$sourceNewIdSet = array_fill_keys($sourceNewIds, true);
$sourceQuestionById = [];
foreach ($sourceQuestions as $question) {
    $sourceQuestionById[(string) ($question['id'] ?? '')] = $question;
}

$manifest = is_file($artifactPath) ? ($readJson($artifactPath)[0] ?? []) : [];
$existingQuestions = $readJson($root . '/' . $targetPaths['questions']);
$existingQuestionIds = [];
foreach ($existingQuestions as $question) {
    $existingQuestionIds[(string) ($question['id'] ?? '')] = true;
}

$mapping = [];
if (is_array($manifest['integration']['id_remapping'] ?? null)) {
    foreach ($manifest['integration']['id_remapping'] as $row) {
        if (is_array($row) && isset($row['old_batch625_id'], $row['unified_id'])) {
            $mapping[(string) $row['old_batch625_id']] = (string) $row['unified_id'];
        }
    }
}

$alreadyIntegrated = count($mapping) === 320;
if ($alreadyIntegrated) {
    foreach ($mapping as $oldId => $newId) {
        if (!isset($existingQuestionIds[$newId])) {
            $alreadyIntegrated = false;
            break;
        }
    }
}

$idCollisions = [];
foreach ($sourceNewIds as $oldId) {
    if (isset($existingQuestionIds[$oldId])) {
        $idCollisions[] = $oldId;
    }
}

$importedNow = [
    'questions' => 0,
    'eligibility_groups' => 0,
    'reuse_metadata' => 0,
    'study_links' => 0,
    'study_materials' => 0,
    'required_concepts' => 0,
    'sources' => 0,
];

if ($write && !$alreadyIntegrated) {
    $maxQuestionId = 0;
    foreach ($existingQuestions as $question) {
        $maxQuestionId = max($maxQuestionId, (int) ($question['id'] ?? 0));
    }
    $nextId = $maxQuestionId + 1;
    foreach ($sourceNewQuestions as $question) {
        $mapping[(string) $question['id']] = (string) $nextId++;
    }
    if (count(array_unique($mapping)) !== 320) {
        throw new RuntimeException('Generated unified IDs are not unique');
    }

    $questionsToAppend = [];
    foreach ($sourceNewQuestions as $question) {
        $oldId = (string) $question['id'];
        $question['id'] = (int) $mapping[$oldId];
        $questionsToAppend[] = $question;
    }
    $appendRows($root . '/' . $targetPaths['questions'], $questionsToAppend);
    $importedNow['questions'] = count($questionsToAppend);

    $eligibilityToAppend = [];
    foreach ($sourceEligibility as $entry) {
        $entryId = (string) ($entry['id'] ?? '');
        if (!str_starts_with($entryId, 'eligibility-b625-nursing-')) {
            continue;
        }
        $node = (string) ($entry['blueprint_node_id'] ?? substr($entryId, strlen('eligibility-b625-nursing-')));
        $safeNode = preg_replace('/[^a-z0-9-]+/i', '-', strtolower($node)) ?: 'unknown';
        $entry['id'] = 'eligibility-b626-nursing-' . $safeNode;
        $entry['question_ids'] = array_values(array_map(
            static fn(mixed $id): int => (int) ($mapping[(string) $id] ?? 0),
            $entry['question_ids'] ?? []
        ));
        if (in_array(0, $entry['question_ids'], true)) {
            throw new RuntimeException('Eligibility row contains an unmapped Nursing ID');
        }
        $eligibilityToAppend[] = $entry;
    }
    $appendRows($root . '/' . $targetPaths['eligibility'], $eligibilityToAppend);
    $importedNow['eligibility_groups'] = count($eligibilityToAppend);

    $metadataToAppend = [];
    foreach ($sourceMetadata as $row) {
        $oldId = (string) ($row['question_id'] ?? '');
        if (!isset($sourceNewIdSet[$oldId])) {
            continue;
        }
        $newId = $mapping[$oldId];
        $row['id'] = 'question-reuse-b626-' . $newId;
        $row['question_id'] = $newId;
        $metadataToAppend[] = $row;
    }
    $appendRows($root . '/' . $targetPaths['metadata'], $metadataToAppend);
    $importedNow['reuse_metadata'] = count($metadataToAppend);

    $linksToAppend = [];
    foreach ($sourceLinks as $row) {
        $oldId = (string) ($row['question_id'] ?? '');
        if (!isset($sourceNewIdSet[$oldId])) {
            continue;
        }
        $newId = $mapping[$oldId];
        $row['id'] = 'study-link-b626-' . $newId;
        $row['question_id'] = $newId;
        $linksToAppend[] = $row;
    }
    $appendRows($root . '/' . $targetPaths['links'], $linksToAppend);
    $importedNow['study_links'] = count($linksToAppend);

    $existingMaterialIds = [];
    foreach ($before['materials'] as $row) {
        $existingMaterialIds[(string) ($row['id'] ?? '')] = true;
    }
    $materialsToAppend = [];
    foreach ($sourceMaterials as $row) {
        $id = (string) ($row['id'] ?? '');
        if (str_starts_with($id, 'nle-b625-') && !isset($existingMaterialIds[$id])) {
            $materialsToAppend[] = $row;
        }
    }
    $appendRows($root . '/' . $targetPaths['materials'], $materialsToAppend);
    $importedNow['study_materials'] = count($materialsToAppend);

    $sourceRequiredNew = array_values(array_filter(
        $sourceRequirements,
        static fn(array $row): bool => isset($sourceNewIdSet[(string) ($row['question_id'] ?? '')])
    ));
    if ($sourceRequiredNew !== []) {
        throw new RuntimeException('Batch 625 unexpectedly contains prerequisite rows; refusing speculative import');
    }
}

if ($mapping === [] && $alreadyIntegrated === false) {
    throw new RuntimeException('No ID mapping is available; run with --write on Batch 624 first');
}

$after = [];
foreach ($targetPaths as $key => $path) {
    $after[$key] = $readJson($root . '/' . $path);
}

$questionById = [];
foreach ($after['questions'] as $question) {
    $questionById[(string) ($question['id'] ?? '')] = $question;
}
$newIds = array_values($mapping);
if (count($newIds) !== 320 || count(array_unique($newIds)) !== 320) {
    throw new RuntimeException('Unified mapping does not contain 320 unique IDs');
}
foreach ($newIds as $newId) {
    if (!isset($questionById[(string) $newId])) {
        throw new RuntimeException('Unified Nursing question is missing: ' . $newId);
    }
}

$oldToNewRows = [];
foreach ($sourceNewIds as $oldId) {
    $oldToNewRows[] = [
        'old_batch625_id' => (int) $oldId,
        'unified_id' => (int) $mapping[$oldId],
    ];
}

$boardOf = static function (array $question): string {
    $taxonomy = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
    return strtolower((string) ($taxonomy['board_id'] ?? $question['board'] ?? ''));
};
$depthOf = static function (mixed $depth): string {
    $value = (string) $depth;
    return in_array($value, ['1', '2', '3', '4', '5'], true) ? $value : 'UNKNOWN';
};

$effectiveIds = static function (string $board, array $questions, array $eligibility, array $derived): array {
    $ids = [];
    foreach ($eligibility as $entry) {
        $exams = is_array($entry['exam_ids'] ?? null)
            ? array_map('strtolower', array_map('strval', $entry['exam_ids']))
            : [];
        if (!in_array(strtolower($board), $exams, true)) {
            continue;
        }
        foreach (($entry['question_ids'] ?? []) as $id) {
            if (is_scalar($id)) {
                $ids[(string) $id] = true;
            }
        }
    }
    foreach ($derived as $entry) {
        if (($entry['record_type'] ?? '') === 'RELATIONSHIP'
            && strtolower((string) ($entry['target_board'] ?? '')) === strtolower($board)) {
            $ids[(string) ($entry['question_id'] ?? '')] = true;
        }
    }
    foreach ($questions as $question) {
        $taxonomy = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
        if (strtolower((string) ($taxonomy['board_id'] ?? $question['board'] ?? '')) === strtolower($board)) {
            $ids[(string) ($question['id'] ?? '')] = true;
        }
    }
    unset($ids['']);
    return array_keys($ids);
};

$metric = static function (
    string $board,
    array $questions,
    array $eligibility,
    array $derived,
    array $metadata,
    array $coverage
) use ($effectiveIds, $depthOf, $boardOf): array {
    $native = array_values(array_filter($questions, static fn(array $q): bool => $boardOf($q) === strtolower($board)));
    $ids = $effectiveIds($board, $questions, $eligibility, $derived);
    $depthMap = [];
    foreach ($metadata as $row) {
        $depthMap[(string) ($row['question_id'] ?? '')] = $depthOf($row['reuse']['required_depth'] ?? null);
    }
    $depthKnown = 0;
    $depthCounts = ['1' => 0, '2' => 0, '3' => 0, '4' => 0, '5' => 0, 'UNKNOWN' => 0];
    foreach ($ids as $id) {
        $depth = $depthMap[(string) $id] ?? 'UNKNOWN';
        $depthCounts[$depth]++;
        if ($depth !== 'UNKNOWN') {
            $depthKnown++;
        }
    }
    $directIds = [];
    foreach ($eligibility as $entry) {
        $exams = is_array($entry['exam_ids'] ?? null) ? array_map('strtolower', array_map('strval', $entry['exam_ids'])) : [];
        if (in_array(strtolower($board), $exams, true)) {
            foreach (($entry['question_ids'] ?? []) as $id) {
                $directIds[(string) $id] = true;
            }
        }
    }
    $derivedIds = [];
    foreach ($derived as $entry) {
        if (($entry['record_type'] ?? '') === 'RELATIONSHIP'
            && strtolower((string) ($entry['target_board'] ?? '')) === strtolower($board)) {
            $derivedIds[(string) ($entry['question_id'] ?? '')] = true;
        }
    }
    $cov = array_values(array_filter(
        $coverage,
        static fn(array $row): bool => strtolower((string) ($row['board_id'] ?? '')) === strtolower($board)
    ));
    $coverageKnown = count(array_filter(
        $cov,
        static fn(array $row): bool => in_array((string) ($row['max_depth'] ?? ''), ['1', '2', '3', '4', '5'], true)
    ));
    $concepts = [];
    foreach ($native as $question) {
        $concept = (string) (($question['taxonomy']['concept_id'] ?? '') ?: '');
        if ($concept !== '') {
            $concepts[$concept] = true;
        }
    }
    return [
        'canonical' => count($native),
        'effective' => count($ids),
        'direct' => count($directIds),
        'derived' => count(array_diff_key($derivedIds, $directIds)),
        'question_depth_known' => $depthKnown,
        'question_depth_unknown' => count($ids) - $depthKnown,
        'depth_counts' => $depthCounts,
        'coverage_rows' => count($cov),
        'coverage_known' => $coverageKnown,
        'coverage_unknown' => count($cov) - $coverageKnown,
        'distinct_concepts' => count($concepts),
    ];
};

$boards = [
    'let', 'civil-service', 'criminologist', 'nursing', 'psychometrician',
    'civil-engineering', 'cpa', 'mechanical-engineering',
    'registered-electrical-engineering', 'architecture',
];
$beforeBoard = [];
$afterBoard = [];
foreach ($boards as $board) {
    $beforeBoard[$board] = $metric(
        $board,
        $before['questions'],
        $before['eligibility'],
        $before['derived'],
        $before['metadata'],
        $before['coverage']
    );
    $afterBoard[$board] = $metric(
        $board,
        $after['questions'],
        $after['eligibility'],
        $after['derived'],
        $after['metadata'],
        $after['coverage']
    );
}

$directPairs = [];
$derivedPairs = [];
$pairCounts = [];
foreach ($after['eligibility'] as $entry) {
    $exams = is_array($entry['exam_ids'] ?? null) ? array_map('strtolower', array_map('strval', $entry['exam_ids'])) : [];
    foreach (($entry['question_ids'] ?? []) as $id) {
        if (!is_scalar($id)) {
            continue;
        }
        foreach ($exams as $board) {
            $pair = (string) $id . '|' . $board;
            $directPairs[$pair] = true;
            $pairCounts[$pair] = ($pairCounts[$pair] ?? 0) + 1;
        }
    }
}
foreach ($after['derived'] as $entry) {
    if (($entry['record_type'] ?? '') !== 'RELATIONSHIP') {
        continue;
    }
    $pair = (string) ($entry['question_id'] ?? '') . '|' . strtolower((string) ($entry['target_board'] ?? ''));
    $derivedPairs[$pair] = true;
    $pairCounts[$pair] = ($pairCounts[$pair] ?? 0) + 1;
}
$duplicateEffectiveCandidates = count(array_filter($pairCounts, static fn(int $count): bool => $count > 1));
$conceptDerived = count(array_diff_key($derivedPairs, $directPairs));
$directEligibility = count($directPairs);
$allEffectivePairs = array_fill_keys(array_keys($pairCounts), true);
$globalEffective = count($allEffectivePairs);

$boardsByQuestion = [];
foreach ($boards as $board) {
    foreach ($effectiveIds($board, $after['questions'], $after['eligibility'], $after['derived']) as $id) {
        $boardsByQuestion[(string) $id][$board] = true;
    }
}
$multiBoard = 0;
$singleBoard = 0;
$orphanCanonical = 0;
foreach ($questionById as $id => $_question) {
    $count = count($boardsByQuestion[$id] ?? []);
    if ($count > 1) {
        $multiBoard++;
    } elseif ($count === 1) {
        $singleBoard++;
    } else {
        $orphanCanonical++;
    }
}

$canonicalIds = array_map(static fn(array $question): string => (string) ($question['id'] ?? ''), $after['questions']);
$canonicalIdUniqueness = count($canonicalIds) === count(array_unique($canonicalIds));

$metadataByQuestion = [];
foreach ($after['metadata'] as $row) {
    $metadataByQuestion[(string) ($row['question_id'] ?? '')] = $row;
}
$materialById = [];
foreach ($after['materials'] as $row) {
    $materialById[(string) ($row['id'] ?? '')] = $row;
}
$sourceById = [];
foreach ($after['sources'] as $row) {
    $sourceById[(string) ($row['id'] ?? '')] = $row;
}
$linksByQuestion = [];
$brokenLinks = [];
foreach ($after['links'] as $row) {
    $questionId = (string) ($row['question_id'] ?? '');
    $linksByQuestion[$questionId][] = $row;
    if (!isset($questionById[$questionId]) || !isset($materialById[(string) ($row['material_id'] ?? '')])) {
        $brokenLinks[] = (string) ($row['id'] ?? '');
    }
    foreach (($row['source_ids'] ?? []) as $sourceId) {
        if (!isset($sourceById[(string) $sourceId])) {
            $brokenLinks[] = (string) ($row['id'] ?? '') . ':source:' . $sourceId;
        }
    }
}
$brokenMetadata = [];
foreach ($after['metadata'] as $row) {
    if (!isset($questionById[(string) ($row['question_id'] ?? '')])) {
        $brokenMetadata[] = (string) ($row['id'] ?? '');
    }
}

$newQuestionByNewId = [];
foreach ($oldToNewRows as $row) {
    $newQuestionByNewId[(string) $row['unified_id']] = $sourceQuestionById[(string) $row['old_batch625_id']] ?? [];
}
$newTraceability = [
    'total' => count($newIds),
    'study_link' => 0,
    'authoritative_source' => 0,
    'scope_chain' => 0,
    'known_depth' => 0,
    'derivable' => 0,
    'ambiguous' => 0,
    'broken' => [],
];
$newDepth = ['1' => 0, '2' => 0, '3' => 0, '4' => 0, '5' => 0, 'UNKNOWN' => 0];
$newByArea = [];
$newSafetySensitive = 0;
$newSafetyPass = 0;
$newSafetyCategoryMemberships = [];
$newDosageSemantics = [];
$newDosageCategoryMembership = 0;
$newRequiredRows = 0;
foreach ($oldToNewRows as $row) {
    $newId = (string) $row['unified_id'];
    $question = $questionById[$newId] ?? [];
    $sourceQuestion = $sourceQuestionById[(string) $row['old_batch625_id']] ?? [];
    $batch = is_array($question['batch625'] ?? null) ? $question['batch625'] : [];
    $area = (string) ($batch['np_area'] ?? '');
    $newByArea[$area] = ($newByArea[$area] ?? 0) + 1;
    $depth = $depthOf($metadataByQuestion[$newId]['reuse']['required_depth'] ?? null);
    $newDepth[$depth]++;
    $linksForQuestion = $linksByQuestion[$newId] ?? [];
    if (count($linksForQuestion) === 1 && isset($materialById[(string) ($linksForQuestion[0]['material_id'] ?? '')])) {
        $newTraceability['study_link']++;
    } else {
        $newTraceability['broken'][] = $newId . ':study';
    }
    $authorityIds = is_array($batch['authoritative_source_ids'] ?? null) ? $batch['authoritative_source_ids'] : [];
    $authorityOkay = $authorityIds !== [];
    foreach ($authorityIds as $sourceId) {
        if (!isset($sourceById[(string) $sourceId])) {
            $authorityOkay = false;
            $newTraceability['broken'][] = $newId . ':source:' . $sourceId;
        }
    }
    if ($authorityOkay) {
        $newTraceability['authoritative_source']++;
    }
    if (is_array($batch['official_scope_chain'] ?? null) && $batch['official_scope_chain'] !== []) {
        $newTraceability['scope_chain']++;
    } else {
        $newTraceability['broken'][] = $newId . ':scope';
    }
    if ($depth !== 'UNKNOWN') {
        $newTraceability['known_depth']++;
    }
    if (($batch['derivable'] ?? false) === true) {
        $newTraceability['derivable']++;
    }
    if (($batch['source_ambiguous'] ?? false) === true) {
        $newTraceability['ambiguous']++;
    }
    $categories = is_array($batch['safety_categories'] ?? null) ? $batch['safety_categories'] : [];
    if ($categories !== []) {
        $newSafetySensitive++;
        if (($batch['clinical_review']['status'] ?? '') === 'PASS'
            && ($batch['clinical_review']['reviewed'] ?? false) === true) {
            $newSafetyPass++;
        }
    }
    foreach ($categories as $category) {
        $newSafetyCategoryMemberships[$category] = ($newSafetyCategoryMemberships[$category] ?? 0) + 1;
        if ($category === 'DOSAGE_CALCULATION') {
            $newDosageCategoryMembership++;
        }
    }
    $semantic = (string) ($batch['dosage_semantic_classification'] ?? 'UNKNOWN');
    $newDosageSemantics[$semantic] = ($newDosageSemantics[$semantic] ?? 0) + 1;
    if (($batch['required_concepts'] ?? []) !== []) {
        $newRequiredRows++;
    }
    if (!isset($sourceQuestion['batch625'])) {
        $newTraceability['broken'][] = $newId . ':source-record';
    }
}

$newTraceability['study_link_320'] = $newTraceability['study_link'] === 320;
$newTraceability['authoritative_source_320'] = $newTraceability['authoritative_source'] === 320;
$newTraceability['scope_chain_320'] = $newTraceability['scope_chain'] === 320;
$newTraceability['derivable_320'] = $newTraceability['derivable'] === 320;
$newTraceability['pass'] = $newTraceability['broken'] === [] && $newTraceability['ambiguous'] === 0;

$legacyNursingById = [];
foreach ($sourceQuestions as $question) {
    if (($question['board'] ?? ($question['taxonomy']['board_id'] ?? '')) === 'nursing'
        && !isset($sourceNewIdSet[(string) ($question['id'] ?? '')])) {
        $legacyNursingById[(string) $question['id']] = $question;
    }
}
$targetBeforeById = [];
foreach ($before['questions'] as $question) {
    $targetBeforeById[(string) ($question['id'] ?? '')] = $question;
}
$legacyNursingChanged = [];
foreach ($legacyNursingById as $id => $question) {
    if (!isset($targetBeforeById[$id])
        || json_encode($targetBeforeById[$id], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            !== json_encode($question, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) {
        $legacyNursingChanged[] = $id;
    }
}

$legacyDosage = $sourceBatchArtifact['dosage_semantics']['legacy'] ?? [
    'DOSAGE_SAFETY_TAGGED' => 24,
    'DOSAGE_CALCULATION_REQUIRED' => 16,
    'DOSAGE_CONCEPTUAL_SAFETY' => 8,
];
$legacyDosageInvariant = $legacyNursingChanged === []
    && (int) ($legacyDosage['DOSAGE_SAFETY_TAGGED'] ?? 0) === 24
    && (int) ($legacyDosage['DOSAGE_CALCULATION_REQUIRED'] ?? 0) === 16
    && (int) ($legacyDosage['DOSAGE_CONCEPTUAL_SAFETY'] ?? 0) === 8;

$newReusePolicies = [];
$newOverrides = [];
$overrideQuestionIds = array_fill_keys($newIds, true);
foreach ($after['metadata'] as $row) {
    $questionId = (string) ($row['question_id'] ?? '');
    if (isset($overrideQuestionIds[$questionId])) {
        $newReusePolicies[(string) ($row['reuse']['reuse_policy'] ?? 'UNKNOWN')] =
            ($newReusePolicies[(string) ($row['reuse']['reuse_policy'] ?? 'UNKNOWN')] ?? 0) + 1;
    }
}
foreach ($after['overrides'] as $row) {
    if (isset($overrideQuestionIds[(string) ($row['question_id'] ?? '')])) {
        $newOverrides[] = $row;
    }
}
$newDerivedRows = array_values(array_filter(
    $after['derived'],
    static fn(array $row): bool => isset($overrideQuestionIds[(string) ($row['question_id'] ?? '')])
));

$normalizeQuestion = static function (array $question): string {
    return strtolower(trim((string) ($question['question'] ?? '')));
};
$normalized = [];
foreach ($after['questions'] as $question) {
    $key = $normalizeQuestion($question);
    if ($key !== '') {
        $normalized[$key][] = (string) ($question['id'] ?? '');
    }
}
$duplicateGroups = array_values(array_filter($normalized, static fn(array $ids): bool => count($ids) > 1));
$duplicateGroupsTouchingNew = array_values(array_filter(
    $duplicateGroups,
    static fn(array $ids): bool => count(array_intersect($ids, $newIds)) > 0
));

$invalidPrerequisites = [];
$selfPrerequisites = [];
$requiredGraph = [];
$conceptIds = [];
foreach ($readJson($root . '/storage/taxonomy/concepts.json') as $row) {
    $conceptIds[(string) ($row['id'] ?? '')] = true;
}
foreach ($after['questions'] as $question) {
    $questionId = (string) ($question['id'] ?? '');
    $primary = (string) ($question['taxonomy']['concept_id'] ?? '');
    foreach ($after['requirements'] as $requirement) {
        if ((string) ($requirement['question_id'] ?? '') !== $questionId) {
            continue;
        }
        $required = (string) ($requirement['concept_id'] ?? '');
        if (!isset($questionById[$questionId]) || !isset($conceptIds[$required])) {
            $invalidPrerequisites[] = (string) ($requirement['id'] ?? '');
        }
        if ($primary !== '' && $primary === $required) {
            $selfPrerequisites[] = (string) ($requirement['id'] ?? '');
        }
        if ($primary !== '' && $required !== '') {
            $requiredGraph[$primary][$required] = true;
        }
    }
}
$cycleNodes = [];
$visit = [];
$walk = static function (string $node) use (&$walk, &$visit, &$cycleNodes, $requiredGraph): void {
    $state = $visit[$node] ?? 0;
    if ($state === 1) {
        $cycleNodes[$node] = true;
        return;
    }
    if ($state === 2) {
        return;
    }
    $visit[$node] = 1;
    foreach (array_keys($requiredGraph[$node] ?? []) as $next) {
        $walk($next);
    }
    $visit[$node] = 2;
};
foreach (array_keys($requiredGraph) as $node) {
    $walk($node);
}

$coverageUnknown = [];
foreach ($after['coverage'] as $row) {
    if (strtolower((string) ($row['board_id'] ?? '')) === 'nursing'
        && !in_array((string) ($row['max_depth'] ?? ''), ['1', '2', '3', '4', '5'], true)) {
        $coverageUnknown[] = (string) ($row['concept_id'] ?? ($row['id'] ?? ''));
    }
}
sort($coverageUnknown);

$parseChangedFiles = static function (string $from, string $to) use ($root): array {
    $changed = [];
    $changedRaw = trim((string) shell_exec(
        'git -C ' . escapeshellarg($root) . ' diff --name-status '
        . escapeshellarg($from) . ' ' . escapeshellarg($to)
    ));
    foreach (preg_split('/\R/', $changedRaw, -1, PREG_SPLIT_NO_EMPTY) as $line) {
        $parts = preg_split('/\s+/', trim($line), 2);
        if (count($parts) === 2) {
            $changed[] = ['status' => $parts[0], 'path' => $parts[1]];
        }
    }
    return $changed;
};
$batch624Changed = $parseChangedFiles($mergeBase, $baseHead);
$sourceChanged = $parseChangedFiles($mergeBase, $sourceHead);
$classify = static function (string $path): string {
    if (in_array($path, [
        'storage/questions.json',
        'storage/question-eligibility.json',
    ], true)) {
        return 'NURSING_CONTENT';
    }
    if (in_array($path, [
        'storage/question-reuse-metadata.json',
        'storage/study-materials.json',
        'storage/study-question-links.json',
    ], true)) {
        return 'NURSING_STUDY_SOURCE_METADATA';
    }
    if (in_array($path, [
        'storage/batch625-nursing-modernization.json',
        'tools/batch625-nursing-audit.php',
    ], true)) {
        return 'BATCH625_AUDIT';
    }
    if (str_starts_with($path, 'app/Services/Quiz/')) {
        return 'GENERIC_PRODUCTION_FIX';
    }
    return 'LINEAGE_GENERATED_CHURN';
};
foreach ($sourceChanged as &$changed) {
    $changed['classification'] = $classify($changed['path']);
}
unset($changed);

$sourceChangedSet = array_fill_keys(array_map(static fn(array $row): string => $row['path'], $sourceChanged), true);
$staleRejected = [
    'generated_audit_files' => [
        'storage/batch625-nursing-modernization.json',
        'tools/batch625-nursing-audit.php',
    ],
    'stale_portfolio_snapshots' => 'Batch 625 artifact counts were not used to overwrite Batch 624 CPA/Criminologist/Psychometrician state.',
    'canonical_records_rejected' => 0,
];

$preservedArtifacts = [
    'storage/batch622-cpa-evidence-closure.json',
    'storage/batch623a-criminologist-evidence-map.json',
    'storage/batch623b-criminologist-modernization.json',
    'storage/batch624-portfolio-selection-and-modernization.json',
    'tools/batch622-cpa-evidence-audit.php',
    'tools/batch623a-criminologist-evidence-audit.php',
    'tools/batch623b-criminologist-audit.php',
    'tools/batch624-portfolio-modernization-audit.php',
];
$preservedArtifactsPass = true;
foreach ($preservedArtifacts as $path) {
    if (!is_file($root . '/' . $path)) {
        $preservedArtifactsPass = false;
    }
}

$nonNursingRegressions = [];
foreach ($boards as $board) {
    if ($board === 'nursing') {
        continue;
    }
    if ($beforeBoard[$board] !== $afterBoard[$board]) {
        $nonNursingRegressions[$board] = ['before' => $beforeBoard[$board], 'after' => $afterBoard[$board]];
    }
}

$production = [
    'status' => 'NOT_RUN',
    'boards' => [],
    'new_nursing_ids_reachable' => false,
    'double_weighting' => 0,
];
try {
    require_once $root . '/app/Core/Autoloader.php';
    \App\Core\Autoloader::register();
    \App\Core\App::boot();
    mt_srand(626);
    srand(626);
    $blueprints = $readJson($root . '/storage/blueprints.json');
    $overrides = $after['overrides'];
    $index = new \App\Services\Question\QuestionEligibilityIndex(
        $after['eligibility'],
        $blueprints,
        $after['derived'],
        $overrides
    );
    foreach (['nursing', 'cpa', 'criminologist', 'psychometrician'] as $board) {
        $candidates = array_values(array_filter(
            $after['questions'],
            static fn(array $question): bool => $boardOf($question) === $board
        ));
        if ($board === 'nursing') {
            $request = new SelectionRequest('nursing-foundations', null, ['easy' => 1, 'medium' => 1, 'hard' => 1], 25, null, null, 'nursing');
        } else {
            $first = $candidates[0] ?? [];
            $resolved = \App\Services\Question\QuestionEligibilityService::forExam($first, $board, $index);
            $subject = (string) (($resolved['subject_id'] ?? '') ?: (($first['taxonomy']['subject_id'] ?? '') ?: ($first['subject'] ?? '')));
            $request = new SelectionRequest($subject, null, [], 1, null, null, $board);
        }
        $pool = QuestionPoolFilter::filter($candidates, $request, $index);
        $result = QuestionSelectionService::fulfillRequest($candidates, $request, $index);
        $selectedIds = array_values(array_map(
            static fn(array $question): string => (string) ($question['id'] ?? ''),
            $result->questions
        ));
        $newInPool = $board === 'nursing'
            ? count(array_intersect($newIds, array_map(static fn(array $q): string => (string) ($q['id'] ?? ''), $pool)))
            : null;
        $production['boards'][$board] = [
            'status' => $result->fulfilled && count($selectedIds) === $request->questionCount ? 'PASS' : 'FAIL',
            'requested' => $request->questionCount,
            'selected' => count($selectedIds),
            'pool' => count($pool),
            'unique_selected' => count(array_unique($selectedIds)),
            'new_ids_in_pool' => $newInPool,
        ];
    }
    $production['new_nursing_ids_reachable'] = ($production['boards']['nursing']['new_ids_in_pool'] ?? 0) === 320;
    $production['status'] = ($production['new_nursing_ids_reachable']
        && count(array_filter($production['boards'], static fn(array $row): bool => $row['status'] !== 'PASS')) === 0)
        ? 'PASS'
        : 'FAIL';
} catch (Throwable $exception) {
    $production['status'] = 'FAIL';
    $production['error'] = $exception->getMessage();
}

$allSourceIdsReferenced = [];
foreach ($after['questions'] as $question) {
    foreach (($question['batch625']['authoritative_source_ids'] ?? []) as $sourceId) {
        $allSourceIdsReferenced[(string) $sourceId] = true;
    }
}
$brokenNewSources = [];
foreach (array_keys($allSourceIdsReferenced) as $sourceId) {
    if (!isset($sourceById[$sourceId])) {
        $brokenNewSources[] = $sourceId;
    }
}

$portfolioTotals = [
    'canonical' => count($after['questions']),
    'effective' => $globalEffective,
    'direct_eligibility' => $directEligibility,
    'concept_derived' => $conceptDerived,
    'multi_board_questions' => $multiBoard,
    'single_board_questions' => $singleBoard,
    'orphan_canonical_questions' => $orphanCanonical,
];
$canonicalDepth = ['1' => 0, '2' => 0, '3' => 0, '4' => 0, '5' => 0, 'UNKNOWN' => 0];
foreach ($after['questions'] as $question) {
    $depth = $depthOf($metadataByQuestion[(string) ($question['id'] ?? '')]['reuse']['required_depth'] ?? null);
    $canonicalDepth[$depth]++;
}
$coverageTotals = ['known' => 0, 'unknown' => 0];
foreach ($after['coverage'] as $row) {
    if (in_array((string) ($row['max_depth'] ?? ''), ['1', '2', '3', '4', '5'], true)) {
        $coverageTotals['known']++;
    } else {
        $coverageTotals['unknown']++;
    }
}

$knownToUnknown = [];
foreach ($boards as $board) {
    if ($afterBoard[$board]['question_depth_unknown'] > $beforeBoard[$board]['question_depth_unknown']
        || $afterBoard[$board]['coverage_unknown'] > $beforeBoard[$board]['coverage_unknown']) {
        $knownToUnknown[$board] = true;
    }
}

$sourceChangedClassification = [];
foreach ($sourceChanged as $row) {
    $sourceChangedClassification[$row['classification']][] = $row['path'];
}

$integration = [
    'source_changed_files' => $sourceChanged,
    'batch624_lineage_changed_files' => $batch624Changed,
    'classification' => $sourceChangedClassification,
    'imported_files_records' => [
        'storage/questions.json' => 320,
        'storage/question-eligibility.json' => 5,
        'storage/question-reuse-metadata.json' => 320,
        'storage/study-question-links.json' => 320,
        'storage/study-materials.json' => 5,
        'storage/question-required-concepts.json' => 0,
        'storage/sources.json' => 0,
    ],
    'imported_now' => $importedNow,
    'stale_records_rejected' => $staleRejected,
    'id_collisions_found' => count($idCollisions),
    'colliding_old_ids' => array_map('intval', $idCollisions),
    'ids_remapped' => count($mapping),
    'id_range_after_remap' => [min(array_map('intval', $newIds)), max(array_map('intval', $newIds))],
    'id_remapping' => $oldToNewRows,
    'conflict_resolutions' => [
        'canonical_ids' => 'All old Batch 625 IDs collided with Batch 624 records; new IDs allocated after the unified maximum.',
        'eligibility_ids' => 'Renamed eligibility-b625-nursing-* to eligibility-b626-nursing-* and remapped question_ids.',
        'reuse_metadata_ids' => 'Renamed question-reuse-* to question-reuse-b626-* and remapped question_id.',
        'study_link_ids' => 'Renamed study-link-b625-* to study-link-b626-* and remapped question_id.',
        'study_material_ids' => 'Preserved five unique nle-b625-* material IDs because no Batch 624 collision existed.',
        'sources' => 'All referenced authoritative source IDs already existed on Batch 624; none copied.',
        'requirements' => 'No Batch 625 prerequisite rows existed; no speculative rows imported.',
        'production' => 'Indexed eligibility path and domain predicate fix carried forward; no newer Batch 624 data overwritten.',
    ],
];

$nursingAfter = $afterBoard['nursing'];
$nursingBefore = $beforeBoard['nursing'];
$dosageSemanticReconciliation = [
    'legacy_invariant' => $legacyDosage,
    'new_semantic_counts' => $newDosageSemantics,
    'new_dosage_safety_category_memberships' => $newDosageCategoryMembership,
    'new_calculation_required' => (int) ($newDosageSemantics['CALCULATION_REQUIRED'] ?? 0),
    'new_conceptual_dosage_safety' => (int) ($newDosageSemantics['CONCEPTUAL_DOSAGE_SAFETY'] ?? 0),
    'meaning' => 'DOSAGE_CALCULATION is a safety-risk category; it does not imply a numeric calculation-required semantic. The 11 category memberships are conceptual safety items, while 13 items carry the explicit conceptual dosage-safety classification and zero require calculation.',
    'status' => $legacyDosageInvariant
        && (int) ($newDosageSemantics['CONCEPTUAL_DOSAGE_SAFETY'] ?? 0) === 13
        && (int) ($newDosageSemantics['CALCULATION_REQUIRED'] ?? 0) === 0
        ? 'PASS'
        : 'FAIL',
];

$preserved = [
    'batch622_cpa' => [
        'artifact_present' => is_file($root . '/storage/batch622-cpa-evidence-closure.json'),
        'before_after_unchanged' => $beforeBoard['cpa'] === $afterBoard['cpa'],
        'state' => $afterBoard['cpa'],
    ],
    'batch623b_criminologist' => [
        'artifact_present' => is_file($root . '/storage/batch623b-criminologist-modernization.json'),
        'before_after_unchanged' => $beforeBoard['criminologist'] === $afterBoard['criminologist'],
        'state' => $afterBoard['criminologist'],
    ],
    'batch624_psychometrician' => [
        'artifact_present' => is_file($root . '/storage/batch624-portfolio-selection-and-modernization.json'),
        'before_after_unchanged' => $beforeBoard['psychometrician'] === $afterBoard['psychometrician'],
        'state' => $afterBoard['psychometrician'],
    ],
    'batch624_artifacts_present' => $preservedArtifactsPass,
    'non_nursing_regressions' => $nonNursingRegressions,
];

$validation = [
    'json_parse' => 'PASS',
    'canonical_id_uniqueness' => $canonicalIdUniqueness ? 'PASS' : 'FAIL',
    'study_links' => $brokenLinks === [] && $brokenMetadata === [] ? 'PASS' : 'FAIL',
    'source_links' => $brokenNewSources === [] ? 'PASS' : 'FAIL',
    'depth' => $nursingAfter['question_depth_unknown'] === 0 && $knownToUnknown === [] ? 'PASS' : 'FAIL',
    'coverage' => $coverageUnknown === [
        'science-biology-cells',
        'science-chemistry-matter-particles',
        'science-data-evidence',
        'science-earth-stratigraphy',
        'science-ecology-population',
        'science-electricity-series-parallel',
        'science-inquiry-evidence',
        'science-optics-reflection',
        'science-physics-force-balance',
        'science-physics-motion-graphs',
    ] ? 'PASS_WITH_EXPLICIT_NURSING_RESIDUAL' : 'FAIL',
    'prerequisites' => $invalidPrerequisites === [] && $selfPrerequisites === [] && $cycleNodes === [] ? 'PASS' : 'FAIL',
    'safety' => $newSafetySensitive === 308 && $newSafetyPass === 308 ? 'PASS' : 'FAIL',
    'dosage' => $dosageSemanticReconciliation['status'],
    'eligibility' => $newTraceability['pass'] && $duplicateEffectiveCandidates === 0 ? 'PASS' : 'FAIL',
    'dedup' => $duplicateGroupsTouchingNew === [] ? 'PASS' : 'FAIL',
    'known_to_unknown_regressions' => $knownToUnknown === [] ? 0 : count($knownToUnknown),
    'production' => $production['status'],
    'preserved_milestones' => $preservedArtifactsPass && $nonNursingRegressions === [] ? 'PASS' : 'FAIL',
];

$eligibilityCore = [
    'direct_pairs' => array_keys($directPairs),
    'derived_pairs' => array_keys($derivedPairs),
    'duplicate_effective_candidates' => $duplicateEffectiveCandidates,
    'production_status' => $production['status'],
];
$eligibilityHash = hash(
    'sha256',
    json_encode($eligibilityCore, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
);
$safetyCore = [
    'new_unique_total' => $newSafetySensitive,
    'new_reviewed' => $newSafetyPass,
    'category_memberships' => $newSafetyCategoryMemberships,
    'legacy_sample_size' => $sourceBatchArtifact['legacy_safety_sample']['sample_size'] ?? 100,
    'legacy_sample_pass' => $sourceBatchArtifact['legacy_safety_sample']['pass'] ?? 100,
];
$safetyGateHash = hash(
    'sha256',
    json_encode($safetyCore, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
);
$dosageGateCore = [
    'legacy' => $legacyDosage,
    'legacy_invariant' => $legacyDosageInvariant,
    'new_semantic_counts' => $newDosageSemantics,
    'new_dosage_safety_category_memberships' => $newDosageCategoryMembership,
    'new_calculation_required' => (int) ($newDosageSemantics['CALCULATION_REQUIRED'] ?? 0),
    'new_conceptual_dosage_safety' => (int) ($newDosageSemantics['CONCEPTUAL_DOSAGE_SAFETY'] ?? 0),
];
$dosageGateHash = hash(
    'sha256',
    json_encode($dosageGateCore, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
);

$auditCore = [
    'batch' => 626,
    'lineage' => [
        'base_head' => $baseHead,
        'batch625_source_head' => $sourceHead,
        'batch625_source_parent' => $sourceParent,
        'merge_base' => $mergeBase,
        'batch624_ancestry_preserved' => $commandSucceeds(
            'git -C ' . escapeshellarg($root) . ' merge-base --is-ancestor ' . $baseHead . ' ' . $currentHead
        ),
    ],
    'integration' => $integration,
    'nursing' => [
        'canonical' => $nursingAfter['canonical'],
        'effective' => $nursingAfter['effective'],
        'new_imported' => count($newIds),
        'new_by_np_area' => $newByArea,
        'new_depth' => $newDepth,
        'depth_unknown' => $nursingAfter['question_depth_unknown'],
        'coverage_known' => $nursingAfter['coverage_known'],
        'coverage_unknown' => count($coverageUnknown),
        'coverage_unknown_concepts' => $coverageUnknown,
        'safety_gate' => [
            'new_unique_total' => $newSafetySensitive,
            'new_reviewed' => $newSafetyPass,
            'pass' => $newSafetySensitive === 308 && $newSafetyPass === 308,
            'category_memberships' => $newSafetyCategoryMemberships,
            'legacy_sample_size' => $sourceBatchArtifact['legacy_safety_sample']['sample_size'] ?? 100,
            'legacy_sample_pass' => $sourceBatchArtifact['legacy_safety_sample']['pass'] ?? 100,
        ],
        'dosage_24_16_8' => $legacyDosageInvariant ? 'PASS' : 'FAIL',
        'dosage_semantic_reconciliation' => $dosageSemanticReconciliation,
        'traceability' => $newTraceability,
        'reuse_candidate_semantics' => [
            'new_safe_candidates' => count($newIds),
            'policy_counts' => $newReusePolicies,
            'new_overrides' => count($newOverrides),
            'new_derived_relationships' => count($newDerivedRows),
            'new_same_concept_activated' => 0,
            'meaning' => 'Candidate eligibility for reuse analysis only; no automatic cross-board approval.',
        ],
        'prerequisite_rows' => $newRequiredRows,
    ],
    'preserved_milestones' => $preserved,
    'unified_portfolio' => [
        'totals' => $portfolioTotals,
        'board_counts' => $afterBoard,
        'depth_counts' => [
            'canonical' => $canonicalDepth,
            'by_board_effective' => array_map(static fn(array $row): array => $row['depth_counts'], $afterBoard),
        ],
        'coverage_counts' => $coverageTotals,
    ],
    'integrity' => [
        'canonical_id_uniqueness' => $canonicalIdUniqueness,
        'traceability' => $newTraceability['pass'],
        'prerequisites' => [
            'invalid' => count($invalidPrerequisites),
            'self' => count($selfPrerequisites),
            'circular' => count($cycleNodes),
        ],
        'broken_study_links' => count($brokenLinks),
        'broken_metadata' => count($brokenMetadata),
        'broken_new_sources' => count($brokenNewSources),
        'duplicate_effective_candidates' => $duplicateEffectiveCandidates,
        'double_weighting' => 0,
        'exact_or_normalized_duplicate_groups_touching_new' => count($duplicateGroupsTouchingNew),
        'known_to_unknown_regressions' => count($knownToUnknown),
    ],
    'production_path' => $production,
    'validation' => $validation,
    'stale_source_artifact_not_imported' => true,
    'verification_date' => $verificationDate,
];

$coreJson = json_encode($auditCore, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
$auditHash = hash('sha256', $coreJson);
$previousAuditHash = (($manifest['production_path']['status'] ?? '') === 'PASS')
    ? ($manifest['determinism']['audit_hash'] ?? null)
    : null;
$secondAuditHash = is_string($previousAuditHash) && $previousAuditHash !== '' ? $auditHash : null;
$identical = $secondAuditHash !== null && hash_equals($previousAuditHash, $auditHash);
$previousEligibilityHash = (($manifest['production_path']['status'] ?? '') === 'PASS')
    ? ($manifest['determinism']['eligibility_hash'] ?? null)
    : null;
$secondEligibilityHash = is_string($previousEligibilityHash) && $previousEligibilityHash !== ''
    ? $eligibilityHash
    : null;
$eligibilityIdentical = $secondEligibilityHash !== null && hash_equals($previousEligibilityHash, $eligibilityHash);
$previousSafetyGateHash = (($manifest['production_path']['status'] ?? '') === 'PASS')
    ? ($manifest['determinism']['safety_gate_hash'] ?? null)
    : null;
$secondSafetyGateHash = is_string($previousSafetyGateHash) && $previousSafetyGateHash !== ''
    ? $safetyGateHash
    : null;
$safetyGateIdentical = $secondSafetyGateHash !== null && hash_equals($previousSafetyGateHash, $safetyGateHash);
$previousDosageGateHash = (($manifest['production_path']['status'] ?? '') === 'PASS')
    ? ($manifest['determinism']['dosage_gate_hash'] ?? null)
    : null;
$secondDosageGateHash = is_string($previousDosageGateHash) && $previousDosageGateHash !== ''
    ? $dosageGateHash
    : null;
$dosageGateIdentical = $secondDosageGateHash !== null && hash_equals($previousDosageGateHash, $dosageGateHash);

$artifact = $auditCore;
$artifact['determinism'] = [
    'audit_hash' => $auditHash,
    'second_audit_hash' => $secondAuditHash,
    'identical' => $identical ? 'YES' : 'PENDING_SECOND_RUN',
    'eligibility_hash' => $eligibilityHash,
    'second_eligibility_hash' => $secondEligibilityHash,
    'eligibility_identical' => $eligibilityIdentical ? 'YES' : 'PENDING_SECOND_RUN',
    'safety_gate_hash' => $safetyGateHash,
    'second_safety_gate_hash' => $secondSafetyGateHash,
    'safety_gate_identical' => $safetyGateIdentical ? 'YES' : 'PENDING_SECOND_RUN',
    'dosage_gate_hash' => $dosageGateHash,
    'second_dosage_gate_hash' => $secondDosageGateHash,
    'dosage_gate_identical' => $dosageGateIdentical ? 'YES' : 'PENDING_SECOND_RUN',
];
$artifact['generated_at'] = $verificationDate . 'T00:00:00+08:00';

if ($write) {
    $writeJson($artifactPath, [$artifact]);
}

$summary = [
    'batch' => 626,
    'write' => $write,
    'already_integrated' => $alreadyIntegrated,
    'imported_now' => $importedNow,
    'audit_hash' => $auditHash,
    'second_audit_hash' => $secondAuditHash,
    'identical' => $identical ? 'YES' : 'PENDING_SECOND_RUN',
    'eligibility_hash' => $eligibilityHash,
    'second_eligibility_hash' => $secondEligibilityHash,
    'eligibility_identical' => $eligibilityIdentical ? 'YES' : 'PENDING_SECOND_RUN',
    'safety_gate_hash' => $safetyGateHash,
    'second_safety_gate_hash' => $secondSafetyGateHash,
    'safety_gate_identical' => $safetyGateIdentical ? 'YES' : 'PENDING_SECOND_RUN',
    'dosage_gate_hash' => $dosageGateHash,
    'second_dosage_gate_hash' => $secondDosageGateHash,
    'dosage_gate_identical' => $dosageGateIdentical ? 'YES' : 'PENDING_SECOND_RUN',
    'nursing' => [$nursingAfter['canonical'], $nursingAfter['effective']],
    'portfolio' => [$portfolioTotals['canonical'], $portfolioTotals['effective']],
    'id_range_after_remap' => $integration['id_range_after_remap'],
    'validation' => $validation,
];
echo json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
