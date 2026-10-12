<?php

declare(strict_types=1);

const BATCH669_MAIN_COMMIT = '0eb9704ba293e0aa3402af5d01a2d3392c005b73';
const BATCH669_B668_COMMIT = '2354826b46820118083c61b3176002cbf8d8c124';
const BATCH669_FIRST_LEGACY_ID = 4163;
const BATCH669_LAST_LEGACY_ID = 4482;
const BATCH669_FIRST_CANONICAL_ID = 4873;
const BATCH669_MAPPING_VERSION = 'batch669-nursing-id-map-v1';

$root = dirname(__DIR__);
$write = in_array('--write', $argv, true);

$streamArray = static function (string $revision, string $path, callable $visit) use ($root): int {
    $process = proc_open(
        ['git', 'show', $revision . ':' . $path],
        [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']],
        $pipes,
        $root
    );
    if (!is_resource($process)) {
        throw new RuntimeException('Unable to read the committed source collection.');
    }
    fclose($pipes[0]);

    $active = false;
    $depth = 0;
    $quoted = false;
    $escaped = false;
    $buffer = '';
    $count = 0;
    while (($line = fgets($pipes[1])) !== false) {
        for ($index = 0, $length = strlen($line); $index < $length; $index++) {
            $character = $line[$index];
            if (!$active) {
                if ($character === '{') {
                    $active = true;
                    $depth = 1;
                    $quoted = false;
                    $escaped = false;
                    $buffer = '{';
                }
                continue;
            }

            $buffer .= $character;
            if ($quoted) {
                if ($escaped) {
                    $escaped = false;
                } elseif ($character === '\\') {
                    $escaped = true;
                } elseif ($character === '"') {
                    $quoted = false;
                }
                continue;
            }

            if ($character === '"') {
                $quoted = true;
            } elseif ($character === '{') {
                $depth++;
            } elseif ($character === '}' && --$depth === 0) {
                $visit(json_decode($buffer, true, 512, JSON_THROW_ON_ERROR));
                $count++;
                $active = false;
                $buffer = '';
            }
        }
    }

    fclose($pipes[1]);
    $error = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    if (proc_close($process) !== 0) {
        throw new RuntimeException('Unable to read ' . $path . ': ' . trim($error));
    }
    if ($active) {
        throw new RuntimeException('Truncated JSON array in ' . $path);
    }

    return $count;
};

$stable = static function (mixed $value) use (&$stable): mixed {
    if (!is_array($value)) {
        return $value;
    }
    if (!array_is_list($value)) {
        ksort($value);
    }
    foreach ($value as &$child) {
        $child = $stable($child);
    }
    unset($child);
    return $value;
};

$stemFingerprint = static function (array $question): string {
    $stem = trim((string) ($question['question'] ?? ''));
    $stem = preg_replace('/\s+/u', ' ', $stem) ?? $stem;
    return hash('sha256', mb_strtolower($stem, 'UTF-8'));
};

$questionFingerprint = static function (array $question) use ($stable): string {
    foreach ([
        'id', 'timesUsed', 'timesCorrect', 'timesIncorrect', 'bookmarks', 'reports',
        'helpfulExplanation', 'notHelpfulExplanation', 'createdAt', 'updatedAt',
    ] as $field) {
        unset($question[$field]);
    }
    return hash('sha256', json_encode($stable($question), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
};

$mutableStateFields = [
    'timesUsed', 'timesCorrect', 'timesIncorrect', 'bookmarks', 'reports',
    'helpfulExplanation', 'notHelpfulExplanation', 'createdAt', 'updatedAt',
];
$mutableStateEqual = static function (array $left, array $right) use ($mutableStateFields): bool {
    foreach ($mutableStateFields as $field) {
        if (($left[$field] ?? null) !== ($right[$field] ?? null)) {
            return false;
        }
    }
    return true;
};

$inRange = static function (mixed $id): bool {
    if (!is_scalar($id) || preg_match('/^\d+$/', (string) $id) !== 1) {
        return false;
    }
    $value = (int) $id;
    return $value >= BATCH669_FIRST_LEGACY_ID && $value <= BATCH669_LAST_LEGACY_ID;
};

$mainQuestions = [];
$streamArray(BATCH669_MAIN_COMMIT, 'storage/questions.json', static function (array $question) use (&$mainQuestions, $inRange): void {
    if ($inRange($question['id'] ?? null) && strtolower((string) ($question['board'] ?? '')) === 'nursing') {
        $mainQuestions[(string) $question['id']] = $question;
    }
});

$b668ById = [];
$b668NursingByStem = [];
$streamArray(BATCH669_B668_COMMIT, 'storage/questions.json', static function (array $question) use (&$b668ById, &$b668NursingByStem, $stemFingerprint, $inRange): void {
    $id = (string) ($question['id'] ?? '');
    if ($inRange($id)) {
        $b668ById[$id] = $question;
    }
    if (strtolower((string) ($question['board'] ?? '')) === 'nursing') {
        $fingerprint = $stemFingerprint($question);
        if (isset($b668NursingByStem[$fingerprint])) {
            throw new RuntimeException('Duplicate Nursing stem fingerprint in Batch 668.');
        }
        $b668NursingByStem[$fingerprint] = $question;
    }
});

if (count($mainQuestions) !== 320 || count($b668ById) !== 320) {
    throw new RuntimeException('Expected exactly 320 main Nursing records and 320 colliding Batch 668 records.');
}

$mapping = [];
$collisionBoardCounts = [];
foreach ($mainQuestions as $legacyId => $incoming) {
    $nursing = $b668NursingByStem[$stemFingerprint($incoming)] ?? null;
    $collision = $b668ById[$legacyId] ?? null;
    if (!is_array($nursing) || !is_array($collision)) {
        throw new RuntimeException('Missing a one-to-one Nursing match or shared-ID collision for ' . $legacyId);
    }

    $canonicalId = (string) ($nursing['id'] ?? '');
    $expectedCanonicalId = (string) (BATCH669_FIRST_CANONICAL_ID + ((int) $legacyId - BATCH669_FIRST_LEGACY_ID));
    $collisionBoard = strtolower((string) ($collision['board'] ?? ''));
    if ($canonicalId !== $expectedCanonicalId
        || !in_array($collisionBoard, ['cpa', 'criminologist'], true)) {
        throw new RuntimeException('Unexpected board or canonical ID for source ID ' . $legacyId);
    }
    if (strtolower((string) ($nursing['board'] ?? '')) !== 'nursing'
        || $stemFingerprint($incoming) === $stemFingerprint($collision)
        || $questionFingerprint($incoming) !== $questionFingerprint($nursing)) {
        throw new RuntimeException('Question content or board provenance differs for source ID ' . $legacyId);
    }
    $collisionBoardCounts[$collisionBoard] = ($collisionBoardCounts[$collisionBoard] ?? 0) + 1;

    $mapping[$legacyId] = [
        'canonical_id' => $canonicalId,
        'incoming' => $incoming,
        'nursing' => $nursing,
        'collision' => $collision,
    ];
}

$mutableStateMatchCount = 0;
$mutableStateDifferenceCount = 0;
foreach ($mapping as $entry) {
    if ($mutableStateEqual($entry['incoming'], $entry['nursing'])) {
        $mutableStateMatchCount++;
    } else {
        $mutableStateDifferenceCount++;
    }
}

$mainIds = array_fill_keys(array_keys($mapping), true);
$b668Ids = $mainIds;
foreach ($mapping as $entry) {
    $b668Ids[(string) $entry['canonical_id']] = true;
}

$references = [];
foreach ([
    'main' => ['revision' => BATCH669_MAIN_COMMIT, 'ids' => $mainIds],
    'batch668' => ['revision' => BATCH669_B668_COMMIT, 'ids' => $b668Ids],
] as $side => $config) {
    $references[$side] = ['eligibility' => [], 'reuse' => [], 'links' => []];
    $streamArray($config['revision'], 'storage/question-eligibility.json', static function (array $row) use (&$references, $side, $config): void {
        foreach (($row['question_ids'] ?? []) as $questionId) {
            $id = (string) $questionId;
            if (!isset($config['ids'][$id])) {
                continue;
            }
            $references[$side]['eligibility'][$id][] = [
                'record_id' => $row['id'] ?? null,
                'exam_ids' => $row['exam_ids'] ?? [],
                'subject_ids' => $row['subject_ids'] ?? [],
                'blueprint_node_id' => $row['blueprint_node_id'] ?? null,
                'question_mapping' => $row['question_mappings'][$id] ?? null,
            ];
        }
    });
    $streamArray($config['revision'], 'storage/question-reuse-metadata.json', static function (array $row) use (&$references, $side, $config): void {
        $id = (string) ($row['question_id'] ?? '');
        if (isset($config['ids'][$id])) {
            $references[$side]['reuse'][$id] = $row;
        }
    });
    $streamArray($config['revision'], 'storage/study-question-links.json', static function (array $row) use (&$references, $side, $config): void {
        $id = (string) ($row['question_id'] ?? '');
        if (isset($config['ids'][$id])) {
            $references[$side]['links'][$id][] = $row;
        }
    });
}

$mainMaterials = [];
$b668Materials = [];
$mainSources = [];
$b668Sources = [];
$streamArray(BATCH669_MAIN_COMMIT, 'storage/study-materials.json', static function (array $row) use (&$mainMaterials): void {
    $mainMaterials[(string) ($row['id'] ?? '')] = $row;
});
$streamArray(BATCH669_B668_COMMIT, 'storage/study-materials.json', static function (array $row) use (&$b668Materials): void {
    $b668Materials[(string) ($row['id'] ?? '')] = $row;
});
$streamArray(BATCH669_MAIN_COMMIT, 'storage/sources.json', static function (array $row) use (&$mainSources): void {
    $mainSources[(string) ($row['id'] ?? '')] = true;
});
$streamArray(BATCH669_B668_COMMIT, 'storage/sources.json', static function (array $row) use (&$b668Sources): void {
    $b668Sources[(string) ($row['id'] ?? '')] = true;
});

$eligibilitySummary = static function (array $rows): array {
    return array_map(static function (array $row): array {
        unset($row['record_id']);
        return $row;
    }, $rows);
};
$linkSummary = static function (array $rows): array {
    return array_map(static fn(array $row): array => [
        'material_id' => $row['material_id'] ?? null,
        'source_ids' => $row['source_ids'] ?? [],
    ], $rows);
};
$reuseSummary = static function (array $row): array {
    unset($row['id'], $row['question_id']);
    return $row;
};

$inventory = [];
$allSourcesExist = true;
$allReferencesMatch = true;
$materialsIdentical = [];
foreach ($mapping as $legacyId => $pair) {
    $canonicalId = (string) $pair['canonical_id'];
    $incomingLinks = $references['main']['links'][$legacyId] ?? [];
    $retainedLinks = $references['batch668']['links'][$canonicalId] ?? [];
    $collisionLinks = $references['batch668']['links'][$legacyId] ?? [];
    $incomingReuse = $references['main']['reuse'][$legacyId] ?? [];
    $retainedReuse = $references['batch668']['reuse'][$canonicalId] ?? [];
    $collisionReuse = $references['batch668']['reuse'][$legacyId] ?? [];
    $incomingEligibility = $references['main']['eligibility'][$legacyId] ?? [];
    $retainedEligibility = $references['batch668']['eligibility'][$canonicalId] ?? [];
    $collisionEligibility = $references['batch668']['eligibility'][$legacyId] ?? [];

    $sameLinks = $stable($linkSummary($incomingLinks)) === $stable($linkSummary($retainedLinks));
    $sameReuse = $stable($reuseSummary($incomingReuse)) === $stable($reuseSummary($retainedReuse));
    $sameEligibility = $stable($eligibilitySummary($incomingEligibility)) === $stable($eligibilitySummary($retainedEligibility));
    if (!$sameLinks || !$sameReuse || !$sameEligibility) {
        $allReferencesMatch = false;
    }

    $materialRows = [];
    $linkedSourceIds = [];
    foreach ($incomingLinks as $link) {
        $materialId = (string) ($link['material_id'] ?? '');
        $materialSource = $mainMaterials[$materialId] ?? null;
        $retainedMaterial = $b668Materials[$materialId] ?? null;
        $sameMaterial = is_array($materialSource) && is_array($retainedMaterial)
            && $stable($materialSource) === $stable($retainedMaterial);
        $materialsIdentical[$materialId] = $sameMaterial;
        $materialRows[] = [
            'material_id' => $materialId,
            'title' => $materialSource['title'] ?? null,
            'identical_in_batch668' => $sameMaterial,
        ];
        foreach (($link['source_ids'] ?? []) as $sourceId) {
            $linkedSourceIds[(string) $sourceId] = true;
        }
    }
    foreach (array_keys($linkedSourceIds) as $sourceId) {
        if (!isset($mainSources[$sourceId]) || !isset($b668Sources[$sourceId])) {
            $allSourcesExist = false;
        }
    }

    $incoming = $pair['incoming'];
    $retained = $pair['nursing'];
    $collision = $pair['collision'];
    $nursingRefsEqual = $sameLinks && $sameReuse && $sameEligibility
        && !in_array(false, $materialsIdentical, true);
    $inventory[] = [
        'source_question_id' => (int) $legacyId,
        'canonical_nursing_question_id' => (int) $canonicalId,
        'same_numeric_id_collision' => [
            'classification' => 'DISTINCT_QUESTIONS_SHARED_ID',
            'incoming_board' => $incoming['board'] ?? null,
            'incoming_stem_sha256' => $stemFingerprint($incoming),
            'incoming_answer' => $incoming['answer'] ?? null,
            'incoming_source' => $incoming['source'] ?? null,
            'incoming_eligibility' => $incomingEligibility,
            'existing_board' => $collision['board'] ?? null,
            'existing_stem_sha256' => $stemFingerprint($collision),
            'existing_answer' => $collision['answer'] ?? null,
            'existing_source' => $collision['source'] ?? null,
            'existing_eligibility' => $collisionEligibility,
            'existing_study_links' => $linkSummary($collisionLinks),
            'existing_reuse' => $collisionReuse['reuse'] ?? null,
        ],
        'nursing_record_reconciliation' => [
            'classification' => 'IDENTICAL_RECORD_AND_REFERENCES_ALREADY_PRESENT',
            'incoming_stem_sha256' => $stemFingerprint($incoming),
            'canonical_stem_sha256' => $stemFingerprint($retained),
            'incoming_content_sha256' => $questionFingerprint($incoming),
            'canonical_content_sha256' => $questionFingerprint($retained),
            'content_equal_except_id_and_mutable_counters' => $questionFingerprint($incoming) === $questionFingerprint($retained),
            'incoming_answer' => $incoming['answer'] ?? null,
            'canonical_answer' => $retained['answer'] ?? null,
            'incoming_source' => $incoming['source'] ?? null,
            'canonical_source' => $retained['source'] ?? null,
            'eligibility_equal_after_mapping' => $sameEligibility,
            'study_links_equal_after_mapping' => $sameLinks,
            'reuse_metadata_equal_after_mapping' => $sameReuse,
            'linked_materials' => $materialRows,
            'linked_source_ids' => array_keys($linkedSourceIds),
            'incoming_eligibility' => $incomingEligibility,
            'canonical_eligibility' => $retainedEligibility,
            'incoming_study_links' => $linkSummary($incomingLinks),
            'canonical_study_links' => $linkSummary($retainedLinks),
            'incoming_reuse' => $incomingReuse['reuse'] ?? null,
            'canonical_reuse' => $retainedReuse['reuse'] ?? null,
            'references_equal' => $nursingRefsEqual,
        ],
        'provenance' => [
            'incoming_branch' => 'main',
            'incoming_commit' => BATCH669_MAIN_COMMIT,
            'retained_branch' => 'batch668-acceptance-preparation',
            'retained_commit' => BATCH669_B668_COMMIT,
        ],
    ];
}

if (count($inventory) !== 320 || !$allReferencesMatch || !$allSourcesExist
    || in_array(false, $materialsIdentical, true)) {
    throw new RuntimeException('Question, source, eligibility, study, or reuse equivalence verification failed.');
}

$report = [
    'schema_version' => 1,
    'mapping_version' => BATCH669_MAPPING_VERSION,
    'baseline' => [
        'common_ancestor' => '42bdfd7581f2b50bae8584707df9ffeab37fb055',
        'main_commit' => BATCH669_MAIN_COMMIT,
        'batch668_commit' => BATCH669_B668_COMMIT,
    ],
    'method' => [
        'same_numeric_id_records_compared_by_board_stem_answer_and_source',
        'incoming_nursing_records_matched_to_existing_nursing_records_by_normalized_stem',
        'content_fingerprints_exclude_id_and_mutable_state_fields',
        'mutable_state_fields_compared_separately',
        'eligibility_study_links_reuse_metadata_materials_and_source_ids_compared',
    ],
    'summary' => [
        'reported_id_collisions' => 320,
        'verified_same_numeric_id_collisions' => 320,
        'shared_id_classifications' => [
            'identical' => 0,
            'compatible_edits' => 0,
            'conflicting_edits' => 0,
            'distinct_questions_shared_id' => 320,
            'unresolved' => 0,
        ],
        'distinct_nursing_vs_other_board_records_at_shared_ids' => 320,
            'existing_collision_board_counts' => $collisionBoardCounts,
        'identical_incoming_nursing_records_already_present' => 320,
        'incoming_records_not_reimported' => 320,
        'canonical_question_additions' => 0,
        'true_duplicate_rows_avoided' => 320,
        'legacy_question_ids_mapped' => 320,
        'mutable_state_fields_compared' => $mutableStateFields,
        'mutable_state_matches' => $mutableStateMatchCount,
        'mutable_state_differences' => $mutableStateDifferenceCount,
        'canonical_nursing_id_min' => min(array_map(static fn(array $row): int => $row['canonical_nursing_question_id'], $inventory)),
        'canonical_nursing_id_max' => max(array_map(static fn(array $row): int => $row['canonical_nursing_question_id'], $inventory)),
        'all_nursing_content_and_references_identical' => true,
        'all_linked_sources_resolve_in_both_commits' => true,
        'all_linked_materials_identical_in_both_commits' => true,
    ],
    'mapping' => $inventory,
];

$json = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
if ($write) {
    $path = $root . '/docs/BATCH669_NURSING_ID_RECONCILIATION.json';
    if (is_file($path)) {
        throw new RuntimeException('Refusing to overwrite an existing reconciliation artifact.');
    }
    if (file_put_contents($path, $json, LOCK_EX) === false) {
        throw new RuntimeException('Unable to write the reconciliation artifact.');
    }
    fwrite(STDOUT, 'Wrote ' . $path . ' (' . count($inventory) . " records).\n");
} else {
    fwrite(STDOUT, $json);
}
