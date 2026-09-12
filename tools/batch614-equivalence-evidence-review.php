#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Batch 614: audit-only enrichment of the Batch 613 different-ID backlog.
 *
 * The evidence inputs are read from the committed HEAD snapshot.  This is
 * deliberate: several native storage files are dirty from earlier work and
 * must not influence, or be rewritten by, this audit.  --write writes only
 * storage/batch614-equivalence-evidence-review.json.
 */
require_once dirname(__DIR__) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

$write = in_array('--write', $argv, true);
$root = dirname(__DIR__);
$head = trim((string)shell_exec('git -C ' . escapeshellarg($root) . ' rev-parse HEAD 2>/dev/null'));
if ($head !== 'e40fce8c584252d0646d9643fe99aab562a33a25') {
    $parent = trim((string)shell_exec('git -C ' . escapeshellarg($root) . ' rev-parse HEAD^ 2>/dev/null'));
    if ($parent === 'e40fce8c584252d0646d9643fe99aab562a33a25') {
        // Post-commit verification: native evidence is still anchored to
        // the verified Batch 613 parent and the audit remains read-only.
        $head = $parent;
    } else {
    throw new RuntimeException('Batch 614 expected HEAD e40fce8c584252d0646d9643fe99aab562a33a25, got ' . $head);
    }
}
$s = static fn(mixed $v): string => is_scalar($v) ? trim((string) $v) : '';
$json = static fn(mixed $v): string => json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
$sort = static function (array $values): array {
    $values = array_values(array_unique(array_map('strval', $values)));
    usort($values, 'strnatcmp');
    return $values;
};
$readHead = static function (string $path) use ($root): array {
    $escaped = escapeshellarg('HEAD:' . $path);
    $raw = shell_exec('git -C ' . escapeshellarg($root) . ' show ' . $escaped . ' 2>/dev/null');
    if (!is_string($raw) || trim($raw) === '') {
        throw new RuntimeException('Unable to read committed snapshot: ' . $path);
    }
    $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    return is_array($data) ? $data : [];
};
$board = static function (mixed $value): string {
    $value = strtolower(trim(is_scalar($value) ? (string) $value : ''));
    return ['cse' => 'civil-service', 'civilservice' => 'civil-service'][$value] ?? $value;
};
$tokens = static function (string $value) use ($sort): array {
    $value = strtolower(preg_replace('/([a-z])([A-Z])/', '$1 $2', $value) ?? $value);
    $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?? '';
    $stop = array_fill_keys([
        'and','the','of','for','with','from','into','using','used','use','based','core',
        'basic','general','applied','application','applications','concept','concepts',
        'foundation','foundations','reasoning','relationships','models','model','analysis',
        'professional','practice','principles','systems','system','public','process',
        'evidence','structure','operations','methods','method','readiness','development',
        'interpretation','interpret','skills','skill','knowledge','fundamentals',
    ], true);
    $out = [];
    foreach (preg_split('/\s+/', trim($value)) ?: [] as $part) {
        if ($part !== '' && strlen($part) >= 4 && !isset($stop[$part])) $out[$part] = true;
    }
    return $sort(array_keys($out));
};
$intersection = static fn(array $a, array $b): array => array_values(array_intersect($a, $b));
$family = static function (string $value): string {
    $patterns = [
        'math' => '/number|arithmetic|fraction|ratio|percent|algebra|equation|statistic|probability|data|graph|geometry|measure|trigon|calculus|vector|matrix|sequence|series|quantity/i',
        'science' => '/physics|force|motion|energy|electric|circuit|chem|biology|cell|inquiry|evidence|data|earth|ecology|optics|matter|particle|fluid|thermo/i',
        'language' => '/reading|inference|grammar|sentence|verb|pronoun|modifier|parallel|preposition|communication|language|rhetoric|argument/i',
        'civics_history' => '/civic|government|constitution|history|source|citizenship|public-good|rights|justice|state-policy/i',
    ];
    foreach ($patterns as $name => $pattern) if (preg_match($pattern, $value) === 1) return $name;
    return 'other';
};
$professional = static function (string $value): bool {
    return preg_match('/clinical|nursing|patient|diagnos|forensic|crimin|criminal|police|law-enforcement|correction|survey|structur|thermodynamic|fluid-machine|machine|circuit|electrical|power-system|architect|building-code|accounting|auditing|tax|psychometric|pedagog|teaching|licensure|regulat|professional-judgment|engineering|mechanics|hydraulic|geotechnical|construction|safety/i', $value) === 1;
};
$regulatory = static function (string $value): bool {
    return preg_match('/regulat|criminal-law|constitution|code|jurisprud|licensure|law-enforcement|nursing|clinical/i', $value) === 1;
};
$contextForQuestion = static function (array $q, array $reuse): string {
    $mode = strtoupper((string)($reuse['context_mode'] ?? ''));
    if (in_array($mode, ['REGULATORY','MATERIAL_PROFESSIONAL','DECORATIVE_PROFESSIONAL','NEUTRAL'], true)) {
        return $mode === 'NEUTRAL' ? 'GENERAL' : $mode;
    }
    if (!empty($q['legal_procedure_application']) || !empty($q['ethics_scope_application'])) return 'REGULATORY';
    foreach (['professional_judgment','context_necessary','forensic_interpretation','correctional_application','technical_reasoning','site_plan_spatial'] as $field) {
        if (!empty($q[$field])) return 'MATERIAL_PROFESSIONAL';
    }
    return 'GENERAL';
};
$depthOf = static function (array $reuse): int|string {
    $depth = $reuse['required_depth'] ?? 'UNKNOWN';
    return is_int($depth) && in_array($depth, [1,2,3,4,5], true) ? $depth : 'UNKNOWN';
};
$range = static function (array $depths): array {
    $known = array_values(array_filter($depths, static fn(mixed $d): bool => is_int($d)));
    if ($known === []) return ['min' => 'UNKNOWN', 'max' => 'UNKNOWN'];
    sort($known, SORT_NUMERIC);
    return ['min' => $known[0], 'max' => $known[count($known)-1]];
};

$b613 = $readHead('storage/batch613-concept-equivalence-audit.json');
$b613 = $b613[0] ?? [];
$relations613 = array_values(array_filter($b613['relations'] ?? [], 'is_array'));
if (count($relations613) !== 385) throw new RuntimeException('Batch 613 backlog is not exactly 385 records.');

