<?php

declare(strict_types=1);

putenv('APP_ENV=testing');
putenv('APP_DEBUG=false');

require_once dirname(__DIR__) . '/Doctor/Project/BoardPrep/Simulation/HttpSimulator.php';

use Tools\Doctor\Project\BoardPrep\Simulation\HttpSimulator;

$root = dirname(__DIR__, 2);
$inventoryPath = $root . '/docs/reports/batch-713-cohort-disposition.json';
$inventory = json_decode((string) file_get_contents($inventoryPath), true, 512, JSON_THROW_ON_ERROR);
$items = array_values(array_filter($inventory['items'] ?? [], 'is_array'));
$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException('Batch713 academic integration failed: ' . $message);
    }
};
$answerKeyHash = static function (array $questions): string {
    $answers = [];
    foreach ($questions as $question) {
        if (is_array($question) && is_scalar($question['id'] ?? null)) {
            $answers[(string) $question['id']] = $question['answer'] ?? null;
        }
    }
    ksort($answers, SORT_STRING);
    return hash('sha256', json_encode((object) $answers, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
};
$ids = array_map(static fn(array $item): string => (string) ($item['question_id'] ?? ''), $items);
$assert(count($items) === 276 && count(array_unique($ids)) === 276, 'The recovered cohort must contain exactly 276 unique proposal records.');
$assert(($inventory['cohort_summary']['primary_dispositions']['PENDING_ACADEMIC_REVIEW'] ?? 0) === 276, 'All recovered proposals must remain pending independent academic review.');
$assert(($inventory['cohort_summary']['primary_dispositions']['PENDING_SOURCE_EVIDENCE'] ?? -1) === 0, 'Resolved source records must not be misclassified as missing source evidence.');
$assert(($inventory['cohort_summary']['new_question_records_added_to_canonical'] ?? -1) === 0, 'The cohort must not be represented as canonical question additions.');

$questionPath = $root . '/storage/questions.json';
$eligibilityPath = $root . '/storage/question-eligibility.json';
$questionHashBefore = hash_file('sha256', $questionPath);
$answerHashBefore = $answerKeyHash(json_decode((string) file_get_contents($questionPath), true, 512, JSON_THROW_ON_ERROR));
$eligibilityHashBefore = hash_file('sha256', $eligibilityPath);
$canonical = json_decode((string) file_get_contents($questionPath), true, 512, JSON_THROW_ON_ERROR);
$canonicalById = [];
foreach ($canonical as $question) {
    if (is_array($question) && is_scalar($question['id'] ?? null)) {
        $canonicalById[(string) $question['id']] = true;
    }
}
$assert(count($canonicalById) === 9123, 'Current canonical count should remain 9,123 before the HTTP exercise.');
$assert(array_intersect($ids, array_keys($canonicalById)) === [], 'No proposed cohort ID may already exist in the current canonical bank.');

$sourceRows = json_decode((string) file_get_contents($root . '/storage/sources.json'), true, 512, JSON_THROW_ON_ERROR);
$sourceIds = array_fill_keys(array_map(static fn(array $source): string => (string) ($source['id'] ?? ''), array_filter($sourceRows, 'is_array')), true);
$boardIds = array_fill_keys(array_map(static fn(array $row): string => (string) ($row['id'] ?? ''), array_filter(json_decode((string) file_get_contents($root . '/storage/boards.json'), true, 512, JSON_THROW_ON_ERROR), 'is_array')), true);
$subjectIds = array_fill_keys(array_map(static fn(array $row): string => (string) ($row['id'] ?? ''), array_filter(json_decode((string) file_get_contents($root . '/storage/subjects.json'), true, 512, JSON_THROW_ON_ERROR), 'is_array')), true);
$boardSubjectRows = json_decode((string) file_get_contents($root . '/storage/board-subjects.json'), true, 512, JSON_THROW_ON_ERROR);
$boardSubjectPairs = [];
foreach ($boardSubjectRows as $row) {
    if (is_array($row)) $boardSubjectPairs[(string) ($row['board_id'] ?? '') . ':' . (string) ($row['subject_id'] ?? '')] = true;
}
$taxonomyRows = [];
foreach (['domains', 'topics', 'concepts'] as $collection) {
    $taxonomyRows[$collection] = [];
    foreach (json_decode((string) file_get_contents($root . '/storage/taxonomy/' . $collection . '.json'), true, 512, JSON_THROW_ON_ERROR) as $row) {
        if (is_array($row)) $taxonomyRows[$collection][(string) ($row['id'] ?? '')] = $row;
    }
}
foreach ($items as $item) {
    $assert(($item['primary_disposition'] ?? '') === 'PENDING_ACADEMIC_REVIEW', 'A proposal was promoted out of the pending academic-review disposition.');
    $assert(($item['classifier_disposition'] ?? '') === 'REVIEW_REQUIRED', 'The recovered classifier label was not retained for provenance.');
    $assert(in_array('PENDING_ITEM_LEVEL_SOURCE_REVIEW', (array) ($item['secondary_flags'] ?? []), true), 'The overlapping item-level source-review requirement was dropped.');
    $assert(($item['current_status'] ?? '') === 'PENDING_INDEPENDENT_SUBJECT_EXPERT_REVIEW', 'A proposal is missing its pending academic review state.');
    $assert(($item['production_status'] ?? '') === 'NOT_CANONICAL_NOT_IN_LEARNER_POOLS', 'A proposal is marked as production-eligible.');
    $assert(!isset($canonicalById[(string) ($item['question_id'] ?? '')]), 'A proposal ID appears in the canonical bank.');
    $assert(trim((string) ($item['proposed_question']['stem'] ?? '')) !== '', 'A proposal has no recovered question stem.');
    $assert(trim((string) ($item['proposed_question']['proposed_answer'] ?? '')) !== '', 'A proposal has no clearly marked proposed answer.');
    $assert(trim((string) ($item['proposed_question']['explanation'] ?? '')) !== '', 'A proposal has no recovered explanation.');
    $assert(trim((string) ($item['proposal_reason']['specific_claim_to_review'] ?? '')) !== '', 'A proposal has no item-specific review claim.');
    $assert(trim((string) ($item['provenance']['original_source_commit_sha'] ?? '')) === (string) ($inventory['source']['commit_sha'] ?? ''), 'Proposal provenance does not point to the recorded B695 source commit.');
    $boardId = (string) ($item['board_id'] ?? '');
    $subjectId = (string) ($item['subject_id'] ?? '');
    $domainId = (string) ($item['domain_id'] ?? '');
    $topicId = (string) ($item['topic_id'] ?? '');
    $conceptId = (string) ($item['concept_id'] ?? '');
    $comparison = (array) ($item['current_comparison'] ?? []);
    $assert(isset($boardIds[$boardId]) && isset($subjectIds[$subjectId]) && isset($boardSubjectPairs[$boardId . ':' . $subjectId]), 'Proposal board and subject do not match an existing current eligibility relation.');
    $domainMatches = isset($taxonomyRows['domains'][$domainId]) && (string) ($taxonomyRows['domains'][$domainId]['subject_id'] ?? '') === $subjectId;
    $assert($domainMatches, 'Proposal domain is absent or does not belong to its current subject.');
    $currentTopicMatches = isset($taxonomyRows['topics'][$topicId]) && (string) ($taxonomyRows['topics'][$topicId]['domain_id'] ?? '') === $domainId;
    $currentConceptMatches = isset($taxonomyRows['concepts'][$conceptId]) && (string) ($taxonomyRows['concepts'][$conceptId]['topic_id'] ?? '') === $topicId;
    $assert(($comparison['current_board_exists'] ?? false) === isset($boardIds[$boardId])
        && ($comparison['current_subject_exists'] ?? false) === isset($subjectIds[$subjectId])
        && ($comparison['current_board_subject_mapping_valid'] ?? false) === true
        && ($comparison['current_domain_exists'] ?? false) === isset($taxonomyRows['domains'][$domainId])
        && ($comparison['current_domain_matches_subject'] ?? false) === $domainMatches
        && ($comparison['current_topic_exists'] ?? false) === isset($taxonomyRows['topics'][$topicId])
        && ($comparison['current_topic_matches_domain'] ?? false) === $currentTopicMatches
        && ($comparison['current_concept_exists'] ?? false) === isset($taxonomyRows['concepts'][$conceptId])
        && ($comparison['current_concept_matches_topic'] ?? false) === $currentConceptMatches,
        'Per-item current taxonomy reconciliation is inconsistent with the active taxonomy files.');
    $evidence = array_values(array_filter((array) ($item['source_evidence'] ?? []), 'is_array'));
    $assert($evidence !== [], 'A proposal has no recovered source evidence.');
    foreach ($evidence as $record) {
        $sourceId = (string) ($record['source_id'] ?? '');
        $assert($sourceId !== '' && isset($sourceIds[$sourceId]), 'A cited source is not resolvable in the current source registry: ' . $sourceId);
        $assert(is_array($record['source_record_snapshot'] ?? null), 'A source citation lost its B695 evidence snapshot.');
    }
}
$taxonomySummary = (array) ($inventory['cohort_summary']['taxonomy_reconciliation'] ?? []);
$assert(($taxonomySummary['board_subject_domain_relationship_valid'] ?? 0) === 276
    && ($taxonomySummary['current_topic_relationship_valid'] ?? 0) === 6
    && ($taxonomySummary['unregistered_topic_items'] ?? 0) === 270
    && ($taxonomySummary['unregistered_concept_items'] ?? 0) === 276,
    'Current taxonomy reconciliation counts do not match per-item mappings.');

$eligibilityRows = json_decode((string) file_get_contents($eligibilityPath), true, 512, JSON_THROW_ON_ERROR);
foreach ($eligibilityRows as $row) {
    if (is_array($row)) {
        $assert(array_intersect($ids, array_map('strval', (array) ($row['question_ids'] ?? []))) === [], 'A pending proposal ID entered a current board eligibility mapping.');
    }
}

$http = new HttpSimulator($root . '/public/index.php');
$cookies = ['PHPSESSID' => 'batch713-academic-' . bin2hex(random_bytes(8))];
$request = static function (string $path, array $query = []) use ($http, $cookies): array {
    $response = $http->request('GET', $path, $query, [], [], $cookies);
    if (!$response['success'] || $response['status'] !== 200) {
        throw new RuntimeException($path . ' failed: HTTP ' . $response['status'] . ', exit ' . $response['exitCode'] . ', ' . substr((string) $response['stderr'], -700));
    }
    return $response;
};
$renderedProposalIds = static function (string $html): array {
    preg_match_all('/<article class="card proposal-item" data-proposal-id="([^"]+)"/u', $html, $matches);
    return $matches[1] ?? [];
};

$page1 = $request('/question-editor', ['proposal_batch' => '695', 'page_num' => '1']);
$page2 = $request('/question-editor', ['proposal_batch' => '695', 'page_num' => '2']);
$page1Ids = $renderedProposalIds($page1['output']);
$page2Ids = $renderedProposalIds($page2['output']);
$assert(count($page1Ids) === 50 && count($page2Ids) === 50, 'Proposal pages must render the expected 50-item page size.');
$assert(count(array_unique(array_merge($page1Ids, $page2Ids))) === 100, 'Adjacent proposal pages must not duplicate items.');
$assert(str_contains($page1['output'], '276 proposals') && str_contains($page1['output'], 'pending independent review'), 'The proposal page must clearly report cohort size and pending status.');
$assert(str_contains($page1['output'], 'Not canonical') && str_contains($page1['output'], 'Not selectable in learner pools'), 'Proposal cards must disclose their non-production status.');

$board = $request('/question-editor', ['proposal_batch' => '695', 'board' => 'architecture']);
$assert(count($renderedProposalIds($board['output'])) === 4 && str_contains($board['output'], '4 proposals'), 'Board filtering must return the four Architecture proposals.');
$subject = $request('/question-editor', ['proposal_batch' => '695', 'subject' => 'early-childhood-education']);
$assert(count($renderedProposalIds($subject['output'])) === 50 && str_contains($subject['output'], '58 proposals'), 'Subject filtering must return the first 50 of 58 matching proposals.');
$search = $request('/question-editor', ['proposal_batch' => '695', 'search' => '9124']);
$assert(count($renderedProposalIds($search['output'])) === 1 && str_contains($search['output'], '1 proposals'), 'Exact proposal ID search must find one item.');

$first = $items[0];
$firstId = (string) ($first['question_id'] ?? '');
$detail = $request('/question-inspector', ['proposal_id' => 'batch695-' . $firstId, 'return' => '/question-editor?proposal_batch=695']);
$candidateStem = (string) ($first['proposed_question']['stem'] ?? '');
$assert(str_contains($detail['output'], 'Pending proposal — not an approved question'), 'Proposal detail must show that the item remains pending.');
$assert(str_contains($detail['output'], 'Proposed answer (not a canonical answer key)'), 'The proposed answer must not be presented as an approved key.');
$assert(str_contains($detail['output'], htmlspecialchars($candidateStem, ENT_QUOTES, 'UTF-8')), 'Proposal detail must render the recovered question text with HTML escaping.');
$assert(str_contains($detail['output'], 'Specific claim to review') && str_contains($detail['output'], 'Item-level source locator'), 'Proposal detail must expose the claim and evidence locator.');
$assert(str_contains($detail['output'], 'Current taxonomy check:')
    && str_contains($detail['output'], 'board and subject mapping valid')
    && str_contains($detail['output'], 'topic not registered')
    && str_contains($detail['output'], 'concept not registered'), 'Proposal detail must distinguish valid current board/subject mapping from inactive topic/concept proposals.');
$assert(!str_contains($detail['output'], '/question-editor/edit?id=' . $firstId) && !str_contains($detail['output'], 'Stored answer key'), 'A pending proposal must not expose canonical edit or answer-key controls.');

$limitedSourceItem = null;
$historicalItem = null;
$existingSectionItem = null;
$missingSectionItem = null;
foreach ($items as $item) {
    if ($limitedSourceItem === null && str_contains(implode(' ', array_map('strval', array_merge(...array_map(static fn(array $source): array => (array) ($source['limitations'] ?? []), (array) ($item['source_evidence'] ?? []))))), 'scanned')) {
        $limitedSourceItem = $item;
    }
    if ($historicalItem === null && is_array($item['currentness_evidence'] ?? null)) $historicalItem = $item;
    if ($existingSectionItem === null && !empty($item['study_relationship']['current_section_exists'])) $existingSectionItem = $item;
    if ($missingSectionItem === null && empty($item['study_relationship']['current_section_exists'])) $missingSectionItem = $item;
}
$assert(is_array($limitedSourceItem) && is_array($historicalItem) && is_array($existingSectionItem) && is_array($missingSectionItem), 'Expected source-limit, currentness, and study-section proposal fixtures are missing.');
$limitedDetail = $request('/question-inspector', ['proposal_id' => (string) $limitedSourceItem['id']]);
$assert(str_contains($limitedDetail['output'], 'annex is scanned'), 'Source limitations must be shown to a reviewer.');
$historicalDetail = $request('/question-inspector', ['proposal_id' => (string) $historicalItem['id']]);
$assert(str_contains($historicalDetail['output'], 'historical') && str_contains($historicalDetail['output'], 'Open official PRC calendar'), 'Historical PRC source status and its official currentness reference must be visible.');
$existingSectionDetail = $request('/question-inspector', ['proposal_id' => (string) $existingSectionItem['id']]);
$missingSectionDetail = $request('/question-inspector', ['proposal_id' => (string) $missingSectionItem['id']]);
$assert(str_contains($existingSectionDetail['output'], 'section exists; the question link remains proposal-only')
    && preg_match('/href="(\/study-library\?material=[^"]+#boardprep-[^"]+)"[^>]*>Open exact current study section/su', $existingSectionDetail['output'], $studyLinkMatch) === 1,
    'An existing exact study section must be directly reviewable without creating a production question link.');
$assert(str_contains($missingSectionDetail['output'], 'absent from the current library')
    && !str_contains($missingSectionDetail['output'], 'Open exact current study section'), 'A missing study section must be disclosed without fabricating a live link.');
$studyLinkParts = parse_url(html_entity_decode((string) ($studyLinkMatch[1] ?? ''), ENT_QUOTES, 'UTF-8'));
$studyQuery = [];
parse_str((string) ($studyLinkParts['query'] ?? ''), $studyQuery);
$studyDetail = $request((string) ($studyLinkParts['path'] ?? '/study-library'), $studyQuery);
$studyAnchor = rawurldecode((string) ($studyLinkParts['fragment'] ?? ''));
$assert($studyAnchor !== '' && str_contains($studyDetail['output'], 'id="' . htmlspecialchars($studyAnchor, ENT_QUOTES, 'UTF-8') . '"'), 'The proposal link must resolve to the recorded exact section in the current study library.');

$canonicalPage = $request('/question-editor');
$assert(preg_match('/Canonical questions in repository:\s*<\/strong>\s*9123/su', $canonicalPage['output']) === 1
    && str_contains($canonicalPage['output'], '276 Batch 695 proposals remain pending'), 'The existing canonical inventory must remain intact and link to the separate proposal queue.');
$simulatorQuestions = json_decode((string) file_get_contents($http->storagePath() . '/questions.json'), true, 512, JSON_THROW_ON_ERROR);
$simulatorById = [];
foreach ($simulatorQuestions as $question) {
    if (is_array($question) && is_scalar($question['id'] ?? null)) $simulatorById[(string) $question['id']] = true;
}
$assert(count($simulatorById) === 9123 && array_intersect($ids, array_keys($simulatorById)) === [], 'The isolated production-path HTTP fixture must retain 9,123 canonical questions and no proposal IDs.');
$simulatorEligibility = json_decode((string) file_get_contents($http->storagePath() . '/question-eligibility.json'), true, 512, JSON_THROW_ON_ERROR);
foreach ($simulatorEligibility as $row) {
    if (is_array($row)) {
        $assert(array_intersect($ids, array_map('strval', (array) ($row['question_ids'] ?? []))) === [], 'The isolated production path placed a proposal into learner eligibility.');
    }
}

$questionHashAfter = hash_file('sha256', $questionPath);
$answerHashAfter = $answerKeyHash(json_decode((string) file_get_contents($questionPath), true, 512, JSON_THROW_ON_ERROR));
$eligibilityHashAfter = hash_file('sha256', $eligibilityPath);
$assert($questionHashBefore === $questionHashAfter && $answerHashBefore === $answerHashAfter, 'The HTTP exercise changed current canonical questions or answer keys.');
$assert($eligibilityHashBefore === $eligibilityHashAfter, 'The HTTP exercise changed canonical board eligibility.');
$assert($questionHashBefore === ($inventory['current_baseline']['question_sha256'] ?? null), 'Canonical question bytes no longer match the recovered baseline.');
$assert($answerHashBefore === ($inventory['current_baseline']['answer_key_sha256'] ?? null), 'Canonical answer keys no longer match the recovered baseline.');

echo '[PASS] Batch 695 cohort inventory and developer review routes preserve proposal-only status, source evidence, eligibility, and canonical data.' . PHP_EOL;
echo 'batch713_measurement=' . json_encode([
    'cohort_items' => count($items),
    'canonical_questions' => count($canonicalById),
    'question_sha256' => $questionHashAfter,
    'answer_key_sha256' => $answerHashAfter,
    'eligibility_sha256' => $eligibilityHashAfter,
    'isolated_fixture' => $http->storagePath(),
    'http_routes' => 12,
    'peak_php_memory_bytes' => memory_get_peak_usage(true),
], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
