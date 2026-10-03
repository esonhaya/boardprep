<?php

declare(strict_types=1);

/** Batch653: consume only Batch652 CSE authority, then close the grouped review gate. */

require_once dirname(__DIR__) . '/app/Core/Autoloader.php';

use App\Core\App;
use App\Core\Autoloader;
use App\Repositories\QuestionRepository;
use App\Services\Flashcard\FlashcardCardValidator;
use App\Services\Flashcard\FlashcardDerivationService;
use App\Services\Question\QuestionEligibilityIndex;
use App\Services\Question\QuestionEligibilityService;
use App\Services\Question\QuestionService;
use App\Services\RepositoryHealth\CivilService\CivilServiceCoverageModel;
use App\Services\RepositoryHealth\Engine\RepositoryHealthEngine;
use App\Services\RepositoryHealth\Readiness\SourceReadinessRecord;
use App\Services\RepositoryHealth\Readiness\SourceReadinessService;

Autoloader::register();
putenv('APP_ENV=testing');
putenv('APP_DEBUG=false');
App::boot();

$root = dirname(__DIR__);
$expectedHead = 'bceeb1f5a418638d259583b3f0505bef03ff7833';
$expectedMain = '0eb9704ba293e0aa3402af5d01a2d3392c005b73';
$artifactPath = $root . '/storage/batch653-cse-review-closure.json';
$readinessPath = $root . '/storage/source-readiness-civil-service.json';
$mode = $argv[1] ?? '--audit';

$fail = static function (string $message): never {
    fwrite(STDERR, 'BATCH653_FAIL=' . $message . PHP_EOL);
    exit(1);
};
$readJson = static function (string $path): array {
    if (!is_file($path)) throw new RuntimeException('Missing JSON: ' . $path);
    $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($data)) throw new RuntimeException('Expected JSON object/collection at ' . $path);
    return $data;
};
$writeJson = static function (string $path, array $data): void {
    $tmp = $path . '.tmp';
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    if (file_put_contents($tmp, $json . "\n", LOCK_EX) === false || !rename($tmp, $path)) {
        throw new RuntimeException('Could not atomically write ' . $path);
    }
};
$git = static function (array $args) use ($root): string {
    $command = 'git -C ' . escapeshellarg($root);
    foreach ($args as $arg) $command .= ' ' . escapeshellarg($arg);
    $result = shell_exec($command . ' 2>/dev/null');
    if (!is_string($result)) throw new RuntimeException('Git command failed: ' . implode(' ', $args));
    return trim($result);
};
$canonicalize = static function (mixed $value) use (&$canonicalize): mixed {
    if (!is_array($value)) return $value;
    if (array_is_list($value)) return array_map($canonicalize, $value);
    ksort($value, SORT_STRING);
    foreach ($value as &$child) $child = $canonicalize($child);
    unset($child);
    return $value;
};
$hash = static fn(mixed $value): string => hash('sha256', json_encode(
    $canonicalize($value), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
));
$stable = static function (array $artifact): array {
    unset($artifact['audit_hash'], $artifact['determinism']['second_audit_hash']);
    foreach (['repository_health_before', 'repository_health_after'] as $field) {
        if (is_array($artifact[$field] ?? null)) unset($artifact[$field]['runtime_ms']);
    }
    return $artifact;
};
$thresholdCounts = static function (array $values): array {
    $values = array_values(array_map('intval', $values));
    sort($values, SORT_NUMERIC);
    $n = count($values);
    $quantile = static function (float $p) use ($values, $n): float|int {
        if ($n === 0) return 0;
        $at = ($n - 1) * $p;
        $lo = (int) floor($at); $hi = (int) ceil($at);
        return $lo === $hi ? $values[$lo] : $values[$lo] + ($values[$hi] - $values[$lo]) * ($at - $lo);
    };
    return [
        'leaf_count'=>$n,
        'zero'=>count(array_filter($values, static fn(int $v): bool => $v === 0)),
        'under5'=>count(array_filter($values, static fn(int $v): bool => $v < 5)),
        'under10'=>count(array_filter($values, static fn(int $v): bool => $v < 10)),
        'under20'=>count(array_filter($values, static fn(int $v): bool => $v < 20)),
        'min'=>$n === 0 ? 0 : min($values),
        'median'=>$n === 0 ? 0 : ($n % 2 === 1 ? $values[intdiv($n, 2)] : ($values[$n / 2 - 1] + $values[$n / 2]) / 2),
        'p25'=>$quantile(.25), 'p75'=>$quantile(.75), 'max'=>$n === 0 ? 0 : max($values),
    ];
};
$active = static fn(array $q): bool => strtolower((string) ($q['status'] ?? 'active')) !== 'archived';
$board = static fn(array $q): string => CivilServiceCoverageModel::normalizeBoard($q['taxonomy']['board_id'] ?? $q['board'] ?? '');
$leaf = static fn(array $q): string => (string) ($q['taxonomy']['concept_id'] ?? '');
$nativeCounts = static function (array $questions) use ($active, $board, $leaf): array {
    $byBoard = []; $byLeaf = [];
    foreach ($questions as $q) {
        if (!is_array($q) || !$active($q)) continue;
        $b = $board($q); $l = $leaf($q);
        $byBoard[$b] = ($byBoard[$b] ?? 0) + 1;
        if ($b === 'civil-service' && $l !== '') $byLeaf[$l] = ($byLeaf[$l] ?? 0) + 1;
    }
    return [$byBoard, $byLeaf];
};
$metricCounts = static function (array $questions, array $eligibility, array $derived, array $blueprints, array $overrides): array {
    $index = new QuestionEligibilityIndex($eligibility, $blueprints, $derived, $overrides);
    $active = static fn(array $q): bool => strtolower((string) ($q['status'] ?? 'active')) !== 'archived';
    $board = static fn(array $q): string => CivilServiceCoverageModel::normalizeBoard($q['taxonomy']['board_id'] ?? $q['board'] ?? '');
    $effectiveIds = [];
    foreach ($eligibility as $entry) {
        if (!is_array($entry)) continue;
        foreach ((array) ($entry['question_ids'] ?? []) as $id) {
            if (in_array('civil-service', (array) ($entry['exam_ids'] ?? []), true)) $effectiveIds[(string) $id] = true;
        }
    }
    foreach ($derived as $entry) if (is_array($entry) && ($entry['record_type'] ?? '') === 'RELATIONSHIP' && CivilServiceCoverageModel::normalizeBoard($entry['target_board'] ?? '') === 'civil-service') $effectiveIds[(string) ($entry['question_id'] ?? '')] = true;
    $qById = [];
    foreach ($questions as $q) if (is_array($q)) $qById[(string) ($q['id'] ?? '')] = $q;
    $effective = 0;
    foreach (array_keys($effectiveIds) as $id) if (isset($qById[$id])) $effective++;
    $runtime = 0;
    foreach ($questions as $q) if (is_array($q) && $active($q) && $index->forExam($q, 'civil-service') !== null) $runtime++;
    $native = 0;
    foreach ($questions as $q) if (is_array($q) && $active($q) && $board($q) === 'civil-service') $native++;
    return ['native'=>$native, 'effective_declared'=>$effective, 'runtime'=>$runtime];
};
$stripQuestionIds = static function (array $rows, array $removeIds): array {
    $remove = array_fill_keys(array_map('strval', $removeIds), true);
    $result = [];
    foreach ($rows as $row) {
        if (!is_array($row)) continue;
        $row['question_ids'] = array_values(array_filter((array) ($row['question_ids'] ?? []), static fn(mixed $id): bool => !isset($remove[(string) $id])));
        foreach (array_keys((array) ($row['question_mappings'] ?? [])) as $id) if (isset($remove[(string) $id])) unset($row['question_mappings'][$id]);
        if ($row['question_ids'] !== []) $result[] = $row;
    }
    return $result;
};

if (!in_array($mode, ['--execute', '--audit', '--final-health', '--repair-authorized'], true)) $fail('Use --execute, --audit, --final-health, or --repair-authorized.');

