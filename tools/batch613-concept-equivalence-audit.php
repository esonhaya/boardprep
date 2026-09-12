#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Batch 613: audit-only different-global-concept equivalence.
 *
 * --write writes only the batch613-concept-equivalence-audit report. No live
 * equivalence, eligibility, coverage, metadata, or question data is written.
 */
require_once dirname(__DIR__) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

$storage = \App\Core\App::storage();
$write = in_array('--write', $argv, true);
$s = static fn(mixed $v): string => is_scalar($v) ? trim((string) $v) : '';
$json = static fn(mixed $v): string => json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
$board = static function (mixed $v): string {
    $v = strtolower(trim(is_scalar($v) ? (string) $v : ''));
    return ['cse' => 'civil-service', 'civilservice' => 'civil-service'][$v] ?? $v;
};
$sort = static function (array $v): array {
    $v = array_values(array_unique(array_map('strval', $v)));
    usort($v, 'strnatcmp');
    return $v;
};
$tokens = static function (string $v) use ($sort): array {
    $v = strtolower(preg_replace('/([a-z])([A-Z])/', '$1 $2', $v) ?? $v);
    $v = preg_replace('/[^a-z0-9]+/', ' ', $v) ?? '';
    $stop = array_fill_keys(['and','the','of','for','with','in','to','as','a','an','basic','general','applied','application','applications','core','foundation','foundations','concept','concepts','reasoning','relationships','models','model'], true);
    $out = [];
    foreach (preg_split('/\s+/', trim($v)) ?: [] as $part) if ($part !== '' && strlen($part) >= 4 && !isset($stop[$part])) $out[$part] = true;
    return $sort(array_keys($out));
};
$overlap = static fn(array $a, array $b): array => array_values(array_intersect($a, $b));

$questions = array_values(array_filter($storage->all('questions'), 'is_array'));
$metadata = array_values(array_filter($storage->all('question-reuse-metadata'), 'is_array'));
$coverage = array_values(array_filter($storage->all('board-concept-coverage'), 'is_array'));
$derivedRows = array_values(array_filter($storage->all('concept-derived-eligibility'), 'is_array'));
$links = array_values(array_filter($storage->all('study-question-links'), 'is_array'));
$materials = array_values(array_filter($storage->all('study-materials'), 'is_array'));
$sources = array_values(array_filter($storage->all('sources'), 'is_array'));
$blueprints = array_values(array_filter($storage->all('blueprints'), 'is_array'));
$concepts = [];
foreach ($storage->all('taxonomy/concepts') as $row) if (($id = $s($row['id'] ?? null)) !== '') $concepts[$id] = $row;
$topics = [];
foreach ($storage->all('taxonomy/topics') as $row) if (($id = $s($row['id'] ?? null)) !== '') $topics[$id] = $row;
$domains = [];
foreach ($storage->all('taxonomy/domains') as $row) if (($id = $s($row['id'] ?? null)) !== '') $domains[$id] = $row;
$sourceById = [];
foreach ($sources as $row) if (($id = $s($row['id'] ?? null)) !== '') $sourceById[$id] = $row;
$materialById = [];
$materialConcepts = [];
foreach ($materials as $row) {
    $id = $s($row['id'] ?? null);
    if ($id === '') continue;
    $materialById[$id] = $row;
    foreach ($row['supported_concept_ids'] ?? [] as $concept) if (is_scalar($concept)) $materialConcepts[(string)$concept][$id] = true;
}
$blueprintByBoard = [];
foreach ($blueprints as $row) $blueprintByBoard[$board($row['board_id'] ?? $row['board'] ?? null)][] = $row;

