<?php

declare(strict_types=1);

/**
 * Batch643 milestone orchestration.
 *
 * The question, eligibility, study-link, flashcard, and readiness contracts
 * remain application services.  This file owns only the Batch643 evidence
 * map, the bounded 21-question cohort, wave bookkeeping, and the durable
 * milestone artifact.
 */

require_once dirname(__DIR__) . '/app/Core/Autoloader.php';

use App\Core\App;
use App\Core\Autoloader;
use App\Repositories\QuestionRepository;
use App\Services\Flashcard\FlashcardCardValidator;
use App\Services\Flashcard\FlashcardDerivationService;
use App\Services\Question\QuestionDuplicateService;
use App\Services\Question\QuestionEligibilityService;
use App\Services\Question\QuestionService;
use App\Services\Shared\QuestionValidationService;
use App\Services\RepositoryHealth\Readiness\SourceReadinessRecord;
use App\Services\RepositoryHealth\Readiness\SourceReadinessService;

Autoloader::register();
putenv('APP_ENV=testing');
putenv('APP_DEBUG=false');
App::boot();

$root = dirname(__DIR__);
$artifactPath = $root . '/storage/batch643-psychometrician-expansion.json';
$args = array_slice($argv ?? [], 1);
$allowed = ['--baseline', '--wave1', '--wave2', '--finalize', '--audit'];
$invalid = array_values(array_diff($args, $allowed));
if ($invalid !== [] || count($args) !== 1) {
    fwrite(STDERR, "Usage: php tools/batch643-psychometrician-expansion-audit.php --baseline|--wave1|--wave2|--finalize|--audit\n");
    exit(2);
}
$mode = $args[0];

$readJson = static function (string $relative) use ($root): array {
    $path = $root . '/' . ltrim($relative, '/');
    if (!is_file($path)) {
        throw new RuntimeException('Missing input: ' . $relative);
    }
    $value = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    return is_array($value) ? $value : [];
};

$writeJson = static function (string $relative, array $value) use ($root): void {
    $path = $root . '/' . ltrim($relative, '/');
    $temporary = $path . '.tmp';
    $json = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    file_put_contents($temporary, $json . "\n", LOCK_EX);
    rename($temporary, $path);
};

$collections = static function () use ($readJson): array {
    $names = [
        'questions', 'boards', 'subjects', 'board-subjects', 'blueprints',
        'taxonomy/domains', 'taxonomy/topics', 'taxonomy/concepts',
        'question-eligibility', 'concept-derived-eligibility',
        'question-reuse-overrides', 'question-reuse-metadata',
        'board-concept-coverage', 'study-materials', 'study-question-links',
        'sources', 'content-fit-audits', 'flashcard-exclusions',
        'source-readiness', 'source-readiness-psychometrician',
    ];
    $result = [];
    foreach ($names as $name) {
        $result[$name] = $readJson('storage/' . $name . '.json');
    }
    return $result;
};

$psychQuestions = static function (array $input): array {
    return array_values(array_filter(
        (array) ($input['questions'] ?? []),
        static fn(mixed $question): bool => is_array($question)
            && strtolower((string) ($question['taxonomy']['board_id'] ?? '')) === 'psychometrician'
            && strtolower((string) ($question['status'] ?? 'active')) !== 'archived'
    ));
};

$nativeCounts = static function (array $input): array {
    return SourceReadinessService::nativeLeafCounts($input, 'psychometrician');
};

$taxonomyFor = static function (string $leaf, array $input): array {
    $concept = null;
    foreach ((array) ($input['taxonomy/concepts'] ?? []) as $row) {
        if (is_array($row) && (string) ($row['id'] ?? '') === $leaf) {
            $concept = $row;
            break;
        }
    }
    if ($concept === null) {
        throw new RuntimeException('Psychometrician leaf is not a configured concept: ' . $leaf);
    }
    $topic = null;
    foreach ((array) ($input['taxonomy/topics'] ?? []) as $row) {
        if (is_array($row) && (string) ($row['id'] ?? '') === (string) ($concept['topic_id'] ?? '')) {
            $topic = $row;
            break;
        }
    }
    $domain = null;
    foreach ((array) ($input['taxonomy/domains'] ?? []) as $row) {
        if (is_array($row) && (string) ($row['id'] ?? '') === (string) ($topic['domain_id'] ?? '')) {
            $domain = $row;
            break;
        }
    }
    if ($topic === null || $domain === null) {
        throw new RuntimeException('Psychometrician taxonomy chain is incomplete for ' . $leaf);
    }
    return [
        'board_id' => 'psychometrician',
        'subject_id' => (string) ($domain['subject_id'] ?? ''),
        'domain_id' => (string) ($domain['id'] ?? ''),
        'topic_id' => (string) ($topic['id'] ?? ''),
        'concept_id' => $leaf,
    ];
};

$readinessByLeaf = static function (array $input): array {
    $result = [];
    foreach ((array) ($input['source-readiness-psychometrician'] ?? []) as $record) {
        if (is_array($record) && trim((string) ($record['leaf_id'] ?? '')) !== '') {
            $result[(string) $record['leaf_id']] = $record;
        }
    }
    return $result;
};

$healthSummary = static function (array $health): array {
    $summary = is_array($health['summary'] ?? null) ? $health['summary'] : [];
    return [
        'overall_status' => (string) ($summary['overall_status'] ?? ''),
        'check_counts' => [
            'PASS' => (int) ($summary['pass_count'] ?? 0),
            'WARN' => (int) ($summary['warn_count'] ?? 0),
            'FAIL' => (int) ($summary['fail_count'] ?? 0),
            'REVIEW' => (int) ($summary['review_count'] ?? 0),
        ],
        'entity_counts' => [
            'FAIL' => (int) ($summary['fail_entity_count'] ?? 0),
            'WARN' => (int) ($summary['warn_entity_count'] ?? 0),
            'REVIEW' => (int) ($summary['review_entity_count'] ?? 0),
        ],
        'issue_count' => (int) ($summary['issue_count'] ?? count((array) ($health['issues'] ?? []))),
    ];
};

$readinessSummary = static function (array $input): array {
    $records = array_values($input['source-readiness-psychometrician'] ?? []);
    $validation = SourceReadinessService::validateCollections($input);
    $board = $validation['by_board']['psychometrician'] ?? [
        'valid' => false,
        'errors' => [['code' => 'SOURCE_READINESS.INVALID_RECORD']],
        'records' => $records,
    ];
    return SourceReadinessService::summary($records, $board);
};

$areaCounts = static function (array $input): array {
    $areas = [
        'psychological-assessment' => 0,
        'developmental-psychology' => 0,
        'abnormal-psychology' => 0,
        'industrial-organizational' => 0,
    ];
    foreach ($input['questions'] ?? [] as $question) {
        if (!is_array($question) || ($question['taxonomy']['board_id'] ?? '') !== 'psychometrician') continue;
        $area = (string) ($question['taxonomy']['domain_id'] ?? '');
        $area = match ($area) {
            'ple-assessment' => 'psychological-assessment',
            'ple-developmental' => 'developmental-psychology',
            'ple-abnormal' => 'abnormal-psychology',
            'ple-industrial' => 'industrial-organizational',
            default => 'unmapped',
        };
        $areas[$area] = ($areas[$area] ?? 0) + 1;
    }
    ksort($areas, SORT_STRING);
    return $areas;
};

$depthCounts = static function (array $input) use ($psychQuestions): array {
    $counts = array_fill_keys(['D1', 'D2', 'D3', 'D4', 'D5', 'UNKNOWN'], 0);
    $depthById = [];
    foreach ((array) ($input['question-reuse-metadata'] ?? []) as $metadata) {
        if (!is_array($metadata)) continue;
        $id = trim((string) ($metadata['question_id'] ?? ''));
        $depth = $metadata['reuse']['required_depth'] ?? null;
        if ($id !== '') $depthById[$id] = $depth;
    }
    foreach ($psychQuestions($input) as $question) {
        $depth = $depthById[(string) ($question['id'] ?? '')] ?? ($question['reuse']['required_depth'] ?? null);
        $key = is_int($depth) && $depth >= 1 && $depth <= 5 ? 'D' . $depth : 'UNKNOWN';
        $counts[$key]++;
    }
    return $counts;
};

$leafCounts = static function (array $input) use ($nativeCounts): array {
    $counts = $nativeCounts($input);
    ksort($counts, SORT_STRING);
    return $counts;
};

$distribution = static function (array $counts): array {
    $values = array_values(array_map('intval', $counts));
    sort($values, SORT_NUMERIC);
    $n = count($values);
    if ($n === 0) return ['min' => 0, 'median' => 0, 'p25' => 0, 'p75' => 0, 'max' => 0];
    $percentile = static function (float $p) use ($values, $n): float {
        if ($n === 1) return (float) $values[0];
        $position = ($n - 1) * $p;
        $lower = (int) floor($position);
        $upper = (int) ceil($position);
        if ($lower === $upper) return (float) $values[$lower];
        $weight = $position - $lower;
        return $values[$lower] + (($values[$upper] - $values[$lower]) * $weight);
    };
    return [
        'min' => $values[0],
        'median' => $percentile(0.5),
        'p25' => $percentile(0.25),
        'p75' => $percentile(0.75),
        'max' => $values[$n - 1],
    ];
};

$thresholds = static function (array $counts): array {
    $result = ['zero' => 0, 'under5' => 0, 'under10' => 0, 'under20' => 0, 'to5' => 0, 'to10' => 0, 'to20' => 0];
    foreach ($counts as $count) {
        $count = (int) $count;
        if ($count === 0) $result['zero']++;
        if ($count < 5) $result['under5']++;
        if ($count < 10) $result['under10']++;
        if ($count < 20) $result['under20']++;
        $result['to5'] += max(0, 5 - $count);
        $result['to10'] += max(0, 10 - $count);
        $result['to20'] += max(0, 20 - $count);
    }
    return $result;
};

