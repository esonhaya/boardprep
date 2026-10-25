<?php

declare(strict_types=1);

/** Build a read-only governance worksheet from current registries and pilot evidence. */
$root = dirname(__DIR__);
$read = static function (string $relative) use ($root): array {
    $raw = file_get_contents($root . '/' . $relative);
    if (!is_string($raw)) throw new RuntimeException('Cannot read ' . $relative);
    $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($data)) throw new RuntimeException('Expected JSON list/object in ' . $relative);
    return $data;
};
$normalize = static function (string $value): string {
    $value = strtolower(trim($value));
    $value = preg_replace('/[^\pL\pN]+/u', ' ', $value) ?? $value;
    return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
};
$byId = static function (array $rows): array {
    $indexed = [];
    foreach ($rows as $row) if (is_array($row) && is_scalar($row['id'] ?? null)) $indexed[(string) $row['id']] = $row;
    return $indexed;
};
$paths = [
    'pilot_manifest' => 'docs/reports/batch-715-pilot-cohort.json',
    'review_packets' => 'docs/reports/batch-716-pilot-review-packets.json',
    'taxonomy_domains' => 'storage/taxonomy/domains.json',
    'taxonomy_topics' => 'storage/taxonomy/topics.json',
    'taxonomy_concepts' => 'storage/taxonomy/concepts.json',
    'study_materials' => 'storage/study-materials.json',
    'study_lessons' => 'storage/study-lessons.json',
    'study_question_links' => 'storage/study-question-links.json',
];
$data = [];
$hashes = [];
foreach ($paths as $key => $path) {
    $data[$key] = $read($path);
    $hashes[$key] = ['path' => $path, 'sha256' => hash_file('sha256', $root . '/' . $path)];
}
$domains = $byId($data['taxonomy_domains']);
$topics = $byId($data['taxonomy_topics']);
$concepts = $byId($data['taxonomy_concepts']);
$materials = $byId($data['study_materials']);
$lessonsByMaterial = [];
foreach ($data['study_lessons'] as $lesson) {
    if (!is_array($lesson) || !is_scalar($lesson['material_id'] ?? null)) continue;
    $materialId = (string) $lesson['material_id'];
    if (($lesson['status'] ?? '') === 'published' && !isset($lessonsByMaterial[$materialId])) $lessonsByMaterial[$materialId] = $lesson;
}
$packetItems = [];
foreach ((array) ($data['review_packets']['items'] ?? []) as $item) {
    if (is_array($item)) $packetItems[(string) ($item['question_id'] ?? '')] = $item;
}
$topicQuestionGroups = [];
$items = [];
$candidateEdges = 0;
$candidateExactEdges = 0;
$candidateNearEdges = 0;
$topicUnregisteredInstances = 0;
$conceptUnregisteredInstances = 0;
$studyCounts = ['EXACT_SECTION_MATCH' => 0, 'RELATED_MATERIAL_ONLY' => 0, 'MISSING_SECTION' => 0, 'SOURCE_ONLY' => 0, 'NOT_APPLICABLE' => 0, 'OTHER' => 0];