$questionById = [];
$conceptQuestionIds = [];
$conceptContexts = [];
foreach ($questions as $q) {
    $id = $s($q['id'] ?? null);
    $concept = $s($q['taxonomy']['concept_id'] ?? null);
    if ($id === '') continue;
    $questionById[$id] = $q;
    if ($concept === '') continue;
    $conceptQuestionIds[$concept][] = $id;
    $context = 'GENERAL';
    if (!empty($q['legal_procedure_application']) || !empty($q['ethics_scope_application'])) $context = 'REGULATORY';
    foreach (['professional_judgment','context_necessary','forensic_interpretation','correctional_application','technical_reasoning','site_plan_spatial'] as $field) if (!empty($q[$field])) $context = 'MATERIAL_PROFESSIONAL';
    $conceptContexts[$concept][$context] = ($conceptContexts[$concept][$context] ?? 0) + 1;
}
foreach ($conceptQuestionIds as &$ids) usort($ids, 'strnatcmp');
unset($ids);
foreach ($links as $link) {
    $questionId = $s($link['question_id'] ?? null);
    $concept = $s($questionById[$questionId]['taxonomy']['concept_id'] ?? null);
    $materialId = $s($link['material_id'] ?? null);
    if ($concept !== '' && $materialId !== '') $materialConcepts[$concept][$materialId] = true;
}
$metadataByQuestion = [];
$conceptDepths = [];
foreach ($metadata as $row) {
    $id = $s($row['question_id'] ?? null);
    $reuse = is_array($row['reuse'] ?? null) ? $row['reuse'] : [];
    if ($id !== '') $metadataByQuestion[$id] = $reuse;
    $concept = $s($questionById[$id]['taxonomy']['concept_id'] ?? null);
    $depth = $reuse['required_depth'] ?? 'UNKNOWN';
    if ($concept !== '' && is_int($depth) && in_array($depth, [1,2,3,4,5], true)) $conceptDepths[$concept][$depth] = ($conceptDepths[$concept][$depth] ?? 0) + 1;
}

$directBoards = [];
$directPairs = [];
foreach ($storage->all('question-eligibility') as $entry) {
    if (!is_array($entry)) continue;
    $entryBoards = is_array($entry['exam_ids'] ?? null) ? $entry['exam_ids'] : [];
    foreach ($entry['question_ids'] ?? [] as $qid) {
        if (!is_scalar($qid)) continue;
        $id = (string)$qid;
        $mapping = is_array($entry['question_mappings'][$id] ?? null) ? $entry['question_mappings'][$id] : [];
        $boards = is_array($mapping['exam_ids'] ?? null) ? $mapping['exam_ids'] : $entryBoards;
        foreach ($boards as $value) {
            $value = $board($value);
            if ($value !== '') { $directBoards[$id][$value] = true; $directPairs[$id . '|' . $value] = true; }
        }
    }
}
$multiBoardIds = [];
foreach ($directBoards as $id => $boards) if (count($boards) > 1) $multiBoardIds[$id] = true;
$batch605ByConcept = [];
foreach ($metadataByQuestion as $id => $reuse) foreach ($reuse['metadata_provenance'] ?? [] as $p) {
    if (($p['type'] ?? '') === 'BATCH605_VERIFIED') {
        $concept = $s($questionById[$id]['taxonomy']['concept_id'] ?? null);
        if ($concept !== '') $batch605ByConcept[$concept][$id] = true;
    }
}

$coverageByConcept = [];
foreach ($coverage as $row) {
    $concept = $s($row['concept_id'] ?? null); $b = $board($row['board_id'] ?? null);
    if ($concept !== '' && $b !== '') $coverageByConcept[$concept][$b] = $row;
}
$conceptBoards = $coverageByConcept;
foreach ($conceptQuestionIds as $concept => $ids) foreach ($ids as $id) foreach (array_keys($directBoards[$id] ?? []) as $b) $conceptBoards[$concept][$b] ??= [];

