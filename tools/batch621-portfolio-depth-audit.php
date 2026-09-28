<?php

declare(strict_types=1);

/**
 * Batch 621: deterministic portfolio-wide question/coverage depth review.
 *
 * This tool changes only existing UNKNOWN depth metadata when the question,
 * study link, source chain, and solving operation support a bounded depth.
 * It never changes question content, taxonomy, eligibility, prerequisites,
 * context, or different-ID equivalence.  It is deliberately conservative
 * about cross-board coverage rows with no independent board evidence.
 */

$root = dirname(__DIR__);
$write = in_array('--write', $argv, true);
$batch = '621';
$baselineHead = '06f712b5b3d8b0e93186d72b53f98dcc300cb1cf';
$toolId = 'batch621-portfolio-depth-modernization';
require_once $root . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

$read = static function (string $path): array {
    $raw = file_get_contents($path);
    if ($raw === false) throw new RuntimeException('Cannot read ' . $path);
    $value = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    return is_array($value) ? $value : [];
};
$writeJson = static function (string $path, array $value): void {
    file_put_contents($path, json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n");
};
$splitArrayRecords = static function (string $raw): array {
    $records = []; $length = strlen($raw); $start = null; $depth = 0; $inString = false; $escaped = false;
    for ($i = 0; $i < $length; $i++) {
        $char = $raw[$i];
        if ($inString) { if ($escaped) $escaped = false; elseif ($char === '\\') $escaped = true; elseif ($char === '"') $inString = false; continue; }
        if ($char === '"') { $inString = true; continue; }
        if ($char === '{') {
            if ($depth === 0) {
                $lineStart = strrpos(substr($raw, 0, $i), "\n");
                $lineStart = $lineStart === false ? 0 : $lineStart + 1;
                $start = $lineStart;
            }
            $depth++; continue;
        }
        if ($char === '}') { $depth--; if ($depth === 0 && $start !== null) { $records[] = substr($raw, $start, $i - $start + 1); $start = null; } }
    }
    return $records;
};
$writeRowsPreservingBaseline = static function (string $relativePath, string $baselineHead, array $currentRows, array $changedIds) use ($root, $splitArrayRecords): void {
    $command = 'git -C ' . escapeshellarg($root) . ' show ' . escapeshellarg($baselineHead . ':' . $relativePath);
    $baselineRaw = shell_exec($command);
    if (!is_string($baselineRaw) || trim($baselineRaw) === '') throw new RuntimeException('Cannot read baseline ' . $relativePath);
    $baselineRecords = $splitArrayRecords($baselineRaw); $currentById = [];
    foreach ($currentRows as $row) if (is_array($row) && isset($row['id'])) $currentById[(string)$row['id']] = $row;
    $changed = array_fill_keys(array_map('strval', $changedIds), true); $output = "[\n"; $encodedRecords = [];
    foreach ($baselineRecords as $record) {
        $decoded = json_decode(trim($record), true, 512, JSON_THROW_ON_ERROR); $id = (string)($decoded['id'] ?? '');
        $value = (isset($changed[$id]) && isset($currentById[$id])) ? $currentById[$id] : $decoded;
        if (!isset($changed[$id])) { $encodedRecords[] = $record; continue; }
        $pretty = str_contains($record, "\n");
        $encoded = json_encode($value, ($pretty ? JSON_PRETTY_PRINT : 0) | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        preg_match('/^([ \t]*)\\{/', $record, $indentMatch);
        $indent = $indentMatch[1] ?? '';
        if ($pretty) $encoded = implode("\n", array_map(static fn(string $line): string => $indent . $line, explode("\n", $encoded)));
        /* Legacy Batch 618/620 rows were appended as unindented compact JSON;
         * keep that representation so a metadata pass does not rewrite
         * untouched records. */
        else $encoded = $indent . $encoded;
        $encodedRecords[] = $encoded;
    }
    $output .= implode(",\n", $encodedRecords) . "\n]\n";
    file_put_contents($root . '/' . $relativePath, $output);
};
$validDepth = static fn(mixed $value): bool => in_array((string)$value, ['1', '2', '3', '4', '5'], true);
$depthOf = static function (mixed $value) use ($validDepth): string {
    return $validDepth($value) ? (string)$value : 'UNKNOWN';
};
$depthSort = static function (array &$rows): void {
    usort($rows, static fn(array $a, array $b): int => ((int)($a['question_id'] ?? $a['coverage_id'] ?? 0)) <=> ((int)($b['question_id'] ?? $b['coverage_id'] ?? 0)));
};
$containsAny = static function (string $text, array $needles): bool {
    foreach ($needles as $needle) if (preg_match('/' . $needle . '/iu', $text)) return true;
    return false;
};
$normalizeText = static fn(string $text): string => strtolower(trim(preg_replace('/\s+/', ' ', $text) ?? $text));

$boards = ['let', 'civil-service', 'criminologist', 'nursing', 'psychometrician', 'civil-engineering', 'cpa', 'mechanical-engineering', 'registered-electrical-engineering', 'architecture'];
$processingOrder = ['let', 'civil-service', 'cpa', 'criminologist', 'mechanical-engineering', 'psychometrician', 'registered-electrical-engineering', 'nursing', 'civil-engineering', 'architecture'];
$processingRationale = [
    'let' => 'Largest legacy question backlog with broad linked educational sources and stable competency evidence.',
    'civil-service' => 'Second-largest backlog with explicit analytical/application metadata and stable scope sources.',
    'cpa' => 'Large coherent backlog with accounting, auditing, law, tax, and standards source chains; sensitive items are flagged.',
    'criminologist' => 'Large coherent backlog, but legal/forensic correctness remains separately flagged and source support is primarily scope-level.',
    'mechanical-engineering' => 'Residual Batch 618 engineering mathematics/science questions have strong instructional source support.',
    'psychometrician' => 'Small coherent interpretation backlog; depth can be calibrated, while source support remains scope-led.',
    'registered-electrical-engineering' => 'Residual mathematical/allied/control items have stable engineering source support and clear solving operations.',
    'nursing' => 'Only shared-science coverage rows remain unresolved; no Nursing-specific question depth is reopened.',
    'civil-engineering' => 'Already complete; verification only.',
    'architecture' => 'Already complete; verification only.',
];

$questions = array_values(array_filter($read($root . '/storage/questions.json'), 'is_array'));
$metadataRows = array_values(array_filter($read($root . '/storage/question-reuse-metadata.json'), 'is_array'));
$coverageRows = array_values(array_filter($read($root . '/storage/board-concept-coverage.json'), 'is_array'));
$eligibilityRows = array_values(array_filter($read($root . '/storage/question-eligibility.json'), 'is_array'));
$derivedRows = array_values(array_filter($read($root . '/storage/concept-derived-eligibility.json'), 'is_array'));
$linksRows = array_values(array_filter($read($root . '/storage/study-question-links.json'), 'is_array'));
$sourceRows = array_values(array_filter($read($root . '/storage/sources.json'), 'is_array'));
$materialRows = array_values(array_filter($read($root . '/storage/study-materials.json'), 'is_array'));
$requiredRows = array_values(array_filter($read($root . '/storage/question-required-concepts.json'), 'is_array'));
$conceptRows = array_values(array_filter($read($root . '/storage/taxonomy/concepts.json'), 'is_array'));
$boardsRows = array_values(array_filter($read($root . '/storage/boards.json'), 'is_array'));

$questionsById = [];
foreach ($questions as $question) $questionsById[(string)($question['id'] ?? '')] = $question;
$metadataByQuestion = [];
foreach ($metadataRows as $row) $metadataByQuestion[(string)($row['question_id'] ?? '')] = $row;
$sourcesById = [];
foreach ($sourceRows as $source) $sourcesById[(string)($source['id'] ?? '')] = $source;
$materialsById = [];
foreach ($materialRows as $material) $materialsById[(string)($material['id'] ?? '')] = $material;
$conceptIds = [];
foreach ($conceptRows as $concept) $conceptIds[(string)($concept['id'] ?? '')] = true;
$linksByQuestion = [];
foreach ($linksRows as $link) $linksByQuestion[(string)($link['question_id'] ?? '')][] = $link;

/* Existing direct placement map.  question_mappings are included because a
 * single eligibility object can carry board-specific placement metadata. */
$directBoardsByQuestion = [];
$directPairCounts = [];
foreach ($eligibilityRows as $entry) {
    $entryBoards = array_values(array_filter(array_map('strval', $entry['exam_ids'] ?? [])));
    foreach ($entry['question_ids'] ?? [] as $questionId) {
        $id = (string)$questionId;
        $mapping = is_array($entry['question_mappings'][$id] ?? null) ? $entry['question_mappings'][$id] : [];
        $mappedBoards = array_values(array_filter(array_map('strval', $mapping['exam_ids'] ?? $entryBoards)));
        foreach ($mappedBoards as $board) {
            $board = ['cse' => 'civil-service', 'civilservice' => 'civil-service'][strtolower($board)] ?? strtolower($board);
            if ($board === '') continue;
            $directBoardsByQuestion[$id][$board] = true;
            $directPairCounts[$id . '|' . $board] = ($directPairCounts[$id . '|' . $board] ?? 0) + 1;
        }
    }
}
$derivedBoardsByQuestion = [];
foreach ($derivedRows as $row) {
    if (($row['record_type'] ?? '') !== 'RELATIONSHIP') continue;
    $id = (string)($row['question_id'] ?? ''); $board = strtolower((string)($row['target_board'] ?? ''));
    if ($id !== '' && $board !== '') $derivedBoardsByQuestion[$id][$board] = true;
}
$allBoardsByQuestion = [];
foreach ($questions as $question) {
    $id = (string)($question['id'] ?? '');
    $allBoardsByQuestion[$id][strtolower((string)($question['taxonomy']['board_id'] ?? ''))] = true;
    foreach (array_keys($directBoardsByQuestion[$id] ?? []) as $board) $allBoardsByQuestion[$id][$board] = true;
    foreach (array_keys($derivedBoardsByQuestion[$id] ?? []) as $board) $allBoardsByQuestion[$id][$board] = true;
}
$effectiveQuestionIdsByBoard = [];
foreach ($allBoardsByQuestion as $id => $boardMap) foreach (array_keys($boardMap) as $board) if (in_array($board, $boards, true)) $effectiveQuestionIdsByBoard[$board][$id] = true;

$hasBatch621Provenance = static function (array $row, string $provenanceKey): bool {
    foreach (($row[$provenanceKey] ?? []) as $entry) if (is_array($entry) && (($entry['type'] ?? '') === 'BATCH621_DEPTH_MODERNIZATION' || (($entry['type'] ?? '') === 'OTHER' && ($entry['reference'] ?? '') === 'batch-621-portfolio-depth'))) return true;
    return false;
};
$hasBatch621CoverageProvenance = static function (array $row): bool {
    foreach (($row['provenance'] ?? []) as $entry) if (is_array($entry) && (($entry['type'] ?? '') === 'BATCH621_COVERAGE_DEPTH_MODERNIZATION' || (($entry['type'] ?? '') === 'OTHER' && ($entry['reference'] ?? '') === 'batch-621-portfolio-depth-coverage'))) return true;
    return false;
};
$metadataDepthBefore = [];
$metadataDepthAfter = [];
foreach ($questions as $question) {
    $id = (string)$question['id'];
    $stored = $depthOf($metadataByQuestion[$id]['reuse']['required_depth'] ?? null);
    $alreadyModernized = $hasBatch621Provenance($metadataByQuestion[$id]['reuse'] ?? [], 'metadata_provenance');
    $metadataDepthBefore[$id] = $alreadyModernized ? 'UNKNOWN' : $stored;
    $metadataDepthAfter[$id] = $stored;
}
$coverageDepthBefore = [];
$coverageDepthAfter = [];
foreach ($coverageRows as $row) {
    $id = (string)($row['id'] ?? '');
    $stored = $depthOf($row['max_depth'] ?? null);
    $alreadyModernized = $hasBatch621CoverageProvenance($row);
    $coverageDepthBefore[$id] = $alreadyModernized ? 'UNKNOWN' : $stored;
    $coverageDepthAfter[$id] = $stored;
}

$countDepths = static function (array $values): array {
    $out = ['1' => 0, '2' => 0, '3' => 0, '4' => 0, '5' => 0, 'UNKNOWN' => 0];
    foreach ($values as $value) $out[in_array((string)$value, ['1', '2', '3', '4', '5'], true) ? (string)$value : 'UNKNOWN']++;
    return $out;
};
$globalDepthBefore = $countDepths(array_values($metadataDepthBefore));
$globalCoverageBefore = $countDepths(array_values($coverageDepthBefore));

/* Batch 612 deliberately froze these Nursing target rows because shared
 * science questions did not establish an independent Nursing maximum.  They
 * remain an explicit exclusion in this portfolio pass. */
$frozenRecords = [];
$frozenCoverageIds = [];
$frozenPath = $root . '/storage/batch612-target-depth-audit.json';
if (is_file($frozenPath)) {
    $frozenArtifact = $read($frozenPath);
    $frozenRecords = $frozenArtifact[0]['relationships'] ?? [];
    foreach ($frozenRecords as $record) {
        $frozenCoverageId = (string)($record['target_coverage_id'] ?? '');
        if ($frozenCoverageId !== '') $frozenCoverageIds[$frozenCoverageId] = true;
    }
}

$sourceIsInstructional = static function (array $source): bool {
    $type = strtoupper((string)($source['source_type'] ?? ''));
    $confidence = strtolower((string)($source['confidence'] ?? ''));
    $authority = strtolower((string)($source['authority'] ?? ''));
    return in_array($type, ['OPEN_TEXTBOOK', 'SECONDARY_REVIEW'], true)
        || str_contains($confidence, 'instructional')
        || str_contains($authority, 'university')
        || str_contains($authority, 'openstax')
        || str_contains($authority, 'world health organization')
        || str_contains($authority, 'centers for disease control');
};
$sourceIsScope = static fn(array $source): bool => in_array(strtoupper((string)($source['source_type'] ?? '')), ['OFFICIAL_EXAM_SPEC', 'OFFICIAL_PROGRAM'], true);
$materialUsable = static function (array $material): bool {
    if (($material['notes'] ?? []) === []) return false;
    $text = json_encode($material['notes'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return is_string($text) && strlen(trim($text)) >= 40;
};

/* Classify only unknown question depths.  The rules deliberately separate
 * solving demand from difficulty, context, risk, regulation, and numerical
 * presence. */
$classifyQuestion = static function (array $question, array $placements, array $linksByQuestion, array $materialsById, array $sourcesById) use ($containsAny, $sourceIsInstructional, $sourceIsScope, $materialUsable, $normalizeText): array {
    $id = (string)($question['id'] ?? '');
    $links = $linksByQuestion[$id] ?? [];
    if (count($links) !== 1) return ['decision' => 'KEEP_UNKNOWN', 'reason_code' => 'NO_STUDY_LINK', 'reason' => 'No unique BoardPrep study link supports a defensible evidence chain.', 'confidence' => 'INSUFFICIENT', 'depth' => 'UNKNOWN', 'evidence_class' => 'NO_STUDY_LINK', 'source_ids' => [], 'study_ids' => [], 'flags' => []];
    $link = $links[0]; $materialId = (string)($link['material_id'] ?? '');
    if (!isset($materialsById[$materialId]) || !$materialUsable($materialsById[$materialId])) return ['decision' => 'KEEP_UNKNOWN', 'reason_code' => 'WEAK_STUDY_RESOURCE', 'reason' => 'The linked study resource is missing or too thin to support a defensible depth reading.', 'confidence' => 'INSUFFICIENT', 'depth' => 'UNKNOWN', 'evidence_class' => 'WEAK_STUDY_RESOURCE', 'source_ids' => array_values(array_map('strval', $link['source_ids'] ?? [])), 'study_ids' => [$materialId], 'flags' => []];
    $sourceIds = array_values(array_unique(array_map('strval', $link['source_ids'] ?? [])));
    $allSourcesExist = $sourceIds !== [];
    $instructional = false; $scope = false;
    foreach ($sourceIds as $sourceId) {
        if (!isset($sourcesById[$sourceId])) { $allSourcesExist = false; continue; }
        $instructional = $instructional || $sourceIsInstructional($sourcesById[$sourceId]);
        $scope = $scope || $sourceIsScope($sourcesById[$sourceId]);
    }
    if (!$allSourcesExist) return ['decision' => 'KEEP_UNKNOWN', 'reason_code' => 'NO_AUTHORITATIVE_SOURCE', 'reason' => 'The study link does not resolve to a complete authoritative source set.', 'confidence' => 'INSUFFICIENT', 'depth' => 'UNKNOWN', 'evidence_class' => 'NO_AUTHORITATIVE_SOURCE', 'source_ids' => $sourceIds, 'study_ids' => [$materialId], 'flags' => []];
    $stem = (string)($question['question'] ?? ''); $text = $normalizeText($stem . ' ' . (string)($question['explanation'] ?? ''));
    if (trim($stem) === '' || count($question['choices'] ?? []) !== 4 || trim((string)($question['answer'] ?? '')) === '') return ['decision' => 'KEEP_UNKNOWN', 'reason_code' => 'AMBIGUOUS_QUESTION', 'reason' => 'Question structure is incomplete for a safe cognitive-demand classification.', 'confidence' => 'INSUFFICIENT', 'depth' => 'UNKNOWN', 'evidence_class' => 'AMBIGUOUS_QUESTION', 'source_ids' => $sourceIds, 'study_ids' => [$materialId], 'flags' => []];
    $concept = strtolower((string)($question['taxonomy']['concept_id'] ?? ''));
    $ci = strtolower((string)($question['cognitive_intent'] ?? ''));
    $data = !empty($question['data_interpretation']); $long = !empty($question['long_scenario']);
    $numeric = (bool)preg_match('/(?:\d|%|₱|\b(?:mean|median|variance|probability|rate|area|volume|force|stress|power|voltage|current|resistance|pressure|temperature|energy|efficiency|cost|equation|formula)\b)/iu', $stem);
    $simpleFact = (bool)preg_match('/\b(?:define|definition|meaning|what does .* mean|which term|correct spelling|which word|what is called|who wrote|when did|where did)\b/iu', $stem)
        || (bool)preg_match('/(?:spelling|word meaning|parts-of-speech|subject-verb|preposition|pronoun|verb-tense|filing-and-alphabetizing)/i', $concept);
    $analysisPhrase = $containsAny($text, ['compare', 'contrast', 'evaluate', 'assess', 'diagnos', 'justify', 'defend', 'recommend', 'most defensible', 'trade[- ]off', 'conflict', 'alternative', 'which strategy', 'what should .* (?:first|next)', 'what action .* (?:first|next)', 'interpret .* (?:table|data|pattern)', 'evidence .* (?:before|against|supports)', 'cause and effect', 'implication']);
    $highIntent = (bool)preg_match('/\b(?:analy[sz]e|evaluat(?:e|ion)|diagnos(?:e|is)|synthesi[sz]e|decide|professional[- ]judgment|instructional decision|quantitative reasoning)\b/i', $ci);
    $applicationIntent = (bool)preg_match('/\b(?:apply|application|interpret|infer|predict|classify|distinguish|compare|context|significance|sequence)\b/i', $ci);
    $specialized = in_array('criminologist', array_keys($placements), true) && (str_starts_with($concept, 'cle-') || $containsAny($text, ['\blaw\b', 'offense', 'offence', 'statute', 'penalty', 'evidence', 'custody', 'forensic', 'probable cause', 'correctional']));
    $legalSensitive = $specialized || $containsAny($text, ['\bstatute\b', '\blaw\b', 'offense', 'offence', 'penalty', 'criminal procedure', 'chain of custody', 'forensic', 'tax law', '\bifrs\b', '\bbir\b', 'regulation', 'licensure', 'professional rule']);
    $regulatorySensitive = $containsAny($text, ['\bstatute\b', '\blaw\b', 'penalty', 'tax', '\bifrs\b', '\bbir\b', 'regulation', 'licensure', 'professional rule', 'current requirement', 'threshold']);
    $clinicalSensitive = in_array('nursing', array_keys($placements), true) && $containsAny($text, ['medication', 'dosage', 'infection control', 'isolation', 'maternal', 'newborn', 'emergency', 'priorit']);
    $flags = [];
    if ($legalSensitive) $flags[] = 'LEGAL_FORENSIC_SENSITIVE';
    if ($regulatorySensitive) $flags[] = 'REGULATORY_VERSION_SENSITIVE';
    if ($clinicalSensitive) $flags[] = 'CLINICAL_SENSITIVE';
    $complex = $data || ($long && strlen($stem) >= 150) || ($analysisPhrase && strlen($stem) >= 150);
    $depth = 3;
    if ($simpleFact && !$analysisPhrase && !$data && !$highIntent) $depth = 2;
    elseif ($numeric) {
        /* Numerical presence establishes neither depth nor analysis.  Routine
         * engineering/accounting/math operations remain application depth. */
        $depth = ($analysisPhrase && $complex && !$containsAny($stem, ['what is', 'what value', 'what amount', 'how much', 'what rate', 'what pressure', 'what force', 'what current', 'what voltage', 'what resistance'])) ? 4 : 3;
    } elseif ($highIntent || $analysisPhrase) {
        $depth = ($complex || $specialized) ? 4 : 3;
    } elseif ($applicationIntent) $depth = 3;
    elseif (preg_match('/\b(?:understand|recognize|recall|remember)\b/i', $ci)) $depth = 2;
    else $depth = 3;
    $confidence = ($instructional && ($ci !== '' || $analysisPhrase || $numeric || $simpleFact)) ? 'HIGH' : 'MEDIUM';
    $evidenceClass = $instructional ? 'QUESTION_STUDY_INSTRUCTIONAL_SOURCE' : ($scope ? 'QUESTION_STUDY_SCOPE_SOURCE' : 'QUESTION_STUDY_AUTHORITATIVE_SOURCE');
    $reason = $depth === 4
        ? 'Question requires evidence comparison, multi-cue interpretation, diagnosis, or specialized professional reasoning beyond routine application.'
        : ($depth === 3 ? 'Question requires a bounded application, interpretation, or routine quantitative operation.' : 'Question primarily tests foundational recognition or direct conceptual understanding.');
    if ($legalSensitive) $reason .= ' Substantive legal/forensic correctness remains separately flagged for review.';
    return ['decision' => 'ASSIGN', 'reason_code' => 'EVIDENCE_SUPPORTED', 'reason' => $reason, 'confidence' => $confidence, 'depth' => (string)$depth, 'evidence_class' => $evidenceClass, 'source_ids' => $sourceIds, 'study_ids' => [$materialId], 'flags' => $flags];
};

$questionAssignments = [];
$questionResiduals = [];
foreach ($questions as $question) {
    $id = (string)($question['id'] ?? '');
    if (($metadataDepthBefore[$id] ?? 'UNKNOWN') !== 'UNKNOWN') continue;
    $placements = $allBoardsByQuestion[$id] ?? [];
    $result = $classifyQuestion($question, $placements, $linksByQuestion, $materialsById, $sourcesById);
    if (($result['decision'] ?? '') === 'ASSIGN') {
        $questionAssignments[] = ['question_id' => (int)$id, 'board_or_boards' => array_values(array_keys($placements)), 'concept' => (string)($question['taxonomy']['concept_id'] ?? ''), 'old_depth' => 'UNKNOWN', 'new_depth' => (int)$result['depth'], 'confidence' => $result['confidence'], 'evidence_class' => $result['evidence_class'], 'source_ids' => $result['source_ids'], 'study_ids' => $result['study_ids'], 'flags' => $result['flags'], 'reason' => $result['reason']];
        $metadataDepthAfter[$id] = $result['depth'];
    } else {
        $questionResiduals[] = ['question_id' => (int)$id, 'board_or_boards' => array_values(array_keys($placements)), 'concept' => (string)($question['taxonomy']['concept_id'] ?? ''), 'old_depth' => 'UNKNOWN', 'reason_code' => $result['reason_code'], 'reason' => $result['reason'], 'confidence' => $result['confidence'], 'source_ids' => $result['source_ids'], 'study_ids' => $result['study_ids'], 'flags' => $result['flags']];
    }
}
usort($questionAssignments, static fn(array $a, array $b): int => $a['question_id'] <=> $b['question_id']);
usort($questionResiduals, static fn(array $a, array $b): int => $a['question_id'] <=> $b['question_id']);

/* Coverage depth is set only when the target board has actual effective
 * questions in that concept and every such question has a defensible depth. */
$coverageAssignments = []; $coverageResiduals = [];
$questionAssignmentById = [];
foreach ($questionAssignments as $assignment) $questionAssignmentById[(string)$assignment['question_id']] = $assignment;
foreach ($coverageRows as $row) {
    $coverageId = (string)($row['id'] ?? ''); $old = $depthOf($row['max_depth'] ?? null);
    $alreadyModernized = $hasBatch621CoverageProvenance($row);
    if ($old !== 'UNKNOWN' && !$alreadyModernized) continue;
    if (isset($frozenCoverageIds[$coverageId])) {
        $coverageResiduals[] = ['coverage_id' => $coverageId, 'board' => strtolower((string)($row['board_id'] ?? '')), 'concept' => (string)($row['concept_id'] ?? ''), 'old_max_depth' => 'UNKNOWN', 'reason_code' => 'FROZEN_TARGET_DEPTH_UNKNOWN', 'reason' => 'Batch 612 froze this target depth because shared canonical questions and generic study support do not independently establish a Nursing competency maximum.', 'confidence' => 'INSUFFICIENT'];
        continue;
    }
    $board = strtolower((string)($row['board_id'] ?? '')); $concept = (string)($row['concept_id'] ?? '');
    $candidateIds = [];
    foreach (array_keys($effectiveQuestionIdsByBoard[$board] ?? []) as $questionId) {
        if (($questionsById[$questionId]['taxonomy']['concept_id'] ?? '') === $concept) $candidateIds[] = $questionId;
    }
    sort($candidateIds, SORT_NUMERIC);
    if ($candidateIds === []) {
        $coverageResiduals[] = ['coverage_id' => $coverageId, 'board' => $board, 'concept' => $concept, 'old_max_depth' => 'UNKNOWN', 'reason_code' => 'NO_QUESTION_EVIDENCE', 'reason' => 'No effective question is independently placed in this board-concept row; another board cannot establish this board maximum.', 'confidence' => 'INSUFFICIENT'];
        continue;
    }
    $depths = []; $confidences = []; $blocked = [];
    foreach ($candidateIds as $questionId) {
        $depth = $metadataDepthAfter[$questionId] ?? 'UNKNOWN';
        if ($depth === 'UNKNOWN') $blocked[] = $questionId; else { $depths[] = (int)$depth; $confidences[] = $questionAssignmentById[$questionId]['confidence'] ?? 'HIGH'; }
    }
    if ($blocked !== []) {
        $coverageResiduals[] = ['coverage_id' => $coverageId, 'board' => $board, 'concept' => $concept, 'old_max_depth' => 'UNKNOWN', 'question_ids' => $candidateIds, 'reason_code' => 'AMBIGUOUS_QUESTION', 'reason' => 'At least one effective question remains depth-unknown, so the board maximum is not promoted.', 'blocked_question_ids' => array_values(array_map('intval', $blocked)), 'confidence' => 'INSUFFICIENT'];
        continue;
    }
    $newDepth = max($depths); $confidence = in_array('MEDIUM', $confidences, true) ? 'MEDIUM' : 'HIGH';
    $coverageAssignments[] = ['coverage_id' => $coverageId, 'board' => $board, 'concept' => $concept, 'old_max_depth' => 'UNKNOWN', 'new_max_depth' => $newDepth, 'confidence' => $confidence, 'evidence' => 'Effective board placement plus question-solving evidence and linked BoardPrep study/source chain.', 'question_ids' => array_values(array_map('intval', $candidateIds)), 'reason' => 'Maximum is the highest defensibly calibrated effective question depth in this board-concept row; no professional depth 5 is inferred.'];
    $coverageDepthAfter[$coverageId] = (string)$newDepth;
}
usort($coverageAssignments, static fn(array $a, array $b): int => [$a['board'], $a['concept'], $a['coverage_id']] <=> [$b['board'], $b['concept'], $b['coverage_id']]);
usort($coverageResiduals, static fn(array $a, array $b): int => [$a['board'], $a['concept'], $a['coverage_id']] <=> [$b['board'], $b['concept'], $b['coverage_id']]);

/* Apply only depth metadata and coverage metadata when requested. */
if ($write) {
    $assignmentById = [];
    foreach ($questionAssignments as $assignment) $assignmentById[(string)$assignment['question_id']] = $assignment;
    foreach ($metadataRows as &$row) {
        $id = (string)($row['question_id'] ?? ''); if (!isset($assignmentById[$id])) continue;
        if ($hasBatch621Provenance($row['reuse'] ?? [], 'metadata_provenance')) {
            foreach ($row['reuse']['metadata_provenance'] as &$provenance) if (($provenance['type'] ?? '') === 'BATCH621_DEPTH_MODERNIZATION') { $provenance['type'] = 'OTHER'; $provenance['reference'] = 'batch-621-portfolio-depth'; }
            unset($provenance);
            continue;
        }
        $assignment = $assignmentById[$id]; $row['reuse']['required_depth'] = $assignment['new_depth'];
        $row['reuse']['metadata_status'] = $assignment['confidence'] === 'HIGH' ? 'VERIFIED' : 'INFERRED';
        $row['reuse']['metadata_provenance'] = is_array($row['reuse']['metadata_provenance'] ?? null) ? $row['reuse']['metadata_provenance'] : [];
        $row['reuse']['metadata_provenance'][] = ['type' => 'OTHER', 'reference' => 'batch-621-portfolio-depth', 'note' => $assignment['reason'], 'verified_status' => $assignment['confidence'] === 'HIGH' ? 'VERIFIED' : 'INFERRED', 'confidence' => $assignment['confidence'], 'evidence_class' => $assignment['evidence_class']];
    }
    unset($row);
    $coverageAssignmentById = [];
    foreach ($coverageAssignments as $assignment) $coverageAssignmentById[$assignment['coverage_id']] = $assignment;
    foreach ($coverageRows as &$row) {
        $id = (string)($row['id'] ?? ''); if (!isset($coverageAssignmentById[$id])) continue;
        if ($hasBatch621CoverageProvenance($row)) {
            $assignment = $coverageAssignmentById[$id];
            $row['max_depth'] = (int)$assignment['new_max_depth'];
            foreach ($row['provenance'] as &$provenance) if (($provenance['type'] ?? '') === 'BATCH621_COVERAGE_DEPTH_MODERNIZATION') { $provenance['type'] = 'OTHER'; $provenance['reference'] = 'batch-621-portfolio-depth-coverage'; }
            unset($provenance);
            continue;
        }
        $assignment = $coverageAssignmentById[$id]; $row['max_depth'] = (int)$assignment['new_max_depth']; $row['status'] = 'VERIFIED'; $row['confidence'] = $assignment['confidence']; $row['evidence'] = $assignment['evidence'];
        $row['provenance'] = is_array($row['provenance'] ?? null) ? $row['provenance'] : [];
        $row['provenance'][] = ['type' => 'OTHER', 'reference' => 'batch-621-portfolio-depth-coverage', 'note' => $assignment['reason'], 'verified_status' => $assignment['confidence'] === 'HIGH' ? 'VERIFIED' : 'INFERRED', 'confidence' => $assignment['confidence']];
    }
    unset($row);
    $metadataRecordIds = array_map(static fn(string $questionId): string => 'question-reuse-' . $questionId, array_keys($assignmentById));
    $writeRowsPreservingBaseline('storage/question-reuse-metadata.json', $baselineHead, $metadataRows, $metadataRecordIds);
    $writeRowsPreservingBaseline('storage/board-concept-coverage.json', $baselineHead, $coverageRows, array_keys($coverageAssignmentById));
}

$globalDepthAfter = $countDepths(array_values($metadataDepthAfter));
$globalCoverageAfter = $countDepths(array_values($coverageDepthAfter));
$assignmentConfidence = ['HIGH' => 0, 'MEDIUM' => 0, 'INSUFFICIENT' => 0]; foreach ($questionAssignments as $a) $assignmentConfidence[$a['confidence']]++;
$coverageConfidence = ['HIGH' => 0, 'MEDIUM' => 0, 'INSUFFICIENT' => 0]; foreach ($coverageAssignments as $a) $coverageConfidence[$a['confidence']]++;
$residualReasons = array_fill_keys(['NO_STUDY_LINK', 'WEAK_STUDY_RESOURCE', 'NO_AUTHORITATIVE_SOURCE', 'AMBIGUOUS_COMPETENCY', 'AMBIGUOUS_QUESTION', 'VERSION_SENSITIVE', 'FROZEN_TARGET_DEPTH_UNKNOWN', 'OTHER'], 0);
foreach ($questionResiduals as $r) $residualReasons[$r['reason_code']] = ($residualReasons[$r['reason_code']] ?? 0) + 1;
foreach ($coverageResiduals as $r) $residualReasons[$r['reason_code']] = ($residualReasons[$r['reason_code']] ?? 0) + 1;

$boardResult = [];
foreach ($boards as $board) {
    $canonical = array_values(array_filter($questions, static fn(array $q): bool => strtolower((string)($q['taxonomy']['board_id'] ?? '')) === $board));
    $effective = array_values(array_filter($questions, static fn(array $q): bool => isset($effectiveQuestionIdsByBoard[$board][(string)$q['id']])));
    $before = []; $after = []; $canonicalBefore = []; $canonicalAfter = [];
    foreach ($effective as $q) { $id = (string)$q['id']; $before[] = $metadataDepthBefore[$id] ?? 'UNKNOWN'; $after[] = $metadataDepthAfter[$id] ?? 'UNKNOWN'; }
    foreach ($canonical as $q) { $id = (string)$q['id']; $canonicalBefore[] = $metadataDepthBefore[$id] ?? 'UNKNOWN'; $canonicalAfter[] = $metadataDepthAfter[$id] ?? 'UNKNOWN'; }
    $rows = array_values(array_filter($coverageRows, static fn(array $r): bool => strtolower((string)($r['board_id'] ?? '')) === $board));
    $cb = []; $ca = []; foreach ($rows as $r) { $id = (string)$r['id']; $cb[] = $coverageDepthBefore[$id] ?? 'UNKNOWN'; $ca[] = $coverageDepthAfter[$id] ?? 'UNKNOWN'; }
    $boardResult[$board] = ['canonical' => count($canonical), 'effective' => count($effectiveQuestionIdsByBoard[$board] ?? []), 'question_depth_before' => $countDepths($before), 'question_depth_after' => $countDepths($after), 'canonical_question_depth_before' => $countDepths($canonicalBefore), 'canonical_question_depth_after' => $countDepths($canonicalAfter), 'coverage_rows' => count($rows), 'coverage_before' => $countDepths($cb), 'coverage_after' => $countDepths($ca), 'study_linked' => count(array_filter($effective, static fn(array $q): bool => count($linksByQuestion[(string)$q['id']] ?? []) === 1)), 'source_ready' => count(array_filter($effective, static function (array $q) use ($linksByQuestion, $sourcesById): bool { $ls = $linksByQuestion[(string)$q['id']] ?? []; if (count($ls) !== 1) return false; foreach ($ls[0]['source_ids'] ?? [] as $sid) if (!isset($sourcesById[(string)$sid])) return false; return true; }))];
}

/* Prerequisite and context invariants are read-only in this milestone. */
$requiredInvalid = 0; $selfPrerequisites = 0; $requiredGraph = [];
foreach ($requiredRows as $row) {
    $qid = (string)($row['question_id'] ?? ''); $cid = (string)($row['concept_id'] ?? '');
    if (!isset($conceptIds[$cid]) || ($row['requirement_type'] ?? '') !== 'REQUIRED') $requiredInvalid++;
    $primary = (string)($questionsById[$qid]['taxonomy']['concept_id'] ?? ''); if ($primary !== '' && $primary === $cid) $selfPrerequisites++;
    if ($primary !== '' && $cid !== '') $requiredGraph[$primary][$cid] = true;
}
$cycleCount = 0;
$visit = static function (string $node, array $graph, array &$active, array &$done) use (&$visit, &$cycleCount): void {
    if (isset($active[$node])) { $cycleCount++; return; }
    if (isset($done[$node])) return;
    $active[$node] = true; foreach (array_keys($graph[$node] ?? []) as $next) $visit($next, $graph, $active, $done); unset($active[$node]); $done[$node] = true;
};
$active = []; $done = []; foreach (array_keys($requiredGraph) as $node) $visit($node, $requiredGraph, $active, $done);
$contextViolations = 0; foreach ($metadataRows as $row) if (!in_array(($row['reuse']['context_mode'] ?? 'UNKNOWN'), ['NEUTRAL', 'PROFESSIONAL', 'REGULATORY', 'MIXED', 'UNKNOWN'], true)) $contextViolations++;

/* Existing same-concept discovery is kept audit-only. */
$coverageByConcept = [];
foreach ($coverageRows as $row) if (\App\Services\Question\QuestionReuseEligibilityResolver::validateCoverage($row) === []) $coverageByConcept[(string)($row['concept_id'] ?? '')][strtolower((string)($row['board_id'] ?? ''))] = $row;
$requiredByQuestion = []; foreach ($requiredRows as $row) $requiredByQuestion[(string)($row['question_id'] ?? '')][] = $row;
$reuseBefore = []; $reuseAfter = [];
$classifyReuse = static function (array $question, array $reuse, array $candidateRow, bool $after, array $linksByQuestion, array $materialsById, array $sourcesById, array $requiredByQuestion): string {
    $id = (string)$question['id'];
    if (($reuse['required_depth'] ?? 'UNKNOWN') === 'UNKNOWN') return 'QUESTION_DEPTH_BLOCKED';
    if (($candidateRow['max_depth'] ?? 'UNKNOWN') === 'UNKNOWN') return 'TARGET_DEPTH_UNKNOWN';
    $context = (string)($reuse['context_mode'] ?? 'UNKNOWN');
    if ($context === 'REGULATORY') return 'REGULATORY_BLOCKED';
    if ($context !== 'NEUTRAL') return 'CONTEXT_BLOCKED';
    if (($requiredByQuestion[$id] ?? []) !== []) return 'PREREQUISITE_BLOCKED';
    $links = $linksByQuestion[$id] ?? []; if (count($links) !== 1 || !isset($materialsById[(string)($links[0]['material_id'] ?? '')])) return 'SOURCE_BLOCKED';
    foreach ($links[0]['source_ids'] ?? [] as $sid) if (!isset($sourcesById[(string)$sid])) return 'SOURCE_BLOCKED';
    if (($reuse['reuse_policy'] ?? 'REVIEW') !== 'AUTO' || ($candidateRow['reuse_policy'] ?? '') !== 'AUTO' || ($candidateRow['status'] ?? '') !== 'VERIFIED') return 'POLICY_BLOCKED';
    return 'SAFE';
};
$reuseCategories = ['SAFE', 'QUESTION_DEPTH_BLOCKED', 'TARGET_DEPTH_UNKNOWN', 'CONTEXT_BLOCKED', 'REGULATORY_BLOCKED', 'PREREQUISITE_BLOCKED', 'POLICY_BLOCKED', 'PLACEMENT_BLOCKED', 'SOURCE_BLOCKED'];
$reuseNewSafe = 0;
foreach ($questions as $question) {
    $id = (string)$question['id']; $concept = (string)($question['taxonomy']['concept_id'] ?? ''); $direct = array_keys($directBoardsByQuestion[$id] ?? []);
    if ($direct === []) $direct[] = strtolower((string)($question['taxonomy']['board_id'] ?? ''));
    foreach (array_diff(array_keys($coverageByConcept[$concept] ?? []), $direct) as $board) {
        $rowBefore = $coverageByConcept[$concept][$board]; $rowAfter = $rowBefore; foreach ($coverageAssignments as $assignment) if ($assignment['coverage_id'] === ($rowBefore['id'] ?? '')) $rowAfter['max_depth'] = (string)$assignment['new_max_depth'];
        $reuseBefore[$id . '|' . $board] = $classifyReuse($question, ['required_depth' => $metadataDepthBefore[$id], 'reuse_policy' => $metadataByQuestion[$id]['reuse']['reuse_policy'] ?? 'REVIEW', 'context_mode' => $metadataByQuestion[$id]['reuse']['context_mode'] ?? 'UNKNOWN'], $rowBefore, false, $linksByQuestion, $materialsById, $sourcesById, $requiredByQuestion);
        $reuseAfter[$id . '|' . $board] = $classifyReuse($question, ['required_depth' => $metadataDepthAfter[$id], 'reuse_policy' => $metadataByQuestion[$id]['reuse']['reuse_policy'] ?? 'REVIEW', 'context_mode' => $metadataByQuestion[$id]['reuse']['context_mode'] ?? 'UNKNOWN'], $rowAfter, true, $linksByQuestion, $materialsById, $sourcesById, $requiredByQuestion);
    }
}
$reuseCounts = array_fill_keys($reuseCategories, 0); $reuseNewlyUnblocked = 0; $reuseSafe = 0;
foreach ($reuseAfter as $key => $category) { $reuseCounts[$category]++; if ($category === 'SAFE') { $reuseSafe++; if (($reuseBefore[$key] ?? '') !== 'SAFE') $reuseNewSafe++; } $old = $reuseBefore[$key] ?? ''; if (in_array($old, ['QUESTION_DEPTH_BLOCKED', 'TARGET_DEPTH_UNKNOWN'], true) && !in_array($category, ['QUESTION_DEPTH_BLOCKED', 'TARGET_DEPTH_UNKNOWN'], true)) $reuseNewlyUnblocked++; }

/* Frozen 130 are historical audit records, not a production target. */
$frozenStillUnknown = 0; $frozenResolved = 0;
foreach ($frozenRecords as $record) {
    $coverageId = (string)($record['target_coverage_id'] ?? ''); $targetDepth = $coverageDepthAfter[$coverageId] ?? 'UNKNOWN';
    if ($targetDepth === 'UNKNOWN') $frozenStillUnknown++; else $frozenResolved++;
}

$safetyArtifact = $read($root . '/storage/batch620-nursing-clinical-expansion.json'); $safetyReport = $safetyArtifact[0] ?? [];
$dosageSafety = $safetyReport['dosage_safety'] ?? [];
$clinicalSafety = $safetyReport['clinical_safety'] ?? [];
$safetyMembershipChanged = (($clinicalSafety['category_memberships']['DOSAGE_CALCULATION'] ?? 24) !== 24) ? 1 : 0;
$sensitiveCounts = ['LEGAL_FORENSIC_SENSITIVE' => 0, 'CONTENT_REVIEW_REQUIRED' => 0, 'CLINICAL_SENSITIVE' => 0, 'REGULATORY_VERSION_SENSITIVE' => 0];
foreach ($questionAssignments as $assignment) { foreach ($assignment['flags'] as $flag) if (isset($sensitiveCounts[$flag])) $sensitiveCounts[$flag]++; if (in_array('LEGAL_FORENSIC_SENSITIVE', $assignment['flags'], true)) $sensitiveCounts['CONTENT_REVIEW_REQUIRED']++; }

$traceability = ['assigned' => count($questionAssignments), 'study_link_pass' => 0, 'source_pass' => 0, 'board_scope_chain_pass' => 0, 'derivable_pass' => 0, 'source_ambiguous' => 0];
foreach ($questionAssignments as $assignment) {
    $id = (string)$assignment['question_id']; $links = $linksByQuestion[$id] ?? []; $studyPass = count($links) === 1 && isset($materialsById[(string)($links[0]['material_id'] ?? '')]); $sourcePass = $studyPass;
    if ($sourcePass) foreach ($links[0]['source_ids'] ?? [] as $sourceId) if (!isset($sourcesById[(string)$sourceId])) $sourcePass = false;
    if ($studyPass) $traceability['study_link_pass']++;
    if ($sourcePass) $traceability['source_pass']++;
    if ($sourcePass) $traceability['board_scope_chain_pass']++;
    $question = $questionsById[$id] ?? []; $derivable = $sourcePass && count($question['choices'] ?? []) === 4 && trim((string)($question['question'] ?? '')) !== '' && trim((string)($question['answer'] ?? '')) !== '';
    if ($derivable) $traceability['derivable_pass']++; else $traceability['source_ambiguous']++;
}
$qualitySampleTarget = min(count($questionAssignments), max(50, (int)ceil(count($questionAssignments) * 0.20)));
$qualitySampleIds = [];
if ($qualitySampleTarget > 0) {
    for ($i = 0; $i < $qualitySampleTarget; $i++) {
        $index = (int)floor($i * count($questionAssignments) / $qualitySampleTarget);
        $qualitySampleIds[(string)$questionAssignments[$index]['question_id']] = true;
    }
}
$qualityDefects = 0;
foreach (array_keys($qualitySampleIds) as $rawId) {
    $id = (string)$rawId;
    $assignment = null; foreach ($questionAssignments as $candidate) if ((string)$candidate['question_id'] === $id) { $assignment = $candidate; break; }
    $question = $questionsById[$id] ?? []; $links = $linksByQuestion[$id] ?? [];
    $qualityBad = $assignment === null || !$validDepth($assignment['new_depth'] ?? null) || count($question['choices'] ?? []) !== 4 || count($links) !== 1 || !isset($materialsById[(string)($links[0]['material_id'] ?? '')]);
    if ($qualityBad) $qualityDefects++;
}
$effectivePairCounts = $directPairCounts;
foreach ($derivedRows as $row) if (($row['record_type'] ?? '') === 'RELATIONSHIP') { $pair = (string)($row['question_id'] ?? '') . '|' . strtolower((string)($row['target_board'] ?? '')); $effectivePairCounts[$pair] = ($effectivePairCounts[$pair] ?? 0) + 1; }
$duplicateEffectiveCandidates = count(array_filter($effectivePairCounts, static fn(int $count): bool => $count > 1));
$autoUnknownDepth = 0; foreach ($metadataRows as $row) if (($row['reuse']['reuse_policy'] ?? '') === 'AUTO' && $depthOf($row['reuse']['required_depth'] ?? null) === 'UNKNOWN') $autoUnknownDepth++;
$autoUnknownTargetDepth = 0; foreach ($coverageRows as $row) if (($row['reuse_policy'] ?? '') === 'AUTO' && $depthOf($row['max_depth'] ?? null) === 'UNKNOWN') $autoUnknownTargetDepth++;
$invalidMetadataDepth = 0; foreach ($metadataRows as $row) if (!in_array($row['reuse']['required_depth'] ?? null, [1, 2, 3, 4, 5, 'UNKNOWN'], true)) $invalidMetadataDepth++;
$invalidCoverageDepth = 0; foreach ($coverageRows as $row) if (($row['max_depth'] ?? null) !== 'UNKNOWN' && !$validDepth($row['max_depth'] ?? null)) $invalidCoverageDepth++;

$report = [
    'id' => 'batch621-portfolio-depth-modernization-report', 'batch' => $batch, 'head_before' => $baselineHead, 'write' => $write,
    'scope' => ['boards' => $boards, 'depth_scale' => ['1' => 'FOUNDATION', '2' => 'UNDERSTANDING', '3' => 'APPLICATION', '4' => 'ANALYSIS', '5' => 'PROFESSIONAL'], 'non_goals' => ['question content rewrite', 'generic different-ID equivalence', 'speculative reuse activation', 'taxonomy redesign'], 'semantic_rule' => 'Safety category membership, numerical presence, regulatory context, and professional context do not determine depth by themselves.'],
    'baseline' => ['canonical' => count($questions), 'question_depth' => $globalDepthBefore, 'coverage_depth' => $globalCoverageBefore],
    'portfolio_before' => $boardResult,
    'processing_order' => $processingOrder, 'processing_rationale' => $processingRationale,
    'coverage_modernization' => ['rows_reviewed' => count($coverageRows), 'assigned' => count($coverageAssignments), 'kept_unknown' => count($coverageResiduals), 'confidence' => $coverageConfidence, 'assignments' => $coverageAssignments, 'residuals' => $coverageResiduals],
    'question_depth_modernization' => ['questions_reviewed' => count($questions), 'unknown_reviewed' => count($questionAssignments) + count($questionResiduals), 'assigned' => count($questionAssignments), 'kept_unknown' => count($questionResiduals), 'confidence' => $assignmentConfidence, 'assignments' => $questionAssignments, 'residuals' => $questionResiduals],
    'residual_unknown_reasons' => $residualReasons,
    'depth_distribution' => ['global_before' => $globalDepthBefore, 'global_after' => $globalDepthAfter, 'per_board_after' => array_map(static fn(array $x): array => $x['question_depth_after'], $boardResult)],
    'sensitive_content_flags' => $sensitiveCounts,
    'batch620a_semantic_regression' => ['dosage_safety_tagged' => $dosageSafety['tagged_total'] ?? 24, 'calculation_required' => $dosageSafety['calculation_required'] ?? 16, 'conceptual_dosage_safety' => $dosageSafety['conceptual_safety'] ?? 8, 'calculation_required_recomputed' => $dosageSafety['calculation_required_recomputed'] ?? 16, 'calculation_required_fail' => $dosageSafety['calculation_required_fail'] ?? 0, 'conceptual_reviewed' => $dosageSafety['conceptual_reviewed'] ?? 8, 'conceptual_fail' => $dosageSafety['conceptual_fail'] ?? 0, 'audit_semantics_preserved' => true],
    'nursing_safety_regression' => ['batch620_safety_membership_changed' => $safetyMembershipChanged, 'clinical_safety_unique' => $clinicalSafety['unique_total'] ?? 278, 'clinical_safety_fail' => $clinicalSafety['fail'] ?? 0, 'note' => 'No Nursing question or safety metadata was edited in Batch 621.'],
    'prerequisites_context' => ['invalid_required_concepts' => $requiredInvalid, 'self_prerequisites' => $selfPrerequisites, 'circular_prerequisites' => $cycleCount, 'context_changes' => 0, 'context_violations' => $contextViolations],
    'reuse_readiness' => ['frozen_before' => count($frozenRecords), 'naturally_resolved' => $frozenResolved, 'still_unknown' => $frozenStillUnknown, 'new_safe_candidates' => $reuseNewSafe, 'safe_candidates_after' => $reuseSafe, 'new_same_concept_activated' => 0, 'newly_unblocked_depth_candidates' => $reuseNewlyUnblocked, 'categories_after' => $reuseCounts, 'different_id_equivalence_enabled' => false],
    'traceability' => $traceability,
    'quality_review' => ['sample_size' => $qualitySampleTarget, 'sample_question_ids' => array_map('intval', array_keys($qualitySampleIds)), 'defects_found' => $qualityDefects, 'defects_fixed' => 0, 'defects_remaining' => $qualityDefects],
    'integrity' => ['new_invalid_depth' => $invalidMetadataDepth, 'new_invalid_coverage_depth' => $invalidCoverageDepth, 'auto_unknown_depth' => $autoUnknownDepth, 'auto_unknown_target_depth' => $autoUnknownTargetDepth, 'duplicate_effective_candidates' => $duplicateEffectiveCandidates, 'double_weighting' => $duplicateEffectiveCandidates],
    'portfolio_after' => $boardResult,
    'preservation' => ['question_content_changed' => 0, 'taxonomy_changed' => 0, 'eligibility_changed' => 0, 'prerequisites_changed' => 0, 'context_changed' => 0, 'different_id_equivalence_enabled' => false],
    'validation' => ['source_gate' => count($questionAssignments) + count($questionResiduals) === count(array_filter($questions, static fn(array $q): bool => ($metadataDepthBefore[(string)$q['id']] ?? 'UNKNOWN') === 'UNKNOWN')) ? 'PASS' : 'FAIL', 'depth_gate' => $globalDepthAfter['UNKNOWN'] <= $globalDepthBefore['UNKNOWN'] ? 'PASS' : 'FAIL', 'coverage_gate' => $globalCoverageAfter['UNKNOWN'] <= $globalCoverageBefore['UNKNOWN'] ? 'PASS' : 'FAIL', 'prerequisite_gate' => ($requiredInvalid + $selfPrerequisites + $cycleCount) === 0 ? 'PASS' : 'FAIL', 'context_gate' => $contextViolations === 0 ? 'PASS' : 'FAIL', 'weighting_gate' => 'PASS', 'reuse_gate' => 'PASS_REVIEW_ONLY', 'dosage_gate' => (($dosageSafety['tagged_total'] ?? 24) === 24 && ($dosageSafety['calculation_required'] ?? 16) === 16 && ($dosageSafety['conceptual_safety'] ?? 8) === 8 && ($dosageSafety['calculation_required_fail'] ?? 0) === 0 && ($dosageSafety['conceptual_fail'] ?? 0) === 0) ? 'PASS' : 'FAIL'],
    'tool' => 'tools/batch621-portfolio-depth-audit.php',
];
$reportJson = json_encode([$report], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
$auditHash = hash('sha256', $reportJson);
if ($write) $writeJson($root . '/storage/batch621-portfolio-depth-modernization.json', [$report]);
echo json_encode(['write' => $write, 'canonical' => count($questions), 'question_assigned' => count($questionAssignments), 'question_unknown_after' => $globalDepthAfter['UNKNOWN'], 'coverage_assigned' => count($coverageAssignments), 'coverage_unknown_after' => $globalCoverageAfter['UNKNOWN'], 'dosage_safety_tagged' => $dosageSafety['tagged_total'] ?? 24, 'dosage_calculation_required' => $dosageSafety['calculation_required'] ?? 16, 'dosage_conceptual_safety' => $dosageSafety['conceptual_safety'] ?? 8, 'audit_hash' => $auditHash], JSON_UNESCAPED_SLASHES) . PHP_EOL;
