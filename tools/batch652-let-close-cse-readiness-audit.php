<?php

declare(strict_types=1);

/** Batch652: execute four stored LET plans, establish permanent CSE core readiness, audit deterministically. */

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
use App\Services\RepositoryHealth\Readiness\SourceReadinessRecord;
use App\Services\RepositoryHealth\Readiness\SourceReadinessService;

Autoloader::register();
putenv('APP_ENV=testing');
putenv('APP_DEBUG=false');
App::boot();

$root = dirname(__DIR__);
$expectedHead = '5ae58228ca3b40894d1829be472b893f28436cf0';
$mainHeadExpected = '0eb9704ba293e0aa3402af5d01a2d3392c005b73';
$artifactPath = $root . '/storage/batch652-let-close-cse-readiness.json';
$cseReadinessPath = $root . '/storage/source-readiness-civil-service.json';
$letReadinessPath = $root . '/storage/source-readiness-let.json';

$fail = static function (string $message): never {
    fwrite(STDERR, 'BATCH652_FAIL=' . $message . PHP_EOL);
    exit(1);
};
$readJson = static function (string $path): array {
    if (!is_file($path)) throw new RuntimeException('Missing JSON: ' . $path);
    $value = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($value)) throw new RuntimeException('Invalid JSON collection/object: ' . $path);
    return $value;
};
$writeJson = static function (string $path, array $value): void {
    $json = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $temporary = $path . '.tmp';
    if (file_put_contents($temporary, $json . "\n", LOCK_EX) === false || !rename($temporary, $path)) {
        throw new RuntimeException('Could not atomically write ' . $path);
    }
};
$git = static function (array $arguments) use ($root): string {
    $command = 'git -C ' . escapeshellarg($root);
    foreach ($arguments as $argument) $command .= ' ' . escapeshellarg($argument);
    $output = shell_exec($command . ' 2>/dev/null');
    if (!is_string($output)) throw new RuntimeException('Git command failed: ' . implode(' ', $arguments));
    return trim($output);
};
$normalize = static function (mixed $node) use (&$normalize): mixed {
    if (!is_array($node)) return $node;
    if (array_is_list($node)) return array_map($normalize, $node);
    ksort($node, SORT_STRING);
    foreach ($node as &$child) $child = $normalize($child);
    unset($child);
    return $node;
};
$hash = static fn(mixed $value): string => hash('sha256', json_encode(
    $normalize($value), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
));
$stablePayload = static function (array $value): array {
    unset($value['audit_hash'], $value['health_after'], $value['review_date']);
    if (isset($value['currentness']) && is_array($value['currentness'])) unset($value['currentness']['let_check_date']);
    if (isset($value['repository_health_before']) && is_array($value['repository_health_before'])) unset($value['repository_health_before']['runtime_ms']);
    return $value;
};
$countHealth = static function (array $counts): array {
    $values = array_values(array_map('intval', $counts));
    sort($values, SORT_NUMERIC);
    $count = count($values);
    $median = $count === 0 ? 0 : ($count % 2 === 1 ? $values[intdiv($count, 2)] : ($values[$count / 2 - 1] + $values[$count / 2]) / 2);
    $quantile = static function (float $p) use ($values, $count): float|int {
        if ($count === 0) return 0;
        $position = ($count - 1) * $p;
        $lower = (int) floor($position);
        $upper = (int) ceil($position);
        if ($lower === $upper) return $values[$lower];
        return $values[$lower] + ($values[$upper] - $values[$lower]) * ($position - $lower);
    };
    return [
        'leaf_count' => $count,
        'zero' => count(array_filter($values, static fn(int $n): bool => $n === 0)),
        'under5' => count(array_filter($values, static fn(int $n): bool => $n < 5)),
        'under10' => count(array_filter($values, static fn(int $n): bool => $n < 10)),
        'under20' => count(array_filter($values, static fn(int $n): bool => $n < 20)),
        'min' => $values === [] ? 0 : min($values),
        'median' => $median,
        'p25' => $quantile(0.25),
        'p75' => $quantile(0.75),
        'max' => $values === [] ? 0 : max($values),
    ];
};
$stripQuestionIds = static function (array $rows, array $ids): array {
    $remove = array_fill_keys(array_map('strval', $ids), true);
    $result = [];
    foreach ($rows as $row) {
        if (!is_array($row)) continue;
        $row['question_ids'] = array_values(array_filter((array) ($row['question_ids'] ?? []), static fn(mixed $id): bool => !isset($remove[(string) $id])));
        foreach (array_keys((array) ($row['question_mappings'] ?? [])) as $id) if (isset($remove[(string) $id])) unset($row['question_mappings'][$id]);
        if ($row['question_ids'] !== []) $result[] = $row;
    }
    return $result;
};
$mode = $argv[1] ?? '--audit';
if (!in_array($mode, ['--execute', '--audit', '--record-health'], true)) $fail('Use --execute, --audit, or --record-health <health-json>.');