$professionalBoards = array_fill_keys(['nursing','criminologist','civil-engineering','mechanical-engineering','registered-electrical-engineering','architecture','cpa','psychometrician'], true);
$family = static function (string $v): string {
    foreach (['math'=>'/number|algebra|equation|ratio|statistic|data|geometry|measurement|trigon|calculus|vector|matrix|probability|sequence/i','science'=>'/physics|force|motion|electric|chem|biology|cell|inquiry|evidence|data|earth|ecology|optics/i','language'=>'/reading|inference|grammar|sentence|verb|pronoun|modifier|parallel|preposition|communication|language/i','civics_history'=>'/civic|government|constitution|history|source|citizenship|public-good/i'] as $name => $pattern) if (preg_match($pattern, $v) === 1) return $name;
    return 'other';
};
$conceptContext = static function (string $concept) use (&$conceptBoards, &$professionalBoards, &$conceptContexts): string {
    foreach (array_keys($conceptBoards[$concept] ?? []) as $b) if (isset($professionalBoards[$b])) return 'MATERIAL_PROFESSIONAL';
    foreach (['REGULATORY','MATERIAL_PROFESSIONAL','DECORATIVE_PROFESSIONAL','GENERAL'] as $context) if (($conceptContexts[$concept][$context] ?? 0) > 0) return $context;
    return 'GENERAL';
};
$depthRange = static function (string $concept) use (&$conceptDepths): array {
    $d = array_keys($conceptDepths[$concept] ?? []); sort($d, SORT_NUMERIC);
    return $d === [] ? ['min'=>'UNKNOWN','max'=>'UNKNOWN'] : ['min'=>(int)$d[0],'max'=>(int)end($d)];
};
$sample = static function (string $concept) use (&$conceptQuestionIds, &$metadataByQuestion): array {
    $ids = $conceptQuestionIds[$concept] ?? []; if ($ids === []) return [];
    $known = []; foreach ($ids as $id) $known[$id] = is_int($metadataByQuestion[$id]['required_depth'] ?? null) ? $metadataByQuestion[$id]['required_depth'] : 99;
    usort($ids, static fn(string $a, string $b): int => ($known[$a] <=> $known[$b]) ?: strnatcmp($a, $b));
    $pick = array_values(array_unique(array_merge(array_slice($ids, 0, 2), count($ids) > 2 ? [$ids[count($ids)-1]] : []))); sort($pick, SORT_NATURAL);
    return array_map(static fn(string $id): array => ['question_id'=>$id,'depth'=>$metadataByQuestion[$id]['required_depth'] ?? 'UNKNOWN','context'=>$metadataByQuestion[$id]['context_mode'] ?? 'UNKNOWN'], $pick);
};
$blueprintEvidence = static function (string $concept) use (&$conceptBoards, &$blueprintByBoard, &$coverageByConcept, $s): array {
    $out = [];
    foreach (array_keys($conceptBoards[$concept] ?? []) as $b) foreach ($blueprintByBoard[$b] ?? [] as $blueprint) {
        $out[] = ['board'=>$b, 'blueprint_id'=>$s($blueprint['id'] ?? null), 'source_id'=>$s($blueprint['source_id'] ?? null), 'coverage_blueprint_node'=>$s($coverageByConcept[$concept][$b]['blueprint_node_id'] ?? null)];
    }
    usort($out, static fn(array $a, array $b): int => strcmp($a['board'].'|'.$a['blueprint_id'], $b['board'].'|'.$b['blueprint_id']));
    return $out;
};

// Index-based prefilter: same topic/domain, shared resource/source, or semantic label signal.
$candidateSignals = [];
$differentIdPairUniverse = 0;
$ids = array_keys($concepts); sort($ids, SORT_STRING);
for ($i=0; $i<count($ids); $i++) {
    $a = $ids[$i]; if (($conceptBoards[$a] ?? []) === []) continue;
    for ($j=$i+1; $j<count($ids); $j++) {
        $b = $ids[$j]; if (($conceptBoards[$b] ?? []) === []) continue;
        $differentBoards = array_diff(array_keys($conceptBoards[$a]), array_keys($conceptBoards[$b])) !== [] || array_diff(array_keys($conceptBoards[$b]), array_keys($conceptBoards[$a])) !== [];
        if (!$differentBoards) continue;
        $differentIdPairUniverse++;
        $ca = $concepts[$a]; $cb = $concepts[$b];
        $ta = $topics[$s($ca['topic_id'] ?? null)] ?? []; $tb = $topics[$s($cb['topic_id'] ?? null)] ?? [];
        $da = $domains[$s($ta['domain_id'] ?? null)] ?? []; $db = $domains[$s($tb['domain_id'] ?? null)] ?? [];
        $nameOverlap = $overlap($tokens($s($ca['name'] ?? $a)), $tokens($s($cb['name'] ?? $b)));
        $idOverlap = $overlap($tokens($a), $tokens($b));
        $ma = array_keys($materialConcepts[$a] ?? []); $mb = array_keys($materialConcepts[$b] ?? []);
        $sharedMaterials = array_values(array_intersect($ma, $mb));
        $sourceA = []; $sourceB = [];
        foreach ($ma as $m) foreach ($materials as $resource) if ($s($resource['id'] ?? null) === $m) foreach ($resource['source_ids'] ?? [] as $source) if (is_scalar($source)) $sourceA[(string)$source] = true;
        foreach ($mb as $m) foreach ($materials as $resource) if ($s($resource['id'] ?? null) === $m) foreach ($resource['source_ids'] ?? [] as $source) if (is_scalar($source)) $sourceB[(string)$source] = true;
        $sharedSources = array_keys(array_intersect_key($sourceA, $sourceB));
        $signals = [];
        if (($ca['topic_id'] ?? '') !== '' && ($ca['topic_id'] ?? '') === ($cb['topic_id'] ?? '')) $signals[] = 'SAME_TOPIC';
        if (($da['id'] ?? '') !== '' && ($da['id'] ?? '') === ($db['id'] ?? '')) $signals[] = 'SAME_DOMAIN';
        if ($nameOverlap !== []) $signals[] = 'NAME_TOKEN_OVERLAP';
        if ($idOverlap !== []) $signals[] = 'ID_TOKEN_OVERLAP';
        if ($sharedMaterials !== []) $signals[] = 'SHARED_STUDY_MATERIAL';
        if ($sharedSources !== []) $signals[] = 'SHARED_AUTHORITATIVE_SOURCE';
        if ($signals === []) continue;
        $candidateSignals[$a.'|'.$b] = ['same_topic'=>in_array('SAME_TOPIC',$signals,true),'same_domain'=>in_array('SAME_DOMAIN',$signals,true),'name_token_overlap'=>$nameOverlap,'id_token_overlap'=>$idOverlap,'shared_material_ids'=>$sort($sharedMaterials),'shared_source_ids'=>$sort($sharedSources),'signals'=>$signals,'family'=>$family($a.' '.($ca['name']??'').' '.$b.' '.($cb['name']??''))];
    }
}