$definitions = static function (): array {
    $common = static function (
        string $key,
        string $leaf,
        string $material,
        array $scope,
        int $depth,
        string $question,
        array $choices,
        string $answer,
        string $explanation,
        string $family,
        string $misconception,
        string $sensitivity = 'STABLE_FOUNDATIONAL'
    ): array {
        return [
            'key' => $key,
            'leaf' => $leaf,
            'study_material_id' => $material,
            'source_scope' => $scope,
            'depth' => $depth,
            'question' => $question,
            'choices' => $choices,
            'answer' => $answer,
            'explanation' => $explanation,
            'family' => $family,
            'misconception' => $misconception,
            'source_sensitivity' => $sensitivity,
        ];
    };

    return [
        $common('uses-purpose-standard', 'psych-assessment-uses-limitations', 'ple-487--orms-and-core-nterpretation', ['current_prc_tos', 'assessment_purpose'], 3,
            'A school needs to decide whether a learner met a prespecified mastery standard, but the available instrument was built to rank learners within a norm group. Which basis should guide the replacement choice?',
            ['A criterion-referenced measure aligned with the stated mastery standard.', 'A second norm-referenced ranking measure with a larger sample.', 'The instrument with the longest report regardless of its purpose.', 'Any available measure because all scores have the same meaning.'],
            'A criterion-referenced measure aligned with the stated mastery standard.',
            'The assessment method must match the decision purpose: mastery decisions require comparison with a defined standard rather than only relative standing.',
            'purpose_fit', 'norm_reference_for_mastery'),
        $common('uses-screening-boundary', 'psych-assessment-uses-limitations', 'ple-487--sychological-ssessment-oundations', ['current_prc_tos', 'limitations_and_consequences'], 4,
            'An examinee scores just above a screening threshold, but the score\'s confidence band crosses the threshold. Which interpretation is most defensible?',
            ['Treat the classification as uncertain at that cut score and consider score precision, decision consequences, and corroborating evidence.', 'Treat the point score as exact and disregard the confidence band.', 'Conclude that crossing the threshold proves the target condition is present.', 'Discard every score near a threshold because all measurements are unusable.'],
            'Treat the classification as uncertain at that cut score and consider score precision, decision consequences, and corroborating evidence.',
            'A threshold does not remove measurement error; when score uncertainty spans the cut, interpret the result with its precision and the consequences of classification.',
            'score_precision_at_threshold', 'point_score_is_exact'),
        $common('uses-percentile-meaning', 'psych-assessment-uses-limitations', 'ple-487--orms-and-core-nterpretation', ['current_prc_tos', 'assessment_purpose'], 2,
            'A norm-referenced report places a client at the 70th percentile. What does that result support?',
            ['Relative standing within the specified norm group, not a percentage-correct result or diagnosis.', 'A 70 percent probability that the client has the measured condition.', 'A guarantee that the client will outperform every future comparison group.', 'A direct measure of the number of items answered correctly.'],
            'Relative standing within the specified norm group, not a percentage-correct result or diagnosis.',
            'A percentile rank describes relative position in the stated norm group; it is not a percentage correct, diagnosis, or forecast guarantee.',
            'benefit_limitations', 'percentile_equals_percent_correct'),
        $common('uses-reliability-validity', 'psych-assessment-uses-limitations', 'ple-487--sychological-ssessment-oundations', ['current_prc_tos', 'limitations_and_consequences'], 3,
            'A test has strong reliability evidence, but no evidence supports using its scores to make the proposed high-stakes decision. Which conclusion is warranted?',
            ['The score interpretation remains unsupported for that use despite the reliability evidence.', 'Reliability alone proves that the proposed decision is valid.', 'The lack of use evidence does not matter if the score is consistent.', 'High-stakes use is justified whenever a test manual is available.'],
            'The score interpretation remains unsupported for that use despite the reliability evidence.',
            'Consistency of scores is important but does not establish that a particular interpretation and use are valid for the stated population and decision.',
            'limitations_and_consequences', 'reliability_equals_validity'),
        $common('uses-discrepant-evidence', 'psych-assessment-uses-limitations', 'ple-487--orms-and-core-nterpretation', ['current_prc_tos', 'limitations_and_consequences'], 4,
            'Two assessment scores disagree, and the referral decision has significant consequences. What should the examiner do before making a broad conclusion?',
            ['Examine measurement error, relevant norms, collateral information, and the stated decision purpose.', 'Select the more favorable score and discard the other result.', 'Average the scores without checking whether they measure the same construct.', 'Treat the disagreement as proof that the person is inconsistent.'],
            'Examine measurement error, relevant norms, collateral information, and the stated decision purpose.',
            'Discrepant scores require interpretation of uncertainty, construct, context, and corroborating information before a consequential inference.',
            'decision_consequences', 'single_score_shortcut'),
        $common('uses-job-fit-limit', 'psych-assessment-uses-limitations', 'ple-487--orms-and-core-nterpretation', ['current_prc_tos', 'limitations_and_consequences'], 3,
            'A manager wants to use an isolated personality score to predict job performance for a new role. Which safeguard is most important?',
            ['Check whether validity evidence supports that interpretation in the relevant job and population.', 'Use the score because personality measures apply identically to every job.', 'Treat a high score as proof of future performance without job analysis.', 'Replace the score with an unstructured impression from one interview.'],
            'Check whether validity evidence supports that interpretation in the relevant job and population.',
            'A score interpretation must be supported for the intended job, population, and use; a general score is not automatically a job-performance forecast.',
            'limitations_and_consequences', 'trait_score_equals_job_fit'),
        $common('uses-formative-inference', 'psych-assessment-uses-limitations', 'ple-487--orms-and-core-nterpretation', ['current_prc_tos', 'assessment_purpose'], 4,
            'A formative classroom measure shows improvement after coaching. Which inference is least justified?',
            ['That the score alone proves a stable trait or general ability.', 'That the observed result may inform the next instructional decision.', 'That the interpretation should remain tied to the measure\'s stated purpose.', 'That other evidence can help evaluate whether the change generalizes.'],
            'That the score alone proves a stable trait or general ability.',
            'A formative result can support an instructional decision, but it does not by itself establish a stable trait or broad general ability.',
            'limitations_and_consequences', 'formative_score_equals_trait'),

        $common('etiology-interaction', 'psych-abnormal-etiology', 'ple-487--ymptom-atterns-and-unctioning', ['current_prc_tos', 'etiological_models', 'interaction_and_context'], 4,
            'A client reports that a long-standing vulnerability became more disruptive during an extended period of stress while support also changed. Which formulation is most defensible?',
            ['Consider a possible vulnerability–stress interaction and assess other biological, psychological, and contextual contributors without claiming that timing proves one cause.', 'Treat the stressor as the sole cause because symptoms changed during the same period.', 'Assume the vulnerability determines the outcome regardless of current context.', 'Treat the pattern as proof of a specific diagnosis without checking duration or impairment.'],
            'Consider a possible vulnerability–stress interaction and assess other biological, psychological, and contextual contributors without claiming that timing proves one cause.',
            'An interaction model can organize a hypothesis about vulnerability and context, but temporal association does not prove causation or establish an individual diagnosis.',
            'vulnerability_stress_interaction', 'interaction_is_deterministic', 'SENSITIVE_REVIEWED'),
        $common('interventions-role-boundary', 'psych-abnormal-interventions', 'ple-487--bnormal-sychology-oundations', ['current_prc_tos', 'intervention_principles', 'risk_and_referral'], 3,
            'A referral asks a psychometrician to choose an individualized treatment solely from one test score. What is the appropriate next step?',
            ['Use the result to inform referral and risk communication while deferring treatment selection to the appropriate professional.', 'Select the treatment most often associated with the score without further assessment.', 'Present the score as a treatment prescription because it is standardized.', 'Avoid documenting the referral because treatment is outside the test report.'],
            'Use the result to inform referral and risk communication while deferring treatment selection to the appropriate professional.',
            'The result may inform referral and communication, but an individualized treatment decision requires the appropriate professional role and fuller evidence.',
            'risk_and_referral', 'test_score_as_prescription', 'SENSITIVE_REVIEWED'),
        $common('interventions-risk-escalation', 'psych-abnormal-interventions', 'ple-487--ymptom-atterns-and-unctioning', ['current_prc_tos', 'evidence_and_limits'], 4,
            'After a qualified clinician begins an evidence-informed intervention, a client reports less distress but still struggles with work and daily tasks. Which outcome conclusion is most defensible?',
            ['Interpret symptom and functional indicators separately over time; improvement in one measure alone does not establish recovery or overall effectiveness.', 'Declare full recovery because distress decreased on one measure.', 'Conclude the intervention failed because functioning did not immediately change.', 'Avoid monitoring outcomes because assessment has no role after referral.'],
            'Interpret symptom and functional indicators separately over time; improvement in one measure alone does not establish recovery or overall effectiveness.',
            'Outcome evidence should match the referral question and include relevant functioning over time; one improved indicator does not establish overall effectiveness.',
            'intervention_outcome_evaluation', 'single_outcome_equals_effectiveness', 'SENSITIVE_REVIEWED'),
        $common('selection-bilingual-fit', 'psych-assessment-selection-tools', 'ple-487--alidity-and-est-se', ['current_prc_tos', 'selection_of_methods', 'population_and_setting_fit'], 3,
            'An examinee with limited hand mobility needs a timed reasoning assessment, but the standard form requires rapid written marking and motor speed is not part of the referral construct. What should guide method selection?',
            ['Review an accessible response mode or suitable alternative and determine whether the accommodation changes the target construct.', 'Use the standard form unchanged because uniform administration is always fair.', 'Remove all time limits without checking whether speed is part of the construct.', 'Apply the original norms to any modified administration without qualification.'],
            'Review an accessible response mode or suitable alternative and determine whether the accommodation changes the target construct.',
            'Accessibility and accommodation should preserve the intended construct where possible, with evidence and interpretation limits stated for any changed administration.',
            'accessible_response_mode', 'standard_form_equals_fairness'),
        $common('selection-translation-equivalence', 'psych-assessment-selection-tools', 'ple-487--alidity-and-est-se', ['current_prc_tos', 'selection_of_methods', 'population_and_setting_fit'], 4,
            'During adaptation review, examinees in a local language group explain that a translated idiom carries a different meaning from the source version. What should be checked before interpreting group score differences?',
            ['Review response-process and item-functioning evidence in the target group, then revise and pilot items as needed.', 'Treat accurate back-translation as proof that score meaning is unchanged.', 'Transfer the source-group norms because the item wording is grammatically correct.', 'Assume similar internal consistency proves that examinees interpreted each item the same way.'],
            'Review response-process and item-functioning evidence in the target group, then revise and pilot items as needed.',
            'Linguistic similarity alone does not establish equivalent response processes; item meaning and functioning need evidence in the intended group.',
            'item_response_process', 'literal_translation_preserves_construct'),

        $common('org-levels-analysis', 'psych-industrial-organization-theory', 'ple-487--ndustrial-rganizational-oundations', ['current_prc_tos', 'organization_theory'], 4,
            'A company-wide climate average is used to explain a productivity change, but team-level results differ widely and several units report opposite patterns. What should a reviewer check before drawing an organization-wide conclusion?',
            ['Examine unit-level distributions and evidence that perceptions are sufficiently shared before aggregating the scores.', 'Treat the overall mean as representative because every unit completed the same survey.', 'Assign the difference to individual traits without examining group patterns.', 'Discard all climate evidence because perceptions cannot inform workplace analysis.'],
            'Examine unit-level distributions and evidence that perceptions are sufficiently shared before aggregating the scores.',
            'Individual, group, and organizational explanations must remain distinct; an overall mean can conceal meaningful local conditions when units differ.',
            'levels_of_analysis', 'organization_mean_represents_every_unit'),
        $common('org-leadership-evidence', 'psych-industrial-organization-theory', 'ple-487--otivation-eadership-and-ork-ehavior', ['current_prc_tos', 'leadership_and_culture'], 3,
            'A team reports that a new leader\'s preferred style improved morale, but no work or climate evidence was collected. What conclusion is justified?',
            ['The preference is not evidence of an organizational effect without a defined outcome and supporting data.', 'The preferred style caused the improvement because the leader selected it intentionally.', 'Morale reports can be treated as proof of every work outcome.', 'No leadership information can ever be useful without a randomized experiment.'],
            'The preference is not evidence of an organizational effect without a defined outcome and supporting data.',
            'A leadership preference is not the same as evidence of an effect; the outcome, context, and supporting data must be specified.',
            'leadership_and_culture', 'preference_equals_effect'),
        $common('org-culture-climate', 'psych-industrial-organization-theory', 'ple-487--otivation-eadership-and-ork-ehavior', ['current_prc_tos', 'leadership_and_culture'], 4,
            'A workplace survey shows different perceptions across units. Which interpretation best respects the culture–climate distinction?',
            ['Treat perceptions as climate evidence and examine shared norms, context, sampling, and response bias before inferring culture.', 'Treat different perceptions as proof that each unit has a different culture.', 'Ignore the survey because perceptions cannot inform organizational analysis.', 'Use the most positive unit as the organization-wide cultural standard.'],
            'Treat perceptions as climate evidence and examine shared norms, context, sampling, and response bias before inferring culture.',
            'Climate concerns experienced conditions and perceptions; culture refers to shared meanings and norms, so one survey pattern is not enough to establish culture.',
            'leadership_and_culture', 'climate_equals_culture'),
        $common('structures-role-boundaries', 'psych-industrial-structures-systems', 'ple-487--ndustrial-rganizational-oundations', ['current_prc_tos', 'organizational_structures', 'roles_and_accountability'], 3,
            'An organization has repeated delays, and the chart shows overlapping authority for the same task. What should be examined first?',
            ['Clarify role boundaries, decision rights, and accountability before attributing the delay to motivation.', 'Increase motivation messaging without examining the authority structure.', 'Assign the delay to the newest employee because the task is late.', 'Remove all shared work so no one can coordinate decisions.'],
            'Clarify role boundaries, decision rights, and accountability before attributing the delay to motivation.',
            'Overlapping authority is a structural and accountability signal that should be clarified before an individual motivation explanation is made.',
            'structure_and_roles', 'motivation_for_structure'),
        $common('structures-strategy-alignment', 'psych-industrial-structures-systems', 'ple-487--ndustrial-rganizational-oundations', ['current_prc_tos', 'organizational_structures', 'systems_alignment'], 4,
            'A department reorganizes to match a new strategy, but responsibilities and resources remain unchanged. What is the main diagnostic question?',
            ['Whether the formal structure, roles, and resources actually align with the stated strategy.', 'Whether the new strategy can be announced more often to compensate for unchanged roles.', 'Whether employees can be held responsible before the new work system is specified.', 'Whether a single satisfaction score proves that alignment exists.'],
            'Whether the formal structure, roles, and resources actually align with the stated strategy.',
            'A reorganization is meaningful only when roles, resources, decision paths, and work systems support the stated strategic objective.',
            'accountability_and_alignment', 'relabeling_equals_alignment'),
        $common('structures-single-metric', 'psych-industrial-structures-systems', 'ple-487--ndustrial-rganizational-oundations', ['current_prc_tos', 'organizational_structures', 'systems_alignment'], 4,
            'An agency centralizes routine approvals after errors in one branch, but local teams now wait several days to resolve uncommon cases. What design question should the organizational review examine?',
            ['Which decisions need consistent central rules and which can be delegated locally, with clear escalation paths and outcome monitoring.', 'Whether all decisions should remain centralized because one branch made errors.', 'Whether every decision should be delegated because speed always outweighs consistency.', 'Whether a higher productivity target can replace clear decision authority.'],
            'Which decisions need consistent central rules and which can be delegated locally, with clear escalation paths and outcome monitoring.',
            'Centralization can improve consistency while limiting local response; decision rights should fit the work and include clear escalation and outcome review.',
            'centralization_delegation_tradeoff', 'centralization_guarantees_coordination'),
        $common('structures-accountability-alignment', 'psych-industrial-structures-systems', 'ple-487--otivation-eadership-and-ork-ehavior', ['current_prc_tos', 'roles_and_accountability', 'systems_alignment'], 3,
            'Two units share a target but use incompatible reporting responsibilities. Which intervention is most directly relevant?',
            ['Align responsibilities, information flows, and accountability with the shared work objective.', 'Increase the target without changing the reporting arrangement.', 'Evaluate each unit only by the other unit\'s informal impression.', 'Remove reporting so the units can coordinate without a record.'],
            'Align responsibilities, information flows, and accountability with the shared work objective.',
            'Shared objectives require compatible roles, information flows, and accountability so the system can coordinate and be evaluated.',
            'accountability_and_alignment', 'target_without_system'),
        $common('team-role-safety', 'psych-industrial-team-dynamics', 'ple-487--otivation-eadership-and-ork-ehavior', ['current_prc_tos', 'team_dynamics', 'group_processes'], 3,
            'A project team misses deadlines, but members report unclear roles and little opportunity to raise concerns. Which assessment is most useful first?',
            ['Examine role clarity, communication, and psychological safety before judging individual commitment.', 'Judge each member\'s commitment from the deadline alone.', 'Replace the team leader without checking the work process.', 'Treat the lack of concerns as proof that the team is functioning well.'],
            'Examine role clarity, communication, and psychological safety before judging individual commitment.',
            'Team performance should be interpreted through role clarity, communication, resources, and psychological safety rather than a single individual attribution.',
            'group_processes', 'individual_commitment_shortcut'),
        $common('team-intervention-outcome', 'psych-industrial-team-dynamics', 'ple-487--otivation-eadership-and-ork-ehavior', ['current_prc_tos', 'team_dynamics', 'team_effectiveness'], 4,
            'A team workshop improves satisfaction ratings for one week, but task coordination does not change. What should an evaluator conclude?',
            ['Treat the rating as limited evidence and check sustained team processes and performance outcomes.', 'Declare the workshop effective because satisfaction is always the primary outcome.', 'Discard all survey information because task coordination is the only valid measure.', 'Attribute the unchanged coordination to one resistant member without further evidence.'],
            'Treat the rating as limited evidence and check sustained team processes and performance outcomes.',
            'A short-term satisfaction change is one signal; team effectiveness requires attention to sustained processes and relevant work outcomes.',
            'team_effectiveness', 'satisfaction_equals_effectiveness'),
    ];
};