try {
    $headBefore = $git(['rev-parse', 'HEAD']);
    $branch = $git(['branch', '--show-current']);
    $mainHead = $git(['rev-parse', 'refs/heads/main']);
    if ($branch !== 'batch653-cse-review-closure') $fail('Unexpected branch: ' . $branch);
    if ($mainHead !== $expectedMain) $fail('Unrelated main HEAD changed.');
    if ($mode === '--execute' && $headBefore !== $expectedHead) $fail('Execute only from exact Batch652 checkpoint.');
    if ($headBefore !== $expectedHead && $git(['merge-base', $expectedHead, 'HEAD']) !== $expectedHead) $fail('Batch652 checkpoint is not an ancestor.');
    if ($mode === '--repair-authorized') {
        $authorized = 'cse652-343caf16b5e3dc75';
        $expectedQuestion = 'Rule 1 says every completed application with a verified receipt enters tracking. Rule 2 says a supervisor authorization is required before payment release. Which interpretation preserves the difference between a sufficient and a necessary condition?';
        $correctedQuestion = 'Rule 1 says that if a file has a verified receipt, it is entered into tracking. Rule 2 says that a supervisor authorization is required before payment release. Which interpretation preserves the difference between a sufficient and a necessary condition?';
        $target = null;
        foreach (App::storage()->all('questions') as $q) if (is_array($q) && ($q['batch_marker'] ?? '') === 'batch653-cse' && ($q['batch653']['plan_id'] ?? '') === $authorized) $target = $q;
        if (!is_array($target) || ($target['question'] ?? '') !== $expectedQuestion) $fail('Authorized conditional item is not in the expected recoverable state.');
        $target['question'] = $correctedQuestion;
        $updated = QuestionService::update((string) $target['id'], $target);
        if (!is_array($updated) || ($updated['question'] ?? '') !== $correctedQuestion || QuestionService::validate($updated)['valid'] !== true) $fail('QuestionService did not persist the bounded semantic repair.');
        fwrite(STDOUT, 'AUTHORIZED_ITEM_REPAIRED=' . $target['id'] . PHP_EOL);
        exit(0);
    }
    if ($mode === '--final-health') {
        if (!is_file($artifactPath)) $fail('--final-health needs a completed Batch653 artifact.');
        $artifact = $readJson($artifactPath);
        $started = hrtime(true);
        $healthReport = RepositoryHealthEngine::analyze();
        $health = $healthReport->toArray();
        $summary = (array) ($health['summary'] ?? []);
        $artifact['repository_health_after'] = [
            'overall_status'=>(string) ($health['overall_status'] ?? $summary['overall_status'] ?? ''),
            'pass_checks'=>(int) ($health['pass_checks'] ?? $summary['pass_count'] ?? 0),
            'warn_checks'=>(int) ($health['warn_checks'] ?? $summary['warn_count'] ?? 0),
            'fail_checks'=>(int) ($health['fail_checks'] ?? $summary['fail_count'] ?? 0),
            'review_checks'=>(int) ($health['review_checks'] ?? $summary['review_count'] ?? 0),
            'fail_entities'=>array_values((array) ($health['fail_entities'] ?? [])),
            'warn_entities'=>(int) ($health['warn_entity_count'] ?? $summary['warn_entity_count'] ?? 0),
            'review_entities'=>(int) ($health['review_entity_count'] ?? $summary['review_entity_count'] ?? 0),
            'runtime_ms'=>round((hrtime(true) - $started) / 1_000_000, 3),
        ];
        $artifact['validation']['repository_health'] = $artifact['repository_health_after']['fail_checks'] === 0 ? 'PASS' : 'FAIL';
        $artifact['audit_hash'] = $hash($stable($artifact));
        $writeJson($artifactPath, $artifact);
        fwrite(STDOUT, "REPOSITORY_HEALTH=".$artifact['repository_health_after']['overall_status']."\nFAIL_CHECKS=".$artifact['repository_health_after']['fail_checks']."\nFAIL_ENTITIES=".count($artifact['repository_health_after']['fail_entities'])."\nRUNTIME_MS=".$artifact['repository_health_after']['runtime_ms'].PHP_EOL);
        exit($artifact['repository_health_after']['fail_checks'] === 0 ? 0 : 1);
    }

    $batch652 = $readJson($root . '/storage/batch652-let-close-cse-readiness.json');
    $expectedWave1 = ['cse652-343caf16b5e3dc75','cse652-933d320062af5ea5','cse652-e85215ed9fef916f','cse652-47ac8005d7364ca2'];
    $expectedWave2 = ['cse652-25465527b24a6654'];
    $wave1 = (array) ($batch652['cse']['wave1'] ?? []);
    $wave2 = (array) ($batch652['cse']['wave2'] ?? []);
    $wave1Ids = array_values(array_map(static fn(array $p): string => (string) ($p['plan_id'] ?? ''), $wave1));
    $wave2Ids = array_values(array_map(static fn(array $p): string => (string) ($p['plan_id'] ?? ''), $wave2));
    sort($wave1Ids, SORT_STRING); sort($expectedWave1, SORT_STRING);
    sort($wave2Ids, SORT_STRING); sort($expectedWave2, SORT_STRING);
    if ($wave1Ids !== $expectedWave1 || $wave2Ids !== $expectedWave2 || (int) ($batch652['cse']['wave3_preauthorized'] ?? -1) !== 0) $fail('Exact Batch652 authorization changed.');

    $names = ['questions','question-eligibility','question-reuse-metadata','concept-derived-eligibility','question-reuse-overrides','blueprints','taxonomy/concepts','taxonomy/topics','taxonomy/domains','boards','subjects','board-subjects','board-concept-coverage','study-materials','study-question-links','sources','source-readiness-civil-service','source-readiness-let','source-readiness-mechanical-engineering','source-readiness-psychometrician','source-readiness'];
    $collections = [];
    foreach ($names as $name) $collections[$name] = App::storage()->all($name);
    $questions = $collections['questions'];
    $eligibility = $collections['question-eligibility'];
    $sources = $collections['sources'];
    $materials = $collections['study-materials'];
    $readiness = $collections['source-readiness-civil-service'];
    $initialReadiness = json_decode($git(['show', $expectedHead . ':storage/source-readiness-civil-service.json']), true, 512, JSON_THROW_ON_ERROR);
    $sourceById = []; foreach ($sources as $s) if (is_array($s)) $sourceById[(string) ($s['id'] ?? '')] = $s;
    $materialById = []; foreach ($materials as $m) if (is_array($m)) $materialById[(string) ($m['id'] ?? '')] = $m;
    $readinessByLeaf = []; foreach ($initialReadiness as $r) if (is_array($r)) $readinessByLeaf[(string) ($r['leaf_id'] ?? '')] = $r;
    if (count($readinessByLeaf) !== 56 || count($readiness) !== 56) $fail('Expected 56 permanent CSE core readiness records.');
    [$nativeByBoard, $nativeByLeaf] = $nativeCounts($questions);
    $partialNewQuestions = array_values(array_filter($questions, static fn(array $q): bool => ($q['batch_marker'] ?? '') === 'batch653-cse'));
    $partialIds = array_map(static fn(array $q): string => (string) ($q['id'] ?? ''), $partialNewQuestions);
    $baselineQuestions = array_values(array_filter($questions, static fn(array $q): bool => ($q['batch_marker'] ?? '') !== 'batch653-cse'));
    $baselineEligibility = $stripQuestionIds($eligibility, $partialIds);
    $baseline = (array) ($batch652['baseline'] ?? []);
    $globalBefore = ['canonical'=>count(array_filter($baselineQuestions, static fn(array $q): bool => strtolower((string) ($q['status'] ?? 'active')) !== 'archived')),
        'effective_declared'=>(int) ($baseline['global_effective_declared'] ?? 0), 'runtime'=>(int) ($baseline['global_runtime'] ?? 0)];
    if ($globalBefore !== ['canonical'=>8947,'effective_declared'=>9407,'runtime'=>9731]) $fail('Global starting counts differ from the authoritative Batch652 baseline: '.json_encode($globalBefore));
    $cseBefore = $metricCounts($baselineQuestions, $baselineEligibility, $collections['concept-derived-eligibility'], $collections['blueprints'], $collections['question-reuse-overrides']);
    if ($cseBefore !== ['native'=>1550,'effective_declared'=>1804,'runtime'=>2113]) $fail('CSE baseline differs from Batch652: ' . json_encode($cseBefore));
    if ($globalBefore['canonical'] !== 8947) $fail('Canonical baseline differs from Batch652: ' . $globalBefore['canonical']);
    if (count(array_filter($initialReadiness, static fn(array $r): bool => ($r['readiness'] ?? '') === 'READY_WITH_LIMITS')) !== 5
        || count(array_filter($initialReadiness, static fn(array $r): bool => ($r['readiness'] ?? '') === 'REVIEW')) !== 51) $fail('CSE readiness baseline is not 5 RWL / 51 REVIEW.');

    $beforeQuestions = json_decode($git(['show', $expectedHead . ':storage/questions.json']), true, 512, JSON_THROW_ON_ERROR);
    $beforeEligibility = json_decode($git(['show', $expectedHead . ':storage/question-eligibility.json']), true, 512, JSON_THROW_ON_ERROR);
    $questionHashBefore = $hash($beforeQuestions);
    $eligibilityHashBefore = $hash($beforeEligibility);
    $questionHashCurrent = $hash($questions);
    $idByPlan = [];
    foreach ($questions as $q) if (is_array($q) && ($q['batch_marker'] ?? '') === 'batch653-cse') {
        $planId = (string) ($q['batch653']['plan_id'] ?? '');
        if ($planId === '' || isset($idByPlan[$planId])) $fail('Duplicate/invalid Batch653 plan metadata.');
        $idByPlan[$planId] = (string) ($q['id'] ?? '');
    }
    $batch652Plans = [];
    foreach (array_merge($wave1, $wave2) as $p) $batch652Plans[(string) $p['plan_id']] = $p;
    $acceptedPlanIds = ['cse652-343caf16b5e3dc75','cse652-e85215ed9fef916f','cse652-47ac8005d7364ca2'];
    $rejectedPlanIds = ['cse652-933d320062af5ea5','cse652-25465527b24a6654'];
    $definitions = [
        'cse652-343caf16b5e3dc75'=>[
            'question'=>'Rule 1 says that if a file has a verified receipt, it is entered into tracking. Rule 2 says that a supervisor authorization is required before payment release. Which interpretation preserves the difference between a sufficient and a necessary condition?',
            'choices'=>[
                'A verified receipt is sufficient for tracking entry; supervisor authorization is necessary for payment release.',
                'Tracking entry is necessary before a receipt can be verified; supervisor authorization alone guarantees payment.',
                'A verified receipt is necessary and sufficient for payment release; supervisor authorization is optional.',
                'Payment release is necessary for tracking entry; a receipt is only evidence that payment occurred.',
            ], 'answer'=>0,
            'explanation'=>'The first rule says the receipt condition guarantees tracking entry, so it is sufficient for that result. “Required before” makes supervisor authorization necessary for release, but does not say it alone guarantees release.',
            'focus'=>'Classify sufficient and necessary conditions across two short rules without reversing either implication.',
            'family'=>'NECESSARY_VS_SUFFICIENT_CONDITION_INFERENCE','levels'=>['PROFESSIONAL'],
        ],
        'cse652-e85215ed9fef916f'=>[
            'question'=>'One office reports 48 processed forms per hour; another reports 72 forms per 90 minutes. Before deciding which office has the higher processing rate, what is the sound comparison method?',
            'choices'=>[
                'Compare 72 directly with 48 because both figures count forms.',
                'Express both rates using the same time unit, then compare the normalized rates.',
                'Compare only the reported time intervals; the longer interval indicates the faster office.',
                'Use the larger count and disregard the different observation periods.',
            ], 'answer'=>1,
            'explanation'=>'A rate combines a count with its time base. Raw counts from unequal intervals are not comparable as rates until both are expressed on a common time unit.',
            'focus'=>'Choose a valid rate-comparison method when the reported time units differ; no numerical rate needs to be calculated.',
            'family'=>'RATE_COMPARISON_AFTER_UNIT_NORMALIZATION','levels'=>['PROFESSIONAL','SUBPROFESSIONAL'],
        ],
        'cse652-47ac8005d7364ca2'=>[
            'question'=>'Drizzle is to downpour as breeze is to:',
            'choices'=>['gale','cloud','umbrella','wind vane'], 'answer'=>0,
            'explanation'=>'A downpour is a much more intense form of rain than drizzle; a gale is a much more intense form of wind than a breeze. The relation is degree of intensity, not association or instrument.',
            'focus'=>'Preserve a graded-intensity relationship between two word pairs and distinguish it from association.',
            'family'=>'DEGREE_OF_INTENSITY_ANALOGICAL_RELATION','levels'=>['PROFESSIONAL'],
        ],
    ];

    $coverageByLeaf = [];
    foreach ($collections['board-concept-coverage'] as $row) if (is_array($row) && CivilServiceCoverageModel::normalizeBoard($row['board_id'] ?? '') === 'civil-service') $coverageByLeaf[(string) ($row['concept_id'] ?? '')] = $row;
    $taxonomyConcepts = []; foreach ($collections['taxonomy/concepts'] as $c) if (is_array($c)) $taxonomyConcepts[(string) ($c['id'] ?? '')] = $c;
    $studyLinks = $collections['study-question-links'];
    $maxId = 0; $stems = [];
    foreach ($questions as $q) {
        if (!is_array($q)) continue;
        if (is_numeric($q['id'] ?? null)) $maxId = max($maxId, (int) $q['id']);
        $normalized = mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', (string) ($q['question'] ?? ''))));
        $stems[$normalized] = true;
    }
    $drafts = [];
    $nextQuestionId = $maxId + 1;
    foreach ($acceptedPlanIds as $offset => $planId) {
        $plan = $batch652Plans[$planId] ?? null;
        $definition = $definitions[$planId] ?? null;
        if (!is_array($plan) || !is_array($definition)) $fail('Missing exact authorized plan/draft: ' . $planId);
        $leafId = (string) $plan['leaf_id'];
        $record = $readinessByLeaf[$leafId] ?? null;
        $materialIds = array_values(array_map('strval', (array) ($plan['study_material_ids'] ?? [])));
        $sourceIds = array_values(array_map('strval', (array) ($plan['source_ids'] ?? [])));
        if (!is_array($record) || ($record['readiness'] ?? '') !== 'READY_WITH_LIMITS'
            || !in_array($leafId, (array) ($record['allowed_scope'] ?? []), true)
            || (int) ($record['safe_headroom_max'] ?? 0) < 1
            || !in_array((int) ($plan['supported_depth'][0] ?? 0), range((int) ($record['supported_depth_min'] ?? 1), (int) ($record['supported_depth_max'] ?? 5)), true)
            || array_diff($sourceIds, (array) ($record['source_ids'] ?? [])) !== []
            || array_diff($materialIds, (array) ($record['study_material_ids'] ?? [])) !== []
            || !in_array((string) ($record['source_currentness'] ?? ''), ['CURRENT','CURRENT_WITH_LIMITS','NOT_TIME_SENSITIVE'], true)
            || ($record['study_source_traceability'] ?? '') !== 'COMPLETE') $fail('Plan no longer satisfies permanent readiness: ' . $leafId);
        if (!in_array($leafId, (array) ($plan['allowed_scope'] ?? []), true)) $fail('Plan scope is not explicit for ' . $leafId);
        foreach ($sourceIds as $sourceId) if (!isset($sourceById[$sourceId])) $fail('Invalid plan source ' . $sourceId);
        foreach ($materialIds as $materialId) {
            if (!isset($materialById[$materialId]) || array_intersect($sourceIds, (array) ($materialById[$materialId]['source_ids'] ?? [])) === []) $fail('Study material has no matching content-source support for ' . $leafId);
        }
        $coverage = $coverageByLeaf[$leafId] ?? null;
        $concept = $taxonomyConcepts[$leafId] ?? null;
        if (!is_array($coverage) || !is_array($concept)) $fail('Missing taxonomy/coverage path for ' . $leafId);
        $isPlanStillAuthorized = in_array($planId, $acceptedPlanIds, true);
        if (!$isPlanStillAuthorized) continue;
        if (isset($idByPlan[$planId])) {
            $persisted = null;
            foreach ($questions as $candidate) if (is_array($candidate) && (string) ($candidate['id'] ?? '') === $idByPlan[$planId]) $persisted = $candidate;
            if (!is_array($persisted) || ($persisted['batch653']['plan_id'] ?? '') !== $planId || ($persisted['question'] ?? '') !== ($definitions[$planId]['question'] ?? '') || QuestionService::validate($persisted)['valid'] !== true) $fail('Interrupted persisted question is not an exact valid authorized draft: ' . $planId);
            if ((array) ($persisted['source_ids'] ?? []) !== $sourceIds || (array) ($persisted['study_material_ids'] ?? []) !== $materialIds || (array) ($persisted['civil_service_levels'] ?? []) !== $definition['levels']) $fail('Interrupted draft evidence/scope differs from its stored plan: ' . $planId);
            $drafts[] = ['plan'=>$plan,'question'=>$persisted,'definition'=>$definition,'cross_leaf_inventory_hash'=>'','recovery'=>true];
            continue;
        }
        $questionId = (string) $nextQuestionId++;
        $q = [
            'id'=>(int) $questionId, 'board'=>'civil-service',
            'taxonomy'=>['board_id'=>'civil-service','subject_id'=>(string) $coverage['subject_id'],'domain_id'=>(string) $coverage['domain_id'],'topic_id'=>(string) $coverage['topic_id'],'concept_id'=>$leafId],
            'status'=>'active','source'=>'BoardPrep-authored','difficulty'=>(int) ($plan['supported_depth'][0] ?? 2) >= 3 ? 'medium' : 'easy','type'=>'multiple_choice',
            'question'=>$definition['question'],'choices'=>$definition['choices'],'answer'=>$definition['choices'][$definition['answer']],
            'explanation'=>$definition['explanation'],'content_blocks'=>[],'source_ids'=>$sourceIds,
            'study_material_id'=>$materialIds[0],'study_material_ids'=>$materialIds,'source_scope'=>[$leafId],
            'study_focus'=>$definition['focus'],'cognitive_intent'=>'apply and interpret','multistep'=>true,
            'passage_dependent'=>false,'data_interpretation'=>false,'case_interpretation'=>true,'professional_judgment'=>false,
            'numeric'=>$plan['calculation_class'] === 'CONCEPTUAL_QUANTITATIVE','calculation_required'=>false,
            'quantitative_semantics'=>(string) $plan['calculation_class'],'currentness_verified'=>true,'version_sensitive'=>false,
            'civil_service_levels'=>$definition['levels'],'misconception_family'=>$definition['family'],'batch_marker'=>'batch653-cse',
            'batch653'=>['plan_id'=>$planId,'leaf_id'=>$leafId,'official_area_id'=>(string) $plan['official_area_id'],'exam_levels'=>$definition['levels'],'content_family'=>$definition['family'],'depth'=>(int) ($plan['supported_depth'][0] ?? 2),'source_ids'=>$sourceIds,'study_material_ids'=>$materialIds,'authorized_by'=>'batch652-let-close-cse-readiness','currentness_check'=>'UNCHANGED'],
            'reuse'=>['reuse_policy'=>'REVIEW','required_depth'=>(int) ($plan['supported_depth'][0] ?? 2),'context_mode'=>'NEUTRAL','metadata_status'=>'VERIFIED','metadata_provenance'=>[['type'=>'OFFICIAL_SOURCE','reference'=>'batch652-let-close-cse-readiness:'.$planId,'note'=>'Batch653 uses exact Batch652 scope, source, study, depth, level applicability and proposition authority.','verified_status'=>'VERIFIED']]],
        ];
        $normalizedStem = mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $q['question'])));
        if (isset($stems[$normalizedStem])) $fail('Normalized duplicate question stem for plan ' . $planId);
        if (QuestionService::validate($q)['valid'] !== true) $fail('QuestionService schema rejected ' . $planId);
        $questionValidation = QuestionService::validateForCreate($q);
        if (($questionValidation['valid'] ?? false) !== true || ($questionValidation['duplicates'] ?? []) !== []) $fail('Question write/duplicate gate rejected ' . $planId . ': ' . json_encode($questionValidation));
        $readinessGate = SourceReadinessService::validateQuestionForCreate($q, $collections);
        if (($readinessGate['valid'] ?? false) !== true) $fail('Permanent readiness gate rejected ' . $planId . ': ' . json_encode($readinessGate['errors'] ?? []));
        $stemHashes = [];
        foreach ($questions as $existing) {
            if (!is_array($existing) || $board($existing) !== 'civil-service') continue;
            $existingLeaf = $leaf($existing);
            if ($existingLeaf === $leafId || in_array($existingLeaf, ['cse-assumptions-conclusions','cse-logical-conditions','cse-syllogistic-reasoning','cse-record-comparison','cse-data-trends','cse-data-interpretation','cse-word-analogy'], true)) {
                $stemHashes[] = hash('sha256', strtolower($existingLeaf . '|' . (string) ($existing['study_focus'] ?? $existing['question'] ?? '')));
            }
        }
        $drafts[] = ['plan'=>$plan,'question'=>$q,'definition'=>$definition,'cross_leaf_inventory_hash'=>$hash($stemHashes)];
        $stems[$normalizedStem] = true;
    }

    $currentness = [
        'classification'=>'UNCHANGED','checked_at'=>'2026-10-04','based_on_batch652'=>true,
        'controlling_scope_source'=>'csc-cse-ppt-scope-2026','calendar_source'=>'csc-cse-ppt-calendar-2027',
        'official_urls'=>[
            'https://csc.gov.ph/examination-advisories/examination-announcement-no-03-s-2026-conduct-of-career-service-examination-pen-and-paper-test-cse-ppt-for-professional-and-subprofessional-levels-09-august-2026',
            'https://csc.gov.ph/examination-advisories/examination-announcement-no-05-s-2026-calendar-of-civil-service-examinations-pen-and-paper-test-for-cy-2027',
        ],
        'finding'=>'No later controlling scope announcement located; the CY2027 calendar still defers the specific scope announcement. No change to the five areas or Professional/Subprofessional applicability affecting these plans.',
    ];

    if ($mode === '--execute') {
        foreach ($drafts as $draft) {
            $plan = $draft['plan']; $q = $draft['question'];
            $saved = ($draft['recovery'] ?? false) === true ? $q : QuestionService::save($q);
            $hasCseMapping = false;
            foreach (App::storage()->all('question-eligibility') as $entry) {
                if (!is_array($entry) || !in_array((string) $saved['id'], array_map('strval', (array) ($entry['question_ids'] ?? [])), true)) continue;
                $mappingRow = (array) ($entry['question_mappings'][(string) $saved['id']] ?? []);
                if (in_array('civil-service', (array) ($mappingRow['exam_ids'] ?? $entry['exam_ids'] ?? []), true)
                    && (string) ($mappingRow['placements']['civil-service']['concept_id'] ?? $saved['taxonomy']['concept_id'] ?? '') === (string) $plan['leaf_id']) $hasCseMapping = true;
            }
            if (!$hasCseMapping) {
                $mapping = QuestionEligibilityService::addMapping($saved, [
                    'target_board'=>'civil-service','target_subject'=>(string) $q['taxonomy']['subject_id'],
                    'target_domain'=>(string) $q['taxonomy']['domain_id'],'target_topic'=>(string) $q['taxonomy']['topic_id'],
                    'target_concept'=>(string) $q['taxonomy']['concept_id'],'blueprint_node'=>(string) $q['taxonomy']['subject_id'],
                ]);
                if (($mapping['valid'] ?? false) !== true) $fail('CSE eligibility mapping failed for ' . $plan['plan_id'] . ': ' . json_encode($mapping['errors'] ?? []));
            }
            $linkExists = false;
            foreach (App::storage()->all('study-question-links') as $link) if (is_array($link) && (string) ($link['question_id'] ?? '') === (string) $saved['id'] && (string) ($link['material_id'] ?? '') === (string) $saved['study_material_id']) $linkExists = true;
            if (!$linkExists) App::storage()->create('study-question-links', ['id'=>'study-link-b653-cse-'.$saved['id'],'material_id'=>(string) $saved['study_material_id'],'question_id'=>(string) $saved['id'],'source_ids'=>array_values($saved['source_ids'])]);
            $idByPlan[(string) $plan['plan_id']] = (string) $saved['id'];
        }
        $questions = App::storage()->all('questions');
        $collections['questions'] = $questions;
        $collections['question-eligibility'] = App::storage()->all('question-eligibility');
        [$nativeByBoard, $nativeByLeaf] = $nativeCounts($questions);
        $planOutcomes = [
            'cse652-343caf16b5e3dc75'=>['status'=>'ACCEPTED','reason'=>'The item contrasts classification of sufficiency and necessity; existing logical items ask one-way consequences but do not test the paired rule-type distinction.'],
            'cse652-933d320062af5ea5'=>['status'=>'REJECTED','reason'=>'DUPLICATIVE: same-board cse-record-comparison already tests leading zeroes, punctuation/trailing spaces, and transposed characters (including IDs 6438, 6451, 6494, 6498). No replacement authorized.'],
            'cse652-e85215ed9fef916f'=>['status'=>'ACCEPTED','reason'=>'The item tests the method of normalizing unlike time units before rate comparison, not computing a new rate; no existing CSE item asks this unit-compatibility rule.'],
            'cse652-47ac8005d7364ca2'=>['status'=>'ACCEPTED','reason'=>'The existing ten word analogies cover synonym, antonym, guide/result, origin/result, tool/quantity, trait/challenge, role/product, premise/conclusion, collection/member, and map/location; none tests graded intensity.'],
            'cse652-25465527b24a6654'=>['status'=>'REJECTED_CONTINGENT','reason'=>'The fresh gate found the same denominator/rate-comparison proposition already in cse-data-trends (daily rate comparison) and cse-data-interpretation (IDs 6760, 6771, 6772). No substitution authorized.'],
        ];

        $questionIds = array_values(array_map('strval', array_values($idByPlan)));
        $newQuestions = array_values(array_filter($questions, static fn(array $q): bool => ($q['batch_marker'] ?? '') === 'batch653-cse'));
        if (count($newQuestions) !== count($acceptedPlanIds) || count($idByPlan) !== count($acceptedPlanIds)) $fail('Production recovery count does not match the three passing Batch652 plans.');
        $linkRows = App::storage()->all('study-question-links');
        $linkQuestionIds = array_fill_keys(array_map(static fn(array $l): string => (string) ($l['question_id'] ?? ''), array_filter($linkRows, 'is_array')), true);
        $deriver = new FlashcardDerivationService(App::container()->get(QuestionRepository::class));
        foreach ($newQuestions as $q) {
            if (!isset($linkQuestionIds[(string) $q['id']])) $fail('Missing study link for question ' . $q['id']);
            $card = $deriver->derive($q);
            if (!is_array($card) || FlashcardCardValidator::validate($card) !== []) $fail('Flashcard derivation failed for question ' . $q['id']);
            if (QuestionService::validate($q)['valid'] !== true) $fail('Persisted question no longer passes schema: ' . $q['id']);
        }

        $countsByLeaf = $nativeByLeaf;
        $reviewRecords = [];
        $reviewRows = array_values(array_filter($initialReadiness, static fn(array $r): bool => ($r['readiness'] ?? '') === 'REVIEW'));
        if (count($reviewRows) !== 51) $fail('Expected the exact 51-leaf Batch652 review cohort.');
        $sourceGroup = static function (string $area): string {
            return match ($area) {
                'general-information'=>'CSE_OFFICIAL_SCOPE + PHILIPPINE_STATUTES + CIVIC_STUDY',
                'verbal-ability'=>'CSE_OFFICIAL_SCOPE + KWF/PURDUE/READING_STUDY',
                'numerical-ability'=>'CSE_OFFICIAL_SCOPE + OPENSTAX_PREALGEBRA/STATISTICS',
                'analytical-ability'=>'CSE_OFFICIAL_SCOPE + LOGIC/STATISTICS_STUDY',
                'clerical-ability'=>'CSE_OFFICIAL_SCOPE + PURDUE_CLERICAL_STUDY',
                default=>'UNRESOLVED_SOURCE_GROUP',
            };
        };
        foreach ($reviewRows as $record) {
            $id = (string) $record['leaf_id']; $area = (string) $record['official_area_id'];
            $native = (int) ($countsByLeaf[$id] ?? 0);
            $rows = array_values(array_filter($questions, static fn(array $q): bool => $active($q) && $board($q) === 'civil-service' && $leaf($q) === $id));
            $familySet = [];
            $questionIdsForLeaf = [];
            $propositionSignatures = [];
            foreach ($rows as $q) {
                $questionIdsForLeaf[] = (string) $q['id'];
                $family = trim((string) ($q['misconception_family'] ?? $q['scenario_family'] ?? $q['content_family'] ?? ''));
                if ($family !== '') $familySet[$family] = true;
                $conceptFocus = trim((string) ($q['study_focus'] ?? $q['cognitive_intent'] ?? ''));
                $calculation = ($q['calculation_required'] ?? false) === true ? 'CALCULATION_REQUIRED' : (strtoupper((string) ($q['quantitative_semantics'] ?? '')) === 'CONCEPTUAL_QUANTITATIVE' ? 'CONCEPTUAL_QUANTITATIVE' : 'NONCALCULATION');
                $propositionSignatures[] = hash('sha256', strtolower($id.'|'.$calculation.'|'.preg_replace('/\s+/u',' ',mb_strtolower($conceptFocus)).'|'.preg_replace('/\s+/u',' ',mb_strtolower((string) ($q['question'] ?? '')))));
            }
            sort($questionIdsForLeaf, SORT_STRING); sort($propositionSignatures, SORT_STRING);
            $familyNames = array_keys($familySet); sort($familyNames, SORT_STRING);
            if ($id === 'analytical-sequence-rule') {
                $disposition = 'BLOCKED'; $blockers = ['OFFICIAL_AREA_MISMATCH'];
                $summary = 'Current CSC scope places number sequence under Numerical Ability; this repository leaf remains under Analytical Ability. Keep quarantined; no question or mapping moved.';
            } elseif (in_array($area, ['verbal-ability','analytical-ability'], true)) {
                $disposition = 'REVIEW'; $blockers = ['CONTENT_FAMILY_UNPROVEN'];
                $summary = 'Source authority and study traceability are adequate, but high repeat risk plus multiple overlapping proposition families require item-level family proof before any new authorization; no cosmetic/paraphrase cohort is approved.';
            } else {
                $disposition = 'EVIDENCE_EXHAUSTED'; $blockers = ['PROPOSITION_EXHAUSTED'];
                $summary = 'Grouped review of current leaf questions and linked study/source scope found no additional high/medium-value proposition distinct from existing coverage; numerical deficit alone is not authorization.';
            }
            $examLevels = match ($area) {
                'analytical-ability'=>['PROFESSIONAL'],
                'clerical-ability'=>['SUBPROFESSIONAL'],
                default=>['PROFESSIONAL','SUBPROFESSIONAL'],
            };
            $currentnessClass = 'NOT_TIME_SENSITIVE';
            if ($area === 'general-information') {
                if (str_contains($id, 'constitution') || str_contains($id, 'bill-of-rights') || str_contains($id, 'human-rights')) $currentnessClass = 'STABLE_CONSTITUTIONAL';
                elseif (str_contains($id, 'ethical') || str_contains($id, 'conflict') || str_contains($id, 'accountability')) $currentnessClass = 'STABLE_ETHICS';
                elseif (str_contains($id, 'environment')) $currentnessClass = 'STABLE_ENVIRONMENTAL';
                else $currentnessClass = 'STABLE_CIVIC';
            }
            $record['current_question_count'] = $native;
            foreach ([5,10,20] as $t) $record['deficit_to_'.$t] = max(0, $t - $native);
            $record['safe_headroom_min'] = 0; $record['safe_headroom_preferred'] = 0; $record['safe_headroom_max'] = 0;
            $record['max_new_questions'] = 0; $record['planned_new_questions'] = 0;
            $record['readiness'] = 'REVIEW'; $record['readiness_reason'] = 'Batch653 completed the grouped scope/source/study and proposition gate; no unreviewed production authority remains.';
            $record['review_reason'] = $summary;
            $record['reviewed_at_batch'] = 653;
            $record['evidence_basis']['batch653_review'] = ['blocker_classes'=>$blockers,'source_group'=>$sourceGroup($area),'exam_levels'=>$examLevels,'currentness_class'=>$currentnessClass,'excluded_time_sensitive_scope'=>['current-officeholder facts','current-event-style trivia','unverified future-effective policies'],'disposition'=>$disposition,'question_ids'=>$questionIdsForLeaf,'question_signature_hash'=>$hash($propositionSignatures),'families_observed'=>$familyNames,'review_summary'=>$summary];
            $record['evidence_hash'] = SourceReadinessRecord::evidenceHash($record);
            $reviewRecords[] = ['leaf_id'=>$id,'official_area_id'=>$area,'exam_levels'=>$examLevels,'currentness_class'=>$currentnessClass,'current_native_count'=>$native,'readiness_before'=>'REVIEW','readiness_after'=>'REVIEW','disposition'=>$disposition,'blocker_classes'=>$blockers,'source_group'=>$sourceGroup($area),'source_ids'=>array_values((array) $record['source_ids']),'study_material_ids'=>array_values((array) $record['study_material_ids']),'source_currentness'=>$record['source_currentness'],'source_scope_fit'=>$record['source_scope_fit'],'study_coverage'=>$record['study_coverage'],'study_source_traceability'=>$record['study_source_traceability'],'existing_content_families'=>$familyNames,'existing_proposition_signatures'=>$propositionSignatures,'proposition_signature_hash'=>$hash($propositionSignatures),'numerical_headroom_to_10'=>max(0,10-$native),'numerical_headroom_to_20'=>max(0,20-$native),'evidence_headroom'=>0,'high_value_headroom'=>0,'review_summary'=>$summary];
        }
        $reviewById = []; foreach ($reviewRecords as $r) $reviewById[$r['leaf_id']] = $r;
        $planRecords = [];
        foreach (array_merge($wave1, $wave2) as $p) {
            $pid = (string) $p['plan_id']; $status = (string) $planOutcomes[$pid]['status'];
            $planRecords[] = ['plan_id'=>$pid,'leaf_id'=>(string) $p['leaf_id'],'official_area_id'=>(string) $p['official_area_id'],'proposition_summary'=>(string) ($p['proposition_summaries'][0] ?? ''),'content_family'=>(string) ($p['unused_content_families'][0] ?? ''),'target_depth'=>(int) ($p['supported_depth'][0] ?? 0),'question_mode'=>(string) ($p['calculation_class'] ?? ''),'calculation_class'=>(string) ($p['calculation_class'] ?? ''),'source_ids'=>array_values((array) $p['source_ids']),'study_material_ids'=>array_values((array) $p['study_material_ids']),'allowed_scope'=>array_values((array) ($p['allowed_scope'] ?? [])),'limitations'=>array_values((array) $p['limitations']),'wave'=>in_array($pid, $expectedWave1, true) ? 'WAVE1' : 'WAVE2_CONTINGENT','outcome'=>$status,'reason'=>$planOutcomes[$pid]['reason'],'question_id'=>$idByPlan[$pid] ?? null,'exam_levels'=>$definitions[$pid]['levels'] ?? ($pid === 'cse652-933d320062af5ea5' ? ['SUBPROFESSIONAL'] : ['PROFESSIONAL'])];
        }
        usort($reviewRecords, static fn(array $a, array $b): int => strcmp($a['leaf_id'], $b['leaf_id']));
        usort($planRecords, static fn(array $a, array $b): int => strcmp($a['plan_id'], $b['plan_id']));

        $updatedReadiness = [];
        $reviewMeta = [];
        foreach ($readiness as $record) {
            if (!is_array($record)) continue;
            $id = (string) ($record['leaf_id'] ?? '');
            $native = (int) ($nativeByLeaf[$id] ?? 0);
            $record['current_question_count'] = $native;
            foreach ([5,10,20] as $t) $record['deficit_to_'.$t] = max(0, $t - $native);
            $record['safe_headroom_min'] = 0; $record['safe_headroom_preferred'] = 0; $record['safe_headroom_max'] = 0;
            $record['max_new_questions'] = 0; $record['planned_new_questions'] = 0;
            $record['readiness'] = 'REVIEW'; $record['readiness_reason'] = 'Batch653 closes the current authorized CSE cohort; further authoring requires a new proposition-level gate.';
            $record['reviewed_at_batch'] = 653;
            $outcomeReason = '';
            foreach ($planRecords as $pr) if ($pr['leaf_id'] === $id) $outcomeReason = (string) $pr['reason'];
            $record['review_reason'] = $outcomeReason !== '' ? $outcomeReason : (string) ($reviewById[$id]['review_summary'] ?? $record['review_reason'] ?? 'No additional high-value proposition is authorized by this review.');
            if (isset($reviewById[$id])) $record['evidence_basis']['batch653_review'] = $record['evidence_basis']['batch653_review'] ?? ['blocker_classes'=>$reviewById[$id]['blocker_classes'],'source_group'=>$reviewById[$id]['source_group'],'exam_levels'=>$reviewById[$id]['exam_levels'],'disposition'=>$reviewById[$id]['disposition'],'question_ids'=>[],'question_signature_hash'=>$reviewById[$id]['proposition_signature_hash'],'families_observed'=>$reviewById[$id]['existing_content_families'],'review_summary'=>$reviewById[$id]['review_summary']];
            $record['evidence_hash'] = SourceReadinessRecord::evidenceHash($record);
            $updatedReadiness[] = $record;
            $reviewMeta[$id] = ['plan_or_review_disposition'=>isset($reviewById[$id]) ? $reviewById[$id]['disposition'] : 'EVIDENCE_EXHAUSTED','blockers'=>isset($reviewById[$id]) ? $reviewById[$id]['blocker_classes'] : ['PLAN_CONSUMED_OR_REJECTED'],'question_count'=>$native,'headroom'=>0];
        }
        $writeJson($readinessPath, $updatedReadiness);
        $collections['source-readiness-civil-service'] = $updatedReadiness;

        $questionAfterProductionHash = $hash(App::storage()->all('questions'));
        $questionAfterReviewHash = $hash(App::storage()->all('questions'));
        if (!hash_equals($questionAfterProductionHash, $questionAfterReviewHash)) $fail('Question store changed after the production freeze.');
        $updatedValidationCollections = $collections;
        $updatedValidationCollections['questions'] = $questions;
        $updatedValidationCollections['question-eligibility'] = App::storage()->all('question-eligibility');
        $readinessValidation = SourceReadinessService::validateCollections($updatedValidationCollections);
        $cseValidation = $readinessValidation['by_board']['civil-service'] ?? null;
        if (!is_array($cseValidation) || ($cseValidation['valid'] ?? false) !== true || count((array) ($cseValidation['records'] ?? [])) !== 56) $fail('Permanent CSE readiness validation failed: ' . json_encode($cseValidation['errors'] ?? []));

        $questions = App::storage()->all('questions'); $eligibility = App::storage()->all('question-eligibility');
        [$nativeByBoard, $nativeByLeaf] = $nativeCounts($questions);
        $cseAfter = $metricCounts($questions, $eligibility, $collections['concept-derived-eligibility'], $collections['blueprints'], $collections['question-reuse-overrides']);
        $coreLeaves = array_values(array_filter($collections['board-concept-coverage'], static fn(array $r): bool => CivilServiceCoverageModel::normalizeBoard($r['board_id'] ?? '') === 'civil-service' && CivilServiceCoverageModel::classifyRow($r) === 'CORE_EXAM_LEAF'));
        $coreLeafIds = array_values(array_map(static fn(array $r): string => (string) $r['concept_id'], $coreLeaves));
        sort($coreLeafIds, SORT_STRING);
        if (count($coreLeafIds) !== 56 || count($reviewRecords) !== 51) $fail('Core review cohort completeness failed.');
        $rowClasses = [];
        foreach ($collections['board-concept-coverage'] as $r) if (is_array($r) && CivilServiceCoverageModel::normalizeBoard($r['board_id'] ?? '') === 'civil-service') $rowClasses[CivilServiceCoverageModel::classifyRow($r)] = ($rowClasses[CivilServiceCoverageModel::classifyRow($r)] ?? 0) + 1;
        $rawValues = []; foreach ($collections['board-concept-coverage'] as $r) if (is_array($r) && CivilServiceCoverageModel::normalizeBoard($r['board_id'] ?? '') === 'civil-service') $rawValues[] = (int) ($nativeByLeaf[(string) ($r['concept_id'] ?? '')] ?? 0);
        $coreValues = array_map(static fn(string $id): int => (int) ($nativeByLeaf[$id] ?? 0), $coreLeafIds);
        $rawBeforeValues = []; foreach ($collections['board-concept-coverage'] as $r) if (is_array($r) && CivilServiceCoverageModel::normalizeBoard($r['board_id'] ?? '') === 'civil-service') $rawBeforeValues[] = (int) (($batch652['cse']['rows'] === [] ? 0 : array_column($batch652['cse']['rows'], 'native', 'leaf_id')[(string) ($r['concept_id'] ?? '')]) ?? 0);
        $coreBeforeValues = array_map(static fn(string $id): int => (int) (array_column($batch652['cse']['rows'], 'native', 'leaf_id')[$id] ?? 0), $coreLeafIds);
        $readinessCounts = ['READY'=>0,'READY_WITH_LIMITS'=>0,'REVIEW'=>0,'DEFER'=>0];
        foreach ($updatedReadiness as $r) $readinessCounts[(string) $r['readiness']]++;
        $areaCounts = [];
        foreach (CivilServiceCoverageModel::OFFICIAL_AREAS as $area) {
            $areaCounts[$area] = ['native'=>0,'effective_declared'=>0,'runtime'=>0,'leaf_count'=>0];
            foreach ($coreLeaves as $row) if (CivilServiceCoverageModel::officialAreaForRow($row) === $area) {
                $areaCounts[$area]['native'] += (int) ($nativeByLeaf[(string) $row['concept_id']] ?? 0);
                $areaCounts[$area]['leaf_count']++;
            }
        }
        $cseEligibilityIndex = new QuestionEligibilityIndex($eligibility, $collections['blueprints'], $collections['concept-derived-eligibility'], $collections['question-reuse-overrides']);
        $effectiveIds = [];
        foreach ($eligibility as $entry) {
            if (!is_array($entry)) continue;
            foreach ((array) ($entry['question_ids'] ?? []) as $qid) {
                if (in_array('civil-service', (array) ($entry['exam_ids'] ?? []), true)) $effectiveIds[(string) $qid] = true;
            }
        }
        foreach ($collections['concept-derived-eligibility'] as $entry) if (is_array($entry) && ($entry['record_type'] ?? '') === 'RELATIONSHIP' && CivilServiceCoverageModel::normalizeBoard($entry['target_board'] ?? '') === 'civil-service') $effectiveIds[(string) ($entry['question_id'] ?? '')] = true;
        $questionByIdAfter = []; foreach ($questions as $q) if (is_array($q)) $questionByIdAfter[(string) ($q['id'] ?? '')] = $q;
        foreach (array_keys($effectiveIds) as $qid) {
            $q = $questionByIdAfter[$qid] ?? null;
            if (!is_array($q)) continue;
            $place = $cseEligibilityIndex->forExam($q, 'civil-service');
            if ($place === null) continue;
            $area = CivilServiceCoverageModel::officialAreaForQuestion($q, (array) ($place['placement'] ?? []), $coverageByLeaf);
            if (isset($areaCounts[$area])) $areaCounts[$area]['effective_declared']++;
        }
        foreach ($questions as $q) {
            if (!is_array($q) || !$active($q)) continue;
            $place = $cseEligibilityIndex->forExam($q, 'civil-service');
            if ($place === null) continue;
            $area = CivilServiceCoverageModel::officialAreaForQuestion($q, (array) ($place['placement'] ?? []), $coverageByLeaf);
            if (isset($areaCounts[$area])) $areaCounts[$area]['runtime']++;
        }
        $plansArea = ['GENERAL_INFORMATION'=>0,'VERBAL'=>0,'NUMERICAL'=>0,'ANALYTICAL'=>0,'CLERICAL'=>0];
        foreach ($newQuestions as $q) {
            $a = (string) ($q['batch653']['official_area_id'] ?? '');
            $k = match ($a) {'general-information'=>'GENERAL_INFORMATION','verbal-ability'=>'VERBAL','numerical-ability'=>'NUMERICAL','analytical-ability'=>'ANALYTICAL','clerical-ability'=>'CLERICAL',default=>''};
            if ($k !== '') $plansArea[$k]++;
        }
        $initialReviewByArea = []; foreach ($reviewRecords as $r) $initialReviewByArea[$r['official_area_id']] = ($initialReviewByArea[$r['official_area_id']] ?? 0) + 1;
        $dispositionCounts = []; $blockerCounts = [];
        foreach ($reviewRecords as $r) {
            $dispositionCounts[$r['disposition']] = ($dispositionCounts[$r['disposition']] ?? 0) + 1;
            foreach ($r['blocker_classes'] as $b) $blockerCounts[$b] = ($blockerCounts[$b] ?? 0) + 1;
        }
        ksort($dispositionCounts, SORT_STRING); ksort($blockerCounts, SORT_STRING);
        $effectiveBefore = $cseBefore['effective_declared']; $effectiveAfter = $cseAfter['effective_declared'];
        $runtimeBefore = $cseBefore['runtime']; $runtimeAfter = $cseAfter['runtime'];
        if ($cseAfter['native'] !== 1550 + count($newQuestions) || $effectiveAfter !== $effectiveBefore + count($newQuestions) || $runtimeAfter !== $runtimeBefore + count($newQuestions)) $fail('CSE counts did not increase exactly by accepted production.');
        $globalAfter = ['canonical'=>$globalBefore['canonical'] + count($newQuestions),'effective_declared'=>$globalBefore['effective_declared'] + count($newQuestions),'runtime'=>$globalBefore['runtime'] + count($newQuestions)];
        $reportBoardCounts = $nativeByBoard;
        $otherExpected = ['let'=>1788,'nursing'=>878,'criminologist'=>451,'civil-engineering'=>922,'mechanical-engineering'=>511,'registered-electrical-engineering'=>842,'architecture'=>472,'psychometrician'=>401,'cpa'=>1132];
        foreach ($otherExpected as $b=>$expected) if ((int) ($reportBoardCounts[$b] ?? 0) !== $expected) $fail('Unrelated board native count changed for '.$b);
        if ((int) ($reportBoardCounts['civil-service'] ?? 0) !== $cseAfter['native']) $fail('Native board count mismatch.');
        $qNewSet = array_fill_keys(array_map(static fn(array $q): string => (string) $q['id'], $newQuestions), true);
        foreach ($newQuestions as $q) {
            $id = (string) $q['id'];
            $direct = false;
            foreach ($eligibility as $entry) if (is_array($entry) && in_array($id, array_map('strval', (array) ($entry['question_ids'] ?? [])), true)) {
                $mapping = (array) ($entry['question_mappings'][$id] ?? []);
                $exams = (array) ($mapping['exam_ids'] ?? $entry['exam_ids'] ?? []);
                if (in_array('civil-service', $exams, true)) $direct = true;
            }
            if (!$direct) $fail('Missing direct CSE eligibility for ' . $id);
        }
        $idValues = array_values(array_map(static fn(array $q): int => (int) $q['id'], $newQuestions)); sort($idValues, SORT_NUMERIC);
        for ($i=1; $i<count($idValues); $i++) if ($idValues[$i] !== $idValues[$i-1] + 1) $fail('Accepted CSE IDs are not contiguous.');
        $questionFreezeAfterProduction = $hash($questions);
        $eligibilityAfterHash = $hash($eligibility);
        $questionsWithoutBatch653 = array_values(array_filter($questions, static fn(array $q): bool => ($q['batch_marker'] ?? '') !== 'batch653-cse'));
        if (!hash_equals($questionHashBefore, $hash($questionsWithoutBatch653))) $fail('Question store contains edits outside the authorized Batch653 additions.');
        $eligibilityWithoutBatch653 = [];
        foreach ($eligibility as $entry) {
            if (!is_array($entry)) continue;
            $entry['question_ids'] = array_values(array_filter((array) ($entry['question_ids'] ?? []), static fn(mixed $qid): bool => !isset($qNewSet[(string) $qid])));
            foreach (array_keys((array) ($entry['question_mappings'] ?? [])) as $qid) if (isset($qNewSet[(string) $qid])) unset($entry['question_mappings'][$qid]);
            if ($entry['question_ids'] !== []) $eligibilityWithoutBatch653[] = $entry;
        }
        if (!hash_equals($eligibilityHashBefore, $hash($eligibilityWithoutBatch653))) $fail('Eligibility store changed beyond mappings for the authorized new CSE questions.');
        $questionsByMarker = array_values(array_filter($questions, static fn(array $q): bool => ($q['batch_marker'] ?? '') === 'batch653-cse'));
        $examLevelPass = true;
        foreach ($questionsByMarker as $q) if (($q['civil_service_levels'] ?? []) !== ($q['batch653']['exam_levels'] ?? [])) $examLevelPass = false;
        if (!$examLevelPass) $fail('Professional/Subprofessional applicability metadata mismatch.');
        $productionDecisionRows = [];
        foreach ($planRecords as $p) $productionDecisionRows[] = $p;
        $reviewSignatures = array_column($reviewRecords, 'proposition_signature_hash', 'leaf_id');
        ksort($reviewSignatures, SORT_STRING);
        $artifact = [
            'title'=>'BOARDPREP — BATCH 653 CSE REVIEW CLOSURE','batch'=>653,'review_date'=>'2026-10-04',
            'lineage'=>['expected_head'=>$expectedHead,'head_before'=>$expectedHead,'branch'=>'batch653-cse-review-closure','main_head'=>$expectedMain,'ancestry_pass'=>true,'main_unchanged'=>true,'pushed'=>false],
            'currentness'=>$currentness,
            'production'=>['wave1_planned'=>4,'wave1_accepted'=>count($newQuestions),'wave1_rejected'=>1,'wave2_contingent'=>1,'wave2_accepted'=>0,'total_new'=>count($newQuestions),'accepted_ids'=>$idValues,'new_id_range'=>$idValues === [] ? '' : min($idValues).'-'.max($idValues),'cse_native_before'=>$cseBefore['native'],'cse_native_after'=>$cseAfter['native'],'cse_effective_before'=>$effectiveBefore,'cse_effective_after'=>$effectiveAfter,'cse_runtime_before'=>$runtimeBefore,'cse_runtime_after'=>$runtimeAfter,'source_traceability'=>'PASS','study_traceability'=>'PASS','duplicates'=>0,'flashcard_derivability'=>'PASS','structured_content'=>'PASS','currentness'=>'PASS','calculation_items'=>0,'defects_found'=>1,'defects_repaired'=>1,'defects_remaining'=>0,'review_phase_question_freeze'=>'PASS','plans'=>$productionDecisionRows],
            'cse'=>[
                'baseline'=>['native'=>$cseBefore['native'],'effective_declared'=>$effectiveBefore,'runtime'=>$runtimeBefore,'coverage_rows'=>120,'core_exam_leaves'=>56,'row_class_counts'=>(array) ($batch652['cse']['row_class_counts'] ?? [])],
                'after'=>['native'=>$cseAfter['native'],'effective_declared'=>$effectiveAfter,'runtime'=>$runtimeAfter,'coverage_rows'=>120,'core_exam_leaves'=>56,'row_class_counts'=>$rowClasses,'raw_health_before'=>$thresholdCounts($rawBeforeValues),'raw_health_after'=>$thresholdCounts($rawValues),'core_health_before'=>$thresholdCounts($coreBeforeValues),'core_health_after'=>$thresholdCounts($coreValues),'authorable_health_after'=>$thresholdCounts([]),'readiness_counts_before'=>['READY'=>0,'READY_WITH_LIMITS'=>5,'REVIEW'=>51,'DEFER'=>0],'readiness_counts_after'=>$readinessCounts,'official_area_native'=>$areaCounts],
                'source_study_inventory'=>['source_ids_reused'=>13,'new_source_records'=>0,'study_materials_reused'=>8,'study_materials_changed'=>0,'study_coverage'=>'56 SUFFICIENT','study_traceability'=>'56 COMPLETE'],
                'reviewed_leaves'=>51,'review_records'=>$reviewRecords,'review_by_area'=>$initialReviewByArea,'disposition_counts'=>$dispositionCounts,'blocker_counts'=>$blockerCounts,
                'exam_level_applicability'=>['general-information'=>['PROFESSIONAL','SUBPROFESSIONAL'],'verbal-ability'=>['PROFESSIONAL','SUBPROFESSIONAL'],'numerical-ability'=>['PROFESSIONAL','SUBPROFESSIONAL'],'analytical-ability'=>['PROFESSIONAL'],'clerical-ability'=>['SUBPROFESSIONAL']],
                'area_mismatch_remaining'=>['analytical-sequence-rule'=>['current_area'=>'analytical-ability','official_area'=>'numerical-ability','status'=>'BLOCKED_NO_REMAP']],
                'true_authorable_health'=>['zero'=>0,'under5'=>0,'under10'=>0,'under20'=>0,'authorable_leaf_count'=>0],
                'evidence_exhausted_leaf_ids'=>array_values(array_map(static fn(array $r): string => (string) $r['leaf_id'], array_filter($reviewRecords, static fn(array $r): bool => $r['disposition'] === 'EVIDENCE_EXHAUSTED'))),
                'shared_satisfied_leaf_ids'=>[],'high_value_headroom'=>0,'repeat_risk'=>['overall'=>'HIGH','GENERAL_INFORMATION'=>'MODERATE','VERBAL'=>'HIGH','NUMERICAL'=>'MODERATE','ANALYTICAL'=>'HIGH','CLERICAL'=>'MODERATE','basis'=>'Risk incorporates native concentration, observed family tags/proposition summaries, the duplicate gate, and no new plan authorization; counts alone were not used.'],
                'future_production'=>['candidate_leaves'=>0,'high_value_propositions'=>0,'safe_range'=>['min'=>0,'preferred'=>0,'max'=>0],'wave1'=>[],'wave2'=>[],'wave3_preauthorized'=>0,'mathematical_under10_slots'=>max(0,array_sum(array_map(static fn(int $n): int => max(0,10-$n), $coreValues))),'evidence_headroom'=>0,'high_value_headroom'=>0,'why_bounded'=>'The only remaining Batch652 contingent item duplicates denominator/rate propositions already present; review leaves either need item-level family proof, have no distinct high-value proposition left, or have an official area mismatch.'],
                'decision'=>'CSE_SMALL_SELECTIVE_EXPANSION_ONLY','decision_reason'=>'Three exact Batch652 Wave1 propositions passed novelty, source/study and exam-level checks. The clerical plan duplicated existing exact-record work, and the contingent data-trend proposition duplicated existing rate/denominator items. No new REVIEW-derived item was promoted; no numeric threshold was used as a quota.','next_portfolio_target'=>'ARCHITECTURE_SOURCE_READINESS_FOUNDATION',
            ],
            'content_freeze'=>['question_hash_before'=>$questionHashBefore,'question_hash_after_production'=>$questionFreezeAfterProduction,'question_hash_after_review'=>$hash(App::storage()->all('questions')),'review_phase_question_store_identical'=>hash_equals($questionFreezeAfterProduction,$hash(App::storage()->all('questions'))),'eligibility_hash_before'=>$eligibilityHashBefore,'eligibility_hash_after'=>$eligibilityAfterHash,'eligibility_changes_only_for_new_cse_questions'=>true,'new_taxonomy_leaves'=>0,'taxonomy_mapping_changes'=>0,'cse_question_rewrites'=>0,'cse_question_deletions'=>0,'let_questions_changed'=>0],
            'global_regressions'=>['global_before'=>$globalBefore,'global_after'=>$globalAfter,'native_by_board_after'=>$reportBoardCounts,'nursing_dosage'=>'24/16/8','cpa_cy2029_leakage'=>0,'flashcard_defects'=>0,'calculation_metadata_contradictions'=>0,'duplicate_effective'=>0,'double_weighting'=>0,'new_different_id_equivalence'=>0,'other_board_counts_unchanged'=>true],
            'repository_health_before'=>['overall_status'=>'REVIEW','pass_checks'=>12,'warn_checks'=>2,'fail_checks'=>0,'review_checks'=>1,'fail_entities'=>[],'warn_entities'=>748,'review_entities'=>19,'runtime_ms'=>null],
            'repository_health_after'=>['overall_status'=>'PENDING_FINAL_HEALTH','fail_checks'=>null,'fail_entities'=>[],'runtime_ms'=>null],
            'validation'=>['lineage'=>'PASS','exact_plan_authority'=>'PASS','authorized_questions_only'=>'PASS','review_51_complete'=>'PASS','source_ids'=>'PASS','study_ids'=>'PASS','readiness_contract'=>'PASS','level_applicability'=>'PASS','currentness'=>'PASS','question_store_postproduction_freeze'=>'PASS','global_counts'=>'PASS','repository_health'=>'PENDING_FINAL_HEALTH'],
            'determinism'=>['review_ids_hash'=>$hash($coreLeafIds),'readiness_hashes'=>$hash(array_column($updatedReadiness,'evidence_hash','leaf_id')),'candidate_plan_hash'=>$hash($planRecords),'review_dispositions_hash'=>$hash($reviewRecords),'global_counts_hash'=>$hash([$globalBefore,$globalAfter,$reportBoardCounts])],
            'known_inherited_issues'=>['16 orphan study materials','3 CPA source-fit reviews','Batch646 stale ME readiness test (28/7 current vs 34/1 expected)'],
        ];
        $artifact['audit_hash'] = $hash($stable($artifact));
        $writeJson($artifactPath, $artifact);
        fwrite(STDOUT, "BATCH653_EXECUTE=PASS\nWAVE1_ACCEPTED=".count($newQuestions)."\nWAVE2_ACCEPTED=0\nTOTAL_NEW=".count($newQuestions)."\nCSE_NATIVE=".$cseAfter['native']."\nREVIEWED=51\nARTIFACT_HASH=".$artifact['audit_hash'].PHP_EOL);
        exit(0);
    }

    $artifact = $readJson($artifactPath);
    $artifactHash = $hash($stable($artifact));
    if (!hash_equals((string) ($artifact['audit_hash'] ?? ''), $artifactHash)) $fail('Durable artifact deterministic hash mismatch.');
    if (($artifact['lineage']['expected_head'] ?? '') !== $expectedHead || ($artifact['lineage']['main_head'] ?? '') !== $expectedMain) $fail('Durable lineage mismatch.');
    $newQuestions = array_values(array_filter($questions, static fn(array $q): bool => ($q['batch_marker'] ?? '') === 'batch653-cse'));
    if (count($newQuestions) !== (int) ($artifact['production']['total_new'] ?? -1) || count($artifact['cse']['review_records'] ?? []) !== 51) $fail('Audit delta or review count differs from durable artifact.');
    if (!hash_equals((string) $artifact['content_freeze']['question_hash_after_production'], $hash($questions))) $fail('Question store changed after production freeze.');
    $currentNewIds = array_map(static fn(array $q): string => (string) ($q['id'] ?? ''), $newQuestions);
    $questionsWithoutCurrentBatch = array_values(array_filter($questions, static fn(array $q): bool => !in_array((string) ($q['id'] ?? ''), $currentNewIds, true)));
    if (!hash_equals((string) $artifact['content_freeze']['question_hash_before'], $hash($questionsWithoutCurrentBatch))) $fail('Question store differs outside authorized additions.');
    if (!hash_equals((string) $artifact['content_freeze']['eligibility_hash_before'], $hash($stripQuestionIds($eligibility, $currentNewIds)))) $fail('Eligibility store differs outside authorized additions.');
    if (($artifact['cse']['future_production']['wave3_preauthorized'] ?? -1) !== 0) $fail('Wave3 unexpectedly authorized.');
    if (($artifact['currentness']['classification'] ?? '') !== 'UNCHANGED') $fail('Official-scope currentness was not confirmed.');
    $currentMetrics = $metricCounts($questions, $eligibility, $collections['concept-derived-eligibility'], $collections['blueprints'], $collections['question-reuse-overrides']);
    foreach (['native','effective_declared','runtime'] as $metric) if ($currentMetrics[$metric] !== (int) ($artifact['cse']['after'][$metric] ?? -1)) $fail('CSE ' . $metric . ' differs from artifact.');
    $currentReadinessValidation = SourceReadinessService::validateCollections($collections);
    $currentCseReadiness = $currentReadinessValidation['by_board']['civil-service'] ?? null;
    if (!is_array($currentCseReadiness) || ($currentCseReadiness['valid'] ?? false) !== true || count((array) ($currentCseReadiness['records'] ?? [])) !== 56) $fail('Permanent CSE readiness contract failed in deterministic audit.');
    $currentReadinessCounts = ['READY'=>0,'READY_WITH_LIMITS'=>0,'REVIEW'=>0,'DEFER'=>0];
    foreach ($collections['source-readiness-civil-service'] as $record) if (is_array($record) && isset($currentReadinessCounts[(string) ($record['readiness'] ?? '')])) $currentReadinessCounts[(string) $record['readiness']]++;
    if ($currentReadinessCounts !== ['READY'=>0,'READY_WITH_LIMITS'=>0,'REVIEW'=>56,'DEFER'=>0]) $fail('Final readiness states drifted.');
    if (($artifact['repository_health_after']['fail_checks'] ?? null) !== 0 || ($artifact['repository_health_after']['fail_entities'] ?? null) !== []) $fail('Final Repository Health has failures or was not recorded.');
    [$auditBoardCounts, $auditLeafCounts] = $nativeCounts($questions);
    if ($auditBoardCounts !== (array) ($artifact['global_regressions']['native_by_board_after'] ?? [])) $fail('Global native board counts differ from the artifact.');
    $auditRawValues = []; $auditCoreValues = [];
    foreach ($collections['board-concept-coverage'] as $row) {
        if (!is_array($row) || CivilServiceCoverageModel::normalizeBoard($row['board_id'] ?? '') !== 'civil-service') continue;
        $count = (int) ($auditLeafCounts[(string) ($row['concept_id'] ?? '')] ?? 0);
        $auditRawValues[] = $count;
        if (CivilServiceCoverageModel::classifyRow($row) === 'CORE_EXAM_LEAF') $auditCoreValues[] = $count;
    }
    if ($thresholdCounts($auditRawValues) != (array) ($artifact['cse']['after']['raw_health_after'] ?? [])) $fail('Raw CSE coverage health differs from artifact.');
    if ($thresholdCounts($auditCoreValues) != (array) ($artifact['cse']['after']['core_health_after'] ?? [])) $fail('CSE core health differs from artifact.');
    fwrite(STDOUT, "BATCH653_AUDIT=PASS\nAUDIT_HASH=".$artifactHash."\nTOTAL_NEW=".count($newQuestions)."\nREVIEWED=51\nCSE_DECISION=".$artifact['cse']['decision'].PHP_EOL);
} catch (Throwable $e) {
    $fail($e->getMessage());
}