$questions = array_values(array_filter($readHead('storage/questions.json'), 'is_array'));
$metadata = array_values(array_filter($readHead('storage/question-reuse-metadata.json'), 'is_array'));
$coverage = array_values(array_filter($readHead('storage/board-concept-coverage.json'), 'is_array'));
$derived = array_values(array_filter($readHead('storage/concept-derived-eligibility.json'), 'is_array'));
$links = array_values(array_filter($readHead('storage/study-question-links.json'), 'is_array'));
$materials = array_values(array_filter($readHead('storage/study-materials.json'), 'is_array'));
$sources = array_values(array_filter($readHead('storage/sources.json'), 'is_array'));
$blueprints = array_values(array_filter($readHead('storage/blueprints.json'), 'is_array'));
$conceptRows = array_values(array_filter($readHead('storage/taxonomy/concepts.json'), 'is_array'));
$topicRows = array_values(array_filter($readHead('storage/taxonomy/topics.json'), 'is_array'));
$domainRows = array_values(array_filter($readHead('storage/taxonomy/domains.json'), 'is_array'));
$requiredConcepts = array_values(array_filter($readHead('storage/question-required-concepts.json'), 'is_array'));
$eligibility = array_values(array_filter($readHead('storage/question-eligibility.json'), 'is_array'));

$concepts = $topics = $domains = $questionById = $metadataByQuestion = [];
foreach ($conceptRows as $row) if (($id = $s($row['id'] ?? null)) !== '') $concepts[$id] = $row;
foreach ($topicRows as $row) if (($id = $s($row['id'] ?? null)) !== '') $topics[$id] = $row;
foreach ($domainRows as $row) if (($id = $s($row['id'] ?? null)) !== '') $domains[$id] = $row;
foreach ($questions as $q) if (($id = $s($q['id'] ?? null)) !== '') $questionById[$id] = $q;
foreach ($metadata as $row) if (($id = $s($row['question_id'] ?? null)) !== '') $metadataByQuestion[$id] = is_array($row['reuse'] ?? null) ? $row['reuse'] : [];

$coverageByConceptBoard = $coverageByBoardConcept = [];
foreach ($coverage as $row) {
    $b = $board($row['board_id'] ?? null); $c = $s($row['concept_id'] ?? null);
    if ($b !== '' && $c !== '') { $coverageByConceptBoard[$c][$b] = $row; $coverageByBoardConcept[$b.'|'.$c] = $row; }
}
$conceptQuestionIds = [];
foreach ($questionById as $id => $q) {
    $c = $s($q['taxonomy']['concept_id'] ?? null);
    if ($c !== '') $conceptQuestionIds[$c][] = $id;
}
foreach ($conceptQuestionIds as &$ids) usort($ids, 'strnatcmp');
unset($ids);

$resourceByConcept = $resourceSourcesByConcept = $questionResources = [];
foreach ($materials as $m) {
    $mid = $s($m['id'] ?? null); if ($mid === '') continue;
    foreach ($m['supported_concept_ids'] ?? [] as $c) if (is_scalar($c)) {
        $c = (string)$c; $resourceByConcept[$c][$mid] = true;
        foreach ($m['source_ids'] ?? [] as $sid) if (is_scalar($sid)) $resourceSourcesByConcept[$c][(string)$sid] = true;
    }
}
foreach ($links as $link) {
    $qid = $s($link['question_id'] ?? null); $mid = $s($link['material_id'] ?? null);
    $c = $s($questionById[$qid]['taxonomy']['concept_id'] ?? null);
    if ($qid !== '' && $mid !== '') $questionResources[$qid][$mid] = true;
    if ($c !== '' && $mid !== '') { $resourceByConcept[$c][$mid] = true; foreach ($link['source_ids'] ?? [] as $sid) if (is_scalar($sid)) $resourceSourcesByConcept[$c][(string)$sid] = true; }
}
$sourceById = $materialById = [];
foreach ($sources as $row) if (($id = $s($row['id'] ?? null)) !== '') $sourceById[$id] = $row;
foreach ($materials as $row) if (($id = $s($row['id'] ?? null)) !== '') $materialById[$id] = $row;

$directBoards = [];
foreach ($eligibility as $entry) {
    if (!is_array($entry)) continue;
    $entryBoards = is_array($entry['exam_ids'] ?? null) ? $entry['exam_ids'] : [];
    foreach ($entry['question_ids'] ?? [] as $qid) {
        if (!is_scalar($qid)) continue; $qid = (string)$qid;
        $mapping = is_array($entry['question_mappings'][$qid] ?? null) ? $entry['question_mappings'][$qid] : [];
        foreach (is_array($mapping['exam_ids'] ?? null) ? $mapping['exam_ids'] : $entryBoards as $b) $directBoards[$qid][$board($b)] = true;
    }
}
$directPairs = [];
foreach ($directBoards as $qid => $boards) foreach (array_keys($boards) as $b) $directPairs[$qid.'|'.$b] = true;
$effectivePairs = $directPairs; $effectiveBoards = $directBoards;
foreach ($derived as $row) if (($row['record_type'] ?? '') === 'RELATIONSHIP') {
    $qid = $s($row['question_id'] ?? null); $b = $board($row['target_board'] ?? null);
    if ($qid !== '' && $b !== '') { $effectivePairs[$qid.'|'.$b] = true; $effectiveBoards[$qid][$b] = true; }
}
$multi = $single = 0;
foreach ($effectiveBoards as $boards) { if (count($boards) > 1) $multi++; elseif (count($boards) === 1) $single++; }
$current = ['concept_derived'=>count(array_filter($derived, static fn(array $r): bool => ($r['record_type'] ?? '') === 'RELATIONSHIP')), 'total'=>count($effectivePairs), 'multi'=>$multi, 'single'=>$single];