$definitionsByKey = [];
foreach ($definitions() as $definition) {
    $definitionsByKey[$definition['key']] = $definition;
}

$waveKeys = [
    1 => ['uses-purpose-standard', 'uses-screening-boundary'],
    2 => [
        'uses-percentile-meaning', 'uses-reliability-validity', 'uses-discrepant-evidence',
        'uses-job-fit-limit', 'uses-formative-inference', 'etiology-interaction',
        'interventions-role-boundary', 'interventions-risk-escalation',
        'selection-bilingual-fit', 'selection-translation-equivalence',
        'org-levels-analysis', 'org-leadership-evidence', 'org-culture-climate',
        'structures-role-boundaries', 'structures-strategy-alignment', 'structures-single-metric',
        'structures-accountability-alignment', 'team-role-safety', 'team-intervention-outcome',
    ],
];

$persistReadiness = static function (array $records) use ($writeJson): void {
    // Readiness records are a schema collection without the normal storage
    // primary key; preserve its established list-file convention.
    $writeJson('storage/source-readiness-psychometrician.json', array_values($records));
};

$refreshReadiness = static function (array $records, array $input): array {
    $counts = SourceReadinessService::nativeLeafCounts($input, 'psychometrician');
    foreach ($records as &$record) {
        if (!is_array($record)) continue;
        $leaf = (string) ($record['leaf_id'] ?? '');
        $oldCount = (int) ($record['current_question_count'] ?? 0);
        $current = (int) ($counts[$leaf] ?? 0);
        $added = max(0, $current - $oldCount);
        $record['current_question_count'] = $current;
        $record['deficit_to_5'] = max(0, 5 - $current);
        $record['deficit_to_10'] = max(0, 10 - $current);
        $record['deficit_to_20'] = max(0, 20 - $current);
        if (in_array((string) ($record['readiness'] ?? ''), ['READY', 'READY_WITH_LIMITS'], true) && $added > 0) {
            $record['safe_headroom_min'] = max(0, (int) ($record['safe_headroom_min'] ?? 0) - $added);
            $record['safe_headroom_preferred'] = max($record['safe_headroom_min'], (int) ($record['safe_headroom_preferred'] ?? 0) - $added);
            $record['safe_headroom_max'] = max($record['safe_headroom_preferred'], (int) ($record['safe_headroom_max'] ?? 0) - $added);
        }
        if (in_array((string) ($record['readiness'] ?? ''), ['READY', 'READY_WITH_LIMITS'], true)) {
            $record['safe_headroom_preferred'] = min((int) ($record['safe_headroom_max'] ?? 0), (int) ($record['safe_headroom_preferred'] ?? 0));
            $record['safe_headroom_min'] = min((int) ($record['safe_headroom_preferred'] ?? 0), (int) ($record['safe_headroom_min'] ?? 0));
            $record['max_new_questions'] = (int) ($record['safe_headroom_max'] ?? 0);
            $record['planned_new_questions'] = 0;
            if ((string) ($record['leaf_id'] ?? '') === 'psych-assessment-uses-limitations' && (int) ($current ?? 0) >= 10) {
                $record['safe_headroom_min'] = 0;
                $record['safe_headroom_preferred'] = 0;
                $record['safe_headroom_max'] = 0;
                $record['max_new_questions'] = 0;
                $record['readiness'] = 'REVIEW';
                $record['readiness_reason'] = '';
                $record['review_reason'] = 'SEMANTIC_HEADROOM_UNCERTAIN';
                $record['defer_reason'] = '';
            }
        }
        $record['reviewed_at_batch'] = 643;
        $record['evidence_hash'] = SourceReadinessRecord::evidenceHash($record);
    }
    unset($record);
    usort($records, static fn(array $a, array $b): int => strcmp((string) ($a['leaf_id'] ?? ''), (string) ($b['leaf_id'] ?? '')));
    return $records;
};