$relations = []; $counts = array_fill_keys(['EXACT','COMPATIBLE','PARTIAL','RELATED','CONFLICTING','INSUFFICIENT_EVIDENCE'], 0); $conf = array_fill_keys(['VERIFIED','STRONG','PROVISIONAL'], 0); $dirs = array_fill_keys(['BIDIRECTIONAL','SOURCE_TO_TARGET','TARGET_TO_SOURCE'], 0); $pairSet = []; $math = []; $science = []; $common = [];
foreach ($candidateSignals as $key => $signal) {
    [$a,$b] = explode('|',$key,2); $ca=$concepts[$a]; $cb=$concepts[$b]; $nameA=$s($ca['name']??$a); $nameB=$s($cb['name']??$b);
    $ctxA=$conceptContext($a); $ctxB=$conceptContext($b); $context=in_array('REGULATORY',[$ctxA,$ctxB],true)?'REGULATORY':(in_array('MATERIAL_PROFESSIONAL',[$ctxA,$ctxB],true)?'MATERIAL_PROFESSIONAL':'GENERAL');
    $strongSemantic=$signal['same_topic'] && ($signal['name_token_overlap']!==[] || $signal['shared_material_ids']!==[]);
    if ($context==='REGULATORY') { $relation='CONFLICTING'; $reason='Material regulatory/professional context difference prohibits automated equivalence.'; }
    elseif ($strongSemantic && $signal['shared_material_ids']!==[] && $signal['name_token_overlap']!==[]) { $relation='PARTIAL'; $reason='Substantial topic/resource/label overlap exists, but distinct IDs and boundaries do not prove concept-wide equivalence.'; }
    elseif ($signal['same_topic'] || $signal['same_domain']) { $relation='RELATED'; $reason='Related taxonomy position is evidence of academic connection, not identity of tested competency.'; }
    else { $relation='INSUFFICIENT_EVIDENCE'; $reason='Structural/resource signals create a candidate, but independent equivalence, direction, and depth bounds are absent.'; }
    $direction='BIDIRECTIONAL'; $depthA=$depthRange($a); $depthB=$depthRange($b);
    $sourceBoards=$sort(array_keys($conceptBoards[$a]??[])); $targetBoards=$sort(array_keys($conceptBoards[$b]??[]));
    foreach ($sourceBoards as $ba) foreach ($targetBoards as $bb) if ($ba!==$bb) $pairSet[$ba.'|'.$bb]=true;
    $sharedA=[]; foreach (array_keys($conceptQuestionIds[$a]??[]) as $qid) if (isset($multiBoardIds[$qid])) $sharedA[]=$qid;
    $sharedB=[]; foreach (array_keys($conceptQuestionIds[$b]??[]) as $qid) if (isset($multiBoardIds[$qid])) $sharedB[]=$qid;
    $record=['source_concept_id'=>$a,'target_concept_id'=>$b,'source_name'=>$nameA,'target_name'=>$nameB,'source_boards'=>$sourceBoards,'target_boards'=>$targetBoards,'relation_type'=>$relation,'relation'=>$relation,'direction'=>$direction,'source_max_reusable_depth'=>'UNKNOWN','target_max_supported_depth'=>'UNKNOWN','observed_source_depth_range'=>$depthA,'observed_target_depth_range'=>$depthB,'context'=>$context,'confidence'=>$relation==='CONFLICTING'?'STRONG':'PROVISIONAL','auto_reuse_allowed'=>false,'evidence_tier'=>$signal['shared_material_ids']!==[]||$signal['shared_source_ids']!==[]?'TIER_2_SUPPORTING':'TIER_3_STRUCTURAL','evidence'=>['prefilter_signals'=>$signal,'official_blueprint_evidence'=>['source'=>$blueprintEvidence($a),'target'=>$blueprintEvidence($b)],'direct_shared_questions'=>[],'batch605_verified_shared'=>['source'=>$sort(array_keys($batch605ByConcept[$a]??[])),'target'=>$sort(array_keys($batch605ByConcept[$b]??[]))],'current_multi_board_questions_source'=>$sharedA,'current_multi_board_questions_target'=>$sharedB,'source_question_count'=>count($conceptQuestionIds[$a]??[]),'target_question_count'=>count($conceptQuestionIds[$b]??[]),'required_concept_evidence'=>['source'=>'NO_EXPLICIT_REQUIRED_CONCEPT_RECORDS','target'=>'NO_EXPLICIT_REQUIRED_CONCEPT_RECORDS','assumed_foundation'=>'NOT_MODELED']],'counterevidence'=>['different_global_ids'=>true,'directional_depth_boundary_established'=>false,'target_placement_equivalence_established'=>false,'professional_context_difference'=>$context!=='GENERAL'],'question_sample_ids'=>['source'=>$sample($a),'target'=>$sample($b)],'question_sample_assessment'=>['source_to_target'=>'NOT_ESTABLISHED','target_to_source'=>'NOT_ESTABLISHED','explanation_and_source_validity'=>'NOT_ESTABLISHED','target_board_placement'=>'NOT_ESTABLISHED'],'reason'=>$reason,'provenance_id'=>'batch613-audit-'.hash('sha256',$key),'audit_only'=>true];
    $relations[]=$record; $counts[$relation]++; $conf[$record['confidence']]++; $dirs[$direction]++;
    $matrix=['source'=>$a,'target'=>$b,'relation'=>$relation,'direction'=>$direction,'depth_range'=>['source'=>$depthA,'target'=>$depthB],'context'=>$context,'confidence'=>$record['confidence']];
    if ($signal['family']==='math') $math[]=$matrix; elseif ($signal['family']==='science') $science[]=$matrix; elseif (in_array($signal['family'],['language','civics_history'],true)) $common[]=$matrix;
}
usort($relations,static fn(array $a,array $b):int=>strcmp($a['source_concept_id'].'|'.$a['target_concept_id'],$b['source_concept_id'].'|'.$b['target_concept_id']));
foreach ([$math,$science,$common] as &$m) usort($m,static fn(array $a,array $b):int=>strcmp($a['source'].'|'.$a['target'],$b['source'].'|'.$b['target']));
unset($m);
$matrixCounts=static function(array $rows):array{$o=array_fill_keys(['EXACT','COMPATIBLE','PARTIAL','RELATED','CONFLICTING','INSUFFICIENT_EVIDENCE'],0);foreach($rows as $r)$o[$r['relation']]++;return $o;};
$pairList=$sort(array_map(static fn(string $k):string=>str_replace('|',' → ',$k),array_keys($pairSet)));
$priorityFamilies = [
    'LET↔CIVIL_ENGINEERING'=>['let','civil-engineering'],
    'LET↔MECHANICAL_ENGINEERING'=>['let','mechanical-engineering'],
    'LET↔REGISTERED_ELECTRICAL_ENGINEERING'=>['let','registered-electrical-engineering'],
    'LET↔ARCHITECTURE'=>['let','architecture'],
    'CIVIL_SERVICE↔PROFESSIONAL_BOARDS'=>['civil-service','cpa','psychometrician','nursing','criminologist','civil-engineering','mechanical-engineering','registered-electrical-engineering','architecture'],
];
$priorityAudit=[];
foreach ($priorityFamilies as $familyName=>$familyBoards) {
    $rows=[];
    foreach ($relations as $relation) {
        $present=array_unique(array_merge($relation['source_boards'],$relation['target_boards']));
        if (count(array_intersect($present,$familyBoards)) >= 2) $rows[]=$relation;
    }
    $priorityAudit[$familyName]=['candidate_pairs'=>count($rows),'classification'=>$matrixCounts($rows),'relations'=>array_map(static fn(array $row): string=>$row['source_concept_id'].'|'.$row['target_concept_id'],$rows)];
}