foreach ((array) ($data['pilot_manifest']['items'] ?? []) as $pilot) {
    if (!is_array($pilot)) continue;
    $qid = (string) ($pilot['question_id'] ?? '');
    $packet = $packetItems[$qid] ?? [];
    $taxonomy = (array) ($packet['reviewer_checklist']['taxonomy_mapping'] ?? []);
    $study = (array) ($packet['reviewer_checklist']['study_relationship'] ?? []);
    $topic = (array) ($pilot['topic'] ?? []);
    $concept = (array) ($pilot['concept'] ?? []);
    $domainId = (string) ($pilot['domain']['id'] ?? ($taxonomy['proposed_domain']['id'] ?? ''));
    $topicId = (string) ($topic['id'] ?? '');
    $conceptId = (string) ($concept['id'] ?? '');
    $topicQuestionGroups[$topicId][] = $qid;

    $registeredTopic = $topics[$topicId] ?? null;
    $topicExactNameMatches = [];
    $topicName = (string) ($topic['proposed_name'] ?? $topic['name'] ?? '');
    foreach ($topics as $existing) {
        if ($normalize((string) ($existing['name'] ?? '')) === $normalize($topicName) && $topicName !== '') {
            $topicExactNameMatches[] = ['id' => (string) $existing['id'], 'name' => (string) ($existing['name'] ?? '')];
        }
    }
    $conceptRecord = $concepts[$conceptId] ?? null;
    $domainRecord = $domains[$domainId] ?? null;
    if (!is_array($registeredTopic)) $topicUnregisteredInstances++;
    if (!is_array($conceptRecord)) $conceptUnregisteredInstances++;

    $candidateRecords = [];
    foreach ((array) ($taxonomy['topic_candidates'] ?? []) as $candidate) {
        if (!is_array($candidate)) continue;
        $candidateEdges++;
        $id = (string) ($candidate['id'] ?? '');
        $current = $topics[$id] ?? null;
        $similarity = (float) ($candidate['similarity'] ?? 0);
        $exact = is_array($current) && ($id === $topicId || $normalize((string) ($current['name'] ?? '')) === $normalize($topicName));
        if ($exact) $candidateExactEdges++; else $candidateNearEdges++;
        $candidateRecords[] = [
            'topic_id' => $id,
            'current_name' => is_array($current) ? (string) ($current['name'] ?? '') : null,
            'current_record_exists' => is_array($current),
            'recorded_candidate_name' => (string) ($candidate['name'] ?? ''),
            'recorded_similarity' => $similarity,
            'recorded_status' => (string) ($candidate['status'] ?? 'REVIEW_CANDIDATE_ONLY'),
            'deterministic_recheck' => $exact ? 'EXACT_ID_OR_NORMALIZED_NAME' : (is_array($current) ? 'NONEXACT_RECORDED_CANDIDATE' : 'STALE_CANDIDATE_ID'),
            'decision' => 'SUGGESTION_ONLY_REQUIRES_SCOPED_TAXONOMY_REVIEW',
        ];
    }

    $materialId = (string) ($study['material_id'] ?? '');
    $sectionId = (string) ($study['section_id'] ?? '');
    $material = $materials[$materialId] ?? null;
    $lesson = $lessonsByMaterial[$materialId] ?? null;
    $section = null;
    if (is_array($material) && ($material['status'] ?? 'published') === 'published' && is_array($lesson)) {
        foreach ((array) ($lesson['sections'] ?? []) as $sectionCandidate) {
            if (is_array($sectionCandidate) && (string) ($sectionCandidate['id'] ?? '') === $sectionId) {
                $section = $sectionCandidate;
                break;
            }
        }
    }
    $computedStudyStatus = is_array($section) ? 'EXACT_SECTION_MATCH' : ((string) ($study['status'] ?? 'MISSING_SECTION'));
    if (!isset($studyCounts[$computedStudyStatus])) $computedStudyStatus = 'OTHER';
    $studyCounts[$computedStudyStatus]++;
    $questionLinkExists = false;
    foreach ($data['study_question_links'] as $link) {
        if (is_array($link) && (string) ($link['material_id'] ?? '') === $materialId
            && (string) ($link['question_id'] ?? '') === $qid
            && (string) ($link['section_id'] ?? $link['lesson_section_id'] ?? '') === $sectionId
            && in_array(strtoupper((string) ($link['status'] ?? 'ACTIVE')), ['ACTIVE', 'APPROVED', 'APPROVED_BY_AUTHORIZED_REVIEWER'], true)) {
            $questionLinkExists = true;
            break;
        }
    }

    $items[] = [
        'proposal_id' => (string) ($pilot['proposal_id'] ?? ''),
        'question_id' => $qid,
        'question_version' => (string) ($pilot['question_version'] ?? ''),
        'board' => (array) ($pilot['board'] ?? []),
        'subject' => (array) ($pilot['subject'] ?? []),
        'taxonomy' => [
            'proposed_domain' => ['id' => $domainId, 'name' => is_array($domainRecord) ? (string) ($domainRecord['name'] ?? '') : null, 'registered' => is_array($domainRecord)],
            'proposed_topic' => ['id' => $topicId, 'name' => $topicName, 'registered' => is_array($registeredTopic), 'current_name' => is_array($registeredTopic) ? (string) ($registeredTopic['name'] ?? '') : null, 'exact_normalized_name_matches' => $topicExactNameMatches],
            'proposed_concept' => ['id' => $conceptId, 'name' => (string) ($concept['proposed_name'] ?? $concept['name'] ?? ''), 'registered' => is_array($conceptRecord), 'current_topic_id' => is_array($conceptRecord) ? (string) ($conceptRecord['topic_id'] ?? '') : null, 'topic_relationship_conflict' => is_array($conceptRecord) && (string) ($conceptRecord['topic_id'] ?? '') !== $topicId],
            'existing_topic_candidates' => $candidateRecords,
            'shared_proposed_topic_group' => count($topicQuestionGroups[$topicId]) > 1 ? ['question_ids' => $topicQuestionGroups[$topicId], 'meaning' => 'Same proposed topic ID is reused across proposals; preserve one label and review its applicability per item. This is not an instruction to merge or activate taxonomy.'] : null,
            'review_status' => 'AWAITING_REVIEW',
            'required_action' => 'A scoped TAXONOMY_RECONCILE reviewer must explicitly map to a current valid topic or submit a separate owner-governed taxonomy request. Concepts remain unresolved until a governed decision. No label is activated by this worksheet.',
        ],
        'study_relationship' => [
            'proposed_material_id' => $materialId,
            'material_exists' => is_array($material),
            'material_published' => is_array($material) && ($material['status'] ?? 'published') === 'published',
            'published_lesson_exists' => is_array($lesson),
            'proposed_section_id' => $sectionId,
            'section_heading' => is_array($section) ? (string) ($section['heading'] ?? $section['title'] ?? '') : (string) ($study['section_heading'] ?? ''),
            'current_registry_match' => $computedStudyStatus,
            'current_question_link_exists' => $questionLinkExists,
            'required_for_import' => (bool) ($study['required_for_import'] ?? false),
            'decision' => is_array($section) ? 'EXACT_CANDIDATE_REQUIRES_SCOPED_STUDY_LINK_REVIEW' : 'UNRESOLVED_NO_SECTION_CREATED_OR_LINK_ACTIVATED',
        ],
        'governance_actions' => [
            'taxonomy' => is_array($registeredTopic) && $candidateRecords === [] ? 'Confirm proposed topic is applicable to this item; record explicit mapping. Concept remains a separate governance decision.' : 'Resolve candidate ambiguity or submit a governed taxonomy request; do not auto-map by text similarity.',
            'study' => is_array($section) ? 'Scoped STUDY_LINK reviewer confirms section fit and separately authorizes the link.' : 'Owner/content-governance decision is required to author a real lesson section; otherwise retain relationship unresolved/optional.',
            'academic' => 'Source review and version-bound academic review remain separate and pending.',
        ],
    ];
}