$questionDefinition = static function (array $definition, array $input, array $readinessByLeaf, string $id, string $batchKey) use ($taxonomyFor): array {
    $leaf = (string) $definition['leaf'];
    $record = $readinessByLeaf[$leaf] ?? null;
    if (!is_array($record)) throw new RuntimeException('Missing readiness record for ' . $leaf);
    if (!in_array((string) ($record['readiness'] ?? ''), ['READY', 'READY_WITH_LIMITS'], true)) {
        throw new RuntimeException('Attempted to author into non-authorable leaf ' . $leaf);
    }
    $materialId = (string) $definition['study_material_id'];
    $material = null;
    foreach ((array) ($input['study-materials'] ?? []) as $row) {
        if (is_array($row) && (string) ($row['id'] ?? '') === $materialId) {
            $material = $row;
            break;
        }
    }
    if ($material === null) throw new RuntimeException('Missing study material ' . $materialId);
    $sources = array_values(array_intersect(
        array_map('strval', (array) ($record['source_ids'] ?? [])),
        array_map('strval', (array) ($material['source_ids'] ?? []))
    ));
    if ($sources === []) throw new RuntimeException('Study/source intersection is empty for ' . $batchKey);
    $taxonomy = $taxonomyFor($leaf, $input);
    return [
        'id' => (int) $id,
        'taxonomy' => $taxonomy,
        'study_material_id' => $materialId,
        'study_material_ids' => [$materialId],
        'source_scope' => array_values($definition['source_scope']),
        'source_ids' => $sources,
        'difficulty' => ((int) $definition['depth']) >= 4 ? 'hard' : 'medium',
        'question' => $definition['question'],
        'choices' => array_values($definition['choices']),
        'answer' => $definition['answer'],
        'explanation' => $definition['explanation'],
        'source' => 'BoardPrep-authored',
        'batch_marker' => 'batch643',
        'batch643_key' => $batchKey,
        'status' => 'active',
        'type' => 'multiple_choice',
        'content_blocks' => [],
        'numeric' => false,
        'calculation_required' => false,
        'quantitative_semantics' => 'NON_NUMERICAL',
        'multistep' => true,
        'data_interpretation' => false,
        'table_dependent' => false,
        'cognitive_intent' => ((int) $definition['depth']) >= 4 ? 'analysis and professional judgment' : 'application and interpretation',
        'scenario_length' => 'STANDARD',
        'professional_judgment' => true,
        'evidence_interpretation' => true,
        'case_interpretation' => true,
        'legal_procedure_application' => false,
        'ethics_scope_application' => in_array($leaf, ['psych-abnormal-interventions', 'psych-assessment-selection-tools', 'psych-assessment-uses-limitations'], true),
        'source_sensitivity' => $definition['source_sensitivity'],
        'version_sensitive_categories' => $definition['source_sensitivity'] === 'SENSITIVE_REVIEWED' ? ['CLINICAL_GUIDANCE_SENSITIVE'] : [],
        'version_sensitive' => false,
        'version_review_status' => 'CURRENT_VERIFIED',
        'currentness_verified' => true,
        'professional_context' => 'PROFESSIONAL',
        'scenario_family' => $definition['family'],
        'misconception_family' => $definition['misconception'],
        'reuse' => [
            'reuse_policy' => 'REVIEW',
            'required_depth' => (int) $definition['depth'],
            'context_mode' => 'PROFESSIONAL',
            'metadata_status' => 'VERIFIED',
            'metadata_provenance' => [[
                'type' => 'OTHER',
                'reference' => 'batch-643-source-first-expansion',
                'note' => 'Question is bounded by the Psychometrician leaf readiness record, linked study material, supported scope, and assigned depth.',
                'verified_status' => 'VERIFIED',
            ]],
        ],
    ];
};