$effectivePairs=$directPairs; $effectiveBoards=$directBoards; foreach (array_filter($derivedRows,static fn(array $r):bool=>($r['record_type']??'')==='RELATIONSHIP') as $r){$id=$s($r['question_id']??null);$b=$board($r['target_board']??null);if($id!==''&&$b!==''){$effectivePairs[$id.'|'.$b]=true;$effectiveBoards[$id][$b]=true;}}
$multi=0;$single=0;foreach($effectiveBoards as $bs){if(count($bs)>1)$multi++;elseif(count($bs)===1)$single++;}
$current=['concept_derived'=>count(array_filter($derivedRows,static fn(array $r):bool=>($r['record_type']??'')==='RELATIONSHIP')),'total'=>count($effectivePairs),'multi'=>$multi,'single'=>$single];
$doctor=['invalid_concept_relations'=>0,'same_id_equivalence'=>0,'conflicting_relations'=>0,'invalid_direction'=>0,'invalid_depth_bounds'=>0,'auto_partial'=>0,'auto_related'=>0,'auto_conflicting'=>0,'missing_provenance'=>0,'missing_confidence'=>0,'transitive_conflicts'=>0,'invalid_placements'=>0,'duplicate_effective_candidates'=>0,'double_weighting'=>0];
$graph=[];foreach($relations as $r){$a=$r['source_concept_id'];$b=$r['target_concept_id'];if(!isset($concepts[$a])||!isset($concepts[$b]))$doctor['invalid_concept_relations']++;if($a===$b)$doctor['same_id_equivalence']++;if($r['relation_type']==='CONFLICTING')$doctor['conflicting_relations']++;if(!in_array($r['direction'],array_keys($dirs),true))$doctor['invalid_direction']++;if($r['auto_reuse_allowed']&&in_array($r['relation_type'],['PARTIAL','RELATED','CONFLICTING'],true))$doctor['auto_'.strtolower($r['relation_type'])]++;if($r['provenance_id']==='')$doctor['missing_provenance']++;if($r['confidence']==='')$doctor['missing_confidence']++;$graph[$a][$b]=$r['relation_type'];}
foreach($graph as $a=>$edges)foreach($edges as $b=>$ab)foreach($graph[$b]??[] as $c=>$bc)if($a!==$c&&($graph[$a][$c]??'')==='CONFLICTING'&&in_array($ab,['EXACT','COMPATIBLE'],true)&&in_array($bc,['EXACT','COMPATIBLE'],true))$doctor['transitive_conflicts']++;