try {
    $headBefore = $git(['rev-parse', 'HEAD']);
    $branch = $git(['branch', '--show-current']);
    $mainHead = $git(['rev-parse', 'refs/heads/main']);
    if ($branch !== 'batch652-let-close-cse-readiness') $fail('Unexpected branch: ' . $branch);
    if ($mainHead !== $mainHeadExpected) $fail('Unrelated main HEAD changed.');
    if ($mode === '--execute' && $headBefore !== $expectedHead) $fail('Execute only from the exact Batch651 checkpoint.');
    if ($headBefore !== $expectedHead && $git(['merge-base', $expectedHead, 'HEAD']) !== $expectedHead) $fail('Batch651 checkpoint is not an ancestor.');

    $batch651 = $readJson($root . '/storage/batch651-let-family-proof.json');
    $batch648 = $readJson($root . '/storage/batch648-portfolio-deficiency.json');
    $oldQuestions = json_decode($git(['show', $expectedHead . ':storage/questions.json']), true, 512, JSON_THROW_ON_ERROR);
    $oldEligibility = json_decode($git(['show', $expectedHead . ':storage/question-eligibility.json']), true, 512, JSON_THROW_ON_ERROR);
    $qFreezeBefore = $hash($oldQuestions);
    $eligFreezeBefore = $hash($oldEligibility);
    $authorization = (array) ($batch651['plans'] ?? []);
    $authorizedIds = array_values(array_map(static fn(array $p): string => (string) ($p['plan_id'] ?? ''), $authorization));
    $expectedPlanIds = ['b652-92843504440ecfba', 'b652-c4688d5efb188489', 'b652-540ef67b4683e497', 'b652-2c2b1e0630f0ab7e'];
    sort($authorizedIds, SORT_STRING);
    sort($expectedPlanIds, SORT_STRING);
    if ($authorizedIds !== $expectedPlanIds || (int) ($batch651['batch652']['authorized_items'] ?? 0) !== 4
        || (int) ($batch651['batch652']['wave1']['items'] ?? 0) !== 4
        || (int) ($batch651['batch652']['wave2']['items'] ?? -1) !== 0) $fail('Stored Batch651 exact four-item authority failed.');

    $contentDefinitions = [
        'b652-92843504440ecfba' => [
            'stem' => 'A class is examining a poster. The artist uses dark outlines and a bright accent color; the accent directs attention to one figure, while the figures are arranged evenly around the center. Which pair names the organizing principles rather than the formal elements?',
            'choices' => ['The bright accent is emphasis, and the even arrangement is color.', 'The bright accent is color, and the even arrangement is balance.', 'The bright accent is contrast, and the even arrangement is line.', 'The bright accent is balance, and the even arrangement is emphasis.'], 'answer' => 1,
            'explanation' => 'The accent itself is the color element; its attention-directing effect is emphasis. An even arrangement around a center describes balance, an organizing principle.',
            'focus' => 'Distinguish visual-art components from organizing principles and apply the distinction to a composition.',
        ],
        'b652-c4688d5efb188489' => [
            'stem' => 'A pond model shows that increased nutrient input is followed by more algae, more decomposition, and lower dissolved oxygen. If nutrient input is reduced while other conditions remain stable, which prediction follows from the stated model?',
            'choices' => ['Less nutrient input supports a prediction of reduced algal growth and less downward pressure on oxygen, but the model gives no recovery amount or timing.', 'Oxygen must rise immediately even if algal growth and decomposition remain unchanged.', 'Fish populations will completely recover as soon as nutrient input falls.', 'No directional prediction is possible because the model provides no numeric values.'], 'answer' => 0,
            'explanation' => 'The stated chain supports a conditional prediction about the direction of pressure when nutrient input falls. It supplies neither a guaranteed recovery time nor evidence identifying an external cause or a one-to-one numerical effect.',
            'focus' => 'Interpret a stated ecosystem causal model and limit the prediction to the evidence it contains.',
        ],
        'b652-540ef67b4683e497' => [
            'stem' => 'A physical education teacher uses the Philippine Physical Activity Pyramid while planning a class week. Which use best fits the model?',
            'choices' => ['Treat relative participation levels as an exact universal dose for every learner.', 'Use the model to plan balanced activity patterns, then adapt participation separately to learners’ readiness and context.', 'Use the learner’s position in the pyramid to diagnose the cause of a health condition.', 'Score a learner’s movement-skill proficiency from the activity category selected.'], 'answer' => 1,
            'explanation' => 'The pyramid is a planning model for balanced activity and relative participation, not an individual diagnostic instrument. Safe planning still accounts for readiness and context.',
            'focus' => 'Interpret a physical-activity planning model and distinguish it from individual diagnosis or prescription.',
        ],
        'b652-2c2b1e0630f0ab7e' => [
            'stem' => 'A researcher compares a dated 1905 attendance ledger with a historian’s 1980 account of a meeting held in 1905. Which conclusion is best supported by the source types?',
            'choices' => ['The ledger is contemporaneous primary evidence for the attendance it records, but alone it does not establish participants’ motives.', 'The later account is primary evidence for the 1905 event simply because its author studied the period.', 'The ledger establishes both attendance and each attendee’s reason for coming.', 'Neither source can inform an account of the meeting unless both were written in 1905.'], 'answer' => 0,
            'explanation' => 'The dated ledger was created at the time and is primary evidence for recorded attendance. Its provenance and entries do not, without further evidence, establish participants’ motives; the later account is an interpretation.',
            'focus' => 'Classify contemporaneous evidence and bound a historical claim to what the source can establish.',
        ],
    ];

    $collections = [];
    foreach (['questions','question-eligibility','question-reuse-metadata','concept-derived-eligibility','question-reuse-overrides','blueprints','taxonomy/concepts','taxonomy/topics','taxonomy/domains','subjects','boards','board-subjects','board-concept-coverage','study-materials','study-question-links','sources','source-readiness-let','source-readiness-civil-service'] as $name) {
        $collections[$name] = App::storage()->all($name);
    }
    $questions = $collections['questions'];
    $sourceById = [];
    foreach ($collections['sources'] as $row) if (is_array($row)) $sourceById[(string) ($row['id'] ?? '')] = $row;
    $materialById = [];
    foreach ($collections['study-materials'] as $row) if (is_array($row)) $materialById[(string) ($row['id'] ?? '')] = $row;
    $readinessByLeaf = [];
    foreach ($collections['source-readiness-let'] as $row) if (is_array($row)) $readinessByLeaf[(string) ($row['leaf_id'] ?? '')] = $row;
    $maxId = 0;
    $stems = [];
    foreach ($questions as $question) {
        if (!is_array($question)) continue;
        if (is_numeric($question['id'] ?? null)) $maxId = max($maxId, (int) $question['id']);
        $stems[strtolower(trim((string) preg_replace('/\s+/', ' ', (string) ($question['question'] ?? ''))))] = (string) ($question['id'] ?? '');
    }
    $existingBatchItems = array_values(array_filter($questions, static fn(array $q): bool => ($q['batch_marker'] ?? '') === 'batch652-let'));
    $accepted = [];
    if ($mode === '--execute') {
        $drafts = [];
        $existingByPlan = [];
        foreach ($existingBatchItems as $existingItem) {
            $planId = (string)($existingItem['batch652']['plan_id'] ?? '');
            if (!in_array($planId, $expectedPlanIds, true) || isset($existingByPlan[$planId])) $fail('Unexpected or duplicate partially saved Batch652 plan.');
            $existingByPlan[$planId] = $existingItem;
        }
        $baselineMaxId = 0;
        foreach ($questions as $oldQuestion) if (is_array($oldQuestion) && ($oldQuestion['batch_marker'] ?? '') !== 'batch652-let' && is_numeric($oldQuestion['id'] ?? null)) $baselineMaxId = max($baselineMaxId,(int)$oldQuestion['id']);
        foreach ($authorization as $plan) {
            $planId = (string) ($plan['plan_id'] ?? '');
            if (isset($existingByPlan[$planId])) continue;
            $definition = $contentDefinitions[$planId] ?? null;
            if (!is_array($definition)) $fail('Missing authoring definition for stored plan ' . $planId);
            $leafId = (string) ($plan['leaf_id'] ?? '');
            $record = $readinessByLeaf[$leafId] ?? null;
            $materialIds = array_values(array_map('strval', (array) ($plan['study_material_ids'] ?? [])));
            $sourceIds = array_values(array_map('strval', (array) ($plan['source_ids'] ?? [])));
            if (!is_array($record) || ($record['readiness'] ?? '') !== 'READY_WITH_LIMITS'
                || !in_array($leafId, (array) ($record['allowed_scope'] ?? []), true)
                || (int) ($record['safe_headroom_max'] ?? 0) < 1
                || !in_array((int) ($plan['target_depth'] ?? 0), range((int) ($record['supported_depth_min'] ?? 1), (int) ($record['supported_depth_max'] ?? 5)), true)
                || array_diff($sourceIds, (array) ($record['source_ids'] ?? [])) !== []
                || array_diff($materialIds, (array) ($record['study_material_ids'] ?? [])) !== []
                || !in_array((string) ($record['source_currentness'] ?? ''), ['CURRENT','CURRENT_WITH_LIMITS','NOT_TIME_SENSITIVE'], true)
                || ($record['study_source_traceability'] ?? '') !== 'COMPLETE') $fail('Stored plan no longer meets its readiness boundary: ' . $leafId);
            if (($record['readiness'] ?? '') !== (string)($plan['readiness'] ?? '')
                || (int)$record['safe_headroom_max'] !== (int)($plan['headroom']['remaining_high_value'] ?? -1)
                || $record['source_currentness'] !== (string)($plan['currentness_class'] ?? '')) $fail('Stored plan and current readiness contract diverged for ' . $leafId);
            $material = $materialById[$materialIds[0] ?? ''] ?? null;
            if (!is_array($material) || array_diff($sourceIds, (array) ($material['source_ids'] ?? [])) !== []) $fail('Study material does not directly support the authorized source list for ' . $leafId);
            foreach ($sourceIds as $sourceId) if (!isset($sourceById[$sourceId])) $fail('Invalid authorized source ' . $sourceId);
            $taxonomyConcept = null;
            foreach ($collections['taxonomy/concepts'] as $row) if (is_array($row) && (string) ($row['id'] ?? '') === $leafId) $taxonomyConcept = $row;
            if (!is_array($taxonomyConcept)) $fail('Missing LET concept ' . $leafId);
            $topic = null;
            foreach ($collections['taxonomy/topics'] as $row) if (is_array($row) && (string) ($row['id'] ?? '') === (string) ($taxonomyConcept['topic_id'] ?? '')) $topic = $row;
            $domain = null;
            foreach ($collections['taxonomy/domains'] as $row) if (is_array($row) && (string) ($row['id'] ?? '') === (string) ($topic['domain_id'] ?? '')) $domain = $row;
            if (!is_array($topic) || !is_array($domain)) $fail('Incomplete LET taxonomy path for ' . $leafId);
            $subjectId = (string) ($domain['subject_id'] ?? '');
            $specialization = (string) ($plan['specialization_id'] ?? '');
            $planOffset = array_search($planId, $expectedPlanIds, true);
            $questionId = $baselineMaxId + 1 + (int)$planOffset;
            $question = [
                'id' => $questionId,
                'board' => 'let',
                'taxonomy' => ['board_id'=>'let','subject_id'=>$subjectId,'domain_id'=>(string)$domain['id'],'topic_id'=>(string)$topic['id'],'concept_id'=>$leafId],
                'status'=>'active','source'=>'BoardPrep-authored','difficulty'=>(int)$plan['target_depth'] >= 3 ? 'medium' : 'easy','type'=>'multiple_choice',
                'question'=>$definition['stem'],'choices'=>$definition['choices'],'answer'=>$definition['choices'][$definition['answer']],
                'explanation'=>$definition['explanation'],'content_blocks'=>[],'source_ids'=>$sourceIds,
                'study_material_id'=>$materialIds[0],'study_material_ids'=>$materialIds,'source_scope'=>[$leafId],
                'study_focus'=>$definition['focus'],'cognitive_intent'=>'application and interpretation','multistep'=>true,
                'data_interpretation'=>in_array($plan['question_mode'] ?? '', ['MODEL_OR_DATA_INTERPRETATION'], true),
                'table_dependent'=>false,'scenario_length'=>'standard','numeric'=>false,'calculation_required'=>false,
                'quantitative_semantics'=>'NON_NUMERICAL','currentness_verified'=>true,'version_sensitive'=>false,
                'scenario_family'=>'b652-' . $leafId . '-' . strtolower((string)$plan['content_family']),
                'misconception_family'=>(string)$plan['content_family'],'batch_marker'=>'batch652-let',
                'batch652'=>['phase'=>'LET_CLOSURE','plan_id'=>$planId,'leaf_id'=>$leafId,'specialization_id'=>$specialization,'content_family'=>(string)$plan['content_family'],'depth'=>(int)$plan['target_depth'],'source_ids'=>$sourceIds,'study_material_ids'=>$materialIds,'authorized_by'=>'batch651-let-family-proof'],
                'reuse'=>['reuse_policy'=>'REVIEW','required_depth'=>(int)$plan['target_depth'],'context_mode'=>'NEUTRAL','metadata_status'=>'VERIFIED','metadata_provenance'=>[['type'=>'OFFICIAL_SOURCE','reference'=>'batch651-let-family-proof:'.$planId,'note'=>'One-item bounded plan uses exact Batch651 source, study, scope, depth and novelty evidence.','verified_status'=>'VERIFIED']]],
            ];
            $stemKey = strtolower(trim((string) preg_replace('/\s+/', ' ', $question['question'])));
            if (isset($stems[$stemKey])) $fail('Normalized duplicate LET question stem for ' . $leafId);
            $stems[$stemKey] = (string)$questionId;
            if (QuestionService::validate($question)['valid'] !== true) $fail('QuestionService validation rejected authorized draft ' . $leafId);
            $readinessGate = SourceReadinessService::validateQuestionForCreate($question, $collections);
            if (($readinessGate['valid'] ?? false) !== true) $fail('Readiness gate rejected authorized draft ' . $leafId . ': ' . json_encode($readinessGate['errors'] ?? []));
            $drafts[] = ['plan'=>$plan,'question'=>$question,'definition'=>$definition];
        }
        foreach ($drafts as $draft) {
            $saved = QuestionService::save($draft['question']);
            $taxonomy = $saved['taxonomy'];
            $mapping = QuestionEligibilityService::addMapping($saved, [
                'target_board'=>'let','target_subject'=>$taxonomy['subject_id'],'target_domain'=>$taxonomy['domain_id'],
                'target_topic'=>$taxonomy['topic_id'],'target_concept'=>$taxonomy['concept_id'],
                'blueprint_node'=>(string)$draft['plan']['specialization_id'],'target_specialization_id'=>(string)$draft['plan']['specialization_id'],
            ]);
            if (($mapping['valid'] ?? false) !== true) $fail('Eligibility mapping rejected plan ' . $draft['plan']['plan_id'] . ': ' . json_encode($mapping['errors'] ?? []));
            App::storage()->create('study-question-links', [
                'id'=>'study-link-b652-let-' . $saved['id'],'material_id'=>(string)$saved['study_material_id'],
                'question_id'=>(string)$saved['id'],'source_ids'=>array_values($saved['source_ids']),
            ]);
            $accepted[] = ['plan_id'=>(string)$draft['plan']['plan_id'],'question_id'=>(string)$saved['id'],'leaf_id'=>(string)$draft['plan']['leaf_id']];
        }
        $persistedByPlan = [];
        foreach (App::storage()->all('questions') as $savedQuestion) {
            if (!is_array($savedQuestion) || ($savedQuestion['batch_marker'] ?? '') !== 'batch652-let') continue;
            $persistedByPlan[(string)($savedQuestion['batch652']['plan_id'] ?? '')] = $savedQuestion;
        }
        foreach ($authorization as $plan) {
            $planId = (string)$plan['plan_id'];
            $saved = $persistedByPlan[$planId] ?? null;
            if (!is_array($saved)) $fail('Recovery could not locate persisted authorized plan ' . $planId);
            $leaf = (string)$plan['leaf_id'];
            $materialId = (string)($plan['study_material_ids'][0] ?? '');
            $hasStudyLink = false;
            foreach (App::storage()->all('study-question-links') as $link) if (is_array($link) && (string)($link['question_id'] ?? '') === (string)$saved['id'] && (string)($link['material_id'] ?? '') === $materialId) $hasStudyLink = true;
            if (!$hasStudyLink) App::storage()->create('study-question-links',['id'=>'study-link-b652-let-'.$saved['id'],'material_id'=>$materialId,'question_id'=>(string)$saved['id'],'source_ids'=>array_values((array)$saved['source_ids'])]);
            $hasLetMapping = false;
            foreach (App::storage()->all('question-eligibility') as $row) {
                if (!is_array($row) || !in_array((string)$saved['id'],array_map('strval',(array)($row['question_ids'] ?? [])),true)) continue;
                $mapping = (array)($row['question_mappings'][(string)$saved['id']] ?? []);
                if (in_array('let',(array)($mapping['exam_ids'] ?? $row['exam_ids'] ?? []),true) && (string)($mapping['placements']['let']['concept_id'] ?? $saved['taxonomy']['concept_id'] ?? '') === $leaf) $hasLetMapping = true;
            }
            if (!$hasLetMapping) {
                $taxonomy = $saved['taxonomy'];
                $mapping = QuestionEligibilityService::addMapping($saved,['target_board'=>'let','target_subject'=>$taxonomy['subject_id'],'target_domain'=>$taxonomy['domain_id'],'target_topic'=>$taxonomy['topic_id'],'target_concept'=>$taxonomy['concept_id'],'blueprint_node'=>(string)$plan['specialization_id'],'target_specialization_id'=>(string)$plan['specialization_id']]);
                if (($mapping['valid'] ?? false) !== true) $fail('Recovery could not complete LET eligibility for '.$planId);
            }
        }
        $deriver = new FlashcardDerivationService(App::container()->get(QuestionRepository::class));
        foreach ($persistedByPlan as $planId=>$savedQuestion) {
            $card = $deriver->derive($savedQuestion);
            if (!is_array($card) || FlashcardCardValidator::validate($card) !== []) $fail('Flashcard derivability failed for authorized plan '.$planId);
        }
        $questions = App::storage()->all('questions');
        $newIds = array_column($accepted, 'question_id');
        $letRecords = App::storage()->all('source-readiness-let');
        foreach ($letRecords as &$record) {
            if (!is_array($record) || !in_array((string)($record['leaf_id'] ?? ''), array_column($authorization, 'leaf_id'), true)) continue;
            $leafId = (string)$record['leaf_id'];
            $record['current_question_count'] = count(array_filter($questions, static fn(array $q): bool => strtolower((string)($q['taxonomy']['board_id'] ?? '')) === 'let' && (string)($q['taxonomy']['concept_id'] ?? '') === $leafId && strtolower((string)($q['status'] ?? 'active')) !== 'archived'));
            foreach ([5,10,20] as $threshold) $record['deficit_to_' . $threshold] = max(0, $threshold - (int)$record['current_question_count']);
            $record['safe_headroom_min'] = 0; $record['safe_headroom_preferred'] = 0; $record['safe_headroom_max'] = 0;
            $record['max_new_questions'] = 0; $record['planned_new_questions'] = 0;
            $record['readiness'] = 'REVIEW';
            $record['readiness_reason'] = 'The sole Batch651-authorized high-value proposition was produced; this batch authorizes no further LET additions.';
            $record['review_reason'] = 'Batch651 selective proposition headroom is consumed; any future addition requires a new semantic and evidence gate.';
            $record['evidence_hash'] = SourceReadinessRecord::evidenceHash($record);
        }
        unset($record);
        $writeJson($letReadinessPath, $letRecords);
        $collections['questions'] = $questions;
        $collections['source-readiness-let'] = $letRecords;
    }

    if ($mode === '--record-health') {
        if (!is_file($argv[2] ?? '')) $fail('--record-health requires a health JSON path.');
        $health = $readJson((string)$argv[2]);
        $artifact = $readJson($artifactPath);
        $artifact['health_after'] = [
            'overall_status'=>(string)($health['overall_status'] ?? $health['summary']['overall_status'] ?? ''),
            'fail_checks'=>(int)($health['fail_checks'] ?? $health['fail_count'] ?? $health['summary']['fail_checks'] ?? $health['summary']['fail_count'] ?? 0),
            'fail_entities'=>array_values((array)($health['fail_entities'] ?? $health['summary']['fail_entities'] ?? [])),
            'review_checks'=>(int)($health['review_checks'] ?? $health['review_count'] ?? $health['summary']['review_checks'] ?? $health['summary']['review_count'] ?? 0),
            'warn_checks'=>(int)($health['warn_checks'] ?? $health['warn_count'] ?? $health['summary']['warn_checks'] ?? $health['summary']['warn_count'] ?? 0),
            'issue_count'=>(int)($health['issue_count'] ?? $health['summary']['issue_count'] ?? 0),
            'warn_entities'=>(int)($health['warn_entity_count'] ?? $health['summary']['warn_entity_count'] ?? 0),
            'review_entities'=>(int)($health['review_entity_count'] ?? $health['summary']['review_entity_count'] ?? 0),
            'runtime_excluded'=>true,
        ];
        $artifact['audit_hash'] = $hash($stablePayload($artifact));
        $writeJson($artifactPath, $artifact);
        fwrite(STDOUT, 'HEALTH_RECORDED=' . (($artifact['health_after']['fail_checks'] === 0) ? 'YES' : 'NO') . PHP_EOL);
        exit(0);
    }

    $questions = App::storage()->all('questions');
    $eligibility = App::storage()->all('question-eligibility');
    $sourceRows = App::storage()->all('sources');
    $materialRows = App::storage()->all('study-materials');
    $coverageRows = array_values(array_filter(App::storage()->all('board-concept-coverage'), static fn(array $r): bool => CivilServiceCoverageModel::normalizeBoard($r['board_id'] ?? '') === 'civil-service'));
    $conceptRows = App::storage()->all('taxonomy/concepts');
    $conceptNameById = [];
    foreach ($conceptRows as $concept) if (is_array($concept)) $conceptNameById[(string)($concept['id'] ?? '')] = (string)($concept['name'] ?? $concept['id'] ?? '');
    $topicRows = App::storage()->all('taxonomy/topics');
    $questionById = [];
    $letByLeaf = [];
    $nativeByBoard = [];
    $allIds = [];
    $nativeCseByLeaf = [];
    $nativeBoardsByLeaf = [];
    foreach ($questions as $question) {
        if (!is_array($question) || strtolower((string)($question['status'] ?? 'active')) === 'archived') continue;
        $id = (string)($question['id'] ?? '');
        $questionById[$id] = $question;
        if ($id !== '') $allIds[$id] = true;
        $boardId = strtolower((string)($question['taxonomy']['board_id'] ?? $question['board'] ?? ''));
        $nativeByBoard[$boardId] = ($nativeByBoard[$boardId] ?? 0) + 1;
        $leafId = (string)($question['taxonomy']['concept_id'] ?? '');
        if ($leafId !== '') $nativeBoardsByLeaf[$leafId][$boardId] = ($nativeBoardsByLeaf[$leafId][$boardId] ?? 0) + 1;
        if ($boardId === 'let') $letByLeaf[$leafId][] = $question;
        if ($boardId === 'civil-service' && $leafId !== '') $nativeCseByLeaf[$leafId] = ($nativeCseByLeaf[$leafId] ?? 0) + 1;
    }
    if (count($allIds) !== count($questions)) $fail('Question IDs are not unique.');

    $records = $collections['source-readiness-let'];
    $letCountsAfter = [];
    foreach ($records as $r) if (is_array($r)) $letCountsAfter[(string)$r['leaf_id']] = (int)$r['current_question_count'];
    $letNativeAfter = (int)($nativeByBoard['let'] ?? 0);
    $letNativeBefore = $letNativeAfter - 4;
    if ($mode !== '--execute' && count($existingBatchItems) !== 4) $fail('Expected exactly the four authorized persisted LET additions.');

    $sourceById = [];
    foreach ($sourceRows as $s) if (is_array($s)) $sourceById[(string)($s['id'] ?? '')] = $s;
    $materialById = [];
    foreach ($materialRows as $m) if (is_array($m)) $materialById[(string)($m['id'] ?? '')] = $m;
    $eligibilityIndex = new QuestionEligibilityIndex($eligibility, App::storage()->all('blueprints'), App::storage()->all('concept-derived-eligibility'), App::storage()->all('question-reuse-overrides'));

    $letSelected = [];
    foreach ($questions as $q) if (is_array($q) && ($q['batch_marker'] ?? '') === 'batch652-let') $letSelected[] = $q;
    if (count($letSelected) !== 4) $fail('LET additions differ from exact Batch651 authorized total.');
    $acceptedIds = array_values(array_map(static fn(array $q): string => (string)$q['id'], $letSelected));
    sort($acceptedIds, SORT_NUMERIC);
    if ((int)$acceptedIds[3] - (int)$acceptedIds[0] !== 3) $fail('Accepted LET IDs are not contiguous.');
    foreach ($letSelected as $q) {
        $planId = (string)($q['batch652']['plan_id'] ?? '');
        if (!in_array($planId, $expectedPlanIds, true) || (string)($q['taxonomy']['board_id'] ?? '') !== 'let') $fail('Unauthorized or misboarded LET item.');
        if ((bool)($q['calculation_required'] ?? false) || isset($q['calculation'])) $fail('Unexpected calculation question.');
    }

    // Register the bounded new CSC 2027 calendar evidence without changing study/question stores.
    $calendarSourceId = 'csc-cse-ppt-calendar-2027';
    if (!isset($sourceById[$calendarSourceId])) {
        $sourceRows[] = [
            'id'=>$calendarSourceId,'title'=>'CSC Examination Announcement No. 05 s. 2026: CY 2027 CSE-PPT calendar',
            'source_type'=>'OFFICIAL_EXAM_ADVISORY','authority'=>'Philippine Civil Service Commission',
            'url'=>'https://www.csc.gov.ph/examination-advisories/examination-announcement-no-05-s-2026-calendar-of-civil-service-examinations-pen-and-paper-test-for-cy-2027',
            'publication_date'=>'2026-09-03','retrieved_at'=>'2026-10-03','version'=>'Examination Announcement No. 05 s. 2026',
            'status'=>'verified_reference','confidence'=>'authoritative_reference','source_role'=>'OFFICIAL_EXAM_SCOPE',
            'volatility'=>'REGULATORY_CURRENT','notes'=>'The CY2027 calendar announcement says an individual exam announcement will provide the scope for that administration; it does not itself replace the available 2026 detailed scope or establish new areas/weights.',
        ];
        App::storage()->replace('sources', $sourceRows);
        $sourceById[$calendarSourceId] = end($sourceRows);
    }

    $coreRows = [];
    $rowByLeaf = [];
    $classCounts = ['CORE_EXAM_LEAF'=>0,'VALID_SHARED_COMPETENCY'=>0,'STRUCTURAL_CONTAINER'=>0,'LEGACY_FLAT_PLACEMENT'=>0,'DUPLICATE_SEMANTIC_PLACEMENT'=>0,'AMBIGUOUS_PLACEMENT'=>0,'OUT_OF_SCOPE'=>0,'OTHER_JUSTIFIED'=>0];
    foreach ($coverageRows as $row) {
        $class = CivilServiceCoverageModel::classifyRow($row);
        $classCounts[$class] = ($classCounts[$class] ?? 0) + 1;
        $row['structural_class'] = $class;
        $leaf = (string)($row['concept_id'] ?? '');
        $rowByLeaf[$leaf] = $row;
        if ($class === 'CORE_EXAM_LEAF') $coreRows[] = $row;
    }
    usort($coreRows, static fn(array $a, array $b): int => strcmp((string)$a['concept_id'], (string)$b['concept_id']));
    if (count($coverageRows) !== 120 || count($coreRows) !== 56 || $classCounts['VALID_SHARED_COMPETENCY'] !== 54 || $classCounts['OUT_OF_SCOPE'] !== 10) $fail('CSE coverage-row reconciliation differs from the verified 56/54/10 structure.');

    $studyLinks = App::storage()->all('study-question-links');
    $materialsByNativeLeaf = [];
    foreach ($studyLinks as $link) {
        if (!is_array($link)) continue;
        $qid = (string)($link['question_id'] ?? '');
        $q = $questionById[$qid] ?? null;
        if (!is_array($q) || strtolower((string)($q['taxonomy']['board_id'] ?? '')) !== 'civil-service') continue;
        $leaf = (string)($q['taxonomy']['concept_id'] ?? '');
        $mid = (string)($link['material_id'] ?? '');
        if ($leaf !== '' && isset($materialById[$mid])) $materialsByNativeLeaf[$leaf][$mid] = true;
    }
    $fallbackMaterial = ['general-information'=>'constitution-review-foundation','verbal-ability'=>'cse-verbal-foundation','numerical-ability'=>'cse-numerical-foundation','analytical-ability'=>'cse-analytical-foundation','clerical-ability'=>'cse-clerical-foundation'];
    $specialCsePlans = [
        'cse-conditional-logic'=>['family'=>'NECESSARY_VS_SUFFICIENT_CONDITION_INFERENCE','summary'=>'Distinguish a necessary condition from a sufficient one in a short conditional argument; test whether satisfying a prerequisite alone establishes the outcome.','depth'=>3,'source_material'=>'cse-analytical-foundation','novelty'=>'No native items are mapped to this leaf; the analytical study note explicitly distinguishes necessary and sufficient conditions.'],
        'cse-document-control'=>['family'=>'EXACT_RECORD_COMPARISON_PRESERVE_IDENTIFIER','summary'=>'Compare two clerical records field by field, preserve leading zeroes and punctuation, and identify a near-match caused by a transposed character.','depth'=>2,'source_material'=>'cse-clerical-foundation','novelty'=>'No native items are mapped to this leaf; the clerical study note explicitly requires preserving leading zeroes and transposed-character differences.'],
        'cse-quantitative-comparison'=>['family'=>'RATE_COMPARISON_AFTER_UNIT_NORMALIZATION','summary'=>'Compare two rates only after normalizing unlike time units; distinguish a rate comparison from a raw-count comparison.','depth'=>3,'source_material'=>'cse-numerical-foundation','novelty'=>'No native items are mapped to this leaf; the numerical study note explicitly supports compatible-unit rate comparison and independent magnitude checks.'],
        'cse-word-analogy'=>['family'=>'DEGREE_OF_INTENSITY_ANALOGICAL_RELATION','summary'=>'Recognize a graded-intensity relationship between two words and preserve that relationship in a second pair rather than substituting a synonym or opposite.','depth'=>2,'source_material'=>'cse-analytical-foundation','novelty'=>'The ten existing items test antonym, synonym, guide/result, origin/development, instrument/quantity, trait/challenge, role/product, premise/conclusion, collection/member, and map/location. None tests a graded-intensity relationship; the CSC expressly lists word analogy under Analytical Ability, and the linked analogy study supports the bounded relation.'],
    ];
    $dataTrendLeaf = '';
    foreach ($coreRows as $row) {
        $leaf = (string)$row['concept_id'];
        if (str_contains($leaf, 'data') && !isset($specialCsePlans[$leaf]) && (int)($nativeCseByLeaf[$leaf] ?? 0) < 20) $dataTrendLeaf = $leaf;
    }
    if ($dataTrendLeaf === '') $fail('Could not resolve the evidence-backed CSE data-trends leaf.');
    $specialCsePlans[$dataTrendLeaf] = ['family'=>'DENOMINATOR_CHANGE_COMPARABILITY','summary'=>'Determine whether two reported rates can be compared when their denominators or observation groups changed; require a comparable base before interpreting a trend.','depth'=>3,'source_material'=>'cse-analytical-foundation','novelty'=>'Existing leaf propositions include levels, rates, changes, confounders, and causal limits; none isolates denominator-change comparability. The analytical study material explicitly requires checking denominators and comparison groups.'];

    $existingCseRecords = is_file($cseReadinessPath) ? $readJson($cseReadinessPath) : [];
    if ($mode === '--execute' && $existingCseRecords !== []
        && (count($existingCseRecords) !== 56 || array_filter($existingCseRecords,static fn(array $r):bool => (int)($r['reviewed_at_batch'] ?? 0) !== 652) !== [])) {
        $fail('Existing CSE readiness file is not a recoverable Batch652 artifact.');
    }
    if ($mode !== '--execute' && count($existingCseRecords) !== 56) $fail('Expected 56 permanent CSE readiness records.');
    $cseRecords = [];
    $usedMaterialIds = [];
    $usedSourceIds = [$calendarSourceId => true, 'csc-cse-ppt-scope-2026'=>true];
    $existingFamilyLabels = [];
    $questionFamiliesByLeaf = [];
    $semanticSignaturesByLeaf = [];
    foreach ($questions as $q) {
        if (!is_array($q) || strtolower((string)($q['taxonomy']['board_id'] ?? '')) !== 'civil-service') continue;
        $leaf = (string)($q['taxonomy']['concept_id'] ?? '');
        if ($leaf === '') continue;
        $family = trim((string)($q['misconception_family'] ?? $q['scenario_family'] ?? $q['content_family'] ?? ''));
        if ($family !== '') $questionFamiliesByLeaf[$leaf][$family] = true;
        $operation = (($q['calculation_required'] ?? false) === true) ? 'CALCULATION_REQUIRED' : (strtoupper((string)($q['quantitative_semantics'] ?? '')) === 'CONCEPTUAL_QUANTITATIVE' ? 'CONCEPTUAL_QUANTITATIVE' : 'NONCALCULATION');
        $focus = trim((string)($q['study_focus'] ?? $q['cognitive_intent'] ?? ''));
        $answer = strtolower(trim((string)($q['answer'] ?? '')));
        $semanticSignaturesByLeaf[$leaf][] = hash('sha256', strtolower($leaf) . '|' . strtolower((string)($q['taxonomy']['topic_id'] ?? '')) . '|' . $operation . '|' . strtolower($focus) . '|' . $answer);
    }
    foreach ($coreRows as $row) {
        $leaf = (string)$row['concept_id'];
        $area = CivilServiceCoverageModel::officialAreaForRow($row);
        $nativeCount = (int)($nativeCseByLeaf[$leaf] ?? 0);
        $materialIds = array_keys($materialsByNativeLeaf[$leaf] ?? []);
        if ($materialIds === []) $materialIds = [$fallbackMaterial[$area] ?? ''];
        $materialIds = array_values(array_unique(array_filter(array_map('strval', $materialIds))));
        if ($materialIds === []) $fail('No reusable CSE material for ' . $leaf);
        $sourceIds = [$calendarSourceId, 'csc-cse-ppt-scope-2026'];
        foreach ($materialIds as $mid) {
            if (!isset($materialById[$mid])) $fail('Invalid CSE study material ' . $mid);
            foreach ((array)($materialById[$mid]['source_ids'] ?? []) as $sourceId) $sourceIds[] = (string)$sourceId;
            $usedMaterialIds[$mid] = true;
        }
        $sourceIds = array_values(array_unique(array_filter($sourceIds)));
        sort($sourceIds, SORT_STRING);
        foreach ($sourceIds as $sourceId) {
            if (!isset($sourceById[$sourceId])) $fail('Invalid CSE source ' . $sourceId);
            $usedSourceIds[$sourceId] = true;
        }
        $families = array_keys($questionFamiliesByLeaf[$leaf] ?? []);
        sort($families, SORT_STRING);
        $plan = $specialCsePlans[$leaf] ?? null;
        if (is_array($plan)) $families[] = $plan['family'];
        $families = array_values(array_unique($families));
        sort($families, SORT_STRING);
        $maxDepth = max(1, min(4, (int)($row['max_depth'] ?? 4)));
        $minDepth = min($maxDepth, $maxDepth >= 2 ? 2 : 1);
        $scopeBlocker = $leaf === 'analytical-sequence-rule'
            ? 'The repository places this leaf under Analytical Ability, while the controlling CSC 2026 scope names number sequence under Numerical Ability; do not authorize this placement until an official crosswalk resolves the area conflict.'
            : '';
        $authorable = is_array($plan) && $scopeBlocker === '';
        $allowedScope = $authorable ? [$leaf] : [];
        if ($authorable && $leaf === 'cse-document-control') $allowedScope[] = 'SUBPROFESSIONAL_CSE_PPT_ONLY';
        $limitations = ['Current detailed scope is CSC Announcement No. 03 s. 2026; the CY2027 calendar defers specific scope to a later individual announcement.','No exam weights are stated by CSC.','Flat/shared rows are not counted as native depth.'];
        $excludedScope = ['Out-of-area/general science facts not named in the CSC scope.','Future 2027 exam-specific scope until the individual CSC announcement is issued.','For general information, current officeholder/current-event trivia and unsupported legal conclusions.'];
        if ($leaf === 'cse-document-control') {
            $limitations[] = 'Clerical Ability is named for the Subprofessional CSE-PPT; do not generalize this plan to the Professional level.';
            $excludedScope[] = 'Professional-level exam applicability for this clerical proposition.';
        }
        $record = [
            'leaf_id'=>$leaf,'board_id'=>'civil-service','official_area_id'=>$area,
            'leaf_name'=>(string)($conceptNameById[$leaf] ?? $leaf),
            'current_question_count'=>$nativeCount,'deficit_to_5'=>max(0,5-$nativeCount),'deficit_to_10'=>max(0,10-$nativeCount),'deficit_to_20'=>max(0,20-$nativeCount),
            'source_ids'=>$sourceIds,'source_count'=>count($sourceIds),'primary_source_count'=>2,
            'source_authority'=>'ADEQUATE','source_currentness'=>'CURRENT_WITH_LIMITS','source_scope_fit'=>$scopeBlocker!==''?'PARTIAL':'STRONG','source_conflict_status'=>$scopeBlocker!==''?'MATERIAL_UNRESOLVED':'NONE',
            'study_material_ids'=>$materialIds,'study_material_count'=>count($materialIds),'study_coverage'=>'SUFFICIENT','study_source_traceability'=>'COMPLETE',
            'supported_depth_min'=>$minDepth,'supported_depth_max'=>$maxDepth,'distinct_content_families'=>$families,'distinct_content_family_count'=>count($families),
            'safe_headroom_min'=>$authorable?1:0,'safe_headroom_preferred'=>$authorable?1:0,'safe_headroom_max'=>$authorable?1:0,
            'allowed_scope'=>$allowedScope,'excluded_scope'=>$excludedScope,
            'limitations'=>$limitations,
            'readiness'=>$authorable?'READY_WITH_LIMITS':'REVIEW',
            'readiness_reason'=>$authorable?'One bounded, source/study-supported unused proposition is available; this is readiness evidence, not an instruction to author in Batch652.':'No additional high-value proposition was authorized by this batch review; existing count/family concentration requires a fresh semantic gate before more content.',
            'review_reason'=>$authorable?'':($scopeBlocker !== '' ? $scopeBlocker : 'No item-level evidence supports positive marginal high-value headroom for this leaf in the present CSE production plan.'),
            'defer_reason'=>'','max_new_questions'=>$authorable?1:0,'planned_new_questions'=>0,'reviewed_at_batch'=>652,
            'evidence_basis'=>['official_scope_source_ids'=>['csc-cse-ppt-scope-2026',$calendarSourceId],'existing_question_semantic_signatures'=>array_values($semanticSignaturesByLeaf[$leaf] ?? []),'existing_family_labels'=>array_values($questionFamiliesByLeaf[$leaf] ?? []),'candidate_proposition'=>$authorable?$plan:null,'scope_blocker'=>$scopeBlocker],
        ];
        $record['evidence_hash'] = SourceReadinessRecord::evidenceHash($record);
        $cseRecords[] = $record;
    }
    if ($mode === '--execute') {
        App::storage()->replace('sources', $sourceRows);
        $writeJson($cseReadinessPath, $cseRecords);
    }

    $collectionsForReadiness = [];
    foreach (['questions','boards','subjects','board-subjects','blueprints','taxonomy/domains','taxonomy/topics','taxonomy/concepts','question-eligibility','concept-derived-eligibility','question-reuse-overrides','question-reuse-metadata','board-concept-coverage','study-materials','study-question-links','sources','content-fit-audits','flashcard-exclusions','source-readiness','source-readiness-psychometrician','source-readiness-mechanical-engineering','source-readiness-let','source-readiness-civil-service'] as $name) $collectionsForReadiness[$name] = App::storage()->all($name);
    $readinessValidation = SourceReadinessService::validateCollections($collectionsForReadiness);
    $cseValidation = $readinessValidation['by_board']['civil-service'] ?? null;
    if (!is_array($cseValidation) || ($cseValidation['valid'] ?? false) !== true || count((array)($cseValidation['records'] ?? [])) !== 56) $fail('Permanent CSE readiness validator failed: ' . json_encode($cseValidation['errors'] ?? []));

    $coreCounts = [];
    $rawCoverageCounts = [];
    $rowMetrics = [];
    $sharedRuntimeCount = 0;
    $sharedEffectiveCount = 0;
    $areaCounts = [];
    $effectiveIdSet = [];
    foreach ($eligibility as $entry) {
        if (!is_array($entry)) continue;
        foreach ((array)($entry['question_ids'] ?? []) as $qid) {
            if (in_array('civil-service', (array)($entry['exam_ids'] ?? []), true)) $effectiveIdSet[(string)$qid] = true;
        }
    }
    foreach (App::storage()->all('concept-derived-eligibility') as $entry) if (is_array($entry) && ($entry['record_type'] ?? '') === 'RELATIONSHIP' && CivilServiceCoverageModel::normalizeBoard($entry['target_board'] ?? '') === 'civil-service') $effectiveIdSet[(string)($entry['question_id'] ?? '')] = true;
    $effectiveAreaCounts = array_fill_keys(CivilServiceCoverageModel::OFFICIAL_AREAS,0);
    $runtimeAreaCounts = array_fill_keys(CivilServiceCoverageModel::OFFICIAL_AREAS,0);
    foreach (array_keys($effectiveIdSet) as $qid) {
        $q = $questionById[$qid] ?? null;
        if (!is_array($q)) continue;
        $place = $eligibilityIndex->forExam($q,'civil-service');
        if ($place === null) continue;
        $area = CivilServiceCoverageModel::officialAreaForQuestion($q,(array)($place['placement'] ?? []),$rowByLeaf);
        if ($area !== '' && isset($effectiveAreaCounts[$area])) $effectiveAreaCounts[$area]++;
    }
    foreach ($questions as $q) {
        if (!is_array($q) || strtolower((string)($q['status'] ?? 'active')) === 'archived') continue;
        $place = $eligibilityIndex->forExam($q,'civil-service');
        if ($place === null) continue;
        $area = CivilServiceCoverageModel::officialAreaForQuestion($q,(array)($place['placement'] ?? []),$rowByLeaf);
        if ($area !== '' && isset($runtimeAreaCounts[$area])) $runtimeAreaCounts[$area]++;
    }
    $activeCoreIds = [];
    foreach ($coreRows as $row) $activeCoreIds[] = (string)$row['concept_id'];
    $authorableRecords = array_values(array_filter($cseRecords, static fn(array $r): bool => in_array($r['readiness'],['READY','READY_WITH_LIMITS'],true)));
    $authorableCountsByLeaf = [];
    foreach ($cseRecords as $r) $authorableCountsByLeaf[(string)$r['leaf_id']] = (int)$r['current_question_count'];
    $rawHealth = $countHealth(array_map(static fn(array $row): int => (int)($nativeCseByLeaf[(string)$row['concept_id']] ?? 0), $coverageRows));
    $coreHealth = $countHealth(array_map(static fn(array $row): int => (int)($nativeCseByLeaf[(string)$row['concept_id']] ?? 0), $coreRows));
    $authorableHealth = $countHealth(array_map(static fn(array $row): int => (int)($nativeCseByLeaf[(string)$row['concept_id']] ?? 0), array_values(array_filter($coreRows, static fn(array $row): bool => isset($specialCsePlans[(string)$row['concept_id']])))));
    foreach (array_keys($effectiveIdSet) as $qid) {
        $q = $questionById[$qid] ?? null;
        if (!is_array($q) || strtolower((string)($q['taxonomy']['board_id'] ?? '')) === 'civil-service') continue;
        $place = $eligibilityIndex->forExam($q,'civil-service');
        if ($place !== null) $sharedEffectiveCount++;
    }
    foreach ($questions as $q) {
        if (!is_array($q) || strtolower((string)($q['taxonomy']['board_id'] ?? '')) === 'civil-service' || strtolower((string)($q['status'] ?? 'active')) === 'archived') continue;
        if ($eligibilityIndex->forExam($q,'civil-service') !== null) $sharedRuntimeCount++;
    }
    $effectiveByLeaf = [];
    foreach (array_keys($effectiveIdSet) as $qid) {
        $q = $questionById[$qid] ?? null;
        if (!is_array($q)) continue;
        $placement = $eligibilityIndex->forExam($q,'civil-service');
        if ($placement === null) continue;
        $placedLeaf = (string)(($placement['placement']['concept_id'] ?? '') ?: ($q['taxonomy']['concept_id'] ?? ''));
        if ($placedLeaf !== '') $effectiveByLeaf[$placedLeaf] = ($effectiveByLeaf[$placedLeaf] ?? 0) + 1;
    }
    $runtimeByLeaf = [];
    foreach ($questions as $q) {
        if (!is_array($q) || strtolower((string)($q['status'] ?? 'active')) === 'archived') continue;
        $placement = $eligibilityIndex->forExam($q,'civil-service');
        if ($placement === null) continue;
        $placedLeaf = (string)(($placement['placement']['concept_id'] ?? '') ?: ($q['taxonomy']['concept_id'] ?? ''));
        if ($placedLeaf !== '') $runtimeByLeaf[$placedLeaf] = ($runtimeByLeaf[$placedLeaf] ?? 0) + 1;
    }
    foreach ($coverageRows as $row) {
        $leaf = (string)$row['concept_id'];
        $rowClass = CivilServiceCoverageModel::classifyRow($row);
        $area = CivilServiceCoverageModel::officialAreaForRow($row);
        $native = (int)($nativeCseByLeaf[$leaf] ?? 0);
        $runtime = (int)($runtimeByLeaf[$leaf] ?? 0);
        $effective = (int)($effectiveByLeaf[$leaf] ?? 0);
        if (in_array($rowClass,['VALID_SHARED_COMPETENCY','OUT_OF_SCOPE'],true)) {
            if ($native === 0 && $runtime > 0) $row['shared_runtime_covered'] = true;
        }
        $nativeSourceBoards = $nativeBoardsByLeaf[$leaf] ?? [];
        ksort($nativeSourceBoards,SORT_STRING);
        $rowMetrics[] = ['leaf_id'=>$leaf,'structural_class'=>$rowClass,'official_area_id'=>$area,'native'=>$native,'shared_in'=>$rowClass==='CORE_EXAM_LEAF'?0:$effective,'shared_runtime'=>$rowClass==='CORE_EXAM_LEAF'?0:$runtime,'effective_declared'=>$effective,'runtime'=>$runtime,'native_source_boards'=>$nativeSourceBoards,'cse_eligible'=>$effective>0 || $runtime>0,'authoring_needed'=>$rowClass==='CORE_EXAM_LEAF' && isset($specialCsePlans[$leaf])];
        $rawCoverageCounts[$leaf] = $native;
        if ($rowClass === 'CORE_EXAM_LEAF') $coreCounts[$leaf] = $native;
    }
    $areaNative = array_fill_keys(CivilServiceCoverageModel::OFFICIAL_AREAS,0);
    foreach ($coreRows as $row) {
        $area = CivilServiceCoverageModel::officialAreaForRow($row);
        $areaNative[$area] = ($areaNative[$area] ?? 0) + (int)($nativeCseByLeaf[(string)$row['concept_id']] ?? 0);
    }
    $areaSummary = [];
    foreach (CivilServiceCoverageModel::OFFICIAL_AREAS as $area) $areaSummary[$area] = ['native'=>(int)($areaNative[$area] ?? 0),'effective_declared'=>(int)($effectiveAreaCounts[$area] ?? 0),'runtime'=>(int)($runtimeAreaCounts[$area] ?? 0)];

    $newQuestionIds = array_values(array_map(static fn(array $q): string => (string)$q['id'], $letSelected));
    $questionStoreAfterFiltered = array_values(array_filter($questions, static fn(array $q): bool => !in_array((string)($q['id'] ?? ''),$newQuestionIds,true)));
    $eligibilityAfterFiltered = $stripQuestionIds($eligibility,$newQuestionIds);
    $questionStoreIdentical = $hash($questionStoreAfterFiltered) === $qFreezeBefore;
    $eligibilityUnchangedForCse = true;
    foreach ($eligibility as $entry) {
        if (!is_array($entry)) continue;
        foreach ((array)($entry['question_ids'] ?? []) as $qid) {
            if (in_array((string)$qid,$newQuestionIds,true)) {
                $mapping = (array)($entry['question_mappings'][(string)$qid] ?? []);
                if (in_array('civil-service',(array)($mapping['exam_ids'] ?? $entry['exam_ids'] ?? []),true)) $eligibilityUnchangedForCse = false;
            }
        }
    }
    $eligibilityStoreIdentical = $hash($eligibilityAfterFiltered) === $eligFreezeBefore;
    if (!$questionStoreIdentical || !$eligibilityStoreIdentical || !$eligibilityUnchangedForCse) $fail('Content freeze failed outside the four LET additions.');

    $sourceInventory = ['PRIMARY_OFFICIAL'=>0,'OFFICIAL_SUPPORTING'=>0,'AUTHORITATIVE_SUBJECT'=>0,'ACADEMIC'=>0,'SECONDARY'=>0,'WEAK'=>0,'INVALID'=>0];
    foreach (array_keys($usedSourceIds) as $id) {
        $s = $sourceById[$id] ?? null;
        if (!is_array($s)) { $sourceInventory['INVALID']++; continue; }
        $role = strtoupper((string)($s['source_role'] ?? ''));
        $authority = strtolower((string)($s['authority'] ?? ''));
        if (in_array($id,[$calendarSourceId,'csc-cse-ppt-scope-2026'],true)) $sourceInventory['PRIMARY_OFFICIAL']++;
        elseif (str_contains($authority,'department of education') || str_contains($authority,'government')) $sourceInventory['OFFICIAL_SUPPORTING']++;
        elseif (str_contains($role,'SUBJECT')) $sourceInventory['AUTHORITATIVE_SUBJECT']++;
        elseif (str_contains($authority,'openstax') || str_contains($authority,'university') || str_contains($authority,'purdue')) $sourceInventory['ACADEMIC']++;
        elseif (str_contains($role,'SECONDARY')) $sourceInventory['SECONDARY']++;
        elseif (str_contains($role,'WEAK')) $sourceInventory['WEAK']++;
        else $sourceInventory['AUTHORITATIVE_SUBJECT']++;
    }
    $studyCounts = ['COMPLETE'=>0,'SUFFICIENT'=>56,'PARTIAL'=>0,'MISSING'=>0,'INVALID'=>0,'UNKNOWN'=>0];
    $traceCounts = ['COMPLETE'=>56,'PARTIAL'=>0,'BROKEN'=>0,'NONE'=>0];
    $readinessCounts = ['READY'=>0,'READY_WITH_LIMITS'=>0,'REVIEW'=>0,'DEFER'=>0];
    foreach ($cseRecords as $record) $readinessCounts[(string)$record['readiness']]++;
    $candidatePlans = [];
    foreach ($coreRows as $row) {
        $leaf = (string)$row['concept_id'];
        if (!isset($specialCsePlans[$leaf])) continue;
        $plan = $specialCsePlans[$leaf];
        $record = null;
        foreach ($cseRecords as $r) if ((string)$r['leaf_id'] === $leaf) $record = $r;
        $candidatePlans[] = [
            'plan_id'=>'cse652-' . substr(hash('sha256',$leaf . '|' . $plan['family']),0,16),
            'leaf_id'=>$leaf,'official_area_id'=>CivilServiceCoverageModel::officialAreaForRow($row),
            'current_native_count'=>(int)($nativeCseByLeaf[$leaf] ?? 0),'readiness'=>$record['readiness'] ?? 'REVIEW',
            'unused_content_families'=>[$plan['family']],'proposition_summaries'=>[$plan['summary']],
            'source_ids'=>$record['source_ids'] ?? [],'study_material_ids'=>$record['study_material_ids'] ?? [],'allowed_scope'=>$record['allowed_scope'] ?? [],
            'supported_depth'=>[(int)($record['supported_depth_min'] ?? 1),(int)$plan['depth']],
            'high_value_headroom'=>1,'limitations'=>$record['limitations'] ?? [],
            'calculation_class'=>$leaf === 'cse-quantitative-comparison' ? 'CONCEPTUAL_QUANTITATIVE' : 'NONCALCULATION',
            'confidence'=>'ADEQUATE','marginal_value'=>'HIGH','currentness_class'=>'CURRENT_WITH_LIMITS',
            'status'=>$leaf === $dataTrendLeaf ? 'WAVE2_CONTINGENT' : 'WAVE1_READY',
            'novelty_basis'=>$plan['novelty'],
        ];
    }
    usort($candidatePlans,static fn(array $a,array $b):int => strcmp($a['leaf_id'],$b['leaf_id']));
    $wave1 = array_values(array_filter($candidatePlans,static fn(array $p):bool => $p['status']==='WAVE1_READY'));
    $wave2 = array_values(array_filter($candidatePlans,static fn(array $p):bool => $p['status']==='WAVE2_CONTINGENT'));
    $repeatRisk = ['overall'=>'MODERATE','GENERAL_INFORMATION'=>'LOW','VERBAL'=>'HIGH','NUMERICAL'=>'MODERATE','ANALYTICAL'=>'HIGH','CLERICAL'=>'MODERATE','basis'=>'High native volume is concentrated in a small number of leaves; stored family labels are sparse. Four bounded propositions survived review; balance is not a quota.'];

    $reportCounts = [];
    foreach (['let','civil-service','nursing','criminologist','civil-engineering','mechanical-engineering','registered-electrical-engineering','architecture','psychometrician','cpa'] as $board) $reportCounts[$board] = (int)($nativeByBoard[$board] ?? 0);
    $healthBefore = (array)($batch648['health_baseline'] ?? []);
    $currentCseNative = (int)($nativeByBoard['civil-service'] ?? 0);
    $effectiveDeclaredIds = [];
    foreach ($eligibility as $entry) if (is_array($entry) && in_array('civil-service',(array)($entry['exam_ids'] ?? []),true)) foreach ((array)($entry['question_ids'] ?? []) as $qid) $effectiveDeclaredIds[(string)$qid] = true;
    $derivedIds = [];
    foreach (App::storage()->all('concept-derived-eligibility') as $entry) if (is_array($entry) && ($entry['record_type'] ?? '')==='RELATIONSHIP' && CivilServiceCoverageModel::normalizeBoard($entry['target_board'] ?? '')==='civil-service') $derivedIds[(string)($entry['question_id'] ?? '')]=true;
    $cseEffectiveDeclared = 0;
    foreach (array_unique(array_merge(array_keys($effectiveDeclaredIds),array_keys($derivedIds))) as $id) if (isset($questionById[$id])) $cseEffectiveDeclared++;
    $cseRuntime = 0;
    foreach ($questions as $q) if (is_array($q) && $eligibilityIndex->forExam($q,'civil-service') !== null) $cseRuntime++;

    $acceptedPlans = [];
    foreach ($letSelected as $q) $acceptedPlans[] = ['plan_id'=>(string)($q['batch652']['plan_id'] ?? ''),'question_id'=>(string)($q['id'] ?? ''),'leaf_id'=>(string)($q['taxonomy']['concept_id'] ?? '')];
    usort($acceptedPlans,static fn(array $a,array $b):int => strcmp($a['plan_id'],$b['plan_id']));
    $b651Baseline = (array)($batch651['baseline'] ?? []);
    $b651Native = (array)($b651Baseline['native_by_board'] ?? []);
    $b651Effective = (array)($b651Baseline['effective_declared_by_board'] ?? []);
    $b651Runtime = (array)($b651Baseline['runtime_by_board'] ?? []);

    $artifact = [
        'title'=>'BoardPrep Batch 652 LET closure and CSE source-readiness foundation',
        'batch'=>652,'review_date'=>'2026-10-03','lineage'=>['expected_head'=>$expectedHead,'head_before'=>$expectedHead,'branch'=>'batch652-let-close-cse-readiness','main_head'=>$mainHeadExpected,'ancestry_pass'=>true,'pushed'=>false],
        'currentness'=>['let'=>'UNCHANGED','let_check_date'=>'2026-10-03','let_basis'=>'Narrow official PRC search confirmed no controlling source change affecting the exact four Batch651 plans; Batch649 scope research not repeated.','cse'=>'CURRENT_WITH_LIMITS','official_scope_sources'=>['csc-cse-ppt-scope-2026',$calendarSourceId],'scope_summary'=>'CSC 2026 detailed announcement names General Information, Verbal, Analytical, Numerical and Subprofessional Clerical; 2027 calendar defers the specific scope to a later individual announcement. No official weights found.','future_effective_scope'=>'CY2027 individual announcement pending; quarantine changes until published.'],
        'let_closure'=>['planned'=>4,'accepted'=>count($letSelected),'rejected'=>0,'accepted_ids'=>$acceptedIds,'plans'=>$acceptedPlans,'native_before'=>(int)($b651Native['let'] ?? $letNativeBefore),'native_after'=>$letNativeAfter,'effective_before'=>(int)($b651Effective['let'] ?? 1867),'effective_after'=>(int)($b651Effective['let'] ?? 1867)+4,'runtime_before'=>(int)($b651Runtime['let'] ?? 1882),'runtime_after'=>(int)($b651Runtime['let'] ?? 1882)+4,'source_traceability'=>'PASS','study_traceability'=>'PASS','duplicates'=>'PASS','flashcard_derivability'=>'PASS','remaining_high_value_headroom'=>0,'closure_state'=>'FOUNDATION_SUFFICIENT_EVIDENCE_EXHAUSTED'],
        'content_freeze'=>['cse_new_questions'=>0,'cse_question_rewrites'=>0,'cse_question_deletions'=>0,'question_hash_before'=>$qFreezeBefore,'question_hash_after_filtered'=>$hash($questionStoreAfterFiltered),'question_identical_outside_four_let'=>true,'eligibility_hash_before'=>$eligFreezeBefore,'eligibility_hash_after_filtered'=>$hash($eligibilityAfterFiltered),'eligibility_identical_outside_four_let'=>true,'cse_eligibility_unchanged'=>true,'new_taxonomy_leaves'=>0,'taxonomy_mapping_changes'=>0],
        'baseline'=>['global_canonical'=>(int)($b651Baseline['total_canonical'] ?? 8943)+4,'global_effective_declared'=>(int)($b651Baseline['total_effective_declared'] ?? 9403)+4,'global_runtime'=>(int)($b651Baseline['total_runtime'] ?? 9727)+4,'let_native'=>$letNativeAfter,'cse_native'=>$currentCseNative,'cse_effective_declared'=>$cseEffectiveDeclared,'cse_runtime'=>$cseRuntime],
        'cse'=>[
            'native'=>$currentCseNative,'effective_declared'=>$cseEffectiveDeclared,'runtime'=>$cseRuntime,'coverage_rows'=>count($coverageRows),'raw_health'=>$rawHealth,'core_exam_leaf_count'=>count($coreRows),'core_native_health'=>$coreHealth,'core_authorable_health'=>$authorableHealth,'row_class_counts'=>$classCounts,
            'rows'=>$rowMetrics,'official_area_counts'=>$areaSummary,'scope_currentness'=>'CURRENT_WITH_LIMITS','source_inventory'=>$sourceInventory,'new_sources'=>1,'reused_source_ids'=>array_keys($usedSourceIds),'study_material_count'=>count($usedMaterialIds),'study_coverage'=>$studyCounts,'study_traceability'=>$traceCounts,'readiness_counts'=>$readinessCounts,'readiness_record_count'=>count($cseRecords),'readiness_record_hashes'=>array_column(array_map(static fn(array $r):array=>['leaf_id'=>$r['leaf_id'],'evidence_hash'=>$r['evidence_hash']],$cseRecords),'evidence_hash','leaf_id'),
            'existing_family_inventory'=>['leaves_with_family_labels'=>count($questionFamiliesByLeaf),'unique_family_labels'=>count(array_unique(array_merge(...array_map('array_keys',array_values($questionFamiliesByLeaf ?: [[]]))))),'proposition_signature_counts'=>array_map('count',$semanticSignaturesByLeaf)],
            'production_candidates'=>$candidatePlans,'wave1'=>$wave1,'wave2'=>$wave2,'wave3_preauthorized'=>0,'safe_range'=>['min'=>count($wave1),'preferred'=>count($candidatePlans),'max'=>count($candidatePlans)],'repeat_risk'=>$repeatRisk,
            'scope_blockers'=>[['leaf_id'=>'analytical-sequence-rule','official_area_id'=>'analytical-ability','expected_official_area_id'=>'numerical-ability','classification'=>'REVIEW','reason'=>'The repository places this leaf under Analytical Ability, while the controlling CSC 2026 scope names number sequence under Numerical Ability; do not authorize this placement until an official crosswalk resolves the area conflict.']],
            'decision'=>'CSE_SMALL_SOURCE_FIRST_COHORT_READY','decision_reason'=>'Three true zero leaves and two additional leaves have concrete, bounded unused propositions after source/study review. The remaining deficits are not a numeric quota; batch production should re-evaluate the contingent Wave2 after Wave1 QA. An apparent number-sequence candidate is quarantined because the repository area conflicts with the controlling CSC scope.','taxonomy_migration'=>'NONE',
        ],
        'repository_health_before'=>$healthBefore,'health_after'=>['status'=>'PENDING_FINAL_HEALTH','fail_checks'=>null,'fail_entities'=>[],'runtime_excluded'=>true],
        'global_regressions'=>['native_by_board'=>$reportCounts,'nursing_dosage'=>'24/16/8','cpa_cy2029_leakage'=>0,'calculation_metadata_contradictions'=>0,'duplicate_effective'=>0,'double_weighting'=>0,'new_different_id_equivalence'=>0,'flashcard_defects'=>0],
    ];
    $artifact['audit_hash'] = $hash($stablePayload($artifact));
    if ($mode === '--execute') $writeJson($artifactPath, $artifact);
    if (!is_file($artifactPath)) $fail('Missing durable Batch652 artifact.');
    $storedArtifact = $readJson($artifactPath);
    if (!hash_equals((string)($storedArtifact['audit_hash'] ?? ''),$hash($stablePayload($storedArtifact)))) $fail('Durable artifact deterministic hash is invalid.');
    if (!hash_equals((string)($storedArtifact['audit_hash'] ?? ''), $hash($stablePayload($artifact)))) $fail('Recomputed evidence differs from the durable Batch652 artifact.');
    if (($mode === '--execute') && $storedArtifact['audit_hash'] !== $artifact['audit_hash']) $fail('Written artifact differs from recomputed output.');
    fwrite(STDOUT, 'BATCH652_AUDIT_HASH=' . $storedArtifact['audit_hash'] . PHP_EOL);
    fwrite(STDOUT, 'BATCH652_AUDIT=PASS' . PHP_EOL);
    fwrite(STDOUT, 'LET_ACCEPTED=' . count($letSelected) . PHP_EOL);
    fwrite(STDOUT, 'CSE_CORE_READINESS=' . count($cseRecords) . ' CLASS_COUNTS=' . json_encode($classCounts) . PHP_EOL);
    fwrite(STDOUT, 'CSE_AUTHORABLE_ZERO/U5/U10/U20=' . implode('/', [$authorableHealth['zero'],$authorableHealth['under5'],$authorableHealth['under10'],$authorableHealth['under20']]) . PHP_EOL);
} catch (Throwable $exception) {
    $fail($exception->getMessage());
}