// Recompute shared-topic groups after all pilot IDs have been observed.
foreach ($items as &$item) {
    $group = $topicQuestionGroups[(string) ($item['taxonomy']['proposed_topic']['id'] ?? '')] ?? [];
    $item['taxonomy']['shared_proposed_topic_group'] = count($group) > 1
        ? ['question_ids' => $group, 'meaning' => 'Same proposed topic ID is reused across proposals; preserve one label and review its applicability per item. This is not an instruction to merge or activate taxonomy.']
        : null;
}
unset($item);

$uniqueTopics = [];
$uniqueMissingTopics = [];
$uniqueConcepts = [];
foreach ((array) ($data['pilot_manifest']['items'] ?? []) as $pilot) {
    if (!is_array($pilot)) continue;
    $topicId = (string) ($pilot['topic']['id'] ?? '');
    $conceptId = (string) ($pilot['concept']['id'] ?? '');
    $uniqueTopics[$topicId] = true;
    if (!isset($topics[$topicId])) $uniqueMissingTopics[$topicId] = true;
    $uniqueConcepts[$conceptId] = true;
}
$exactSectionCount = $studyCounts['EXACT_SECTION_MATCH'];
$worksheet = [
    'schema' => 'boardprep.batch-716-taxonomy-study-governance.v1',
    'generated_at_utc' => gmdate(DATE_ATOM),
    'purpose' => 'Read-only, version-bound governance worksheet. Candidate matches are recommendations only; no taxonomy record, lesson, study link, review state, canonical question, or learner record is modified.',
    'provenance' => $hashes,
    'matching_policy' => [
        'topic_registry_identity' => 'Exact taxonomy ID and normalized exact name are checked against the current registry. Existing B715 candidate suggestions are rechecked for current record presence and exactness.',
        'near_match_policy' => 'No new fuzzy candidates are generated in B716. Similarity values are preserved only from the existing B715 deterministic candidate list and remain nonbinding.',
        'study_section_policy' => 'Exact section match is recomputed from current published study material and its published lesson sections; related material is never upgraded to exact support.',
        'activation' => 'NONE',
    ],
    'summary' => [
        'proposal_items' => count($items),
        'unique_proposed_topic_ids' => count($uniqueTopics),
        'topic_items_unregistered' => $topicUnregisteredInstances,
        'unique_unregistered_topic_ids' => count($uniqueMissingTopics),
        'topic_items_with_exact_registered_record' => count($items) - $topicUnregisteredInstances,
        'recorded_topic_candidate_edges' => $candidateEdges,
        'exact_registered_candidate_edges' => $candidateExactEdges,
        'nonexact_candidate_edges' => $candidateNearEdges,
        'unique_proposed_concept_ids' => count($uniqueConcepts),
        'concept_items_unregistered' => $conceptUnregisteredInstances,
        'study_relationships' => $studyCounts,
        'exact_study_section_candidates' => $exactSectionCount,
        'study_link_activations' => 0,
        'taxonomy_activations' => 0,
    ],
    'items' => $items,
    'required_owner_and_reviewer_path' => [
        'taxonomy' => 'Owner confirms taxonomy governance route and delegates only to a reviewer with active exact-board/exact-subject TAXONOMY_RECONCILE scope; reviewer records an explicit item-level mapping or an owner-governed proposal. Unregistered labels stay inactive.',
        'study' => 'A reviewer with active exact-board/exact-subject STUDY_LINK scope verifies the currently published material and exact section. Missing lessons/sections require actual content governance; no placeholder section or link is created.',
        'audit' => 'Keep proposal version, current taxonomy/study snapshot, reviewer identity/grant and explicit decision together. Any relevant snapshot change invalidates downstream approval/import readiness.',
    ],
];
$output = 'docs/reports/batch-716-taxonomy-study-governance.json';
$encoded = json_encode($worksheet, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
if (file_put_contents($root . '/' . $output, $encoded, LOCK_EX) === false) throw new RuntimeException('Cannot write ' . $output);
fwrite(STDOUT, json_encode(['output' => $output, 'summary' => $worksheet['summary']], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