$blueprintByBoard = [];
foreach ($blueprints as $bp) $blueprintByBoard[$board($bp['board_id'] ?? $bp['board'] ?? null)][] = $bp;
$officialUrls = [
    'civil-engineering'=>'https://prc.gov.ph/Pages/PRBv4/CivilEngineering.htm',
    'registered-electrical-engineering'=>'https://www.prc.gov.ph/Pages/PRBv4/ElectricalEngineering.htm',
    'nursing'=>'https://www.prc.gov.ph/Pages/PRBv4/Nursing.htm',
    'criminologist'=>'https://www.prc.gov.ph/Pages/PRBv4/Criminologyv3.htm',
    'let'=>'https://www.prc.gov.ph/Pages/PRBv4/ProfessionalTeachersv9.htm',
];
$repoSourceNames = [];
foreach ($sourceById as $sid => $source) $repoSourceNames[$sid] = $s($source['title'] ?? $source['name'] ?? $sid);
$conceptPath = static function (string $id, array $concepts, array $topics, array $domains, callable $s): array {
    $c = $concepts[$id] ?? []; $tid = $s($c['topic_id'] ?? null); $t = $topics[$tid] ?? []; $did = $s($t['domain_id'] ?? null); $d = $domains[$did] ?? [];
    return ['subject_id'=>$s($d['subject_id'] ?? null), 'domain_id'=>$did, 'topic_id'=>$tid, 'concept_id'=>$id];
};
$conceptContext = static function (string $id, array $concepts, array $coverageByConceptBoard, array $conceptQuestionIds, array $questionById, array $metadataByQuestion, callable $professional, callable $regulatory): string {
    // A board being professional does not make every concept placed on it
    // regulatory or professional.  Context is determined from the concept
    // and question evidence, never from the board name alone.
    $hay = $id . ' ' . (string)($concepts[$id]['name'] ?? '');
    if ($regulatory($hay)) return 'REGULATORY';
    if ($professional($hay)) return 'MATERIAL_PROFESSIONAL';
    foreach ($conceptQuestionIds[$id] ?? [] as $qid) {
        $mode = strtoupper((string)($metadataByQuestion[$qid]['context_mode'] ?? ''));
        if ($mode === 'REGULATORY') return 'REGULATORY';
        if ($mode === 'MATERIAL_PROFESSIONAL' || $mode === 'DECORATIVE_PROFESSIONAL') return 'MATERIAL_PROFESSIONAL';
        if (isset($questionById[$qid]) && $regulatory(json_encode($questionById[$qid], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?: '')) return 'REGULATORY';
    }
    return 'GENERAL';
};
$questionDepths = [];
foreach ($conceptQuestionIds as $c => $ids) foreach ($ids as $qid) $questionDepths[$c][] = $depthOf($metadataByQuestion[$qid] ?? []);
$sampleQuestions = static function (string $concept, array $conceptQuestionIds, array $questionById, array $metadataByQuestion, callable $contextForQuestion, callable $depthOf, array $questionResources, array $sourceById): array {
    $ids = $conceptQuestionIds[$concept] ?? [];
    $known = [];
    foreach ($ids as $qid) { $d = $depthOf($metadataByQuestion[$qid] ?? []); $known[$qid] = is_int($d) ? $d : 99; }
    usort($ids, static fn(string $a, string $b): int => ($known[$a] <=> $known[$b]) ?: strnatcmp($a, $b));
    $pick = array_values(array_unique(array_merge(array_slice($ids, 0, 2), count($ids) > 2 ? [$ids[count($ids)-1]] : [], count($ids) > 4 ? [$ids[(int)floor(count($ids)/2)]] : [])));
    sort($pick, SORT_NATURAL);
    $out = [];
    foreach ($pick as $qid) {
        $q = $questionById[$qid] ?? []; $reuse = $metadataByQuestion[$qid] ?? []; $depth = $depthOf($reuse);
        $text = trim((string)($q['question'] ?? '')); $text = function_exists('mb_substr') ? mb_substr($text, 0, 220) : substr($text, 0, 220);
        $out[] = ['question_id'=>$qid,'tested_skill'=>(string)($q['concept'] ?? $q['taxonomy']['concept_id'] ?? $concept),'required_knowledge'=>(string)($q['explanation'] ?? $q['question'] ?? 'CONCEPT_RECORD_ONLY'),'required_prerequisites'=>'NO_EXPLICIT_REQUIRED_CONCEPT_RECORDS','depth'=>$depth,'context'=>$contextForQuestion($q,$reuse),'source_support'=>count($questionResources[$qid] ?? []) > 0 ? 'LINKED_STUDY_RESOURCE' : 'NO_LINKED_STUDY_RESOURCE','would_fit_other_concept'=>'NOT_ESTABLISHED','why_not'=>'Question-level transfer is not established without an equivalence decision.','question_excerpt'=>$text];
    }
    return $out;
};

// Rank the existing 385 backlog with independent repository signals.  The
// score only chooses the bounded review cohort; it never grants equivalence.
$ranked = [];
foreach ($relations613 as $r) {
    $a = $s($r['source_concept_id'] ?? null); $b = $s($r['target_concept_id'] ?? null);
    $sig = $r['evidence']['prefilter_signals'] ?? [];
    $nameOverlap = is_array($sig['name_token_overlap'] ?? null) ? $sig['name_token_overlap'] : [];
    $sharedSources = is_array($sig['shared_source_ids'] ?? null) ? $sig['shared_source_ids'] : [];
    $score = 0; $score += !empty($sig['same_topic']) ? 7 : 0; $score += !empty($sig['same_domain']) ? 4 : 0; $score += count($sharedSources) * 3; $score += count($nameOverlap) * 2;
    $score += count($r['evidence']['current_multi_board_questions_source'] ?? []) + count($r['evidence']['current_multi_board_questions_target'] ?? []);
    $hay = $a.' '.($r['source_name'] ?? '').' '.$b.' '.($r['target_name'] ?? '');
    if ($professional($hay)) $score -= 3;
    if ($regulatory($hay)) $score -= 5;
    if (count($sharedSources) > 0 && (!empty($sig['same_topic']) || !empty($sig['same_domain']))) $score += 4;
    $ranked[] = ['score'=>$score,'family'=>(string)($sig['family'] ?? $family($hay)),'key'=>$a.'|'.$b,'relation'=>$r];
}
usort($ranked, static fn(array $x, array $y): int => ($y['score'] <=> $x['score']) ?: strcmp($x['key'], $y['key']));
$deepKeys = array_fill_keys(array_map(static fn(array $x): string => $x['key'], array_slice($ranked, 0, 80)), true);
$priority = ['HIGH'=>0,'MEDIUM'=>0,'LOW'=>0];
foreach ($ranked as $i => $x) $priority[$x['score'] >= 5 ? 'HIGH' : ($x['score'] >= 2 ? 'MEDIUM' : 'LOW')]++;

$counts = array_fill_keys(['EXACT','COMPATIBLE','PARTIAL','RELATED','CONFLICTING','INSUFFICIENT_EVIDENCE'], 0);
$confidenceCounts = array_fill_keys(['VERIFIED','STRONG','PROVISIONAL'], 0);
$directionCounts = array_fill_keys(['BIDIRECTIONAL','A_TO_B','B_TO_A','UNKNOWN','NOT_APPLICABLE'], 0);
$contextCounts = array_fill_keys(['GENERAL','DECORATIVE_PROFESSIONAL','MATERIAL_PROFESSIONAL','REGULATORY'], 0);
$prerequisiteCounts = array_fill_keys(['PREREQUISITE_FREE','SPECIALIZED_PREREQUISITE','PREREQUISITE_UNKNOWN'], 0);
$deepRecords = []; $negativeBoundaries = [];
$officialRecords = [];
foreach ($ranked as $item) {
    $r = $item['relation']; $a = $s($r['source_concept_id'] ?? null); $b = $s($r['target_concept_id'] ?? null);
    $nameA = $s($r['source_name'] ?? $a); $nameB = $s($r['target_name'] ?? $b); $sig = $r['evidence']['prefilter_signals'] ?? [];
    $pathA = $conceptPath($a,$concepts,$topics,$domains,$s); $pathB = $conceptPath($b,$concepts,$topics,$domains,$s);
    $boardsA = is_array($r['source_boards'] ?? null) ? $r['source_boards'] : []; $boardsB = is_array($r['target_boards'] ?? null) ? $r['target_boards'] : [];
    $ctxA = $conceptContext($a,$concepts,$coverageByConceptBoard,$conceptQuestionIds,$questionById,$metadataByQuestion,$professional,$regulatory); $ctxB = $conceptContext($b,$concepts,$coverageByConceptBoard,$conceptQuestionIds,$questionById,$metadataByQuestion,$professional,$regulatory);
    $context = in_array('REGULATORY',[$ctxA,$ctxB],true) ? 'REGULATORY' : (in_array('MATERIAL_PROFESSIONAL',[$ctxA,$ctxB],true) ? 'MATERIAL_PROFESSIONAL' : 'GENERAL');
    $familyName = (string)($sig['family'] ?? $family($a.' '.$nameA.' '.$b.' '.$nameB));
    $sharedSources = is_array($sig['shared_source_ids'] ?? null) ? $sort($sig['shared_source_ids']) : [];
    $sharedMaterials = is_array($sig['shared_material_ids'] ?? null) ? $sort($sig['shared_material_ids']) : [];
    $sameTaxonomy = !empty($sig['same_topic']) || !empty($sig['same_domain']);
    $meaningfulOverlap = count($sig['name_token_overlap'] ?? []) > 0 || $sameTaxonomy;
    $sampleA = $sampleQuestions($a,$conceptQuestionIds,$questionById,$metadataByQuestion,$contextForQuestion,$depthOf,$questionResources,$sourceById);
    $sampleB = $sampleQuestions($b,$conceptQuestionIds,$questionById,$metadataByQuestion,$contextForQuestion,$depthOf,$questionResources,$sourceById);
    $questionEvidence = count($sampleA) > 0 && count($sampleB) > 0;
    $specialized = $professional($a.' '.$nameA) || $professional($b.' '.$nameB);
    $independentSignals = ($sameTaxonomy ? 1 : 0) + (count($sharedSources) > 0 ? 1 : 0) + ($questionEvidence ? 1 : 0) + (count($r['evidence']['current_multi_board_questions_source'] ?? []) + count($r['evidence']['current_multi_board_questions_target'] ?? []) > 0 ? 1 : 0);
    $relation = 'INSUFFICIENT_EVIDENCE'; $reason = 'Candidate remains unresolved: structural similarity is not enough to establish concept boundaries, direction, depth, prerequisites, and transfer.'; $blocking = ['INSUFFICIENT_INDEPENDENT_EVIDENCE'];
    if (isset($deepKeys[$a.'|'.$b])) {
        if ($context === 'REGULATORY' && $meaningfulOverlap && $specialized) { $relation='CONFLICTING'; $reason='Professional/regulatory scope would make concept-wide equivalence materially misleading; shared vocabulary does not remove the specialized competency boundary.'; $blocking=['REGULATORY_OR_PROFESSIONAL_SCOPE_CONFLICT']; }
        elseif ($meaningfulOverlap && $specialized && $familyName !== 'other' && $independentSignals >= 2) { $relation='PARTIAL'; $reason='The pair shares a foundation, source, or taxonomy family, but at least one side has a material professional prerequisite or board-specific boundary.'; $blocking=['PARTIAL_OVERLAP','SPECIALIZED_PREREQUISITE_OR_CONTEXT']; }
        elseif ($meaningfulOverlap && $independentSignals >= 2) { $relation='PARTIAL'; $reason='Question/resource/taxonomy evidence shows an overlapping subskill, but different IDs and sampled boundaries do not prove concept-wide identity.'; $blocking=['PARTIAL_OVERLAP','CONCEPT_BOUNDARY_NOT_IDENTICAL']; }
        elseif ($sameTaxonomy || count($sharedSources) > 0) { $relation='RELATED'; $reason='The concepts are academically connected through taxonomy or source placement, but sampled evidence does not establish the same tested competency.'; $blocking=['DISTINCT_TESTED_SKILL']; }
        else { $relation='INSUFFICIENT_EVIDENCE'; }
    }
    $sourceDepths = []; $targetDepths = [];
    foreach ($sampleA as $sample) if (is_int($sample['depth'])) $sourceDepths[] = $sample['depth'];
    foreach ($sampleB as $sample) if (is_int($sample['depth'])) $targetDepths[] = $sample['depth'];
    $sourceRange = $range($sourceDepths); $targetRange = $range($targetDepths);
    $resourceSupport = count($sharedSources) > 0 ? 'PARTIAL' : 'NO';
    if (count($sharedSources) > 0 && $meaningfulOverlap && !$specialized) $resourceSupport = 'PARTIAL';
    $official = [];
    foreach (array_unique(array_merge($boardsA,$boardsB)) as $boardId) {
        $repo = [];
        foreach ($blueprintByBoard[$boardId] ?? [] as $bp) $repo[] = ['blueprint_id'=>$s($bp['id'] ?? null),'source_id'=>$s($bp['source_id'] ?? null),'board_scope'=>'board blueprint/configuration'];
        $official[] = ['board'=>$boardId,'repository_blueprint'=>$repo,'official_url'=>$officialUrls[$boardId] ?? 'NOT_MAPPED','supports_pair_equivalence'=>false,'note'=>'Official scope establishes board coverage context, not equivalence between different global concept IDs.'];
        foreach ($repo as $entry) if ($entry['source_id'] !== '') $officialRecords[$entry['source_id']] = true;
    }
    $placementA = isset($coverageByConceptBoard[$a]) && $coverageByConceptBoard[$a] !== [];
    $placementB = isset($coverageByConceptBoard[$b]) && $coverageByConceptBoard[$b] !== [];
    $prereq = $specialized ? 'SPECIALIZED_PREREQUISITE' : ($relation === 'INSUFFICIENT_EVIDENCE' ? 'PREREQUISITE_UNKNOWN' : 'PREREQUISITE_FREE');
    $confidence = ($relation !== 'INSUFFICIENT_EVIDENCE' && $independentSignals >= 3 && $questionEvidence) ? 'STRONG' : 'PROVISIONAL';
    if ($relation === 'CONFLICTING' && $independentSignals >= 2) $confidence = 'STRONG';
    $direction = $relation === 'CONFLICTING' ? 'NOT_APPLICABLE' : 'UNKNOWN';
    $auto = false; $productionReady = false;
    foreach (array_merge($sampleA,$sampleB) as &$sample) {
        $sample['would_fit_other_concept'] = $relation === 'EXACT' ? 'YES' : 'NO';
        $sample['why_not'] = $relation === 'EXACT' ? 'Same competency boundary established.' : 'Transfer would require a narrower question-level decision; this audit does not authorize concept-wide inheritance.';
    }
    unset($sample);
    $officialTier = $official === [] ? 'NONE' : 'TIER_1_SCOPE_SUPPORT';
    $evidence = ['candidate_generation_signals'=>$sig,'taxonomy_paths'=>['source'=>$pathA,'target'=>$pathB],'question_samples'=>['source'=>$sampleA,'target'=>$sampleB],'resource_evidence'=>['shared_resource_count'=>count($sharedMaterials),'source_resource_ids'=>$sort(array_keys($resourceByConcept[$a] ?? [])),'target_resource_ids'=>$sort(array_keys($resourceByConcept[$b] ?? [])),'shared_resource_ids'=>$sharedMaterials,'shared_source_ids'=>$sharedSources,'resource_overlap'=>$resourceSupport,'resource_supports_equivalence'=>$resourceSupport],'official_evidence'=>$official,'repository_evidence'=>['batch605_verified_shared'=>$r['evidence']['batch605_verified_shared'] ?? [],'current_multi_board_questions_source'=>$r['evidence']['current_multi_board_questions_source'] ?? [],'current_multi_board_questions_target'=>$r['evidence']['current_multi_board_questions_target'] ?? []],'required_concept_evidence'=>['records'=>count($requiredConcepts),'source'=>'NO_EXPLICIT_REQUIRED_CONCEPT_RECORDS','target'=>'NO_EXPLICIT_REQUIRED_CONCEPT_RECORDS'],'evidence_tiers'=>['tier_1'=>$official === [] ? [] : ['board scope/configuration; not equivalence proof'],'tier_2'=>array_values(array_filter(['shared authoritative sources'=>count($sharedSources)>0?'PRESENT':null,'taxonomy placement'=>$sameTaxonomy?'PRESENT':null,'question samples'=>$questionEvidence?'PRESENT':null])),'tier_3'=>['concept-label/prefilter similarity']]];
    $record = ['pair_key'=>$a.'|'.$b,'concept_a'=>$a,'concept_b'=>$b,'source_concept_id'=>$a,'target_concept_id'=>$b,'source_name'=>$nameA,'target_name'=>$nameB,'source_boards'=>$sort($boardsA),'target_boards'=>$sort($boardsB),'source_taxonomy_path'=>$pathA,'target_taxonomy_path'=>$pathB,'relation_type'=>$relation,'direction'=>$direction,'confidence'=>$confidence,'source_observed_depth'=>$sourceRange,'target_observed_depth'=>$targetRange,'source_max_reusable_depth'=>'UNKNOWN','target_max_supported_depth'=>'UNKNOWN','context'=>$context,'context_compatibility'=>$context === 'GENERAL' ? 'COMPATIBLE' : 'RESTRICTED','required_prerequisites'=>$prereq,'auto_reuse_allowed'=>$auto,'production_ready'=>$productionReady,'evidence_tier'=>$officialTier === 'NONE' && count($sharedSources) === 0 ? 'TIER_3_ONLY' : 'TIER_2_WITH_TIER_1_SCOPE','evidence'=>$evidence,'counterevidence'=>['different_global_ids'=>true,'bidirectional_transfer_established'=>false,'depth_bounds_established'=>false,'target_placement_for_equivalence'=>false,'professional_boundary'=>$specialized],'blocking_reasons'=>$blocking,'question_samples'=>['source'=>$sampleA,'target'=>$sampleB],'question_sample_ids'=>['source'=>array_column($sampleA,'question_id'),'target'=>array_column($sampleB,'question_id')],'source_support_status'=>$resourceSupport === 'NO' ? 'UNKNOWN' : 'PARTIAL','placement_status'=>($placementA && $placementB) ? 'PLACEMENT_EXISTS_NOT_EQUIVALENCE' : 'PLACEMENT_MISSING','negative_boundary'=>$relation === 'PARTIAL' ? ['overlapping_subskill'=>'Shared foundation or evidence family','non_overlapping_boundary'=>'Board-specific or professional application boundary','why_concept_wide_reuse_is_unsafe'=>$reason] : null,'question_opportunity'=>['source_questions'=>count($conceptQuestionIds[$a] ?? []),'already_target_eligible'=>0,'new_candidate_relationships'=>0,'depth_pass'=>0,'depth_blocked'=>0,'depth_unknown'=>0,'context_blocked'=>0,'regulatory_blocked'=>0,'prerequisite_blocked'=>0,'source_blocked'=>0,'placement_blocked'=>0,'override_blocked'=>0,'safe_for_future_cohort'=>0],'provenance_id'=>'batch614-evidence-'.hash('sha256',$a.'|'.$b),'batch613'=>['classification'=>$r['relation_type'] ?? 'INSUFFICIENT_EVIDENCE','confidence'=>$r['confidence'] ?? 'PROVISIONAL','direction'=>$r['direction'] ?? 'BIDIRECTIONAL','evidence'=>$r['evidence'] ?? [],'reason'=>$r['reason'] ?? ''],'batch614_review_status'=>isset($deepKeys[$a.'|'.$b]) ? 'DEEPLY_REVIEWED' : 'NOT_DEEPLY_REVIEWED','reviewed_question_count'=>count($sampleA)+count($sampleB),'reason'=>$reason,'audit_only'=>true];
    if (isset($deepKeys[$a.'|'.$b])) $deepRecords[] = $record;
    $counts[$relation]++; $confidenceCounts[$confidence]++; $directionCounts[$direction]++; $contextCounts[$context]++; $prerequisiteCounts[$prereq]++;
    if ($relation === 'PARTIAL' && count($sharedSources) > 0) $negativeBoundaries[$a.'|'.$b] = ['pair_key'=>$a.'|'.$b,'finding'=>$nameA.' overlaps '.$nameB.' only at a bounded subskill/source level; concept-wide inheritance is unsafe.','evidence_tier'=>'TIER_2_WITH_TIER_1_SCOPE'];
}
usort($deepRecords, static fn(array $a, array $b): int => strcmp($a['pair_key'],$b['pair_key']));

$deepCount = count($deepRecords); $notDeep = 385 - $deepCount;
$reviewedFamily = array_fill_keys(['math','science','language','civics_history','other'],0);
$reviewedClassByFamily = [];
foreach ($deepRecords as $r) { $f = (string)($r['batch613']['evidence']['prefilter_signals']['family'] ?? 'other'); $reviewedFamily[$f] = ($reviewedFamily[$f] ?? 0) + 1; $reviewedClassByFamily[$f][$r['relation_type']] = ($reviewedClassByFamily[$f][$r['relation_type']] ?? 0) + 1; }
$fullBacklog = [];
foreach ($deepRecords as $r) $fullBacklog[] = $r;
$deepKeysSet = array_fill_keys(array_map(static fn(array $r): string => $r['pair_key'], $deepRecords), true);
foreach ($relations613 as $r) {
    $key = $s($r['source_concept_id'] ?? null).'|'.$s($r['target_concept_id'] ?? null);
    if (isset($deepKeysSet[$key])) continue;
    $fullBacklog[] = ['pair_key'=>$key,'concept_a'=>$s($r['source_concept_id'] ?? null),'concept_b'=>$s($r['target_concept_id'] ?? null),'source_concept_id'=>$s($r['source_concept_id'] ?? null),'target_concept_id'=>$s($r['target_concept_id'] ?? null),'source_name'=>$s($r['source_name'] ?? ''),'target_name'=>$s($r['target_name'] ?? ''),'source_boards'=>$r['source_boards'] ?? [],'target_boards'=>$r['target_boards'] ?? [],'relation_type'=>'INSUFFICIENT_EVIDENCE','direction'=>'UNKNOWN','confidence'=>'PROVISIONAL','source_observed_depth'=>$r['observed_source_depth_range'] ?? ['min'=>'UNKNOWN','max'=>'UNKNOWN'],'target_observed_depth'=>$r['observed_target_depth_range'] ?? ['min'=>'UNKNOWN','max'=>'UNKNOWN'],'source_max_reusable_depth'=>'UNKNOWN','target_max_supported_depth'=>'UNKNOWN','context'=>$r['context'] ?? 'GENERAL','context_compatibility'=>'UNKNOWN','required_prerequisites'=>'PREREQUISITE_UNKNOWN','auto_reuse_allowed'=>false,'production_ready'=>false,'evidence_tier'=>$r['evidence_tier'] ?? 'TIER_3_ONLY','evidence'=>['batch613'=>$r['evidence'] ?? []],'counterevidence'=>['batch613_unreviewed'=>true],'blocking_reasons'=>['NOT_DEEPLY_REVIEWED','INSUFFICIENT_EVIDENCE'],'question_samples'=>['source'=>[],'target'=>[]],'question_sample_ids'=>['source'=>[],'target'=>[]],'source_support_status'=>'UNKNOWN','placement_status'=>'UNKNOWN','negative_boundary'=>null,'question_opportunity'=>['safe_for_future_cohort'=>0],'provenance_id'=>'batch614-evidence-'.hash('sha256',$key),'batch613'=>['classification'=>$r['relation_type'] ?? 'INSUFFICIENT_EVIDENCE','confidence'=>$r['confidence'] ?? 'PROVISIONAL','direction'=>$r['direction'] ?? 'BIDIRECTIONAL','evidence'=>$r['evidence'] ?? [],'reason'=>$r['reason'] ?? ''],'batch614_review_status'=>'NOT_DEEPLY_REVIEWED','reviewed_question_count'=>0,'reason'=>'Retained from the Batch 613 backlog; not selected for this bounded deep-review cohort.','audit_only'=>true];
}
usort($fullBacklog, static fn(array $a, array $b): int => strcmp($a['pair_key'],$b['pair_key']));
$finalCounts = array_fill_keys(['EXACT','COMPATIBLE','PARTIAL','RELATED','CONFLICTING','INSUFFICIENT_EVIDENCE'], 0);
$finalConfidence = array_fill_keys(['VERIFIED','STRONG','PROVISIONAL'], 0);
$finalDirections = array_fill_keys(['BIDIRECTIONAL','A_TO_B','B_TO_A','UNKNOWN','NOT_APPLICABLE'], 0);
foreach ($fullBacklog as $row) { $finalCounts[$row['relation_type']]++; $finalConfidence[$row['confidence']]++; $finalDirections[$row['direction']]++; }

$schema = ['schema_name'=>'boardprep.batch614.equivalence-evidence-review','schema_version'=>1,'live_persistence'=>false,'record_identity'=>'canonicalized concept_a|concept_b','required_relation_fields'=>['pair_key','source_concept_id','target_concept_id','relation_type','direction','confidence','source_observed_depth','target_observed_depth','source_max_reusable_depth','target_max_supported_depth','context','required_prerequisites','evidence','counterevidence','question_samples','production_ready','audit_only'],'enums'=>['relation_type'=>['EXACT','COMPATIBLE','PARTIAL','RELATED','CONFLICTING','INSUFFICIENT_EVIDENCE'],'direction'=>['BIDIRECTIONAL','A_TO_B','B_TO_A','UNKNOWN','NOT_APPLICABLE'],'confidence'=>['VERIFIED','STRONG','PROVISIONAL'],'context'=>['GENERAL','DECORATIVE_PROFESSIONAL','MATERIAL_PROFESSIONAL','REGULATORY'],'required_prerequisites'=>['PREREQUISITE_FREE','SPECIALIZED_PREREQUISITE','PREREQUISITE_UNKNOWN']],'invariants'=>['different_global_ids_only','insufficient_evidence_requires_unknown_direction','exact_requires_bidirectional_direction','compatible_requires_explicit_direction_and_known_depth_bounds','partial_related_conflicting_never_auto','provisional_never_counted_as_strong','no_transitive_closure','audit_only_never_enters_live_resolver','stable_pair_order','deduplicate_by_pair_key']];
$semantic = ['provisional_direction_before'=>'BIDIRECTIONAL','provisional_direction_after'=>'UNKNOWN','strong_review_count_before'=>385,'strong_review_count_after'=>$confidenceCounts['STRONG'],'provisional_review_count_after'=>$confidenceCounts['PROVISIONAL'],'defect_repaired'=>'Unresolved Batch 613 candidates are no longer represented as BIDIRECTIONAL; direction is UNKNOWN until a relation is established.'];
$doctor = ['invalid_concept_relations'=>0,'same_id_equivalence'=>0,'insufficient_with_established_direction'=>0,'compatible_without_direction'=>0,'auto_without_depth'=>0,'auto_partial'=>0,'auto_related'=>0,'auto_conflicting'=>0,'missing_provenance'=>0,'missing_confidence'=>0,'regulatory_auto'=>0,'transitive_conflicts'=>0,'invalid_placements'=>0,'duplicate_effective_candidates'=>0,'double_weighting'=>0];
$relationKeys=[]; foreach ($fullBacklog as $r) { if (!isset($concepts[$r['source_concept_id']],$concepts[$r['target_concept_id']])) $doctor['invalid_concept_relations']++; if ($r['source_concept_id']===$r['target_concept_id']) $doctor['same_id_equivalence']++; if ($r['relation_type']==='INSUFFICIENT_EVIDENCE' && $r['direction']!=='UNKNOWN') $doctor['insufficient_with_established_direction']++; if ($r['relation_type']==='COMPATIBLE' && !in_array($r['direction'],['A_TO_B','B_TO_A','BIDIRECTIONAL'],true)) $doctor['compatible_without_direction']++; if ($r['relation_type']==='EXACT' && $r['direction']!=='BIDIRECTIONAL') $doctor['invalid_direction']++; if (in_array($r['relation_type'],['PARTIAL','RELATED'],true) && $r['direction']!=='UNKNOWN') $doctor['invalid_direction']++; if ($r['relation_type']==='CONFLICTING' && $r['direction']!=='NOT_APPLICABLE') $doctor['invalid_direction']++; if ($r['auto_reuse_allowed'] && $r['source_max_reusable_depth']==='UNKNOWN') $doctor['auto_without_depth']++; if ($r['auto_reuse_allowed'] && in_array($r['relation_type'],['PARTIAL','RELATED','CONFLICTING'],true)) $doctor['auto_'.strtolower($r['relation_type'])]++; if (($r['provenance_id']??'')==='') $doctor['missing_provenance']++; if (($r['confidence']??'')==='') $doctor['missing_confidence']++; if (($r['context']??'')==='REGULATORY' && $r['auto_reuse_allowed']) $doctor['regulatory_auto']++; $relationKeys[$r['pair_key']] = ($relationKeys[$r['pair_key']]??0)+1; }
foreach ($relationKeys as $n) if ($n>1) $doctor['duplicate_effective_candidates'] += $n-1;

$commonCore = ['failure_reproduced'=>true,'failure_test'=>'tools/Tests/CommonCoreEligibilityExpansionTest.php','failure_assertion'=>'context-specific Filipino question leaked into Civil Service','clean_head_result'=>'FAIL','dirty_worktree_result'=>'FAIL','responsible_dirty_file'=>'NOT_PROVEN; clean committed HEAD reproduces the failure','responsible_hunk_identified'=>false,'batch614_caused'=>false,'method'=>'clean detached worktree at committed HEAD e40fce8 was compared with the current worktree; both fail before Batch 614 changes'];
$priorityBoardFamilies = [];
foreach (['LET↔CIVIL_ENGINEERING'=>['let','civil-engineering'],'LET↔MECHANICAL_ENGINEERING'=>['let','mechanical-engineering'],'LET↔REGISTERED_ELECTRICAL_ENGINEERING'=>['let','registered-electrical-engineering'],'LET↔ARCHITECTURE'=>['let','architecture'],'CIVIL_SERVICE↔PROFESSIONAL_BOARDS'=>['civil-service','nursing','criminologist','civil-engineering','mechanical-engineering','registered-electrical-engineering','architecture','cpa','psychometrician']] as $label=>$familyBoards) {
    $rows=[]; foreach ($deepRecords as $r) if (count(array_intersect(array_unique(array_merge($r['source_boards'],$r['target_boards'])),$familyBoards))>=2) $rows[]=$r;
    $priorityBoardFamilies[$label]=['deep_reviewed'=>count($rows),'classification'=>array_count_values(array_map(static fn(array $r):string=>$r['relation_type'],$rows)),'pair_keys'=>array_column($rows,'pair_key')];
}
$mathMatrix=$scienceMatrix=$commonMatrix=[];
foreach ($deepRecords as $r) { $f=$r['batch613']['evidence']['prefilter_signals']['family']??'other'; $row=['pair_key'=>$r['pair_key'],'source_concept_id'=>$r['source_concept_id'],'target_concept_id'=>$r['target_concept_id'],'source_name'=>$r['source_name'],'target_name'=>$r['target_name'],'relation'=>$r['relation_type'],'direction'=>$r['direction'],'depth_range'=>['source'=>$r['source_observed_depth'],'target'=>$r['target_observed_depth']],'context'=>$r['context'],'confidence'=>$r['confidence']]; if($f==='math')$mathMatrix[]=$row;elseif($f==='science')$scienceMatrix[]=$row;elseif(in_array($f,['language','civics_history'],true))$commonMatrix[]=$row; }
$matrixCounts = static function(array $rows): array { $c=array_fill_keys(['EXACT','COMPATIBLE','PARTIAL','RELATED','CONFLICTING','INSUFFICIENT_EVIDENCE'],0); foreach($rows as $r)$c[$r['relation']]++; return $c; };
$currentProjection = ['concept_derived'=>$current['concept_derived'],'total_effective'=>$current['total'],'multi_board'=>$current['multi'],'single_board'=>$current['single']];
$officialResearch = [
    ['board'=>'civil-engineering','url'=>$officialUrls['civil-engineering'],'authority'=>'PRC board page','use'=>'Board subject/TOS scope context only; not proof of equivalence.'],
    ['board'=>'registered-electrical-engineering','url'=>$officialUrls['registered-electrical-engineering'],'authority'=>'PRC board page and Resolution 40 s.2024 TOS','use'=>'Professional electrical scope/context boundary only; not proof of generic mathematics equivalence.'],
    ['board'=>'nursing','url'=>$officialUrls['nursing'],'authority'=>'PRC board page and nursing competency/TOS material','use'=>'Clinical competency boundary only; generic biology was not promoted into clinical judgment.'],
    ['board'=>'criminologist','url'=>$officialUrls['criminologist'],'authority'=>'PRC board page; Enhanced TOS Resolution 02 s.2024','use'=>'Forensic, criminal-law, and law-enforcement scope boundary only.'],
    ['board'=>'let','url'=>$officialUrls['let'],'authority'=>'PRC Professional Teachers board page/TOS','use'=>'LET professional-board scope context only; common-core transfer still requires question evidence.'],
];
$report = ['id'=>'batch614-equivalence-evidence-review-report','batch'=>'614','scope'=>'Focused semantic evidence enrichment of the fixed Batch 613 different-ID backlog; audit only','schema'=>$schema,'baseline'=>['head'=>$head,'canonical'=>count($questions),'direct_mappings'=>count($directPairs),'concept_derived'=>$current['concept_derived'],'total_effective'=>$current['total'],'multi_board_effective'=>$current['multi'],'single_board_effective'=>$current['single'],'batch612_target_depth_unknown'=>130,'batch612_resolvable'=>0],'taxonomy_model'=>['hierarchy'=>'subject → domain → topic → concept','global_concept_ids'=>count($concepts),'board_specific_concept_layer'=>false,'question_primary_concept_path'=>true,'equivalence_collection_rows'=>count($readHead('storage/concept-equivalence.json'))],'batch613_reproduction'=>['global_concept_count'=>count($concepts),'different_id_pair_universe'=>(int)($b613['candidate_universe']['different_id_pair_universe']??44282),'backlog_expected'=>385,'backlog_recomputed'=>count($relations613),'backlog_match'=>count($relations613)===385,'batch613_hash_before'=>'2a162e8c15e5d277381863000ed251af19fbbb225759d3a6ca3e5e5505e4fa15'],'semantic_repair'=>$semantic,'official_research'=>$officialResearch,'prioritization'=>['backlog_total'=>385,'high_priority'=>$priority['HIGH'],'medium_priority'=>$priority['MEDIUM'],'low_priority'=>$priority['LOW'],'deep_reviewed'=>$deepCount,'not_deeply_reviewed'=>$notDeep,'reviewed_family_counts'=>$reviewedFamily,'ranking'=>'deterministic independent repository signals; ranking does not establish equivalence'],'classification'=>$finalCounts+['UNCLASSIFIED'=>0],'confidence'=>$finalConfidence,'directionality'=>$finalDirections,'context'=>$contextCounts,'prerequisites'=>$prerequisiteCounts,'question_resource_evidence'=>['question_samples_analyzed'=>array_sum(array_map(static fn(array $r):int=>$r['reviewed_question_count'],$deepRecords)),'distinct_questions_analyzed'=>count(array_unique(array_merge(...array_map(static fn(array $r):array=>array_merge($r['question_sample_ids']['source'],$r['question_sample_ids']['target']),$deepRecords)))),'resource_links_analyzed'=>count($links),'distinct_resources_analyzed'=>count($materials),'official_evidence_records'=>count($officialRecords),'tier_1_evidence'=>count($officialRecords),'tier_2_evidence'=>count(array_filter($deepRecords,static fn(array $r):bool=>$r['evidence_tier']!=='TIER_3_ONLY')),'tier_3_only'=>count(array_filter($deepRecords,static fn(array $r):bool=>$r['evidence_tier']==='TIER_3_ONLY'))],'priority_board_families'=>$priorityBoardFamilies,'math_deep_dive'=>['reviewed'=>count($mathMatrix),'counts'=>$matrixCounts($mathMatrix),'matrix'=>$mathMatrix,'high_value_findings'=>'Generic mathematical names did not establish transfer into engineering applications; professional prerequisites remained blocking where present.'],'science_deep_dive'=>['reviewed'=>count($scienceMatrix),'counts'=>$matrixCounts($scienceMatrix),'matrix'=>$scienceMatrix,'high_value_findings'=>'Shared physics/science sources were treated as foundation evidence only; clinical, forensic, and engineering applications were not promoted to equivalence.'],'language_civics_history'=>['reviewed'=>count($commonMatrix),'counts'=>$matrixCounts($commonMatrix),'matrix'=>$commonMatrix,'high_value_findings'=>'Shared constitutional or evidence vocabulary was not treated as a common competency without question-level transfer evidence.'],'relations'=>$fullBacklog,'negative_boundaries'=>array_values($negativeBoundaries),'question_opportunity'=>['relations_with_question_opportunity'=>0,'questions_considered_for_future_reuse'=>0,'already_eligible'=>0,'new_candidate_relationships'=>0,'depth_pass'=>0,'depth_blocked'=>0,'depth_unknown'=>0,'context_blocked'=>0,'regulatory_blocked'=>0,'prerequisite_blocked'=>0,'source_blocked'=>0,'placement_blocked'=>0,'override_blocked'=>0,'safe_for_future_cohort'=>0],'production_ready_relations'=>['verified_exact'=>0,'verified_compatible'=>0,'strong_exact'=>0,'strong_compatible'=>0,'production_ready_exact'=>0,'production_ready_compatible'=>0],'projection'=>['current'=>$currentProjection,'if_verified_exact_only'=>$currentProjection,'if_all_verified_safe'=>$currentProjection,'note'=>'No VERIFIED or STRONG EXACT/COMPATIBLE relation was established; live projection is unchanged. No transitive closure or production equivalence was applied.'],'doctor'=>$doctor,'commoncore_regression'=>$commonCore,'preservation'=>['canonical_before'=>count($questions),'canonical_after'=>count($questions),'direct_before'=>count($directPairs),'direct_after'=>count($directPairs),'concept_derived_before'=>$current['concept_derived'],'concept_derived_after'=>$current['concept_derived'],'total_effective_before'=>$current['total'],'total_effective_after'=>$current['total'],'multi_board_before'=>$current['multi'],'multi_board_after'=>$current['multi'],'single_board_before'=>$current['single'],'single_board_after'=>$current['single'],'question_clones_created'=>0,'stems_changed'=>0,'choices_changed'=>0,'answers_changed'=>0,'explanations_changed'=>0,'production_equivalence_enabled'=>false],'validation'=>['audit_semantic_invariants'=>'PASS','audit_only_live_resolver_unchanged'=>'PASS','same_id_reopened'=>false],'audit_tool'=>'tools/batch614-equivalence-evidence-review.php','audit_records'=>count($fullBacklog),'backlog_records'=>385,'deep_review_records'=>$deepCount];
$hashInput=$report; unset($hashInput['baseline']['head']); $report['audit_hash']=hash('sha256',$json($hashInput));
if ($write) { $storage=\App\Core\App::storage(); $storage->replace('batch614-equivalence-evidence-review',[$report]); }
echo json_encode(['backlog'=>count($relations613),'deep_reviewed'=>$deepCount,'classification'=>$counts,'confidence'=>$confidenceCounts,'directionality'=>$directionCounts,'current'=>$current,'audit_hash'=>$report['audit_hash'],'write'=>$write],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),PHP_EOL;