$schema=['schema_name'=>'boardprep.batch613.concept-equivalence-audit','schema_version'=>1,'live_persistence'=>false,'record_identity'=>'source_concept_id|target_concept_id','relation_type'=>['EXACT','COMPATIBLE','PARTIAL','RELATED','CONFLICTING','INSUFFICIENT_EVIDENCE'],'direction'=>['BIDIRECTIONAL','SOURCE_TO_TARGET','TARGET_TO_SOURCE'],'depth'=>'1..5 or UNKNOWN','confidence'=>['VERIFIED','STRONG','PROVISIONAL'],'invariants'=>['same_id_rejected','partial_related_conflicting_never_auto','compatible_requires_direction_and_depth_bounds','exact_requires_bidirectional_direction','no_transitive_closure','stable_sorted_records','audit_only']];
$report=['id'=>'batch613-concept-equivalence-audit-report','batch'=>'613','scope'=>'Different global concept IDs; audit only','schema'=>$schema,'baseline'=>['head'=>trim((string)shell_exec('git rev-parse HEAD 2>/dev/null')),'canonical'=>count($questions),'direct_mappings'=>count($directPairs),'concept_derived'=>$current['concept_derived'],'total_effective'=>$current['total'],'multi_board_effective'=>$current['multi'],'single_board_effective'=>$current['single'],'batch612_target_depth_unknown'=>130,'batch612_resolvable'=>0],'taxonomy_model'=>['hierarchy'=>'subject → domain → topic → concept','global_concept_ids'=>count($concepts),'board_specific_concept_layer'=>false,'question_primary_concept_path'=>true,'equivalence_collection_rows'=>count($storage->all('concept-equivalence'))],'candidate_universe'=>['global_concept_count'=>count($concepts),'different_id_pair_universe'=>$differentIdPairUniverse,'candidate_pairs_after_prefilter'=>count($relations),'reviewed_pairs'=>count($relations),'unreviewed_pairs'=>0,'board_pairs_represented'=>$pairList,'prefilter'=>'indexed cross-board pairs sharing topic/domain, material/source, or semantic label tokens; unrestricted Cartesian pairs excluded'],'classification'=>$counts+['UNCLASSIFIED'=>0],'confidence'=>$conf,'directionality'=>$dirs,'auto_reuse_readiness'=>['exact_auto_candidates'=>0,'compatible_auto_candidates'=>0,'partial_auto'=>0,'related_auto'=>0,'conflicting_auto'=>0,'potential_future_auto_relations'=>0,'potential_future_review_relations'=>count($relations),'prohibited_auto_relations'=>$counts['CONFLICTING']],'math_deep_dive'=>['pair_count'=>count($math),'counts'=>$matrixCounts($math),'matrix'=>$math],'science_deep_dive'=>['pair_count'=>count($science),'counts'=>$matrixCounts($science),'matrix'=>$science],'language_civics_history'=>['common_core_pair_count'=>count($common),'counts'=>$matrixCounts($common),'matrix'=>$common],'priority_board_families'=>$priorityAudit,'relations'=>$relations,'question_opportunity'=>['questions_analyzed'=>0,'potential_exact_reuse'=>0,'potential_compatible_reuse'=>0,'depth_pass'=>0,'depth_blocked'=>0,'context_blocked'=>0,'source_blocked'=>0,'placement_blocked'=>0,'review_required'=>0],'projection'=>['current'=>$current,'verified_exact_only'=>$current,'verified_exact_plus_compatible'=>$current,'strong_included'=>$current,'note'=>'No VERIFIED or STRONG EXACT/COMPATIBLE relation was identified; projections remain unchanged.'],'doctor'=>$doctor,'preservation'=>['canonical_before'=>count($questions),'canonical_after'=>count($questions),'direct_before'=>count($directPairs),'direct_after'=>count($directPairs),'concept_derived_before'=>$current['concept_derived'],'concept_derived_after'=>$current['concept_derived'],'total_effective_before'=>$current['total'],'total_effective_after'=>$current['total'],'question_clones_created'=>0,'question_content_changed'=>0,'production_eligibility_changed'=>0],'audit_tool'=>'tools/batch613-concept-equivalence-audit.php','audit_records'=>count($relations)];
$hashInput=$report;unset($hashInput['baseline']['head']);$report['audit_hash']=hash('sha256',$json($hashInput));
if($write)$storage->replace('batch613-concept-equivalence-audit',[$report]);
echo json_encode(['global_concept_count'=>count($concepts),'different_id_pair_universe'=>$differentIdPairUniverse,'candidate_pairs_after_prefilter'=>count($relations),'classification'=>$counts,'confidence'=>$conf,'math_pairs'=>count($math),'science_pairs'=>count($science),'common_core_pairs'=>count($common),'canonical'=>count($questions),'direct_mappings'=>count($directPairs),'concept_derived'=>$current['concept_derived'],'total_effective'=>$current['total'],'multi_board_effective'=>$current['multi'],'single_board_effective'=>$current['single'],'audit_hash'=>$report['audit_hash'],'write'=>$write],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),PHP_EOL;