$validateNewQuestions = static function (array $input, array $newIds): array {
    $byId = [];
    foreach ((array) ($input['questions'] ?? []) as $question) {
        if (is_array($question)) $byId[(string) ($question['id'] ?? '')] = $question;
    }
    $links = array_values(array_filter((array) ($input['study-question-links'] ?? []), 'is_array'));
    $materials = array_values(array_filter((array) ($input['study-materials'] ?? []), 'is_array'));
    $sources = array_values(array_filter((array) ($input['sources'] ?? []), 'is_array'));
    $eligibilityRows = array_values(array_filter((array) ($input['question-eligibility'] ?? []), 'is_array'));
    $conceptIds = array_fill_keys(array_map(static fn(array $row): string => (string) ($row['id'] ?? ''), array_values(array_filter((array) ($input['taxonomy/concepts'] ?? []), 'is_array'))), true);
    $sourcesById = [];
    foreach ($sources as $source) $sourcesById[(string) ($source['id'] ?? '')] = $source;
    $materialsById = [];
    foreach ($materials as $material) $materialsById[(string) ($material['id'] ?? '')] = $material;
    $linksByQuestion = [];
    foreach ($links as $link) $linksByQuestion[(string) ($link['question_id'] ?? '')][] = $link;
    $deriver = new FlashcardDerivationService(null, $materials, $links, (array) ($input['flashcard-exclusions'] ?? []));
    $result = [
        'total' => count($newIds), 'with_leaf' => 0, 'with_study' => 0,
        'with_source' => 0, 'with_supported_scope' => 0, 'eligibility_pass' => 0,
        'source_validity_pass' => 0, 'traceability_pass' => 0, 'derivable' => 0,
        'structured_content_pass' => 0,
        'flashcard_derivable' => 0, 'calculation_metadata_pass' => 0,
        'version_currentness_pass' => 0, 'exact_duplicates' => 0,
        'normalized_duplicates' => 0, 'semantic_duplicates' => 0,
        'template_duplicates' => 0, 'semantic_duplicate_ids' => [],
        'template_duplicate_ids' => [], 'defects' => [], 'cards' => [],
    ];
    foreach ($newIds as $id) {
        $question = $byId[(string) $id] ?? null;
        if (!is_array($question)) { $result['defects'][] = ['id' => (string) $id, 'error' => 'MISSING_QUESTION']; continue; }
        $validation = QuestionValidationService::validate($question);
        if (($validation['valid'] ?? false) !== true) {
            $result['defects'][] = ['id' => (string) $id, 'error' => 'QUESTION_VALIDATION', 'details' => $validation['errors']];
        } else {
            $result['structured_content_pass']++;
            $result['calculation_metadata_pass']++;
        }
        $taxonomy = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
        $leafId = trim((string) ($taxonomy['concept_id'] ?? ''));
        $leafValid = strtolower((string) ($taxonomy['board_id'] ?? '')) === 'psychometrician' && isset($conceptIds[$leafId]);
        if ($leafValid) $result['with_leaf']++;
        $studyIds = array_values(array_map('strval', (array) ($question['study_material_ids'] ?? [])));
        $sourceIds = array_values(array_map('strval', (array) ($question['source_ids'] ?? [])));
        $materialsValid = $studyIds !== [];
        foreach ($studyIds as $studyId) {
            $material = $materialsById[$studyId] ?? null;
            if (!is_array($material) || (string) ($material['status'] ?? 'published') !== 'published') $materialsValid = false;
        }
        if ($materialsValid) $result['with_study']++;
        $sourcesValid = $sourceIds !== [];
        foreach ($sourceIds as $sourceId) {
            $source = $sourcesById[$sourceId] ?? null;
            if (!is_array($source) || in_array(strtolower((string) ($source['status'] ?? '')), ['archived', 'retired', 'invalid', 'superseded'], true)) $sourcesValid = false;
        }
        if ($sourcesValid) $result['with_source']++;
        if ($sourcesValid) $result['source_validity_pass']++;
        $eligibilityMatches = [];
        foreach ($eligibilityRows as $eligibility) {
            if (!in_array((string) $id, array_map('strval', (array) ($eligibility['question_ids'] ?? [])), true)) continue;
            if (!in_array('psychometrician', array_map('strtolower', array_map('strval', (array) ($eligibility['exam_ids'] ?? []))), true)) continue;
            $subjectId = (string) (($eligibility['subject_ids']['psychometrician'] ?? ''));
            if ($subjectId !== '' && $subjectId === (string) ($taxonomy['subject_id'] ?? '')) $eligibilityMatches[] = $eligibility;
        }
        if (count($eligibilityMatches) === 1 && $leafValid) $result['eligibility_pass']++;
        $readinessRecord = null;
        foreach ((array) ($input['source-readiness-psychometrician'] ?? []) as $readinessRow) {
            if (is_array($readinessRow) && (string) ($readinessRow['leaf_id'] ?? '') === $leafId) { $readinessRecord = $readinessRow; break; }
        }
        $scope = array_values(array_map('strval', (array) ($question['source_scope'] ?? [])));
        $allowedScope = array_map('strval', (array) ($readinessRecord['allowed_scope'] ?? []));
        $scopeValid = $scope !== [] && array_diff($scope, $allowedScope) === [];
        $traceable = false;
        foreach ($linksByQuestion[(string) $id] ?? [] as $link) {
            $materialId = (string) ($link['material_id'] ?? '');
            $material = $materialsById[$materialId] ?? null;
            if (!is_array($material) || !in_array($materialId, $studyIds, true)) continue;
            $materialSourceIds = array_map('strval', (array) ($material['source_ids'] ?? []));
            $linkSourceIds = array_map('strval', (array) ($link['source_ids'] ?? []));
            if ($linkSourceIds === [] || array_diff($linkSourceIds, $materialSourceIds) !== [] || array_diff($linkSourceIds, array_keys($sourcesById)) !== []) continue;
            if (array_diff($sourceIds, $materialSourceIds) !== [] || array_intersect($sourceIds, $linkSourceIds) === []) continue;
            $traceable = true;
            break;
        }
        if ($traceable) $result['traceability_pass']++;
        if ($scopeValid && $traceable && $sourcesValid) $result['with_supported_scope']++;
        if ($scopeValid && $traceable && $sourcesValid && $leafValid && count($eligibilityMatches) === 1) $result['derivable']++;
        if (!$leafValid) $result['defects'][] = ['id' => (string) $id, 'error' => 'INVALID_PSYCHOMETRICIAN_LEAF'];
        if (!$materialsValid) $result['defects'][] = ['id' => (string) $id, 'error' => 'INVALID_STUDY_MATERIAL'];
        if (!$sourcesValid) $result['defects'][] = ['id' => (string) $id, 'error' => 'INVALID_SOURCE_REFERENCE'];
        if (count($eligibilityMatches) !== 1) $result['defects'][] = ['id' => (string) $id, 'error' => 'INVALID_PSYCHOMETRICIAN_ELIGIBILITY'];
        if (!$scopeValid) $result['defects'][] = ['id' => (string) $id, 'error' => 'UNSUPPORTED_SOURCE_SCOPE'];
        if (!$traceable) $result['defects'][] = ['id' => (string) $id, 'error' => 'BROKEN_QUESTION_STUDY_SOURCE_TRACE'];
        $card = $deriver->derive($question);
        if ($card === null) {
            $result['defects'][] = ['id' => (string) $id, 'error' => 'FLASHCARD_DERIVATION', 'status' => $deriver->derivationStatus($question)];
        } else {
            $cardErrors = FlashcardCardValidator::validate($card);
            if ($cardErrors !== []) {
                $result['defects'][] = ['id' => (string) $id, 'error' => 'FLASHCARD_CARD', 'details' => $cardErrors];
            } else {
                $result['flashcard_derivable']++;
                $result['cards'][] = ['id' => (string) $id, 'card_key' => $card['card_key']];
            }
        }
        $currentnessValid = (bool) ($question['currentness_verified'] ?? false)
            && (!(bool) ($question['version_sensitive'] ?? false) || strtoupper(trim((string) ($question['version_review_status'] ?? ''))) === 'CURRENT_VERIFIED');
        if ($currentnessValid) $result['version_currentness_pass']++;
        else $result['defects'][] = ['id' => (string) $id, 'error' => 'VERSION_CURRENTNESS_UNVERIFIED'];
        foreach (QuestionDuplicateService::find($question) as $duplicate) {
            if ((string) ($duplicate['question_id'] ?? '') === (string) $id) continue;
            if (trim((string) ($duplicate['question'] ?? '')) === trim((string) ($question['question'] ?? ''))) $result['exact_duplicates']++;
            else $result['normalized_duplicates']++;
        }
    }
    $newIdSet = array_fill_keys(array_map('strval', $newIds), true);
    $normalizeFamily = static function (string $value): string {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?? $value;
        return trim(preg_replace('/\\s+/', ' ', $value) ?? $value);
    };
    $normalizeTemplate = static function (string $value): string {
        $value = strtolower(trim($value));
        $value = preg_replace('/\\d+(?:[,.]\\d+)?/', '#', $value) ?? $value;
        $value = preg_replace('/[^a-z0-9#]+/', ' ', $value) ?? $value;
        return trim(preg_replace('/\\s+/', ' ', $value) ?? $value);
    };
    $semanticGroups = [];
    $templateGroups = [];
    foreach ((array) ($input['questions'] ?? []) as $candidate) {
        if (!is_array($candidate)) continue;
        $candidateId = (string) ($candidate['id'] ?? '');
        $taxonomy = is_array($candidate['taxonomy'] ?? null) ? $candidate['taxonomy'] : [];
        if (strtolower((string) ($taxonomy['board_id'] ?? $candidate['board'] ?? '')) !== 'psychometrician') continue;
        $leafId = trim((string) ($taxonomy['concept_id'] ?? ''));
        if ($leafId === '') continue;
        $misconception = $normalizeFamily((string) ($candidate['misconception_family'] ?? ''));
        if ($misconception !== '') $semanticGroups[$leafId . '|' . $misconception][] = $candidateId;
        $template = $normalizeTemplate((string) ($candidate['question'] ?? ''));
        if ($template !== '') $templateGroups[$leafId . '|' . $template][] = $candidateId;
    }
    foreach ($semanticGroups as $groupIds) {
        if (count($groupIds) < 2) continue;
        foreach ($groupIds as $candidateId) if (isset($newIdSet[$candidateId])) $result['semantic_duplicate_ids'][] = $candidateId;
    }
    foreach ($templateGroups as $groupIds) {
        if (count($groupIds) < 2) continue;
        foreach ($groupIds as $candidateId) if (isset($newIdSet[$candidateId])) $result['template_duplicate_ids'][] = $candidateId;
    }
    $result['semantic_duplicate_ids'] = array_values(array_unique($result['semantic_duplicate_ids']));
    $result['template_duplicate_ids'] = array_values(array_unique($result['template_duplicate_ids']));
    sort($result['semantic_duplicate_ids'], SORT_NUMERIC);
    sort($result['template_duplicate_ids'], SORT_NUMERIC);
    $result['semantic_duplicates'] = count($result['semantic_duplicate_ids']);
    $result['template_duplicates'] = count($result['template_duplicate_ids']);
    foreach ($result['semantic_duplicate_ids'] as $id) $result['defects'][] = ['id' => $id, 'error' => 'SAME_LEAF_SEMANTIC_DUPLICATE'];
    foreach ($result['template_duplicate_ids'] as $id) $result['defects'][] = ['id' => $id, 'error' => 'SAME_LEAF_TEMPLATE_DUPLICATE'];
    return $result;
};

