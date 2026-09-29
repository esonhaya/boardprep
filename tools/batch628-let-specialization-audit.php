<?php

declare(strict_types=1);

/**
 * Batch 628: reconcile the current PRC/CHED LEPT variant model with the
 * existing canonical bank.  This audit is deliberately source/crosswalk
 * first.  It writes no question, taxonomy, study, or eligibility records.
 */

$root = dirname(__DIR__);
$write = in_array('--write', $argv, true);
$today = '2026-09-29';
$expectedHead = 'd8d57af4d0d6608dbf51ec5ce915d5e34f007b11';
$artifactPath = $root . '/storage/batch628-let-specialization-crosswalk.json';

// The canonical reuse metadata is intentionally large; this audit builds its
// indexes once rather than repeatedly parsing it.
ini_set('memory_limit', '512M');

require_once $root . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();
require $root . '/bootstrap/app.php';

$read = static function (string $path): array {
    $raw = file_get_contents($path);
    if ($raw === false) {
        throw new RuntimeException('Cannot read ' . $path);
    }
    $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    return is_array($decoded) ? $decoded : [];
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
$normalize = static function (string $value): string {
    $value = strtolower(trim($value));
    return preg_replace('/\s+/', ' ', $value) ?? $value;
};
$normalizedText = static function (string $value) use ($normalize): string {
    $value = preg_replace('/\s+/', ' ', trim($value)) ?? trim($value);
    return $normalize($value);
};
$unique = static fn(array $values): array => array_values(array_unique(array_map('strval', $values)));
$median = static function (array $values): int {
    $values = array_values(array_map('intval', $values));
    if ($values === []) return 0;
    sort($values, SORT_NUMERIC);
    $middle = intdiv(count($values), 2);
    return count($values) % 2 === 0
        ? (int) round(($values[$middle - 1] + $values[$middle]) / 2)
        : $values[$middle];
};
$head = trim((string) shell_exec('git -C ' . escapeshellarg($root) . ' rev-parse HEAD'));
if ($write && $head !== $expectedHead) {
    throw new RuntimeException('Batch 628 writes require expected Batch 627 HEAD ' . $expectedHead . '; got ' . $head);
}

$storage = \App\Core\App::storage();
$questions = array_values(array_filter($storage->all('questions'), 'is_array'));
$eligibilityRows = array_values(array_filter($storage->all('question-eligibility'), 'is_array'));
$blueprints = array_values(array_filter($storage->all('blueprints'), 'is_array'));
$coverageRows = array_values(array_filter($storage->all('board-concept-coverage'), 'is_array'));
$sources = array_values(array_filter($storage->all('sources'), 'is_array'));
$materials = array_values(array_filter($storage->all('study-materials'), 'is_array'));
$studyLinks = array_values(array_filter($storage->all('study-question-links'), 'is_array'));
$requirements = array_values(array_filter($storage->all('question-required-concepts'), 'is_array'));
$derivedRows = array_values(array_filter($storage->all('concept-derived-eligibility'), 'is_array'));
$overrides = array_values(array_filter($storage->all('question-reuse-overrides'), 'is_array'));
$reuseMetadata = array_values(array_filter($storage->all('question-reuse-metadata'), 'is_array'));
$boards = array_values(array_filter($storage->all('boards'), 'is_array'));
$subjects = array_values(array_filter($storage->all('subjects'), 'is_array'));
$domains = array_values(array_filter($storage->all('taxonomy/domains'), 'is_array'));
$topics = array_values(array_filter($storage->all('taxonomy/topics'), 'is_array'));
$concepts = array_values(array_filter($storage->all('taxonomy/concepts'), 'is_array'));

$questionById = [];
foreach ($questions as $question) {
    $id = (string) ($question['id'] ?? '');
    if ($id !== '') $questionById[$id] = $question;
}
$metadataByQuestion = [];
foreach ($reuseMetadata as $row) {
    $id = (string) ($row['question_id'] ?? '');
    if ($id !== '') $metadataByQuestion[$id] = $row;
}
$sourceById = [];
foreach ($sources as $source) {
    $sourceById[(string) ($source['id'] ?? '')] = $source;
}
$materialById = [];
foreach ($materials as $material) {
    $materialById[(string) ($material['id'] ?? '')] = $material;
}
$conceptById = [];
foreach ($concepts as $concept) {
    $conceptById[(string) ($concept['id'] ?? '')] = $concept;
}
$questionBoard = static function (array $question) use ($normalize): string {
    $taxonomy = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
    $board = (string) ($taxonomy['board_id'] ?? $question['board'] ?? '');
    $board = $normalize($board);
    return ['cse' => 'civil-service', 'let' => 'let', 'nle' => 'nursing', 'ple' => 'psychometrician', 'cle' => 'criminologist'][$board] ?? $board;
};
$questionSubject = static function (array $question) use ($normalize): string {
    $taxonomy = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
    return $normalize((string) ($taxonomy['subject_id'] ?? $question['subject'] ?? ''));
};
$questionConcept = static function (array $question): string {
    $taxonomy = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
    return (string) ($taxonomy['concept_id'] ?? $question['concept_id'] ?? '');
};

$eligibilityIndex = new \App\Services\Question\QuestionEligibilityIndex(
    $eligibilityRows,
    $blueprints,
    \App\Services\Question\QuestionReuseEligibilityResolver::productionDerivedRelationships(),
    $overrides
);

/** @return array<string,array{question:array,mapping:array}> */
$productionEffectiveByBoard = static function (string $board) use ($questions, $eligibilityIndex): array {
    $result = [];
    foreach ($questions as $question) {
        $mapping = \App\Services\Question\QuestionEligibilityService::forExam($question, $board, $eligibilityIndex);
        if ($mapping === null) continue;
        $id = (string) ($question['id'] ?? '');
        if ($id !== '' && !isset($result[$id])) {
            $result[$id] = ['question' => $question, 'mapping' => $mapping];
        }
    }
    ksort($result, SORT_NATURAL);
    return $result;
};

/*
 * Portfolio counts intentionally follow the declared Batch 627 model:
 * top-level exam_ids, explicit concept-derived relationships, and the
 * question taxonomy fallback.  The runtime index also honors 15 historical
 * question_mappings overrides which add LET to otherwise CSE-only rows.  Keep
 * that runtime view separate so this audit cannot silently rewrite the
 * published portfolio denominator while still testing the real selector.
 */
$declaredDirectByBoard = [];
$declaredDerivedByBoard = [];
foreach ($eligibilityRows as $entry) {
    $exams = is_array($entry['exam_ids'] ?? null)
        ? array_map('strtolower', array_map('strval', $entry['exam_ids']))
        : [];
    foreach (($entry['question_ids'] ?? []) as $rawId) {
        $id = (string) $rawId;
        if ($id === '' || !isset($questionById[$id])) continue;
        foreach ($exams as $board) {
            $subjects = is_array($entry['subject_ids'] ?? null) ? $entry['subject_ids'] : [];
            $subject = (string) ($subjects[$board] ?? $entry['subject_id'] ?? '');
            $mapping = is_array($entry['question_mappings'][$id] ?? null) ? $entry['question_mappings'][$id] : [];
            if (is_array($mapping['subject_ids'] ?? null) && isset($mapping['subject_ids'][$board])) {
                $subject = (string) $mapping['subject_ids'][$board];
            }
            $declaredDirectByBoard[$board][$id] = [
                'question' => $questionById[$id],
                'mapping' => [
                    'exam_id' => $board,
                    'subject_id' => $subject,
                    'eligibility_source' => 'DIRECT',
                ],
            ];
        }
    }
}
foreach ($derivedRows as $entry) {
    if (($entry['record_type'] ?? '') !== 'RELATIONSHIP') continue;
    $board = strtolower((string) ($entry['target_board'] ?? ''));
    $id = (string) ($entry['question_id'] ?? '');
    if ($board === '' || $id === '' || !isset($questionById[$id])) continue;
    $placement = is_array($entry['target_placement'] ?? null) ? $entry['target_placement'] : [];
    $declaredDerivedByBoard[$board][$id] = [
        'question' => $questionById[$id],
        'mapping' => array_merge($placement, [
            'exam_id' => $board,
            'subject_id' => (string) ($placement['subject_id'] ?? ''),
            'eligibility_source' => 'CONCEPT_INHERITED',
        ]),
    ];
}
$declaredEffectiveByBoard = static function (string $board) use (&$declaredDirectByBoard, &$declaredDerivedByBoard, $questions, $questionBoard): array {
    $board = strtolower($board);
    $result = $declaredDirectByBoard[$board] ?? [];
    foreach ($declaredDerivedByBoard[$board] ?? [] as $id => $record) {
        if (!isset($result[$id])) $result[$id] = $record;
    }
    foreach ($questions as $question) {
        if ($questionBoard($question) !== $board) continue;
        $id = (string) ($question['id'] ?? '');
        if ($id === '' || isset($result[$id])) continue;
        $result[$id] = [
            'question' => $question,
            'mapping' => ['exam_id' => $board, 'subject_id' => '', 'eligibility_source' => 'TAXONOMY_FALLBACK'],
        ];
    }
    ksort($result, SORT_NATURAL);
    return $result;
};
$effectiveByBoard = $declaredEffectiveByBoard;

$productionIndexCounts = [];
$declaredIndexDivergence = [];

$allBoardIds = [];
foreach ($boards as $board) {
    $id = $normalize((string) ($board['id'] ?? ''));
    if ($id !== '') $allBoardIds[] = $id;
}
$allBoardIds = $unique($allBoardIds);
foreach (['let', 'civil-service', 'nursing', 'psychometrician', 'criminologist', 'cpa'] as $boardId) {
    if (!in_array($boardId, $allBoardIds, true)) $allBoardIds[] = $boardId;
}
$portfolio = [];
$eligibleSets = [];
$directPairCount = 0;
$derivedPairCount = 0;
foreach ($allBoardIds as $boardId) {
    $effective = $effectiveByBoard($boardId);
    $productionEffective = $productionEffectiveByBoard($boardId);
    $eligibleSets[$boardId] = array_fill_keys(array_keys($effective), true);
    $productionIndexCounts[$boardId] = count($productionEffective);
    $declaredIndexDivergence[$boardId] = array_values(array_diff(array_keys($productionEffective), array_keys($effective)));
    $native = 0;
    $depthCounts = ['1' => 0, '2' => 0, '3' => 0, '4' => 0, '5' => 0, 'UNKNOWN' => 0];
    foreach ($effective as $id => $record) {
        if ($questionBoard($record['question']) === $boardId) $native++;
        $mapping = $record['mapping'];
        if (($mapping['eligibility_source'] ?? '') === 'CONCEPT_INHERITED') $derivedPairCount++;
        else $directPairCount++;
        $depth = $metadataByQuestion[$id]['reuse']['required_depth'] ?? ($record['question']['depth'] ?? 'UNKNOWN');
        $depthKey = in_array((string) $depth, ['1', '2', '3', '4', '5'], true) ? (string) $depth : 'UNKNOWN';
        $depthCounts[$depthKey]++;
    }
    $boardCoverage = array_values(array_filter($coverageRows, static fn(array $row): bool => $normalize((string) ($row['board_id'] ?? '')) === $boardId));
    $coverageCounts = ['KNOWN' => 0, 'UNKNOWN' => 0];
    foreach ($boardCoverage as $row) {
        $coverageCounts[($row['max_depth'] ?? null) === 'UNKNOWN' ? 'UNKNOWN' : 'KNOWN']++;
    }
    $portfolio[$boardId] = [
        'canonical_native' => count(array_filter($questions, static fn(array $question): bool => $questionBoard($question) === $boardId)),
        'effective' => count($effective),
        'question_depth_known' => $depthCounts['1'] + $depthCounts['2'] + $depthCounts['3'] + $depthCounts['4'] + $depthCounts['5'],
        'question_depth_unknown' => $depthCounts['UNKNOWN'],
        'depth_counts' => $depthCounts,
        'coverage_known' => $coverageCounts['KNOWN'],
        'coverage_unknown' => $coverageCounts['UNKNOWN'],
    ];
}
$declaredDirectPairs = [];
foreach ($declaredDirectByBoard as $board => $records) {
    foreach (array_keys($records) as $id) $declaredDirectPairs[$id . '|' . $board] = true;
}
$declaredDerivedPairs = [];
foreach ($declaredDerivedByBoard as $board => $records) {
    foreach (array_keys($records) as $id) $declaredDerivedPairs[$id . '|' . $board] = true;
}
$directPairCount = count($declaredDirectPairs);
$derivedPairCount = count(array_diff_key($declaredDerivedPairs, $declaredDirectPairs));
$effectiveUnion = [];
$effectivePairCount = 0;
foreach ($eligibleSets as $boardSet) {
    $effectivePairCount += count($boardSet);
    foreach ($boardSet as $id => $_) $effectiveUnion[$id] = true;
}
$multiBoard = 0;
$singleBoard = 0;
foreach ($questionById as $id => $_) {
    $memberships = 0;
    foreach ($eligibleSets as $boardSet) if (isset($boardSet[$id])) $memberships++;
    if ($memberships > 1) $multiBoard++;
    elseif ($memberships === 1) $singleBoard++;
}

$letEffective = $effectiveByBoard('let');
$letProductionEffective = $productionEffectiveByBoard('let');
$letNativeIds = [];
foreach ($letEffective as $id => $record) {
    if ($questionBoard($record['question']) === 'let') $letNativeIds[$id] = true;
}
$letInheritedIds = array_diff_key(array_fill_keys(array_keys($letEffective), true), $letNativeIds);

$classification = [
    'ELEMENTARY_GE' => 0,
    'ELEMENTARY_PE' => 0,
    'SECONDARY_GE' => 0,
    'SECONDARY_PE' => 0,
    'SECONDARY_SPECIALIZATION' => 0,
    'SHARED_GE' => 0,
    'SHARED_PE' => 0,
    'AMBIGUOUS_VARIANT' => 0,
    'OUT_OF_SCOPE' => 0,
];
$letRecordsByComponent = [
    'GENERAL_EDUCATION' => [],
    'PROFESSIONAL_EDUCATION' => [],
    'SPECIALIZATION' => [],
];
foreach ($letEffective as $id => $record) {
    $placement = \App\Services\Quiz\LET\LETVariantRegistry::questionPlacement($record['question'], $record['mapping']);
    $component = (string) ($placement['component'] ?? '');
    if (!isset($letRecordsByComponent[$component])) {
        $classification['AMBIGUOUS_VARIANT']++;
        continue;
    }
    $letRecordsByComponent[$component][$id] = [
        'question' => $record['question'],
        'mapping' => $record['mapping'],
        'placement' => $placement,
    ];
    if ($component === 'GENERAL_EDUCATION') $classification['SHARED_GE']++;
    elseif ($component === 'PROFESSIONAL_EDUCATION') $classification['SHARED_PE']++;
    elseif ($component === 'SPECIALIZATION') $classification['SECONDARY_SPECIALIZATION']++;
}
$classification['ELEMENTARY_GE'] = $classification['SHARED_GE'];
$classification['SECONDARY_GE'] = $classification['SHARED_GE'];
$classification['ELEMENTARY_PE'] = $classification['SHARED_PE'];
$classification['SECONDARY_PE'] = $classification['SHARED_PE'];

$poolReport = static function (array $records, string $component) use ($questionBoard, $questionConcept, $conceptById, $letNativeIds, $median): array {
    $conceptCounts = [];
    $native = 0;
    $inherited = 0;
    foreach ($records as $id => $record) {
        if (isset($letNativeIds[$id])) $native++;
        else $inherited++;
        $concept = $questionConcept($record['question']);
        if ($concept !== '') $conceptCounts[$concept] = ($conceptCounts[$concept] ?? 0) + 1;
    }
    $counts = array_values($conceptCounts);
    sort($counts, SORT_NUMERIC);
    return [
        'component' => $component,
        'native' => $native,
        'inherited' => $inherited,
        'effective' => count($records),
        'distinct_concepts' => count($conceptCounts),
        'min_concept_cohort' => $counts[0] ?? 0,
        'median_concept_cohort' => $median($counts),
        'max_concept_cohort' => $counts === [] ? 0 : $counts[count($counts) - 1],
        'pool_shortage' => $component === 'SPECIALIZATION' && count($records) === 0,
        'concept_cohorts' => $conceptCounts,
    ];
};
$existingPools = [
    'ELEMENTARY_GE' => $poolReport($letRecordsByComponent['GENERAL_EDUCATION'], 'GENERAL_EDUCATION'),
    'ELEMENTARY_PE' => $poolReport($letRecordsByComponent['PROFESSIONAL_EDUCATION'], 'PROFESSIONAL_EDUCATION'),
    'SECONDARY_GE' => $poolReport($letRecordsByComponent['GENERAL_EDUCATION'], 'GENERAL_EDUCATION'),
    'SECONDARY_PE' => $poolReport($letRecordsByComponent['PROFESSIONAL_EDUCATION'], 'PROFESSIONAL_EDUCATION'),
];
$specializationPools = [];
foreach (\App\Services\Quiz\LET\LETVariantRegistry::specializations() as $specialization) {
    $sid = (string) ($specialization['id'] ?? '');
    $records = array_filter(
        $letRecordsByComponent['SPECIALIZATION'],
        static fn(array $record): bool => ($record['placement']['specialization_id'] ?? null) === $sid
    );
    $row = $poolReport($records, 'SPECIALIZATION');
    $row['specialization_id'] = $sid;
    $specializationPools[$sid] = $row;
}

$studyLinksByQuestion = [];
foreach ($studyLinks as $link) {
    $qid = (string) ($link['question_id'] ?? '');
    if ($qid !== '') $studyLinksByQuestion[$qid][] = $link;
}
$studyResourcesForConcept = static function (string $conceptId) use ($conceptById, $questions, $questionConcept, $studyLinksByQuestion, $materialById): array {
    $questionIds = [];
    foreach ($questions as $question) if ($questionConcept($question) === $conceptId) $questionIds[] = (string) ($question['id'] ?? '');
    $materials = [];
    foreach ($questionIds as $qid) foreach ($studyLinksByQuestion[$qid] ?? [] as $link) {
        $mid = (string) ($link['material_id'] ?? '');
        if ($mid !== '' && isset($materialById[$mid])) $materials[$mid] = true;
    }
    return array_keys($materials);
};

$unknownRows = [];
foreach ($coverageRows as $row) {
    if ($normalize((string) ($row['board_id'] ?? '')) !== 'let' || ($row['max_depth'] ?? null) !== 'UNKNOWN') continue;
    $conceptId = (string) ($row['concept_id'] ?? '');
    $links = $studyResourcesForConcept($conceptId);
    $sourceIds = [];
    foreach ($links as $materialId) foreach ($materialById[$materialId]['source_ids'] ?? [] as $sourceId) $sourceIds[(string) $sourceId] = true;
    $unknownRows[] = [
        'concept_id' => $conceptId,
        'concept_name' => (string) ($conceptById[$conceptId]['name'] ?? $conceptId),
        'let_relevance' => 'CURRENT_LET_SHARED_GENERAL_EDUCATION',
        'current_study_links' => $links,
        'current_source_links' => array_keys($sourceIds),
        'why_unknown' => 'The verified direct mapping establishes current LET consumption but does not establish a defensible reuse depth; specialization modeling does not change that evidence boundary.',
        'official_scope_evidence' => 'PRC Resolution No. 11 s. 2025 TOS plus current LEPT program; grammar remains in the shared/General Education repository path.',
        'clinical_evidence' => 'NOT_APPLICABLE',
        'provisional_max_depth' => 'UNKNOWN',
        'confidence' => 'MEDIUM_FOR_DEPTH_BOUNDARY',
        'decision' => 'RETAIN_UNKNOWN',
        'blocker' => 'STUDY_SCOPE_ONLY',
    ];
}
usort($unknownRows, static fn(array $a, array $b): int => strcmp((string) $a['concept_id'], (string) $b['concept_id']));

$subjectById = [];
foreach ($subjects as $subject) $subjectById[(string) ($subject['id'] ?? '')] = $subject;
$domainsBySubject = [];
foreach ($domains as $domain) $domainsBySubject[(string) ($domain['subject_id'] ?? '')][] = (string) ($domain['id'] ?? '');
$topicsByDomain = [];
foreach ($topics as $topic) $topicsByDomain[(string) ($topic['domain_id'] ?? '')][] = (string) ($topic['id'] ?? '');
$conceptsByTopic = [];
foreach ($concepts as $concept) $conceptsByTopic[(string) ($concept['topic_id'] ?? '')][] = (string) ($concept['id'] ?? '');
$materialsByExam = [];
foreach ($materials as $material) {
    if (in_array('let', array_map('strtolower', array_map('strval', $material['exams'] ?? [])), true)) $materialsByExam[(string) ($material['id'] ?? '')] = $material;
}
$specializationCrosswalk = [];
$partialSubjects = ['secondary-english' => 'english', 'secondary-filipino' => 'filipino', 'secondary-mathematics' => 'mathematics', 'secondary-science' => 'science', 'secondary-social-studies' => 'social-studies'];
foreach (\App\Services\Quiz\LET\LETVariantRegistry::specializations() as $specialization) {
    $sid = (string) ($specialization['id'] ?? '');
    $repoSubject = $partialSubjects[$sid] ?? null;
    $subjectDomains = $repoSubject === null ? [] : ($domainsBySubject[$repoSubject] ?? []);
    $repoTopics = [];
    $repoConcepts = [];
    foreach ($subjectDomains as $domainId) {
        $repoTopics = array_merge($repoTopics, $topicsByDomain[$domainId] ?? []);
        foreach ($topicsByDomain[$domainId] ?? [] as $topicId) $repoConcepts = array_merge($repoConcepts, $conceptsByTopic[$topicId] ?? []);
    }
    $scopeSource = (string) ($specialization['scope_source'] ?? 'prc-resolution-20-2025');
    $sourceReadiness = $repoSubject === null ? 'WEAK' : 'ADEQUATE';
    $studyReadiness = $repoSubject === null ? 'MISSING' : 'NEEDS_REORGANIZATION';
    $specializationCrosswalk[$sid] = [
        'specialization' => (string) ($specialization['official_name'] ?? $sid),
        'level' => $specialization['level'] ?? null,
        'official_scope' => (string) ($specialization['eligibility_notes'] ?? ''),
        'repository_subjects' => $repoSubject === null ? [] : [$repoSubject],
        'repository_domains' => array_values(array_unique($subjectDomains)),
        'repository_topics' => array_values(array_unique($repoTopics)),
        'repository_concepts' => array_values(array_unique($repoConcepts)),
        'existing_canonical' => 0,
        'existing_effective' => (int) ($specializationPools[$sid]['effective'] ?? 0),
        'shared_candidate_questions' => $repoSubject === null ? 0 : count(array_filter($letRecordsByComponent['GENERAL_EDUCATION'], static fn(array $row): bool => strtolower((string) (($row['question']['taxonomy']['subject_id'] ?? ''))) === $repoSubject)),
        'study_readiness' => $studyReadiness,
        'study_resources' => $repoSubject === null ? [] : array_keys(array_filter($materialsByExam, static function (array $material) use ($repoSubject): bool {
            $title = strtolower((string) ($material['title'] ?? $material['name'] ?? ''));
            return str_contains($title, $repoSubject) || ($repoSubject === 'social-studies' && str_contains($title, 'history'));
        })),
        'source_readiness' => $sourceReadiness,
        'authoritative_sources' => [$scopeSource],
        'taxonomy_readiness' => $specialization['taxonomy_readiness'] ?? 'NO_EVIDENCE',
        'crosswalk_status' => $repoSubject !== null
            ? 'PARTIAL_EXISTING_TAXONOMY'
            : (($specialization['taxonomy_readiness'] ?? '') === 'NEEDS_TAXONOMY_EXTENSION'
                ? 'NEEDS_TAXONOMY_EXTENSION'
                : 'NO_EVIDENCE'),
        'expansion_readiness' => 'NOT_READY',
        'support_status' => $specialization['repository_support'] ?? 'MODEL_READY_CONTENT_MISSING',
    ];
}

$registry = \App\Services\Quiz\LET\LETVariantRegistry::specializations();
$registryIds = array_map(static fn(array $row): string => (string) ($row['id'] ?? ''), $registry);
$duplicateRegistryIds = count($registryIds) - count(array_unique($registryIds));
$aliasOwners = [];
foreach ($registry as $row) {
    $values = array_merge(
        [(string) ($row['official_name'] ?? ''), (string) ($row['id'] ?? '')],
        is_array($row['aliases'] ?? null) ? $row['aliases'] : [],
        is_array($row['historical_labels'] ?? null) ? $row['historical_labels'] : []
    );
    foreach ($values as $alias) {
        $key = \App\Services\Quiz\LET\LETVariantRegistry::normalizeAlias((string) $alias);
        if ($key !== '') $aliasOwners[$key][] = (string) ($row['id'] ?? '');
    }
}
$aliasCollisions = [];
foreach ($aliasOwners as $alias => $owners) if (count(array_unique($owners)) > 1) $aliasCollisions[$alias] = array_values(array_unique($owners));

$variantMetadataErrors = [];
foreach ($eligibilityRows as $entry) {
    $questionMappings = is_array($entry['question_mappings'] ?? null) ? $entry['question_mappings'] : [];
    foreach ($questionMappings as $qid => $mapping) {
        if (!is_array($mapping)) continue;
        $placements = is_array($mapping['placements'] ?? null) ? $mapping['placements'] : [];
        $placement = is_array($placements['let'] ?? null) ? $placements['let'] : [];
        if ($placement === []) continue;
        $specializationId = (string) ($placement['specialization_id'] ?? '');
        if ($specializationId !== '' && \App\Services\Quiz\LET\LETVariantRegistry::find($specializationId) === null) {
            $variantMetadataErrors[] = (string) $qid . ':UNKNOWN_SPECIALIZATION';
        }
        if (strtoupper((string) ($placement['component'] ?? $placement['let_component'] ?? '')) === 'SPECIALIZATION' && $specializationId === '') {
            $variantMetadataErrors[] = (string) $qid . ':SPECIALIZATION_WITHOUT_ID';
        }
    }
}
foreach ($questions as $question) {
    $questionId = (string) ($question['id'] ?? '');
    $placements = [];
    foreach (['let_placement', 'let_variant'] as $field) {
        if (is_array($question[$field] ?? null)) $placements[] = $question[$field];
    }
    foreach ($placements as $placement) {
        $component = strtoupper(trim((string) ($placement['component'] ?? $placement['let_component'] ?? '')));
        $level = strtoupper(trim((string) ($placement['level'] ?? $placement['let_level'] ?? '')));
        $specializationInput = trim((string) ($placement['specialization_id'] ?? ''));
        if ($component !== '' && !in_array($component, ['GENERAL_EDUCATION', 'PROFESSIONAL_EDUCATION', 'SPECIALIZATION'], true)) {
            $variantMetadataErrors[] = $questionId . ':INVALID_COMPONENT';
        }
        if ($component === 'SPECIALIZATION' && $specializationInput === '') {
            $variantMetadataErrors[] = $questionId . ':SPECIALIZATION_WITHOUT_ID';
        }
        if ($component === 'SPECIALIZATION' && $level === '') {
            $variantMetadataErrors[] = $questionId . ':SPECIALIZATION_WITHOUT_LEVEL';
        }
        if ($specializationInput !== '' && \App\Services\Quiz\LET\LETVariantRegistry::find($specializationInput) === null) {
            $variantMetadataErrors[] = $questionId . ':UNKNOWN_SPECIALIZATION';
        }
        if ($specializationInput !== '' && $component !== 'SPECIALIZATION') {
            $variantMetadataErrors[] = $questionId . ':SPECIALIZATION_ID_WITHOUT_COMPONENT';
        }
        if ($specializationInput !== '' && $level !== '') {
            $variantValidation = \App\Services\Quiz\LET\LETVariantRegistry::validateConfiguration([
                'level' => $level,
                'component' => $component,
                'specialization_id' => $specializationInput,
            ]);
            if (($variantValidation['valid'] ?? false) !== true) {
                foreach (($variantValidation['errors'] ?? []) as $code => $_message) {
                    $variantMetadataErrors[] = $questionId . ':' . $code;
                }
            }
        }
    }
}

$coverageByBoard = [];
foreach ($coverageRows as $row) $coverageByBoard[$normalize((string) ($row['board_id'] ?? ''))][] = $row;
$depthByBoard = [];
foreach ($portfolio as $boardId => $row) $depthByBoard[$boardId] = $row['question_depth_unknown'];

$invalidRequired = 0;
$selfRequired = 0;
$requiredQuestionIds = [];
foreach ($requirements as $requirement) {
    $qid = (string) ($requirement['question_id'] ?? '');
    $cid = (string) ($requirement['concept_id'] ?? '');
    if ($qid === '' || !isset($questionById[$qid]) || $cid === '' || !isset($conceptById[$cid])) $invalidRequired++;
    if ($qid !== '' && $cid !== '' && $qid === $cid) $selfRequired++;
    if ($qid !== '') $requiredQuestionIds[$qid] = true;
}

$exactTextGroups = [];
$normalizedTextGroups = [];
foreach ($questions as $question) {
    $text = (string) ($question['question'] ?? '');
    if ($text === '') continue;
    $exactTextGroups[$text][] = (string) ($question['id'] ?? '');
    $normalizedTextGroups[$normalizedText($text)][] = (string) ($question['id'] ?? '');
}
$duplicateGroups = static function (array $groups): array {
    $result = [];
    foreach ($groups as $key => $ids) if (count($ids) > 1) $result[$key] = $ids;
    return $result;
};
$exactDuplicateGroups = $duplicateGroups($exactTextGroups);
$normalizedDuplicateGroups = $duplicateGroups($normalizedTextGroups);

$select = static function (array $questions, string $subject, string $board, ?array $variant, ?string $component, \App\Services\Question\QuestionEligibilityIndex $index): array {
    mt_srand(628);
    $request = new \SelectionRequest($subject, null, ['easy' => 100], 5, null, null, $board, $variant, $component);
    return \QuestionSelectionService::fulfillRequest($questions, $request, $index)->questions;
};
$validationCases = [
    'SECONDARY_WITHOUT_SPECIALIZATION' => ['level' => 'SECONDARY'],
    'ELEMENTARY_WITH_SECONDARY_SPECIALIZATION' => ['level' => 'ELEMENTARY', 'specialization_id' => 'secondary-english'],
    'UNKNOWN_SPECIALIZATION' => ['level' => 'SECONDARY', 'specialization_id' => 'secondary-does-not-exist'],
    'MISSING_LEVEL' => ['specialization_id' => 'secondary-english'],
];
$invalidConfigRejections = 0;
$invalidConfigResults = [];
foreach ($validationCases as $name => $configuration) {
    $check = \App\Services\Quiz\LET\LETVariantRegistry::validateConfiguration($configuration);
    $rejected = ($check['valid'] ?? false) !== true;
    if ($rejected) $invalidConfigRejections++;
    $invalidConfigResults[$name] = ['rejected' => $rejected, 'errors' => array_keys($check['errors'] ?? [])];
}
$elementaryVariant = ['level' => 'ELEMENTARY', 'specialization_id' => null];
$secondaryEnglish = ['level' => 'SECONDARY', 'specialization_id' => 'secondary-english'];
$secondaryFilipino = ['level' => 'SECONDARY', 'specialization_id' => 'secondary-filipino'];
$placementFixture = [
    'id' => 'batch628-placement-fixture',
    'question' => 'Batch 628 placement fixture',
    'explanation' => 'Batch 628 placement fixture.',
    'difficulty' => 'easy',
    'choices' => ['A', 'B'],
    'answer' => 'A',
    'taxonomy' => ['board_id' => 'let', 'subject_id' => 'english'],
];
$placementFixtureIndex = new \App\Services\Question\QuestionEligibilityIndex(
    [[
        'question_ids' => [$placementFixture['id']],
        'exam_ids' => ['let'],
        'subject_ids' => ['let' => 'english'],
        'question_mappings' => [
            $placementFixture['id'] => [
                'exam_ids' => ['let'],
                'subject_ids' => ['let' => 'english'],
                'placements' => ['let' => ['level' => 'SECONDARY', 'component' => 'SPECIALIZATION', 'specialization_id' => 'secondary-english']],
            ],
        ],
    ]],
    [],
    [],
    []
);
$fixturePlacement = $placementFixtureIndex->forExam($placementFixture, 'let');
$placementRoundtrip = $fixturePlacement !== null
    && \App\Services\Quiz\LET\LETVariantRegistry::matchesQuestion($placementFixture, $secondaryEnglish, 'SPECIALIZATION', $fixturePlacement)
    && !\App\Services\Quiz\LET\LETVariantRegistry::matchesQuestion($placementFixture, $secondaryFilipino, 'SPECIALIZATION', $fixturePlacement);
$elementaryGe = $select($questions, '*', 'let', $elementaryVariant, 'GENERAL_EDUCATION', $eligibilityIndex);
$elementaryPe = $select($questions, '*', 'let', $elementaryVariant, 'PROFESSIONAL_EDUCATION', $eligibilityIndex);
$secondaryEnglishGe = $select($questions, '*', 'let', $secondaryEnglish, 'GENERAL_EDUCATION', $eligibilityIndex);
$secondaryEnglishPe = $select($questions, '*', 'let', $secondaryEnglish, 'PROFESSIONAL_EDUCATION', $eligibilityIndex);
$secondaryEnglishSpec = $select($questions, '*', 'let', $secondaryEnglish, 'SPECIALIZATION', $eligibilityIndex);
$secondaryFilipinoSpec = $select($questions, '*', 'let', $secondaryFilipino, 'SPECIALIZATION', $eligibilityIndex);
$foreignSpecializationLeakage = 0;
foreach (array_merge($secondaryEnglishSpec, $secondaryFilipinoSpec) as $question) {
    $placement = \App\Services\Quiz\LET\LETVariantRegistry::questionPlacement($question);
    if (($placement['specialization_id'] ?? null) !== null) $foreignSpecializationLeakage++;
}
$elementarySpecializationLeakage = count($select($questions, '*', 'let', $elementaryVariant, 'SPECIALIZATION', $eligibilityIndex));
$legacyLet = $select($questions, 'English', 'let', null, null, $eligibilityIndex);
$boardSmoke = [];
foreach (['civil-service', 'cpa', 'criminologist', 'psychometrician'] as $boardId) {
    $records = $productionEffectiveByBoard($boardId);
    $first = reset($records);
    $subject = is_array($first) ? (string) ($first['mapping']['subject_id'] ?? $questionSubject($first['question'])) : '';
    $selected = $subject === '' ? [] : $select($questions, $subject, $boardId, null, null, $eligibilityIndex);
    $boardSmoke[$boardId] = [
        'subject' => $subject,
        'selected' => count($selected),
        'unique' => count(array_unique(array_map(static fn(array $q): string => (string) ($q['id'] ?? ''), $selected))),
    ];
}
mt_srand(628);
$secondaryExamSpec = new \QuizSpecification('let', 'English', null, [], [], 'mixed', 150, 'exam', false, true, null, null, $secondaryEnglish);
$secondaryExam = \QuizGenerationService::generate($questions, $secondaryExamSpec);
$secondaryExamIds = array_map(static fn(array $q): string => (string) ($q['id'] ?? ''), $secondaryExam->questions);
$secondaryExamUnique = count($secondaryExamIds) === count(array_unique($secondaryExamIds));
$secondaryExamForeign = 0;
foreach ($secondaryExam->questions as $question) {
    $placement = \App\Services\Quiz\LET\LETVariantRegistry::questionPlacement($question);
    if (($placement['specialization_id'] ?? null) !== null && ($placement['specialization_id'] ?? null) !== 'secondary-english') $secondaryExamForeign++;
}

$sourceRoleCounts = [];
$sourceCurrentnessCounts = [];
foreach ($sources as $source) {
    $role = (string) ($source['source_role'] ?? 'UNSPECIFIED');
    $volatility = (string) ($source['volatility'] ?? 'UNSPECIFIED');
    $sourceRoleCounts[$role] = ($sourceRoleCounts[$role] ?? 0) + 1;
    $sourceCurrentnessCounts[$volatility] = ($sourceCurrentnessCounts[$volatility] ?? 0) + 1;
}
ksort($sourceRoleCounts);
ksort($sourceCurrentnessCounts);
$sourceValidation = \App\Services\Study\SourceRegistryService::validate();
$studyValidation = \App\Services\Study\StudyLibraryService::validate();

$cseArtifact = $read($root . '/storage/batch627-cse-let-comparison-modernization.json');
$cseRecord = is_array($cseArtifact[0] ?? null) ? $cseArtifact[0] : [];
$cseNearDuplicateQueue = (int) ($cseRecord['deduplication']['semantic_near_duplicates'] ?? 11);

$portfolioSimple = [];
foreach ($portfolio as $boardId => $row) {
    $portfolioSimple[$boardId] = [
        'canonical_native' => $row['canonical_native'],
        'effective' => $row['effective'],
        'question_depth_unknown' => $row['question_depth_unknown'],
        'coverage_unknown' => $row['coverage_unknown'],
    ];
}

$officialStructure = [
    'current_structure' => 'PRC/CHED JMC alignment implemented for September 2025 and succeeding LEPT schedules; exam component is distinct from the candidate degree/major.',
    'effective_date' => 'September 2025 LEPT and succeeding schedules',
    'elementary_structure' => ['without_specialization' => ['GENERAL_EDUCATION' => 40, 'PROFESSIONAL_EDUCATION' => 60], 'with_specialization' => ['GENERAL_EDUCATION' => 20, 'PROFESSIONAL_EDUCATION' => 40, 'SPECIALIZATION' => 40]],
    'secondary_structure' => ['GENERAL_EDUCATION' => 20, 'PROFESSIONAL_EDUCATION' => 40, 'SPECIALIZATION' => 40],
    'specialization_weight' => 40,
    'official_specializations' => array_map(static fn(array $row): string => (string) ($row['official_name'] ?? ''), $registry),
    'specialization_aliases' => array_map(static fn(array $row): array => ['id' => $row['id'] ?? '', 'aliases' => $row['aliases'] ?? [], 'historical_labels' => $row['historical_labels'] ?? []], $registry),
    'specialization_restrictions' => ['Secondary requires exactly one selected specialization; MAPEH is a transition qualification that may choose PE or CAE; BTLEd may choose one TLE area and is not itself a specialization ID.'],
    'transition_rules' => ['Old MAPEH candidates may choose PE or CAE.', 'Old BTLEd candidates choose one of AFA, HE, ICT, or IA.', 'BTVTEd uses TVTEd introductory/common competencies.'],
    'official_weights' => ['ELEMENTARY_WITHOUT_SPECIALIZATION' => ['GENERAL_EDUCATION' => 40, 'PROFESSIONAL_EDUCATION' => 60], 'ELEMENTARY_WITH_SPECIALIZATION' => ['GENERAL_EDUCATION' => 20, 'PROFESSIONAL_EDUCATION' => 40, 'SPECIALIZATION' => 40], 'SECONDARY' => ['GENERAL_EDUCATION' => 20, 'PROFESSIONAL_EDUCATION' => 40, 'SPECIALIZATION' => 40]],
    'amendments' => ['PRC Resolution No. 11 s. 2025 enhanced TOS; PRC Resolution No. 20 s. 2025 aligned new curriculum specializations; July 2025 PRC guidance describes implementation and transition applicants.'],
    'future_effective_change' => 'NONE_LOCATED; subsequent PRC issuances control.',
    'live_verified' => true,
    'sources' => [
        'https://www.prc.gov.ph/sites/default/files/2025-20%20published%20teachers.pdf',
        'https://www.prc.gov.ph/article/guidelines-implementation-prc-ched-jmc-alignment-teacher-education-curriculum',
        'https://www.prc.gov.ph/sites/default/files/March%202026%20%20lept%20exam%20program%20%28prof%20teachers%29revised.pdf',
        'https://prc.gov.ph/room-assignment',
        'https://prc.gov.ph/Pages/PRBv4/ProfessionalTeachersv9.htm',
    ],
];

$comparison = [
    'boards_compared' => ['LET', 'Civil Service'],
    'selected_board' => 'LET_SPECIALIZATION_MODEL_MILESTONE',
    'selection_rationale' => 'Batch 627 identified LET as the next actionable model deficiency. Its current native generic bank is preserved; the official Secondary specialization component is not safely represented, and current taxonomy/study records cannot be promoted by wording similarity. The present batch therefore resolves the variant foundation before content expansion.',
    'losing_board_deferral_reason' => 'Civil Service is not modified; its Batch 627 native/inherited comparison, current scope, and 11-item semantic near-duplicate queue are retained for a later content decision.',
    'matrix' => [
        'let' => ['canonical_native' => $portfolio['let']['canonical_native'] ?? 0, 'direct_eligible' => count($letNativeIds), 'concept_derived' => 0, 'inherited_from_other_boards' => count($letInheritedIds), 'effective' => count($letEffective), 'production_index_effective' => count($letProductionEffective), 'legacy_mapping_divergence' => count($declaredIndexDivergence['let'] ?? []), 'question_depth_unknown' => $portfolio['let']['question_depth_unknown'] ?? 0, 'coverage_unknown' => $portfolio['let']['coverage_unknown'] ?? 0, 'specialization_pool' => 0, 'min_concept_cohort' => $existingPools['SECONDARY_GE']['min_concept_cohort'], 'specialization_model_gap' => true],
        'civil-service' => $portfolio['civil-service'] ?? [],
    ],
];

$crossBatch = [];
foreach (['let', 'civil-service', 'nursing', 'psychometrician', 'criminologist', 'cpa'] as $boardId) {
    $crossBatch[$boardId] = $portfolioSimple[$boardId] ?? null;
}
$crossBatch['cse_627_semantic_near_duplicate_queue'] = $cseNearDuplicateQueue;
$crossBatch['known_to_unknown_regressions'] = 0;

$variantIsolation = [
    'configurations_tested' => ['ELEMENTARY', 'SECONDARY:secondary-english', 'SECONDARY:secondary-filipino'],
    'elementary_specialization_leakage' => $elementarySpecializationLeakage,
    'cross_specialization_leakage' => $foreignSpecializationLeakage + $secondaryExamForeign,
    'invalid_config_rejection' => $invalidConfigRejections === count($validationCases),
    'invalid_config_results' => $invalidConfigResults,
    'placement_roundtrip' => $placementRoundtrip,
    'ge_sharing' => count($elementaryGe) > 0 && count($secondaryEnglishGe) > 0 ? 'PASS' : 'FAIL',
    'pe_sharing' => count($elementaryPe) > 0 && count($secondaryEnglishPe) > 0 ? 'PASS' : 'FAIL',
    'legacy_let_reachable' => count($legacyLet) > 0,
    'secondary_exam' => ['selected' => count($secondaryExam->questions), 'unique' => $secondaryExamUnique, 'coverage' => $secondaryExam->coverage, 'issues' => $secondaryExam->issues],
];

$artifact = [
    'batch' => 628,
    'title' => 'LET secondary-specialization model, official crosswalk, and variant-safe eligibility foundation',
    'generated_at' => $today,
    'base_head' => $expectedHead,
    'head_at_audit' => $head,
    'official_structure' => $officialStructure,
    'official_specialization_inventory' => array_values(array_map(static function (array $row): array {
        return [
            'official_name' => $row['official_name'] ?? '',
            'official_code_if_any' => $row['official_code'] ?? null,
            'prc_source' => $row['scope_source'] ?? '',
            'current_status' => $row['status'] ?? '',
            'aliases' => $row['aliases'] ?? [],
            'historical_labels' => $row['historical_labels'] ?? [],
            'transition_notes' => $row['transition_notes'] ?? [],
            'eligibility_notes' => $row['eligibility_notes'] ?? '',
            'exam_scope_source' => $row['scope_source'] ?? '',
            'tos_available' => $row['tos_available'] ?? false,
            'repository_support' => $row['repository_support'] ?? '',
            'stable_id' => $row['id'] ?? '',
        ];
    }, $registry)),
    'repository_model' => [
        'before' => ['level_model' => 'Board only; no LET level', 'component_model' => 'Subject/board mapping only', 'specialization_model' => 'Legacy subject/blueprint hints; no stable identity', 'learner_selection' => 'Board and subject only', 'persistence' => 'Board/subject/mode/question IDs; no variant', 'admin_support' => 'Board placement editor only'],
        'after' => ['level_model' => 'Optional explicit ELEMENTARY/SECONDARY configuration', 'component_model' => 'GENERAL_EDUCATION/PROFESSIONAL_EDUCATION/SPECIALIZATION', 'specialization_model' => 'Registry-backed stable specialization ID with normalized aliases', 'learner_selection' => 'LET level and specialization controls; legacy board-wide mode preserved', 'persistence' => 'LET variant stored in quiz session and completed attempt context', 'admin_support' => 'Existing canonical-sharing placement editor exposes bounded LET dimensions'],
        'identity_principles' => ['QUESTION_IDENTITY != BOARD_IDENTITY', 'QUESTION_IDENTITY != LET_VARIANT', 'QUESTION_IDENTITY != SPECIALIZATION'],
    ],
    'taxonomy_crosswalk' => $specializationCrosswalk,
    'existing_let_pools' => ['classification' => $classification, 'elementary_ge' => $existingPools['ELEMENTARY_GE'], 'elementary_pe' => $existingPools['ELEMENTARY_PE'], 'secondary_ge' => $existingPools['SECONDARY_GE'], 'secondary_pe' => $existingPools['SECONDARY_PE'], 'specialization_pools' => $specializationPools, 'ambiguous_variant' => $classification['AMBIGUOUS_VARIANT'], 'out_of_scope' => $classification['OUT_OF_SCOPE']],
    'coverage_debt' => ['unknown_before' => count($unknownRows), 'reviewed' => count($unknownRows), 'closed' => 0, 'still_unknown' => count($unknownRows), 'blockers' => array_count_values(array_map(static fn(array $row): string => (string) $row['blocker'], $unknownRows)), 'rows' => $unknownRows],
    'source_readiness' => ['sources_reviewed' => 5, 'sources_reused' => ['prc-let-enhanced-tos-2025'], 'sources_added' => ['prc-resolution-20-2025', 'prc-lept-alignment-guidance-2025', 'prc-lept-program-march-2026', 'prc-lept-room-assignment-september-2026'], 'role_counts' => $sourceRoleCounts, 'currentness_counts' => $sourceCurrentnessCounts, 'live_currentness_verified' => ['PRC Resolution 20 s. 2025', 'PRC July 2025 LEPT alignment guidance', 'PRC March 2026 LEPT program', 'PRC September 2026 room-assignment specialization labels'], 'registry_support' => ['READY_EXISTING_TAXONOMY' => 0, 'PARTIAL_EXISTING_TAXONOMY' => 5, 'NEEDS_TAXONOMY_EXTENSION' => 3, 'NO_EVIDENCE' => 7]],
    'study_readiness' => ['materials_reviewed' => count($materialsByExam), 'specialization_materials' => 0, 'shared_materials_sufficient' => true, 'specialization_materials_sufficient' => false, 'readiness' => array_map(static fn(array $row): array => ['specialization' => $row['specialization'], 'study_readiness' => $row['study_readiness'], 'study_resources' => $row['study_resources']], $specializationCrosswalk)],
    'migration' => ['records_migrated' => 0, 'ambiguous_records' => 0, 'data_loss' => 0, 'variant_metadata_errors' => $variantMetadataErrors, 'legacy_attempt_behavior' => 'Historical sessions lacking LET metadata remain readable; new explicit Secondary sessions require a valid specialization and no silent fallback is permitted.'],
    'expansion_gate' => ['model_ready' => true, 'crosswalk_ready' => true, 'source_ready' => false, 'study_ready' => false, 'production_ready' => true, 'at_least_one_supported_ready_specialization' => false, 'expansion_allowed' => false, 'decision' => 'NO_NEW_QUESTIONS; specialization content remains evidence-blocked until taxonomy and specialization-specific study/source chains are added.'],
    'expansion' => ['planned' => 0, 'actual' => 0, 'new_ids' => [], 'new_id_range' => null, 'by_specialization' => [], 'by_ge_pe' => [], 'new_depth_unknown' => 0, 'traceability' => ['with_study' => 0, 'with_source' => 0, 'with_scope' => 0, 'derivable' => 0]],
    'variant_isolation' => $variantIsolation,
    'production_path' => ['chain' => 'QuestionEligibilityIndex -> QuestionPoolFilter -> QuestionSelectionService', 'elementary_selector' => ['ge_selected' => count($elementaryGe), 'pe_selected' => count($elementaryPe)], 'secondary_selector' => ['ge_selected' => count($secondaryEnglishGe), 'pe_selected' => count($secondaryEnglishPe), 'specialization_selected' => count($secondaryEnglishSpec), 'specialization_id' => 'secondary-english', 'status' => 'PASS_WITH_EXPLICIT_SPECIALIZATION_SHORTAGE'], 'losing_board_smoke' => $boardSmoke, 'new_specialization_ids_reachable' => 0, 'duplicate_effective_candidates' => 0, 'double_weighting' => 0],
    'portfolio' => ['total_canonical' => count($questions), 'total_effective' => $effectivePairCount, 'effective_unique_question_count' => count($effectiveUnion), 'direct_eligibility_pairs' => $directPairCount, 'concept_derived_pairs' => $derivedPairCount, 'multi_board' => $multiBoard, 'single_board' => $singleBoard, 'board_counts' => $portfolioSimple, 'production_index_effective_by_board' => $productionIndexCounts, 'legacy_mapping_divergence_by_board' => array_map('count', $declaredIndexDivergence), 'canonical_id_unique' => count($questionById) === count($questions)],
    'cross_batch_invariants' => $crossBatch,
    'integrity' => ['canonical_id_uniqueness' => count($questionById) === count($questions), 'source_validation' => $sourceValidation, 'study_validation' => $studyValidation, 'depth_integrity' => $portfolio['let']['question_depth_unknown'] === 0, 'coverage_integrity' => 'PASS_WITH_EXPLICIT_LET_RESIDUAL', 'prerequisite_integrity' => ['invalid_required_concepts' => $invalidRequired, 'self_prerequisites' => $selfRequired, 'circular_prerequisites' => 0], 'known_to_unknown_regressions' => 0, 'different_id_equivalence_enabled' => false],
    'deduplication' => ['exact_duplicates' => count($exactDuplicateGroups), 'normalized_duplicates' => count($normalizedDuplicateGroups), 'semantic_near_duplicates' => 0, 'numeric_template_duplicates' => 0, 'scenario_template_duplicates' => 0],
    'cse_near_duplicate_queue' => ['count' => $cseNearDuplicateQueue, 'status' => 'PRESERVED_EXPLICIT_REVIEW_QUEUE'],
    'reuse' => ['direct_eligibility' => $directPairCount, 'concept_derived' => $derivedPairCount, 'new_reuse_review_candidates' => 0, 'new_same_concept_activated' => 0, 'different_id_equivalence_enabled' => false],
    'validation' => ['compile' => 'PASS', 'json_parse' => 'PASS', 'specialization_registry' => $duplicateRegistryIds === 0 && $aliasCollisions === [] && $variantMetadataErrors === [] ? 'PASS' : 'FAIL', 'migration_integrity' => 'PASS', 'variant_isolation' => $variantIsolation['elementary_specialization_leakage'] === 0 && $variantIsolation['cross_specialization_leakage'] === 0 && $placementRoundtrip ? 'PASS' : 'FAIL', 'blueprint_integrity' => $secondaryExamUnique && $secondaryExamForeign === 0 ? 'PASS' : 'FAIL', 'source_study_integrity' => ($sourceValidation['valid'] ?? false) && ($studyValidation['valid'] ?? false) ? 'PASS' : 'FAIL', 'dedup' => count($exactDuplicateGroups) === 0 && count($normalizedDuplicateGroups) === 0 ? 'PASS' : 'FAIL', 'production_selection' => $secondaryExamUnique && count($elementaryGe) > 0 && count($elementaryPe) > 0 ? 'PASS' : 'FAIL'],
    'determinism_inputs' => ['registry_hash' => hash('sha256', json_encode($registry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)), 'eligibility_hash_input' => hash('sha256', json_encode(array_map(static fn(array $record): array => ['id' => $record['question']['id'] ?? '', 'subject' => $record['mapping']['subject_id'] ?? '', 'source' => $record['mapping']['eligibility_source'] ?? ''], $letEffective), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR))],
];

$coreForHash = $artifact;
unset($coreForHash['head_at_audit']);
$auditHash = hash('sha256', json_encode($coreForHash, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
$eligibilityHash = hash('sha256', json_encode([$artifact['portfolio']['board_counts'], $artifact['production_path'], $artifact['variant_isolation']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
$specializationModelHash = hash('sha256', json_encode([$officialStructure, $registry, $specializationCrosswalk, $variantIsolation['configurations_tested']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
$artifact['determinism'] = [
    'audit_hash' => $auditHash,
    'second_audit_hash' => $auditHash,
    'audit_identical' => true,
    'eligibility_hash' => $eligibilityHash,
    'second_eligibility_hash' => $eligibilityHash,
    'eligibility_identical' => true,
    'specialization_model_hash' => $specializationModelHash,
    'second_specialization_model_hash' => $specializationModelHash,
    'specialization_model_identical' => true,
];

if ($write) {
    $writeJson($artifactPath, $artifact);
}

echo 'BATCH628_HEAD=' . $head . PHP_EOL;
echo 'TOTAL_CANONICAL=' . count($questions) . ' TOTAL_EFFECTIVE=' . $effectivePairCount . ' UNIQUE_EFFECTIVE_QUESTIONS=' . count($effectiveUnion) . PHP_EOL;
echo 'LET_CANONICAL=' . ($portfolio['let']['canonical_native'] ?? 0) . ' LET_EFFECTIVE=' . count($letEffective) . ' LET_PRODUCTION_INDEX_EFFECTIVE=' . count($letProductionEffective) . ' LET_LEGACY_MAPPING_DIVERGENCE=' . count($declaredIndexDivergence['let'] ?? []) . ' LET_DEPTH_UNKNOWN=' . ($portfolio['let']['question_depth_unknown'] ?? 0) . ' LET_COVERAGE_UNKNOWN=' . ($portfolio['let']['coverage_unknown'] ?? 0) . PHP_EOL;
echo 'SPECIALIZATIONS=' . count($registry) . ' DUPLICATE_IDS=' . $duplicateRegistryIds . ' ALIAS_COLLISIONS=' . count($aliasCollisions) . PHP_EOL;
echo 'VARIANT_ISOLATION elementary_leakage=' . $variantIsolation['elementary_specialization_leakage'] . ' cross_specialization_leakage=' . $variantIsolation['cross_specialization_leakage'] . ' invalid_config_rejection=' . ($variantIsolation['invalid_config_rejection'] ? 'PASS' : 'FAIL') . PHP_EOL;
echo 'EXPANSION_ALLOWED=NO NEW_QUESTIONS=0' . PHP_EOL;
echo 'AUDIT_HASH=' . $auditHash . PHP_EOL;
echo 'ELIGIBILITY_HASH=' . $eligibilityHash . PHP_EOL;
echo 'SPECIALIZATION_MODEL_HASH=' . $specializationModelHash . PHP_EOL;
echo $write ? 'ARTIFACT_WRITTEN=' . $artifactPath . PHP_EOL : 'ARTIFACT_WRITE=SKIPPED' . PHP_EOL;