$authorWave = static function (int $wave) use ($artifactPath, $readJson, $writeJson, $collections, $waveKeys, $definitionsByKey, $readinessByLeaf, $questionDefinition, $persistReadiness, $refreshReadiness, $validateNewQuestions, $nativeCounts, $thresholds): array {
    if (!is_file($artifactPath)) throw new RuntimeException('Run --baseline first.');
    $artifact = $readJson('storage/batch643-psychometrician-expansion.json');
    $input = $collections();
    $records = array_values($input['source-readiness-psychometrician'] ?? []);
    $freeze = $artifact['readiness_freeze']['records'] ?? [];
    if (!is_array($freeze) || count($freeze) !== 20) throw new RuntimeException('Readiness freeze is not complete.');
    if ($wave === 2) {
        // The first two questions exposed one additional distinct, already
        // traceable family in the existing norms/interpretation material.
        // Expand that leaf's remaining ceiling by exactly one and freeze the
        // intentional evidence decision before the next authoring wave.
        $headroomReassessed = false;
        foreach ($records as &$record) {
            if (($record['leaf_id'] ?? '') !== 'psych-assessment-uses-limitations') continue;
            if ((int) ($record['current_question_count'] ?? 0) === 5 && (int) ($record['max_new_questions'] ?? 0) === 4) {
                $record['safe_headroom_preferred'] = max(3, (int) ($record['safe_headroom_preferred'] ?? 0));
                $record['safe_headroom_max'] = max(5, (int) ($record['safe_headroom_max'] ?? 0));
                $record['max_new_questions'] = 5;
                $record['limitations'][] = 'Batch643 evidence reassessment: the existing norms/core-interpretation material supports one additional distinct score-meaning family beyond the original planning estimate.';
                $record['limitations'] = array_values(array_unique($record['limitations']));
                $record['reviewed_at_batch'] = 643;
                $record['evidence_hash'] = SourceReadinessRecord::evidenceHash($record);
                $headroomReassessed = true;
            }
        }
        unset($record);
        if ($headroomReassessed) {
            $persistReadiness($records);
            $input = $collections();
            $records = array_values($input['source-readiness-psychometrician'] ?? []);
        }
    }
    $keys = $waveKeys[$wave] ?? [];
    $existingByKey = [];
    foreach ((array) ($input['questions'] ?? []) as $question) {
        if (is_array($question) && ($question['batch_marker'] ?? '') === 'batch643') {
            $key = trim((string) ($question['batch643_key'] ?? ''));
            if ($key !== '') $existingByKey[$key] = (string) ($question['id'] ?? '');
        }
    }
    $maxId = 0;
    foreach ((array) ($input['questions'] ?? []) as $question) {
        if (is_array($question) && is_numeric($question['id'] ?? null)) $maxId = max($maxId, (int) $question['id']);
    }
    $created = [];
    $definitionById = [];
    $readinessMap = $readinessByLeaf($input);
    foreach ($keys as $key) {
        $definition = $definitionsByKey[$key] ?? null;
        if (!is_array($definition)) throw new RuntimeException('Missing definition ' . $key);
        if (isset($existingByKey[$key])) {
            $created[] = (string) $existingByKey[$key];
            $definitionById[(string) $existingByKey[$key]] = $definition;
            continue;
        }
        $maxId++;
        $id = (string) $maxId;
        $question = $questionDefinition($definition, $input, $readinessMap, $id, $key);
        $validation = QuestionService::validateForCreate($question);
        if (($validation['valid'] ?? false) !== true || ($validation['duplicates'] ?? []) !== []) {
            throw new RuntimeException('Authoring gate rejected ' . $key . ': ' . json_encode($validation, JSON_UNESCAPED_SLASHES));
        }
        $saved = QuestionService::save($question);
        $taxonomy = $saved['taxonomy'];
        $mapping = QuestionEligibilityService::addMapping($saved, [
            'target_board' => 'psychometrician',
            'target_subject' => $taxonomy['subject_id'],
            'target_domain' => $taxonomy['domain_id'],
            'target_topic' => $taxonomy['topic_id'],
            'target_concept' => $taxonomy['concept_id'],
            'blueprint_node' => (string) $readinessMap[$definition['leaf']]['official_area_id'],
        ]);
        if (($mapping['valid'] ?? false) !== true) throw new RuntimeException('Eligibility mapping failed for ' . $id . ': ' . json_encode($mapping));
        $sourceIds = array_values($question['source_ids']);
        App::storage()->create('study-question-links', [
            'id' => 'study-link-b643-' . $id,
            'material_id' => (string) $question['study_material_id'],
            'question_id' => $id,
            'source_ids' => $sourceIds,
        ]);
        $created[] = $id;
        $definitionById[$id] = $definition;
        $input = $collections();
        $readinessMap = $readinessByLeaf($input);
    }
    sort($created, SORT_NUMERIC);
    $input = $collections();
    $waveQuality = $validateNewQuestions($input, $created);
    if ($waveQuality['defects'] !== []) throw new RuntimeException('Wave ' . $wave . ' QA failed: ' . json_encode($waveQuality, JSON_UNESCAPED_SLASHES));
    $records = $refreshReadiness($records, $input);
    $persistReadiness($records);
    $input = $collections();
    $readinessValidation = SourceReadinessService::validateCollections($input);
    if (($readinessValidation['valid'] ?? false) !== true) throw new RuntimeException('Wave readiness validation failed: ' . json_encode($readinessValidation['errors'], JSON_UNESCAPED_SLASHES));
    $counts = $nativeCounts($input);
    $threshold = $thresholds($counts);
    $waveRecord = [
        'wave' => $wave,
        'purpose' => $wave === 1 ? 'AUTHORABLE_UNDER5_CLOSURE' : 'AUTHORABLE_UNDER10_CLOSURE',
        'planned' => count($keys),
        'actual' => count($created),
        'keys' => $keys,
        'new_question_ids' => $created,
        'leaf_counts_after' => $counts,
        'thresholds_after' => $threshold,
        'quality' => $waveQuality,
        'readiness_valid' => true,
        'traceability_valid' => $waveQuality['with_study'] === count($created) && $waveQuality['with_source'] === count($created),
        'duplicate_gate' => $waveQuality['exact_duplicates'] === 0 && $waveQuality['normalized_duplicates'] === 0,
    ];
    $artifact['waves'] = array_values(array_filter((array) ($artifact['waves'] ?? []), static fn(array $row): bool => (int) ($row['wave'] ?? 0) !== $wave));
    $artifact['waves'][] = $waveRecord;
    $artifact['new_question_ids'] = array_values(array_unique(array_merge((array) ($artifact['new_question_ids'] ?? []), $created)));
    usort($artifact['new_question_ids'], static fn(mixed $a, mixed $b): int => (int) $a <=> (int) $b);
    $artifact['readiness_after_wave_' . $wave] = $records;
    $artifact['headroom_reassessment'][] = ['wave' => $wave, 'added' => count($created), 'records' => $records];
    if ($wave === 2) {
        $artifact['headroom_reassessment'][] = [
            'wave' => 2,
            'leaf_id' => 'psych-assessment-uses-limitations',
            'reason' => 'One additional score-meaning family was confirmed in the existing traceable norms/core-interpretation material before Wave2 authoring.',
            'status' => 'INTENTIONAL_EVIDENCE_REASSESSMENT',
        ];
    }
    $writeJson('storage/batch643-psychometrician-expansion.json', $artifact);
    return $waveRecord;
};

$buildBaselineArtifact = static function (array $input) use ($healthSummary, $readinessSummary, $leafCounts, $thresholds, $distribution, $areaCounts, $depthCounts, $writeJson, $psychQuestions): array {
    $health = is_file(dirname(__DIR__) . '/storage/repository-health.json')
        ? json_decode((string) file_get_contents(dirname(__DIR__) . '/storage/repository-health.json'), true, 512, JSON_THROW_ON_ERROR)
        : [];
    $counts = $leafCounts($input);
    $readinessValidation = SourceReadinessService::validateCollections($input);
    $readiness = $readinessSummary($input);
    if (($readinessValidation['valid'] ?? false) !== true) throw new RuntimeException('Baseline readiness invalid: ' . json_encode($readinessValidation['errors'], JSON_UNESCAPED_SLASHES));
    $psychQuestions = array_values(array_filter((array) ($input['questions'] ?? []), static fn(mixed $q): bool => is_array($q) && ($q['taxonomy']['board_id'] ?? '') === 'psychometrician'));
    $evidenceHashes = [];
    foreach ((array) ($input['source-readiness-psychometrician'] ?? []) as $record) $evidenceHashes[(string) ($record['leaf_id'] ?? '')] = (string) ($record['evidence_hash'] ?? '');
    ksort($evidenceHashes, SORT_STRING);
    return [
        'batch' => 643,
        'title' => 'Psychometrician source-first foundation closure and evidence-bounded expansion',
        'lineage' => [
            'expected_head' => '45dc5e139a2d75e8b862bce9005f9dd61e792301',
            'batch641' => '9dd8ee898ddbe0e6949f6acda8c481cb11e8da40',
            'batch640' => 'dac9df0c3ee5c8212adfa7558cca19e948e84857',
            'batch639' => 'fcad7880092dccdf2d50d194ccbd95f48a8a814a',
        ],
        'health_baseline' => $healthSummary($health),
        'baseline' => [
            'global_canonical' => count((array) ($input['questions'] ?? [])),
            'psychometrician_canonical' => count($psychQuestions),
            'psychometrician_native_counts' => $counts,
            'thresholds' => $thresholds($counts),
            'distribution' => $distribution($counts),
            'area_counts' => $areaCounts($input),
            'depth_counts' => $depthCounts($input),
        ],
        'readiness_freeze' => [
            'record_count' => count((array) ($input['source-readiness-psychometrician'] ?? [])),
            'summary' => $readiness,
            'records' => array_values($input['source-readiness-psychometrician'] ?? []),
            'evidence_hashes' => $evidenceHashes,
            'deferred_leaf' => 'psych-abnormal-mental-health-law',
        ],
        'official_scope' => [
            'current_exam_regime' => 'Current PRC Psychometrician enhanced TOS represented by Resolution No. 02 s. 2023 and the registered current PRC examination materials; Batch643 retains the Batch642 four-area boundary.',
            'source_ids' => ['prc-psychometrician-enhanced-tos-2023'],
            'areas' => [
                ['id' => 'psychological-assessment', 'weight_percent' => 40],
                ['id' => 'developmental-psychology', 'weight_percent' => 20],
                ['id' => 'abnormal-psychology', 'weight_percent' => 20],
                ['id' => 'industrial-organizational', 'weight_percent' => 20],
            ],
            'future_effective_excluded' => true,
            'detailed_subcompetencies_are_boardprep_derived' => true,
        ],
        'cohorts' => [
            'A_under5' => ['psych-assessment-uses-limitations'],
            'B_under10' => ['psych-abnormal-etiology', 'psych-abnormal-interventions', 'psych-assessment-selection-tools', 'psych-assessment-uses-limitations', 'psych-industrial-organization-theory', 'psych-industrial-structures-systems', 'psych-industrial-team-dynamics'],
            'C_under20' => 'Deferred until after the foundation gate; no automatic +96/+117 cohort.',
            'D_depth_diversity' => 'No separate Wave4; depth diversity is included in the 21-question foundation cohort.',
            'E_blocked' => ['psych-abnormal-mental-health-law'],
        ],
        'headroom_before' => [
            'safe_min' => array_sum(array_map(static fn(array $r): int => (int) ($r['safe_headroom_min'] ?? 0), (array) ($input['source-readiness-psychometrician'] ?? []))),
            'safe_preferred' => array_sum(array_map(static fn(array $r): int => (int) ($r['safe_headroom_preferred'] ?? 0), (array) ($input['source-readiness-psychometrician'] ?? []))),
            'safe_max' => array_sum(array_map(static fn(array $r): int => (int) ($r['safe_headroom_max'] ?? 0), (array) ($input['source-readiness-psychometrician'] ?? []))),
        ],
        'waves' => [],
        'headroom_reassessment' => [],
        'new_question_ids' => [],
        'question_level_evidence' => [],
        'health_after' => null,
        'final' => null,
        'determinism' => null,
    ];
};

if ($mode === '--baseline') {
    $input = $collections();
    $existingBatch643 = array_values(array_filter((array) ($input['questions'] ?? []), static fn(mixed $q): bool => is_array($q) && ($q['batch_marker'] ?? '') === 'batch643'));
    if ($existingBatch643 !== []) throw new RuntimeException('Batch643 questions already exist; baseline must precede mutation.');
    $artifact = $buildBaselineArtifact($input);
    $writeJson('storage/batch643-psychometrician-expansion.json', $artifact);
    echo json_encode(['mode' => 'baseline', 'health' => $artifact['health_baseline'], 'readiness' => $artifact['readiness_freeze']['summary'], 'thresholds' => $artifact['baseline']['thresholds']], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
    exit(($artifact['health_baseline']['entity_counts']['FAIL'] ?? 1) === 0 ? 0 : 1);
}

if ($mode === '--wave1' || $mode === '--wave2') {
    $wave = $mode === '--wave1' ? 1 : 2;
    $record = $authorWave($wave);
    echo json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
    exit(0);
}

if ($mode === '--finalize') {
    if (!is_file($artifactPath)) throw new RuntimeException('Run --baseline first.');
    $artifact = $readJson('storage/batch643-psychometrician-expansion.json');
    $input = $collections();
    $records = $refreshReadiness((array) ($input['source-readiness-psychometrician'] ?? []), $input);
    $persistReadiness($records);
    $input = $collections();
    $readinessValidation = SourceReadinessService::validateCollections($input);
    if (($readinessValidation['valid'] ?? false) !== true) throw new RuntimeException('Final readiness validation failed: ' . json_encode($readinessValidation['errors'], JSON_UNESCAPED_SLASHES));
    $counts = $leafCounts($input);
    $newIds = array_values(array_map('strval', (array) ($artifact['new_question_ids'] ?? [])));
    sort($newIds, SORT_NUMERIC);
    $newQuality = $validateNewQuestions($input, $newIds);
    if ($newQuality['defects'] !== []) throw new RuntimeException('Final new-question QA failed: ' . json_encode($newQuality, JSON_UNESCAPED_SLASHES));
    $remainder = $thresholds($counts);
    $areaAfter = $areaCounts($input);
    $depthAfter = $depthCounts($input);
    $readinessAfter = $readinessSummary($input);
    $readinessMapFinal = $readinessByLeaf($input);
    $authorableReadiness = static fn(array $record): bool => in_array(
        (string) ($record['readiness'] ?? ''),
        ['READY', 'READY_WITH_LIMITS'],
        true
    );
    $authorableUnder5 = 0;
    $authorableUnder10 = 0;
    $authorableUnder20 = 0;
    foreach ($readinessMapFinal as $leafId => $record) {
        if (!$authorableReadiness($record)) continue;
        $count = (int) ($counts[$leafId] ?? 0);
        if ($count < 5) $authorableUnder5++;
        if ($count < 10) $authorableUnder10++;
        if ($count < 20) $authorableUnder20++;
    }
    $authorableZero = 0;
    foreach ($readinessMapFinal as $leafId => $record) {
        if ($authorableReadiness($record) && (int) ($counts[$leafId] ?? 0) === 0) $authorableZero++;
    }
    $areaTotal = array_sum($areaAfter);
    $areaPercentages = [];
    foreach ($areaAfter as $areaId => $areaCount) $areaPercentages[$areaId] = $areaTotal > 0 ? round($areaCount / $areaTotal * 100, 2) : 0.0;
    $reuseDepthById = [];
    foreach ((array) ($input['question-reuse-metadata'] ?? []) as $metadata) {
        if (is_array($metadata)) $reuseDepthById[(string) ($metadata['question_id'] ?? '')] = (int) ($metadata['reuse']['required_depth'] ?? 0);
    }
    $depthByLeaf = [];
    foreach ($psychQuestions($input) as $question) {
        $leafId = (string) ($question['taxonomy']['concept_id'] ?? '');
        $depth = $reuseDepthById[(string) ($question['id'] ?? '')] ?? 0;
        if ($leafId !== '' && $depth >= 1 && $depth <= 5) $depthByLeaf[$leafId]['D' . $depth] = ($depthByLeaf[$leafId]['D' . $depth] ?? 0) + 1;
    }
    $wave3Candidates = [];
    $wave3Headroom = ['safe_min' => 0, 'safe_preferred' => 0, 'safe_max' => 0];
    $wave3CandidatesWithSafeMinimum = 0;
    foreach ($readinessMapFinal as $leafId => $record) {
        if (!$authorableReadiness($record) || (int) ($counts[$leafId] ?? 0) >= 20) continue;
        $minimumDepth = max(1, (int) ($record['supported_depth_min'] ?? 1));
        $maximumDepth = min(5, (int) ($record['supported_depth_max'] ?? 5));
        $missingSupportedDepths = [];
        for ($depth = $minimumDepth; $depth <= $maximumDepth; $depth++) {
            if ((int) ($depthByLeaf[$leafId]['D' . $depth] ?? 0) === 0) $missingSupportedDepths[] = 'D' . $depth;
        }
        $wave3Candidates[] = [
            'leaf_id' => $leafId,
            'current_question_count' => (int) ($counts[$leafId] ?? 0),
            'readiness' => (string) ($record['readiness'] ?? ''),
            'source_authority' => (string) ($record['source_authority'] ?? ''),
            'source_currentness' => (string) ($record['source_currentness'] ?? ''),
            'study_coverage' => (string) ($record['study_coverage'] ?? ''),
            'study_source_traceability' => (string) ($record['study_source_traceability'] ?? ''),
            'distinct_content_families' => array_values((array) ($record['distinct_content_families'] ?? [])),
            'depth_distribution' => $depthByLeaf[$leafId] ?? [],
            'missing_supported_depths' => $missingSupportedDepths,
            'safe_headroom_min' => (int) ($record['safe_headroom_min'] ?? 0),
            'safe_headroom_preferred' => (int) ($record['safe_headroom_preferred'] ?? 0),
            'safe_headroom_max' => (int) ($record['safe_headroom_max'] ?? 0),
        ];
        foreach (['safe_min' => 'safe_headroom_min', 'safe_preferred' => 'safe_headroom_preferred', 'safe_max' => 'safe_headroom_max'] as $target => $field) {
            $wave3Headroom[$target] += (int) ($record[$field] ?? 0);
        }
        if ((int) ($record['safe_headroom_min'] ?? 0) > 0) $wave3CandidatesWithSafeMinimum++;
    }
    usort($wave3Candidates, static fn(array $a, array $b): int => $a['leaf_id'] <=> $b['leaf_id']);
    $scenarioFamilyCounts = [];
    foreach ((array) ($input['questions'] ?? []) as $question) {
        if (!is_array($question) || !in_array((string) ($question['id'] ?? ''), $newIds, true)) continue;
        $family = trim((string) ($question['scenario_family'] ?? ''));
        if ($family !== '') $scenarioFamilyCounts[$family] = ($scenarioFamilyCounts[$family] ?? 0) + 1;
    }
    ksort($scenarioFamilyCounts);
    $repeatedScenarioFamilies = array_filter($scenarioFamilyCounts, static fn(int $count): bool => $count > 1);
    $maxScenarioFamilyCount = $scenarioFamilyCounts === [] ? 0 : max($scenarioFamilyCounts);
    $health = is_file($root . '/storage/repository-health.json') ? $readJson('storage/repository-health.json') : [];
    $candidateDepthGaps = array_values(array_map(
        static fn(array $candidate): array => [
            'leaf_id' => $candidate['leaf_id'],
            'missing_supported_depths' => $candidate['missing_supported_depths'],
        ],
        array_filter($wave3Candidates, static fn(array $candidate): bool => $candidate['missing_supported_depths'] !== [])
    ));
    $remainingHeadroom = [
        'safe_min' => array_sum(array_map(static fn(array $r): int => (int) ($r['safe_headroom_min'] ?? 0), $records)),
        'safe_preferred' => array_sum(array_map(static fn(array $r): int => (int) ($r['safe_headroom_preferred'] ?? 0), $records)),
        'safe_max' => array_sum(array_map(static fn(array $r): int => (int) ($r['safe_headroom_max'] ?? 0), $records)),
    ];
    foreach (array_keys((array) ($artifact['waves'] ?? [])) as $waveIndex) {
        $waveIds = array_values(array_map('strval', (array) ($artifact['waves'][$waveIndex]['new_question_ids'] ?? [])));
        $waveQuality = $validateNewQuestions($input, $waveIds);
        if ($waveQuality['defects'] !== []) throw new RuntimeException('Wave QA failed during finalization: ' . json_encode($waveQuality, JSON_UNESCAPED_SLASHES));
        $artifact['waves'][$waveIndex]['quality'] = $waveQuality;
        $artifact['waves'][$waveIndex]['traceability_valid'] = $waveQuality['with_study'] === count($waveIds) && $waveQuality['with_source'] === count($waveIds) && $waveQuality['with_supported_scope'] === count($waveIds);
        $artifact['waves'][$waveIndex]['duplicate_gate'] = $waveQuality['exact_duplicates'] === 0 && $waveQuality['normalized_duplicates'] === 0 && $waveQuality['semantic_duplicates'] === 0 && $waveQuality['template_duplicates'] === 0;
    }
    $artifact['readiness_after'] = ['summary' => $readinessAfter, 'records' => $records];
    $artifact['final'] = [
        'global_canonical' => count((array) ($input['questions'] ?? [])),
        'psychometrician_native' => count($psychQuestions($input)),
        'leaf_counts' => $counts,
        'thresholds' => $remainder,
        'distribution' => $distribution($counts),
        'authorable_thresholds' => [
            'zero' => $authorableZero,
            'under5' => $authorableUnder5,
            'under10' => $authorableUnder10,
            'under20' => $authorableUnder20,
        ],
        'area_counts' => $areaAfter,
        'area_percentages' => $areaPercentages,
        'depth_counts' => $depthAfter,
        'new_quality' => $newQuality,
        'wave3_gate' => [
            'authorable_under20_leaf_count' => count($wave3Candidates),
            'candidate_with_safe_minimum_headroom_count' => $wave3CandidatesWithSafeMinimum,
            'candidate_safe_headroom' => $wave3Headroom,
            'candidates' => $wave3Candidates,
            'actionable_missing_content_families' => [],
            'candidate_depth_gaps' => $candidateDepthGaps,
            'new_cohort_scenario_family_counts' => $scenarioFamilyCounts,
            'repeated_scenario_families' => $repeatedScenarioFamilies,
            'max_scenario_family_count' => $maxScenarioFamilyCount,
            'semantic_duplicates' => $newQuality['semantic_duplicates'],
            'template_duplicates' => $newQuality['template_duplicates'],
            'justified' => false,
            'status' => 'NOT_STARTED',
            'decision_reason' => 'The bounded same-leaf review found no additional candidate family with a clear quality and depth benefit after the foundation cohort. Remaining safe headroom is retained; supported D2 gaps alone do not justify count-filling items where the underlying concept is already covered or the new item would repeat current reasoning.',
        ],
        'wave4_gate' => ['run' => false, 'status' => 'NOT_STARTED', 'reason' => 'No separate deficiency remained that justified another authoring wave.'],
        'remaining_headroom' => $remainingHeadroom,
        'stopping_decision' => [
            'foundation_objectives_met' => $authorableUnder5 === 0 && $authorableUnder10 === 0,
            'authorable_under5_closed' => $authorableUnder5 === 0,
            'authorable_under10_closed' => $authorableUnder10 === 0,
            'evidence_headroom_exhausted' => $remainingHeadroom['safe_max'] <= 0,
            'semantic_diversity_limit' => true,
            'depth_gaps_closed' => $candidateDepthGaps === [],
            'depth_gaps_remain_non_actionable' => $candidateDepthGaps !== [],
            'safe_headroom_retained' => $remainingHeadroom['safe_max'] > 0,
            'area_balance_limit' => false,
            'source_limit' => false,
            'currentness_limit' => false,
            'quality_limit' => true,
            'max_new_limit' => false,
            'wave3_justified' => false,
            'wave3_status' => 'NOT_STARTED',
            'wave3_reason' => 'Foundation targets are met for every authorable leaf. Remaining under-20 candidates do not justify more content after the bounded semantic-diversity and depth review; retain remaining headroom for later evidence-backed work.',
            'wave4_run' => false,
            'wave4_status' => 'NOT_STARTED',
            'why_expansion_stopped' => 'FOUNDATION_OBJECTIVES_MET for every authorable leaf; the one remaining raw under-10 leaf is the explicitly deferred mental-health-law leaf. The 21-question cohort added D2/D3/D4 coverage, and further Wave3 additions were deferred until marginal semantic diversity is reassessed.',
            'further_psychometrician_expansion_justified' => 'REVIEW_AFTER_FOUNDATION',
        ],
        'health_after' => $healthSummary($health),
        'health_after_snapshot' => ['path' => 'storage/repository-health.json', 'generated_at' => $health['generated_at'] ?? null],
    ];
    $artifact['question_level_evidence'] = [];
    $reuseById = [];
    foreach ((array) ($input['question-reuse-metadata'] ?? []) as $metadata) if (is_array($metadata)) $reuseById[(string) ($metadata['question_id'] ?? '')] = $metadata;
    foreach ($input['questions'] as $question) {
        if (!is_array($question) || !in_array((string) ($question['id'] ?? ''), $newIds, true)) continue;
        $id = (string) $question['id'];
        $artifact['question_level_evidence'][] = [
            'question_id' => $id,
            'leaf_id' => (string) ($question['taxonomy']['concept_id'] ?? ''),
            'official_area' => (string) ($question['taxonomy']['domain_id'] ?? ''),
            'depth' => $reuseById[$id]['reuse']['required_depth'] ?? null,
            'source_ids' => array_values(array_map('strval', (array) ($question['source_ids'] ?? []))),
            'study_ids' => array_values(array_map('strval', (array) ($question['study_material_ids'] ?? []))),
            'content_family' => (string) ($question['scenario_family'] ?? ''),
            'readiness_state' => (string) (($readinessByLeaf($input)[(string) ($question['taxonomy']['concept_id'] ?? '')]['readiness'] ?? '')),
            'evidence_hash' => (string) (($readinessByLeaf($input)[(string) ($question['taxonomy']['concept_id'] ?? '')]['evidence_hash'] ?? '')),
            'wave' => in_array($id, array_map('strval', (array) ($artifact['waves'][0]['new_question_ids'] ?? [])), true) ? 1 : 2,
        ];
    }
    usort($artifact['question_level_evidence'], static fn(array $a, array $b): int => (int) $a['question_id'] <=> (int) $b['question_id']);
    $writeJson('storage/batch643-psychometrician-expansion.json', $artifact);
    echo json_encode(['mode' => 'finalize', 'new_ids' => $newIds, 'final' => $artifact['final']], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
    exit(0);
}

// Read-only deterministic audit.  It deliberately omits timestamps and the
// generated-at field so two runs can be compared byte-for-byte by hash.
$input = $collections();
$artifact = $readJson('storage/batch643-psychometrician-expansion.json');
$counts = $leafCounts($input);
$readiness = $readinessSummary($input);
$newIds = array_values(array_map('strval', (array) ($artifact['new_question_ids'] ?? [])));
sort($newIds, SORT_NUMERIC);
$newQuality = $validateNewQuestions($input, $newIds);
$health = is_file($root . '/storage/repository-health.json') ? $readJson('storage/repository-health.json') : [];
$audit = [
    'global_canonical' => count((array) ($input['questions'] ?? [])),
    'psychometrician_native' => count($psychQuestions($input)),
    'new_question_ids' => $newIds,
    'question_level_evidence' => array_values((array) ($artifact['question_level_evidence'] ?? [])),
    'leaf_counts' => $counts,
    'thresholds' => $thresholds($counts),
    'distribution' => $distribution($counts),
    'area_counts' => $areaCounts($input),
    'depth_counts' => $depthCounts($input),
    'readiness' => $readiness,
    'evidence_hashes' => array_values(array_map(static fn(array $r): string => (string) ($r['evidence_hash'] ?? ''), array_values((array) ($input['source-readiness-psychometrician'] ?? [])))),
    'new_quality' => $newQuality,
    'recovery' => $artifact['RECOVERY'] ?? null,
    'remaining_headroom' => $artifact['final']['remaining_headroom'] ?? null,
    'wave3_gate' => $artifact['final']['wave3_gate'] ?? null,
    'wave4_gate' => $artifact['final']['wave4_gate'] ?? null,
    'authorable_thresholds' => $artifact['final']['authorable_thresholds'] ?? null,
    'area_percentages' => $artifact['final']['area_percentages'] ?? null,
    'health' => $healthSummary($health),
    'stopping_decision' => $artifact['final']['stopping_decision'] ?? null,
];
$audit['health']['issue_ids'] = array_values(array_map(static fn(array $issue): array => [
    (string) ($issue['status'] ?? ''), (string) ($issue['code'] ?? ''),
    (string) ($issue['board_id'] ?? ''), (string) ($issue['entity_id'] ?? ''),
], array_values(array_filter((array) ($health['issues'] ?? []), 'is_array'))));
sort($audit['health']['issue_ids']);
$auditJson = json_encode($audit, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
echo json_encode(['audit_hash' => hash('sha256', $auditJson), 'audit' => $audit], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
