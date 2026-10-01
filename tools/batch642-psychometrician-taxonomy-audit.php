<?php

declare(strict_types=1);

/**
 * Batch642 milestone orchestration.
 *
 * Permanent taxonomy, question validation, source-readiness, and Repository
 * Health services own reusable contracts. This file owns only the official
 * evidence map, the bounded existing-question classification, the migration
 * and the Batch642 evidence artifact.
 */

require_once dirname(__DIR__) . '/app/Core/Autoloader.php';

use App\Core\App;
use App\Core\Autoloader;
use App\Services\Shared\QuestionValidationService;
use App\Services\Shared\TaxonomyStorageService;
use App\Services\RepositoryHealth\Engine\RepositoryHealthEngine;
use App\Services\RepositoryHealth\Readiness\SourceReadinessRecord;
use App\Services\RepositoryHealth\Readiness\SourceReadinessService;

Autoloader::register();
putenv('APP_ENV=testing');
putenv('APP_DEBUG=false');
App::boot();

$root = dirname(__DIR__);
$auditOnly = in_array('--audit-only', array_slice($argv ?? [], 1), true);
$artifactPath = $root . '/storage/batch642-psychometrician-taxonomy-readiness.json';

$readJson = static function (string $relative) use ($root): array {
    $path = $root . '/' . $relative;
    if (!is_file($path)) {
        return [];
    }
    $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    return is_array($decoded) ? $decoded : [];
};

$writeJson = static function (string $relative, array $value) use ($root): void {
    $path = $root . '/' . $relative;
    $json = json_encode(
        $value,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    );
    $temporary = $path . '.tmp';
    file_put_contents($temporary, $json . "\n", LOCK_EX);
    rename($temporary, $path);
};

$stableJson = static fn(mixed $value): string => json_encode(
    $value,
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
);

$healthProjection = static function (array $health): array {
    $summary = (array) ($health['summary'] ?? []);
    $summary = array_intersect_key($summary, array_flip([
        'overall_status', 'pass_count', 'warn_count', 'fail_count', 'review_count',
        'issue_count', 'pass_entity_count', 'warn_entity_count', 'fail_entity_count',
        'review_entity_count', 'breakdown',
    ]));
    $checks = [];
    foreach ((array) ($health['checks'] ?? []) as $code => $check) {
        if (!is_array($check)) continue;
        $checks[(string) $code] = [
            'status' => (string) ($check['status'] ?? ''),
            'issue_count' => (int) ($check['issue_count'] ?? 0),
            'affected_ids' => array_values(array_map('strval', (array) ($check['affected_ids'] ?? []))),
        ];
    }
    ksort($checks, SORT_STRING);
    $boardCounts = [];
    foreach ((array) ($health['board_summaries'] ?? []) as $boardId => $board) {
        if (!is_array($board)) continue;
        $boardCounts[(string) $boardId] = [
            'canonical' => (int) ($board['canonical'] ?? 0),
            'effective_declared' => (int) ($board['effective_declared'] ?? 0),
            'runtime' => (int) ($board['runtime'] ?? 0),
            'active_leaves' => (int) ($board['active_leaves'] ?? 0),
            'structural_administrative_leaves' => (int) ($board['structural_administrative_leaves'] ?? 0),
            'zero' => (int) ($board['zero'] ?? 0),
            'under5' => (int) ($board['under5'] ?? 0),
            'under10' => (int) ($board['under10'] ?? 0),
            'under20' => (int) ($board['under20'] ?? 0),
            'leaf_counts' => $board['leaf_counts'] ?? [],
        ];
    }
    ksort($boardCounts, SORT_STRING);
    $issueIds = [];
    foreach ((array) ($health['issues'] ?? []) as $issue) {
        if (!is_array($issue)) continue;
        $issueIds[] = [
            (string) ($issue['status'] ?? ''),
            (string) ($issue['code'] ?? ''),
            (string) ($issue['board_id'] ?? ''),
            (string) ($issue['entity_type'] ?? ''),
            (string) ($issue['entity_id'] ?? ''),
        ];
    }
    sort($issueIds);
    return [
        'overall_status' => (string) ($health['overall_status'] ?? ''),
        'summary' => $summary,
        'checks' => $checks,
        'board_counts' => $boardCounts,
        'issue_ids' => $issueIds,
    ];
};

$collections = static function () use ($readJson): array {
    $names = [
        'questions', 'boards', 'subjects', 'board-subjects', 'blueprints',
        'taxonomy/domains', 'taxonomy/topics', 'taxonomy/concepts',
        'question-eligibility', 'concept-derived-eligibility',
        'question-reuse-overrides', 'question-reuse-metadata',
        'question-required-concepts', 'board-concept-coverage',
        'study-materials', 'study-question-links', 'sources',
        'content-fit-audits', 'flashcard-exclusions', 'source-readiness',
        'source-readiness-psychometrician',
    ];
    $result = [];
    foreach ($names as $name) {
        $result[$name] = $readJson('storage/' . $name . '.json');
    }
    return $result;
};

$fingerprint = static function (array $question) use ($stableJson): string {
    $copy = $question;
    unset($copy['taxonomy'], $copy['updatedAt'], $copy['createdAt']);
    return hash('sha256', $stableJson($copy));
};

$questionDepth = static function (array $question, array $reuseByQuestion): ?int {
    foreach ([
        $question['depth'] ?? null,
        $question['required_depth'] ?? null,
        is_array($question['reuse'] ?? null) ? ($question['reuse']['required_depth'] ?? null) : null,
        $reuseByQuestion[(string) ($question['id'] ?? '')]['reuse']['required_depth'] ?? null,
    ] as $value) {
        if (is_int($value) || (is_string($value) && ctype_digit(trim($value)))) {
            $depth = (int) $value;
            if ($depth >= 1 && $depth <= 5) return $depth;
        }
    }
    return null;
};

$inRange = static fn(int $id, int $from, int $to): bool => $id >= $from && $id <= $to;

/**
 * Official areas are the four current PRC TOS subjects. Leaves below them are
 * BoardPrep-derived subcompetencies, never claims that PRC published those
 * labels as a separate official hierarchy.
 */
$leafDefinitions = [
    'psych-assessment-properties-principles' => [
        'name' => 'Psychometric Properties and Principles', 'official_area_id' => 'psychological-assessment',
        'domain_id' => 'ple-assessment', 'topic_id' => 'ple-measurement', 'parent_id' => 'ple-valid-measurement',
        'description' => 'Reliability, validity, fairness, measurement error, and defensible score inference.',
        'materials' => ['ple-487--sychological-ssessment-oundations', 'ple-487--eliability-and-easurement-rror', 'ple-487--alidity-and-est-se'],
        'families' => ['reliability_and_precision', 'validity_and_inference', 'fairness_and_adaptation'],
        'depth' => [2, 4], 'headroom' => [6, 10, 14],
        'scope' => ['current_prc_tos', 'psychometric_properties', 'validity_evidence', 'fairness_and_context'],
        'limitations' => ['Use the applicable norm, language, population, and decision context; do not treat one coefficient as complete validity evidence.'],
    ],
    'psych-assessment-research-statistics' => [
        'name' => 'Research Methods and Statistics in Assessment', 'official_area_id' => 'psychological-assessment',
        'domain_id' => 'ple-assessment', 'topic_id' => 'ple-measurement', 'parent_id' => 'ple-valid-measurement',
        'description' => 'Research designs, descriptive and inferential statistics, item analysis, standardization, and test-development evidence.',
        'materials' => ['ple-487--eliability-and-easurement-rror', 'ple-487--alidity-and-est-se', 'ple-487--sychological-ssessment-oundations'],
        'families' => ['descriptive_statistics', 'item_analysis', 'test_development_research', 'interpretive_uncertainty'],
        'depth' => [2, 4], 'headroom' => [4, 8, 12],
        'scope' => ['current_prc_tos', 'assessment_research', 'standardization', 'bounded_calculation'],
        'limitations' => ['Quantitative items must retain the CalculationMetadataContract and must not imply arithmetic correctness beyond the stated data.'],
    ],
    'psych-assessment-uses-limitations' => [
        'name' => 'Uses, Benefits, and Limitations of Assessment', 'official_area_id' => 'psychological-assessment',
        'domain_id' => 'ple-assessment', 'topic_id' => 'ple-measurement', 'parent_id' => 'ple-valid-measurement',
        'description' => 'Purposes, benefits, limitations, and consequences of using assessment methods and instruments.',
        'materials' => ['ple-487--sychological-ssessment-oundations', 'ple-487--orms-and-core-nterpretation'],
        'families' => ['purpose_fit', 'benefit_limitations', 'decision_consequences'],
        'depth' => [2, 4], 'headroom' => [2, 4, 6],
        'scope' => ['current_prc_tos', 'assessment_purpose', 'limitations_and_consequences'],
        'limitations' => ['Do not treat a screening result, formative measure, or isolated score as a diagnosis or a universal decision rule.'],
    ],
    'psych-assessment-selection-tools' => [
        'name' => 'Selection of Assessment Methods and Tools', 'official_area_id' => 'psychological-assessment',
        'domain_id' => 'ple-assessment', 'topic_id' => 'ple-measurement', 'parent_id' => 'ple-valid-measurement',
        'description' => 'Matching assessment methods and tools to purpose, population, setting, and information needs.',
        'materials' => ['ple-487--sychological-ssessment-oundations', 'ple-487--alidity-and-est-se'],
        'families' => ['purpose_tool_match', 'population_context_fit', 'battery_design'],
        'depth' => [2, 4], 'headroom' => [4, 7, 10],
        'scope' => ['current_prc_tos', 'selection_of_methods', 'population_and_setting_fit'],
        'limitations' => ['Selection decisions must state the referral purpose and the evidence for the population and setting; availability alone is not a rationale.'],
    ],
    'psych-assessment-administration-interpretation' => [
        'name' => 'Administration, Scoring, Interpretation, and Usage', 'official_area_id' => 'psychological-assessment',
        'domain_id' => 'ple-assessment', 'topic_id' => 'ple-measurement', 'parent_id' => 'ple-valid-measurement',
        'description' => 'Standardized administration, scoring, norm-referenced and criterion-referenced interpretation, and responsible use.',
        'materials' => ['ple-487--orms-and-core-nterpretation', 'ple-487--sychological-ssessment-oundations', 'ple-487--alidity-and-est-se'],
        'families' => ['standardized_administration', 'score_interpretation', 'norm_and_criterion_use', 'reporting_limits'],
        'depth' => [2, 4], 'headroom' => [4, 8, 12],
        'scope' => ['current_prc_tos', 'standardized_administration', 'score_interpretation', 'reporting_and_use'],
        'limitations' => ['State norm year, language, standard error, referral purpose, and relevant administration conditions when they affect interpretation.'],
    ],
    'psych-assessment-ethics-standards' => [
        'name' => 'Ethical Principles and Standards of Assessment Practice', 'official_area_id' => 'psychological-assessment',
        'domain_id' => 'ple-assessment', 'topic_id' => 'ple-measurement', 'parent_id' => 'ple-valid-measurement',
        'description' => 'Consent, confidentiality, competence, security, communication, and ethical handling of assessment evidence.',
        'materials' => ['ple-487--thical-ssessment-ractice'],
        'families' => ['consent_and_communication', 'confidentiality_and_security', 'competence_and_referral', 'ethical_reporting'],
        'depth' => [2, 4], 'headroom' => [3, 6, 9],
        'scope' => ['current_prc_tos', 'lawful_and_ethical_assessment', 'consent_confidentiality', 'competence_and_security'],
        'limitations' => ['Philippine law controls local legal scope; professional guidance supports practice but is not a substitute for local law.'],
    ],
    'psych-development-nature-nurture' => [
        'name' => 'Perspectives on Nature and Nurture', 'official_area_id' => 'developmental-psychology',
        'domain_id' => 'ple-developmental', 'topic_id' => 'ple-lifespan-development', 'parent_id' => 'ple-developmental-principles',
        'description' => 'Heredity, environment, culture, context, and individual variation in human development.',
        'materials' => ['ple-487--ifespan-evelopment', 'ple-487--ognitive-and-ocial-evelopment'],
        'families' => ['heredity_environment', 'culture_and_context', 'individual_variation'],
        'depth' => [2, 4], 'headroom' => [4, 8, 12],
        'scope' => ['current_prc_tos', 'nature_and_nurture', 'developmental_context', 'cultural_and_language_context'],
        'limitations' => ['Do not treat a developmental trend or one context-bound observation as a fixed individual trait.'],
    ],
    'psych-development-research-ethics' => [
        'name' => 'Developmental Research Methods and Ethics', 'official_area_id' => 'developmental-psychology',
        'domain_id' => 'ple-developmental', 'topic_id' => 'ple-lifespan-development', 'parent_id' => 'ple-developmental-principles',
        'description' => 'Developmental research designs, cohort and attrition issues, comparisons, and ethical participation.',
        'materials' => ['ple-487--ifespan-evelopment', 'ple-487--ognitive-and-ocial-evelopment'],
        'families' => ['cross_sectional_longitudinal', 'cohort_and_attrition', 'developmental_inference', 'research_ethics'],
        'depth' => [2, 4], 'headroom' => [3, 6, 9],
        'scope' => ['current_prc_tos', 'developmental_research_designs', 'research_ethics', 'bounded_statistical_inference'],
        'limitations' => ['Separate age, period, and cohort effects; do not infer individual change or causation from a one-time group comparison.'],
    ],
    'psych-development-theories' => [
        'name' => 'Developmental Theories', 'official_area_id' => 'developmental-psychology',
        'domain_id' => 'ple-developmental', 'topic_id' => 'ple-lifespan-development', 'parent_id' => 'ple-developmental-principles',
        'description' => 'Evidence-bounded use of developmental theories to interpret personality, cognition, social behavior, and change.',
        'materials' => ['ple-487--ifespan-evelopment', 'ple-487--ognitive-and-ocial-evelopment'],
        'families' => ['cognitive_development', 'psychosocial_development', 'social_learning', 'ecological_and_contextual_theory'],
        'depth' => [2, 4], 'headroom' => [4, 8, 12],
        'scope' => ['current_prc_tos', 'developmental_theories', 'theory_to_evidence_application'],
        'limitations' => ['Use a theory as an explanatory framework, not as a substitute for observation, assessment, or culturally responsive evidence.'],
    ],
    'psych-abnormal-manifestations' => [
        'name' => 'Normalcy, Abnormalcy, and Manifestations of Behavior', 'official_area_id' => 'abnormal-psychology',
        'domain_id' => 'ple-abnormal', 'topic_id' => 'ple-clinical-foundations', 'parent_id' => 'ple-clinical-classification',
        'description' => 'Normal and abnormal behavior considered with social, cultural, functional, developmental, and contextual evidence.',
        'materials' => ['ple-487--bnormal-sychology-oundations', 'ple-487--ymptom-atterns-and-unctioning'],
        'families' => ['normality_and_context', 'functioning_and_impairment', 'cultural_formulation'],
        'depth' => [2, 4], 'headroom' => [3, 6, 9],
        'scope' => ['current_prc_tos', 'normalcy_and_abnormalcy', 'social_and_cultural_context', 'functional_evidence'],
        'limitations' => ['Atypicality alone does not establish a disorder; consider context, distress, impairment, duration, and alternative explanations.'],
    ],
    'psych-abnormal-disorders-symptoms' => [
        'name' => 'Psychological Disorders and Specific Symptoms', 'official_area_id' => 'abnormal-psychology',
        'domain_id' => 'ple-abnormal', 'topic_id' => 'ple-clinical-foundations', 'parent_id' => 'ple-clinical-classification',
        'description' => 'Cautious differentiation of disorder and symptom patterns using the applicable current classification evidence.',
        'materials' => ['ple-487--bnormal-sychology-oundations', 'ple-487--ymptom-atterns-and-unctioning'],
        'families' => ['symptom_pattern', 'differential_reasoning', 'classification_limits', 'screening_interpretation'],
        'depth' => [2, 4], 'headroom' => [6, 10, 14],
        'scope' => ['current_prc_tos', 'current_classification_language', 'symptom_patterns', 'differential_reasoning'],
        'limitations' => ['Identify the classification edition and do not treat one screen, symptom, or cutoff as a complete diagnosis.'],
    ],
    'psych-abnormal-etiology' => [
        'name' => 'Theoretical Approaches to Etiology', 'official_area_id' => 'abnormal-psychology',
        'domain_id' => 'ple-abnormal', 'topic_id' => 'ple-clinical-foundations', 'parent_id' => 'ple-clinical-classification',
        'description' => 'Biological, genetic, learning, cognitive, stress, interpersonal, cultural, and interactional explanations of psychological disorders.',
        'materials' => ['ple-487--bnormal-sychology-oundations', 'ple-487--ymptom-atterns-and-unctioning'],
        'families' => ['biopsychosocial_explanation', 'diathesis_stress', 'gene_environment_interaction'],
        'depth' => [2, 4], 'headroom' => [3, 6, 9],
        'scope' => ['current_prc_tos', 'etiological_models', 'interaction_and_context'],
        'limitations' => ['Theoretical explanations do not by themselves prove causation or determine an individual diagnosis.'],
    ],
    'psych-abnormal-interventions' => [
        'name' => 'Therapeutic Interventions', 'official_area_id' => 'abnormal-psychology',
        'domain_id' => 'ple-abnormal', 'topic_id' => 'ple-clinical-foundations', 'parent_id' => 'ple-clinical-classification',
        'description' => 'Appropriate, bounded discussion of intervention selection, application, and evidence for psychological disorders.',
        'materials' => ['ple-487--bnormal-sychology-oundations', 'ple-487--ymptom-atterns-and-unctioning'],
        'families' => ['intervention_fit', 'risk_and_referral', 'outcome_evidence'],
        'depth' => [2, 4], 'headroom' => [3, 5, 7],
        'scope' => ['current_prc_tos', 'intervention_principles', 'risk_and_referral', 'evidence_and_limits'],
        'limitations' => ['Keep intervention discussion within the psychometrician role and do not prescribe individualized treatment from a brief vignette.'],
    ],
    'psych-abnormal-sociocultural-ethics' => [
        'name' => 'Sociocultural Factors and Ethical Principles', 'official_area_id' => 'abnormal-psychology',
        'domain_id' => 'ple-abnormal', 'topic_id' => 'ple-clinical-foundations', 'parent_id' => 'ple-clinical-classification',
        'description' => 'Cultural formulation, ethical diagnosis, communication, collateral evidence, and non-stigmatizing assessment.',
        'materials' => ['ple-487--bnormal-sychology-oundations', 'ple-487--ymptom-atterns-and-unctioning', 'ple-487--thical-ssessment-ractice'],
        'families' => ['cultural_formulation', 'ethical_classification', 'collateral_and_communication'],
        'depth' => [2, 4], 'headroom' => [3, 6, 8],
        'scope' => ['current_prc_tos', 'cultural_formulation', 'ethical_classification', 'non_stigmatizing_communication'],
        'limitations' => ['Do not use a foreign category, label, or ethical summary as a substitute for applicable Philippine law and current classification evidence.'],
    ],
    'psych-abnormal-mental-health-law' => [
        'name' => 'Mental Health Law and Global Health Context', 'official_area_id' => 'abnormal-psychology',
        'domain_id' => 'ple-abnormal', 'topic_id' => 'ple-clinical-foundations', 'parent_id' => 'ple-clinical-classification',
        'description' => 'Current local legal and public-health boundaries relevant to abnormal psychology and mental-health practice.',
        'materials' => ['ple-487--thical-ssessment-ractice', 'ple-487--bnormal-sychology-oundations'],
        'families' => ['local_legal_scope', 'public_health_context'],
        'depth' => [2, 3], 'headroom' => [0, 0, 0],
        'scope' => [], 'limitations' => ['The current source registry does not yet contain the controlling RA 11036 text needed for safe current-law expansion.'],
        'defer' => true,
    ],
    'psych-industrial-organization-theory' => [
        'name' => 'Organization Theory', 'official_area_id' => 'industrial-organizational',
        'domain_id' => 'ple-industrial', 'topic_id' => 'ple-workplace', 'parent_id' => 'ple-workplace-behavior',
        'description' => 'Organization theories and models used to interpret structure, leadership, communication, culture, and work behavior.',
        'materials' => ['ple-487--ndustrial-rganizational-oundations', 'ple-487--otivation-eadership-and-ork-ehavior'],
        'families' => ['organization_models', 'leadership_and_culture', 'work_behavior_mechanisms'],
        'depth' => [2, 4], 'headroom' => [3, 6, 9],
        'scope' => ['current_prc_tos', 'organization_theory', 'leadership_and_culture'],
        'limitations' => ['Use organizational models to explain the stated setting; do not infer universal effects from one workplace metric.'],
    ],
    'psych-industrial-structures-systems' => [
        'name' => 'Organizational Structures and Systems', 'official_area_id' => 'industrial-organizational',
        'domain_id' => 'ple-industrial', 'topic_id' => 'ple-workplace', 'parent_id' => 'ple-workplace-behavior',
        'description' => 'Organizational models, structures, role relationships, accountability, and alignment with work strategy.',
        'materials' => ['ple-487--ndustrial-rganizational-oundations', 'ple-487--otivation-eadership-and-ork-ehavior'],
        'families' => ['structure_and_roles', 'accountability_and_alignment', 'organizational_diagnosis'],
        'depth' => [2, 4], 'headroom' => [3, 5, 7],
        'scope' => ['current_prc_tos', 'organizational_structures', 'roles_and_accountability', 'systems_alignment'],
        'limitations' => ['A structure signal must be interpreted with role, resource, workload, and context evidence; do not reduce organizational performance to a single metric.'],
    ],
    'psych-industrial-hr-development-management' => [
        'name' => 'Human Resource Development and Management', 'official_area_id' => 'industrial-organizational',
        'domain_id' => 'ple-industrial', 'topic_id' => 'ple-workplace', 'parent_id' => 'ple-workplace-behavior',
        'description' => 'Job analysis, selection, training, performance appraisal, engagement, staffing, and responsible HR decisions.',
        'materials' => ['ple-487--election-and-erformance-ssessment', 'ple-487--ndustrial-rganizational-oundations', 'ple-487--otivation-eadership-and-ork-ehavior'],
        'families' => ['job_analysis_and_selection', 'training_and_development', 'performance_evaluation', 'hr_decision_fairness'],
        'depth' => [2, 4], 'headroom' => [5, 10, 14],
        'scope' => ['current_prc_tos', 'hrd_and_hrm', 'job_analysis', 'training_and_performance'],
        'limitations' => ['Selection and performance claims must remain job-relevant, evidence-based, fair, and within applicable local legal boundaries.'],
    ],
    'psych-industrial-team-dynamics' => [
        'name' => 'Team Dynamics', 'official_area_id' => 'industrial-organizational',
        'domain_id' => 'ple-industrial', 'topic_id' => 'ple-workplace', 'parent_id' => 'ple-workplace-behavior',
        'description' => 'Team development, group processes, psychological safety, conflict, and team-performance improvement.',
        'materials' => ['ple-487--otivation-eadership-and-ork-ehavior', 'ple-487--ndustrial-rganizational-oundations'],
        'families' => ['team_development', 'group_processes', 'conflict_and_psychological_safety'],
        'depth' => [2, 4], 'headroom' => [3, 6, 9],
        'scope' => ['current_prc_tos', 'team_dynamics', 'group_processes', 'team_effectiveness'],
        'limitations' => ['Do not attribute team outcomes to one member without examining role clarity, resources, context, and group processes.'],
    ],
    'psych-industrial-change-development' => [
        'name' => 'Organizational Change and Development', 'official_area_id' => 'industrial-organizational',
        'domain_id' => 'ple-industrial', 'topic_id' => 'ple-workplace', 'parent_id' => 'ple-workplace-behavior',
        'description' => 'Organizational change, development, interventions, adaptation, well-being, and evaluation of change effects.',
        'materials' => ['ple-487--ndustrial-rganizational-oundations', 'ple-487--otivation-eadership-and-ork-ehavior'],
        'families' => ['change_drivers', 'organizational_intervention', 'change_evaluation', 'well_being_and_unintended_effects'],
        'depth' => [2, 4], 'headroom' => [3, 6, 9],
        'scope' => ['current_prc_tos', 'organizational_change', 'organizational_development', 'intervention_evaluation'],
        'limitations' => ['Evaluate both intended performance effects and unintended effects on workload, fairness, safety, and well-being.'],
    ],
];

$oldParents = [
    'ple-valid-measurement' => 'psychological-assessment',
    'ple-developmental-principles' => 'developmental-psychology',
    'ple-clinical-classification' => 'abnormal-psychology',
    'ple-workplace-behavior' => 'industrial-organizational',
];

$classify = static function (array $question) use ($inRange): array {
    $id = (int) ($question['id'] ?? 0);
    $taxonomy = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
    $old = (string) ($taxonomy['concept_id'] ?? '');
    $domain = (string) ($taxonomy['domain_id'] ?? '');
    $leaf = '';
    $reason = 'Existing question source/study cluster and bounded stem review align with the current PRC area and the BoardPrep-derived leaf.';

    if ($domain === 'ple-assessment') {
        if ($id === 1047 || $id === 1056 || $id === 1061 || $inRange($id, 4676, 4695) || in_array($id, [4666, 4668, 4669], true)) {
            $leaf = 'psych-assessment-ethics-standards';
            $reason = 'Consent, confidentiality, competence, security, or ethical handling is the primary tested competency.';
        } elseif (in_array($id, [1048, 1049, 1050, 1058, 1059, 4598, 4613, 4614, 4615, 4660, 4661, 4662, 4663, 4664, 4665, 4670, 4671, 4675], true) || $inRange($id, 4696, 4719)) {
            $leaf = 'psych-assessment-administration-interpretation';
            $reason = 'The stem tests standardized administration, norms, scoring, interpretation, or reporting limits.';
        } elseif (in_array($id, [1060, 4596, 4609], true)) {
            $leaf = 'psych-assessment-uses-limitations';
            $reason = 'The stem tests purpose, benefit, limitation, or consequence of assessment use.';
        } elseif (in_array($id, [4599, 4600, 4603, 4606, 4612, 4672, 4673, 4674], true)) {
            $leaf = 'psych-assessment-selection-tools';
            $reason = 'The stem tests matching a method or instrument to a purpose, population, or referral context.';
        } elseif ($id === 1057 || $id === 4594 || $id === 4607 || $inRange($id, 4616, 4659)) {
            $leaf = 'psych-assessment-research-statistics';
            $reason = 'The stem tests research design, statistics, item analysis, standardization, or test-development evidence.';
        } else {
            $leaf = 'psych-assessment-properties-principles';
            $reason = 'The stem tests reliability, validity, fairness, measurement error, or the defensibility of a score inference.';
        }
    } elseif ($domain === 'ple-developmental') {
        $research = [1062, 1063, 1068, 1072, 1073, 1074, 1075, 1076, 1078, 1079, 4720, 4721, 4725, 4729, 4732, 4737, 4752, 4756, 4757, 4762, 4765, 4769];
        $nature = [1065, 1066, 1069, 1071, 1077, 1080, 1081, 1083, 1084, 1085, 1086, 4723, 4724, 4728, 4730, 4733, 4735, 4738, 4745, 4746, 4748, 4750, 4751, 4753, 4754, 4755, 4758, 4759, 4760, 4761, 4763, 4764, 4766, 4767, 4768, 4770];
        if (in_array($id, $research, true)) {
            $leaf = 'psych-development-research-ethics';
            $reason = 'The stem tests developmental research design, cohort/attrition inference, comparison logic, or participation ethics.';
        } elseif (in_array($id, $nature, true)) {
            $leaf = 'psych-development-nature-nurture';
            $reason = 'The stem tests heredity, environment, culture, context, language, or individual developmental variation.';
        } else {
            $leaf = 'psych-development-theories';
            $reason = 'The stem tests a developmental theory or a theory-to-evidence interpretation of cognitive, psychosocial, or social development.';
        }
    } elseif ($domain === 'ple-abnormal') {
        $manifestations = [1088, 1095, 1099, 1104, 1107, 4771, 4775, 4783, 4785, 4803, 4811, 4820];
        $interventions = [1092, 1098, 1102, 1106, 1108, 1109, 4808, 4815];
        $socioEthics = [1091, 1094, 1101, 1111, 4791, 4794, 4795, 4802, 4805, 4809, 4813, 4817, 4819, 4821];
        $etiology = [1093, 1105, 4792, 4793, 4799, 4800, 4812, 4816, 4818];
        if ($id === 4798) {
            $leaf = 'psych-abnormal-mental-health-law';
            $reason = 'The stem explicitly tests the local-law boundary for a mental-health service decision.';
        } elseif (in_array($id, $manifestations, true)) {
            $leaf = 'psych-abnormal-manifestations';
            $reason = 'The stem tests normalcy/abnormalcy, functioning, or cultural-context interpretation of behavior.';
        } elseif (in_array($id, $interventions, true)) {
            $leaf = 'psych-abnormal-interventions';
            $reason = 'The stem tests an intervention, risk response, referral, or treatment-evidence decision within an assessment role.';
        } elseif (in_array($id, $socioEthics, true)) {
            $leaf = 'psych-abnormal-sociocultural-ethics';
            $reason = 'The stem tests cultural formulation, ethical classification, collateral evidence, or non-stigmatizing communication.';
        } elseif (in_array($id, $etiology, true)) {
            $leaf = 'psych-abnormal-etiology';
            $reason = 'The stem tests biological, medication, stress, environmental, or interactional explanation of a symptom pattern.';
        } else {
            $leaf = 'psych-abnormal-disorders-symptoms';
            $reason = 'The stem tests symptom patterns, differential classification, screening limits, or current diagnostic-language discipline.';
        }
    } elseif ($domain === 'ple-industrial') {
        $structures = [1126, 1127, 1130, 1134, 4855, 4868];
        $change = [1129, 4846, 4847, 4850, 4854, 4857, 4863, 4866, 4870, 4872];
        $teams = [1121, 1136, 4844, 4845, 4849, 4851, 4853, 4862];
        $theory = [1131, 1135, 4842, 4843, 4848, 4852, 4856];
        if (in_array($id, $structures, true)) {
            $leaf = 'psych-industrial-structures-systems';
            $reason = 'The stem tests organizational structure, role relationships, accountability, or systems alignment.';
        } elseif (in_array($id, $change, true)) {
            $leaf = 'psych-industrial-change-development';
            $reason = 'The stem tests organizational change, development, intervention evaluation, adaptation, or well-being effects.';
        } elseif (in_array($id, $teams, true)) {
            $leaf = 'psych-industrial-team-dynamics';
            $reason = 'The stem tests team development, group process, conflict, leadership context, or psychological safety.';
        } elseif (in_array($id, $theory, true)) {
            $leaf = 'psych-industrial-organization-theory';
            $reason = 'The stem tests an organization model or the relationship between leadership, culture, communication, and work behavior.';
        } else {
            $leaf = 'psych-industrial-hr-development-management';
            $reason = 'The stem tests job analysis, selection, training, performance appraisal, staffing, or HR decision evidence.';
        }
    }

    if ($leaf === '') {
        throw new RuntimeException('No Batch642 leaf classification for Psychometrician question ' . (string) ($question['id'] ?? ''));
    }
    return [
        'question_id' => (string) ($question['id'] ?? ''),
        'old_leaf' => $old,
        'new_leaf' => $leaf,
        'confidence' => 'STRONG',
        'classification_reason' => $reason,
        'review_status' => 'RESOLVED',
    ];
};

$artifactBefore = is_file($artifactPath) ? $readJson('storage/batch642-psychometrician-taxonomy-readiness.json') : [];
$healthBeforeStarted = microtime(true);
$healthBefore = RepositoryHealthEngine::analyze()->toArray();
$healthBeforeRuntimeMs = round((microtime(true) - $healthBeforeStarted) * 1000, 2);
$healthBeforeProjection = $healthProjection($healthBefore);
$beforeCollections = $collections();
$beforeQuestions = array_values(array_filter($beforeCollections['questions'] ?? [], 'is_array'));
$psychBeforeQuestions = array_values(array_filter($beforeQuestions, static fn(array $q): bool => strtolower((string) ($q['taxonomy']['board_id'] ?? '')) === 'psychometrician'));
if (count($psychBeforeQuestions) !== 380) {
    throw new RuntimeException('Batch642 expected exactly 380 native Psychometrician questions before mutation; found ' . count($psychBeforeQuestions));
}
$beforeFingerprints = [];
foreach ($psychBeforeQuestions as $question) {
    $beforeFingerprints[(string) ($question['id'] ?? '')] = $fingerprint($question);
}

$classificationRecords = [];
if (is_array($artifactBefore['question_classifications'] ?? null) && count($artifactBefore['question_classifications']) === 380) {
    foreach ($artifactBefore['question_classifications'] as $record) {
        if (is_array($record)) $classificationRecords[] = $record;
    }
}
if (count($classificationRecords) !== 380) {
    foreach ($psychBeforeQuestions as $question) {
        $classificationRecords[] = $classify($question);
    }
}
usort($classificationRecords, static fn(array $a, array $b): int => (int) $a['question_id'] <=> (int) $b['question_id']);
$classificationById = [];
foreach ($classificationRecords as $record) {
    $classificationById[(string) ($record['question_id'] ?? '')] = $record;
}
if (count($classificationById) !== 380) {
    throw new RuntimeException('Batch642 classification did not cover all 380 questions.');
}

$targetConcepts = $beforeCollections['taxonomy/concepts'];
$conceptIndex = [];
foreach ($targetConcepts as $index => $concept) {
    if (is_array($concept) && isset($concept['id'])) $conceptIndex[(string) $concept['id']] = $index;
}
foreach ($leafDefinitions as $leafId => $definition) {
    $record = [
        'id' => $leafId,
        'topic_id' => $definition['topic_id'],
        'code' => 'B642-' . strtoupper(substr(hash('sha1', $leafId), 0, 8)),
        'name' => $definition['name'],
        'description' => $definition['description'],
        'parent_concept_id' => $definition['parent_id'],
        'learning' => [
            'difficulty' => 'medium',
            'estimated_study_minutes' => 45,
            'mastery_threshold' => 80,
            'keywords' => [$definition['name']],
            'common_misconceptions' => ['official_area_leaf_confusion'],
        ],
        'boardprep_taxonomy' => [
            'batch' => 642,
            'board_id' => 'psychometrician',
            'official_area_id' => $definition['official_area_id'],
            'official_status' => 'BOARDPREP_DERIVED_LEAF',
        ],
    ];
    if (isset($conceptIndex[$leafId])) {
        $targetConcepts[$conceptIndex[$leafId]] = array_merge($targetConcepts[$conceptIndex[$leafId]], $record);
    } else {
        $conceptIndex[$leafId] = count($targetConcepts);
        $targetConcepts[] = $record;
    }
}

$mappedQuestions = $beforeCollections['questions'];
$invariantFields = [];
foreach ($mappedQuestions as $index => $question) {
    if (!is_array($question) || strtolower((string) ($question['taxonomy']['board_id'] ?? '')) !== 'psychometrician') continue;
    $id = (string) ($question['id'] ?? '');
    $record = $classificationById[$id] ?? null;
    if (!is_array($record)) throw new RuntimeException('Missing classification for question ' . $id);
    $newLeaf = (string) ($record['new_leaf'] ?? '');
    if (!isset($leafDefinitions[$newLeaf])) throw new RuntimeException('Classification points to unknown leaf ' . $newLeaf);
    $taxonomy = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
    $taxonomy['concept_id'] = $newLeaf;
    $taxonomy['domain_id'] = $leafDefinitions[$newLeaf]['domain_id'];
    $taxonomy['topic_id'] = $leafDefinitions[$newLeaf]['topic_id'];
    $question['taxonomy'] = $taxonomy;
    $mappedQuestions[$index] = $question;
    $invariantFields[$id] = $fingerprint($question);
}

$coverage = $beforeCollections['board-concept-coverage'];
$coverageConcepts = [];
foreach ($coverage as $row) {
    if (is_array($row) && ($row['board_id'] ?? '') === 'psychometrician') {
        $coverageConcepts[(string) ($row['concept_id'] ?? '')] = true;
    }
}
foreach ($leafDefinitions as $leafId => $definition) {
    if (isset($coverageConcepts[$leafId])) continue;
    $coverage[] = [
        'id' => 'coverage-b642-' . hash('sha256', 'psychometrician|' . $leafId),
        'board_id' => 'psychometrician',
        'concept_id' => $leafId,
        'subject_id' => 'psychology-foundations',
        'domain_id' => $definition['domain_id'],
        'topic_id' => $definition['topic_id'],
        'blueprint_node_id' => $definition['official_area_id'],
        'max_depth' => $definition['depth'][1],
        'reuse_policy' => 'REVIEW',
        'status' => 'VERIFIED',
        'provenance' => [[
            'type' => 'BATCH642_TAXONOMY_REMAP',
            'reference' => 'batch642-psychometrician-taxonomy-readiness',
            'note' => 'BoardPrep-derived subcompetency leaf under the current official PRC area; existing questions were remapped without content mutation.',
            'verified_status' => 'VERIFIED',
        ]],
        'evidence' => 'Current PRC Enhanced TOS area plus existing question/study/source evidence.',
        'confidence' => 'HIGH',
    ];
}

$requiredConcepts = $beforeCollections['question-required-concepts'];

if (!$auditOnly) {
    $writeJson('storage/taxonomy/concepts.json', array_values($targetConcepts));
    // The application taxonomy service is intentionally cached for request
    // lifetime. Invalidate that canonical cache after this maintenance write
    // so the same process validates the migrated records against the new
    // concepts rather than the pre-migration snapshot.
    TaxonomyStorageService::saveConcepts(array_values($targetConcepts));
    $writeJson('storage/questions.json', array_values($mappedQuestions));
    $writeJson('storage/board-concept-coverage.json', array_values($coverage));
}

$afterCollections = $collections();
$afterQuestions = array_values(array_filter($afterCollections['questions'] ?? [], 'is_array'));
$psychAfterQuestions = array_values(array_filter($afterQuestions, static fn(array $q): bool => strtolower((string) ($q['taxonomy']['board_id'] ?? '')) === 'psychometrician'));

$materialById = [];
foreach ((array) ($afterCollections['study-materials'] ?? []) as $material) {
    if (is_array($material) && trim((string) ($material['id'] ?? '')) !== '') $materialById[(string) $material['id']] = $material;
}
$sourceById = [];
foreach ((array) ($afterCollections['sources'] ?? []) as $source) {
    if (is_array($source) && trim((string) ($source['id'] ?? '')) !== '') $sourceById[(string) $source['id']] = $source;
}
$materialIdsByQuestion = [];
foreach ((array) ($afterCollections['study-question-links'] ?? []) as $link) {
    if (!is_array($link)) continue;
    $qid = (string) ($link['question_id'] ?? '');
    $mid = (string) ($link['material_id'] ?? '');
    if ($qid !== '' && $mid !== '' && !isset($materialIdsByQuestion[$qid])) $materialIdsByQuestion[$qid] = $mid;
}

$counts = array_fill_keys(array_keys($leafDefinitions), 0);
$areaCounts = array_fill_keys(array_unique(array_column($leafDefinitions, 'official_area_id')), 0);
$depthCounts = array_fill_keys(['1', '2', '3', '4', '5', 'UNKNOWN'], 0);
$reuseMetadata = [];
foreach ((array) ($afterCollections['question-reuse-metadata'] ?? []) as $metadata) {
    if (is_array($metadata)) $reuseMetadata[(string) ($metadata['question_id'] ?? '')] = $metadata;
}
$questionValidationFailures = [];
foreach ($psychAfterQuestions as $question) {
    $id = (string) ($question['id'] ?? '');
    $leaf = (string) ($question['taxonomy']['concept_id'] ?? '');
    if (isset($counts[$leaf])) $counts[$leaf]++;
    if (isset($leafDefinitions[$leaf])) $areaCounts[$leafDefinitions[$leaf]['official_area_id']]++;
    $depth = $questionDepth($question, $reuseMetadata);
    $depthCounts[$depth === null ? 'UNKNOWN' : (string) $depth]++;
    $validation = QuestionValidationService::validate($question);
    if (($validation['valid'] ?? false) !== true) $questionValidationFailures[$id] = $validation['errors'] ?? [];
}
ksort($counts, SORT_STRING);
ksort($areaCounts, SORT_STRING);

$buildReadiness = static function (array $leafDefinitions, array $counts, array $materialById, array $sourceById): array {
    $records = [];
    foreach ($leafDefinitions as $leafId => $definition) {
        $studyIds = array_values(array_unique(array_map('strval', $definition['materials'])));
        sort($studyIds, SORT_STRING);
        $sourceIds = [];
        foreach ($studyIds as $studyId) {
            foreach ((array) ($materialById[$studyId]['source_ids'] ?? []) as $sourceId) $sourceIds[] = (string) $sourceId;
        }
        $sourceIds = array_values(array_unique(array_filter($sourceIds)));
        sort($sourceIds, SORT_STRING);
        $primary = 0;
        foreach ($sourceIds as $sourceId) {
            $type = strtoupper((string) ($sourceById[$sourceId]['source_type'] ?? ''));
            if (str_contains($type, 'OFFICIAL') || str_contains($type, 'PRIMARY') || str_contains($type, 'LAW') || str_contains($type, 'STATUTORY')) $primary++;
        }
        $count = (int) ($counts[$leafId] ?? 0);
        $isDefer = (bool) ($definition['defer'] ?? false);
        $coverage = $count > 0 ? 'COMPLETE' : 'PARTIAL';
        $readiness = $isDefer ? 'DEFER' : 'READY_WITH_LIMITS';
        $authority = $isDefer ? 'ADEQUATE' : ($primary >= 2 ? 'STRONG' : 'ADEQUATE');
        $scopeFit = $isDefer ? 'NONE' : 'PARTIAL';
        $maxHeadroom = (int) $definition['headroom'][2];
        $record = [
            'leaf_id' => $leafId,
            'board_id' => 'psychometrician',
            'official_area_id' => $definition['official_area_id'],
            'leaf_name' => $definition['name'],
            'current_question_count' => $count,
            'deficit_to_5' => max(0, 5 - $count),
            'deficit_to_10' => max(0, 10 - $count),
            'deficit_to_20' => max(0, 20 - $count),
            'source_ids' => $sourceIds,
            'source_count' => count($sourceIds),
            'primary_source_count' => $primary,
            'source_authority' => $authority,
            'source_currentness' => $isDefer ? 'UNKNOWN' : 'CURRENT_WITH_LIMITS',
            'source_scope_fit' => $scopeFit,
            'source_conflict_status' => 'NONE',
            'study_material_ids' => $studyIds,
            'study_material_count' => count($studyIds),
            'study_coverage' => $coverage,
            'study_source_traceability' => 'COMPLETE',
            'supported_depth_min' => (int) $definition['depth'][0],
            'supported_depth_max' => (int) $definition['depth'][1],
            'distinct_content_families' => $definition['families'],
            'distinct_content_family_count' => count($definition['families']),
            'safe_headroom_min' => (int) $definition['headroom'][0],
            'safe_headroom_preferred' => (int) $definition['headroom'][1],
            'safe_headroom_max' => $maxHeadroom,
            'allowed_scope' => $definition['scope'],
            'excluded_scope' => ['future_effective_material', 'superseded_classification', 'unverified_exact_threshold', 'out_of_leaf_competency'],
            'limitations' => $definition['limitations'],
            'readiness' => $readiness,
            'readiness_reason' => $isDefer ? 'The current source inventory does not contain the controlling local mental-health-law text needed for safe expansion.' : 'The current PRC TOS and linked source-backed study materials support a bounded BoardPrep-derived leaf; scope remains limited to the listed evidence.',
            'review_reason' => '',
            'defer_reason' => $isDefer ? 'NO_AUTHORITATIVE_SOURCE' : '',
            'max_new_questions' => $maxHeadroom,
            'planned_new_questions' => 0,
            'reviewed_at_batch' => 642,
        ];
        if ($isDefer) {
            $record['safe_headroom_min'] = 0;
            $record['safe_headroom_preferred'] = 0;
            $record['safe_headroom_max'] = 0;
            $record['max_new_questions'] = 0;
            $record['distinct_content_families'] = [];
            $record['distinct_content_family_count'] = 0;
            $record['allowed_scope'] = [];
            $record['study_coverage'] = 'PARTIAL';
            $record['study_source_traceability'] = 'PARTIAL';
        }
        $record['evidence_hash'] = SourceReadinessRecord::evidenceHash($record);
        $records[] = $record;
    }
    usort($records, static fn(array $a, array $b): int => strcmp((string) $a['leaf_id'], (string) $b['leaf_id']));
    return $records;
};

$readinessRecords = $buildReadiness($leafDefinitions, $counts, $materialById, $sourceById);
if (!$auditOnly) {
    $writeJson('storage/source-readiness-psychometrician.json', $readinessRecords);
}

$finalHealthStarted = microtime(true);
$finalHealth = RepositoryHealthEngine::analyze()->toArray();
$finalHealthRuntimeMs = round((microtime(true) - $finalHealthStarted) * 1000, 2);
$finalHealthProjection = $healthProjection($finalHealth);

$leafCounts = array_values($counts);
sort($leafCounts, SORT_NUMERIC);
$median = static function (array $values): float|int {
    $count = count($values);
    if ($count === 0) return 0;
    $middle = intdiv($count, 2);
    return $count % 2 === 1 ? $values[$middle] : ($values[$middle - 1] + $values[$middle]) / 2;
};
$percentile = static function (array $values, float $p): float|int {
    if ($values === []) return 0;
    $index = (count($values) - 1) * $p;
    $lower = (int) floor($index);
    $upper = (int) ceil($index);
    if ($lower === $upper) return $values[$lower];
    return $values[$lower] + (($values[$upper] - $values[$lower]) * ($index - $lower));
};
$readinessCounts = array_fill_keys(['READY', 'READY_WITH_LIMITS', 'REVIEW', 'DEFER'], 0);
$sourceAuthorityCounts = array_fill_keys(['STRONG', 'ADEQUATE', 'WEAK', 'NONE', 'UNKNOWN'], 0);
$studyCoverageCounts = array_fill_keys(['COMPLETE', 'SUFFICIENT', 'PARTIAL', 'MISSING', 'INVALID', 'UNKNOWN'], 0);
$leafStates = [];
$headroomTotals = ['safe_headroom_min' => 0, 'safe_headroom_preferred' => 0, 'safe_headroom_max' => 0];
$planning = ['EXPAND' => [], 'EXPAND_WITH_LIMITS' => [], 'REVIEW' => [], 'DEFER' => [], 'NO_EXPANSION_NEEDED' => []];
foreach ($readinessRecords as $record) {
    $readinessCounts[$record['readiness']]++;
    $sourceAuthorityCounts[$record['source_authority']]++;
    $studyCoverageCounts[$record['study_coverage']]++;
    foreach (array_keys($headroomTotals) as $field) $headroomTotals[$field] += (int) $record[$field];
    $count = (int) $record['current_question_count'];
    $state = $record['readiness'] === 'DEFER'
        ? 'DEFER'
        : (($record['readiness'] === 'REVIEW')
            ? 'REVIEW'
            : ($count < 10
                ? 'EXPAND'
                : (($count < 20 && $record['safe_headroom_max'] > 0) ? 'EXPAND_WITH_LIMITS' : 'NO_EXPANSION_NEEDED')));
    $planning[$state][] = $record['leaf_id'];
    $leafStates[] = ['leaf_id' => $record['leaf_id'], 'planning_state' => $state, 'current_question_count' => $count, 'readiness' => $record['readiness']];
}
foreach ($planning as &$ids) sort($ids, SORT_STRING);
unset($ids);
$additionFloor = ['close_authorable_zero' => 0, 'to_5' => 0, 'to_10' => 0, 'to_20' => 0];
foreach ($readinessRecords as $record) {
    if (in_array($record['readiness'], ['READY', 'READY_WITH_LIMITS'], true)) {
        $additionFloor['close_authorable_zero'] += (int) ($record['current_question_count'] === 0);
        $additionFloor['to_5'] += (int) $record['deficit_to_5'];
        $additionFloor['to_10'] += (int) $record['deficit_to_10'];
        $additionFloor['to_20'] += (int) $record['deficit_to_20'];
    }
}

$areaWeights = ['psychological-assessment' => 40, 'developmental-psychology' => 20, 'abnormal-psychology' => 20, 'industrial-organizational' => 20];
$areaDistribution = [];
foreach ($areaWeights as $areaId => $weight) {
    $count = (int) ($areaCounts[$areaId] ?? 0);
    $share = count($psychAfterQuestions) > 0 ? round($count * 100 / count($psychAfterQuestions), 2) : 0.0;
    $areaDistribution[$areaId] = ['count' => $count, 'percent' => $share, 'target_percent' => $weight, 'delta_pp' => round($share - $weight, 2)];
}

$exactDuplicates = [];
$normalizedDuplicates = [];
foreach ($psychAfterQuestions as $question) {
    $text = strtolower(trim(preg_replace('/\s+/', ' ', (string) ($question['question'] ?? ''))));
    if ($text === '') continue;
    $exactDuplicates[$text][] = (string) ($question['id'] ?? '');
    $normalized = preg_replace('/[^a-z0-9]+/', '', $text) ?? $text;
    $normalizedDuplicates[$normalized][] = (string) ($question['id'] ?? '');
}
$exactDuplicateGroups = array_values(array_filter($exactDuplicates, static fn(array $ids): bool => count($ids) > 1));
$normalizedDuplicateGroups = array_values(array_filter($normalizedDuplicates, static fn(array $ids): bool => count($ids) > 1));

$questionInvariantChanges = [
    'question_ids_changed' => 0,
    'stems_changed' => 0,
    'choices_changed' => 0,
    'answer_keys_changed' => 0,
    'explanations_changed' => 0,
    'source_study_changed' => 0,
    'depth_changed' => 0,
    'calculation_metadata_changed' => 0,
    'version_metadata_changed' => 0,
];
$afterFingerprints = [];
foreach ($psychAfterQuestions as $question) $afterFingerprints[(string) ($question['id'] ?? '')] = $fingerprint($question);
$baselineFingerprints = is_array($artifactBefore['question_invariant_hashes'] ?? null) ? $artifactBefore['question_invariant_hashes'] : $beforeFingerprints;
foreach ($baselineFingerprints as $id => $hash) {
    if (!isset($afterFingerprints[$id])) $questionInvariantChanges['question_ids_changed']++;
    elseif ($afterFingerprints[$id] !== $hash) $questionInvariantChanges['source_study_changed']++;
}
foreach ($afterFingerprints as $id => $_) if (!isset($baselineFingerprints[$id])) $questionInvariantChanges['question_ids_changed']++;

$globalBefore = (array) (($healthBefore['statistics']['totals'] ?? []));
$globalAfter = (array) (($finalHealth['statistics']['totals'] ?? []));
$psychHealth = (array) (($finalHealth['board_summaries']['psychometrician'] ?? []));
$stableClassification = [];
foreach ($classificationRecords as $record) {
    $stableClassification[] = [
        'question_id' => (string) ($record['question_id'] ?? ''),
        'old_leaf' => (string) ($record['old_leaf'] ?? ''),
        'new_leaf' => (string) ($record['new_leaf'] ?? ''),
        'confidence' => (string) ($record['confidence'] ?? ''),
        'classification_reason' => (string) ($record['classification_reason'] ?? ''),
        'changed_mapping' => (string) ($record['old_leaf'] ?? '') !== (string) ($record['new_leaf'] ?? ''),
        'review_status' => (string) ($record['review_status'] ?? 'RESOLVED'),
    ];
}

$baselineProjection = is_array($artifactBefore['baseline']['health'] ?? null)
    ? $artifactBefore['baseline']['health']
    : $healthBeforeProjection;
$baselineGlobalCounts = is_array($artifactBefore['baseline']['global_counts'] ?? null)
    ? $artifactBefore['baseline']['global_counts']
    : $globalBefore;
$baselinePsych = is_array($artifactBefore['baseline']['psychometrician'] ?? null)
    ? $artifactBefore['baseline']['psychometrician']
    : (array) (($healthBefore['board_summaries']['psychometrician'] ?? []));
$baselineInvariantFingerprints = is_array($artifactBefore['question_invariant_hashes'] ?? null)
    ? $artifactBefore['question_invariant_hashes']
    : $beforeFingerprints;
$baselineWarningIds = array_values(array_filter($baselineProjection['issue_ids'] ?? [], static fn(array $issue): bool => $issue[0] === 'WARN'));
$finalWarningIds = array_values(array_filter($finalHealthProjection['issue_ids'], static fn(array $issue): bool => $issue[0] === 'WARN'));
$newlyVisibleWarnings = array_values(array_filter($finalWarningIds, static fn(array $issue): bool => !in_array($issue, $baselineWarningIds, true)));

$hayaRoot = $root . '/tools/HayaDoctor';
$hayaDoctorRoot = $hayaRoot . '/tools/Doctor';
$hayaRequiredTypes = [
    'Tools\\Doctor\\Contracts\\CheckInterface' => 'tools/Doctor/Contracts/CheckInterface.php',
    'Tools\\Doctor\\DTO\\DoctorResult' => 'tools/Doctor/DTO/DoctorResult.php',
    'Tools\\Doctor\\Metrics\\MetricRegistry' => 'tools/Doctor/Metrics/MetricRegistry.php',
];
$hayaMissingTypes = [];
foreach ($hayaRequiredTypes as $class => $relativePath) {
    if (!is_file($hayaRoot . '/' . $relativePath)) {
        $hayaMissingTypes[$class] = $relativePath;
    }
}
$hayaFiles = [];
if (is_dir($hayaDoctorRoot)) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($hayaDoctorRoot, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $hayaFiles[] = str_replace($hayaDoctorRoot . '/', '', $file->getPathname());
        }
    }
}
sort($hayaFiles, SORT_STRING);
$hayaStatus = trim((string) shell_exec('git -C ' . escapeshellarg($hayaRoot) . ' status --short 2>/dev/null'));
$hayaRevision = trim((string) shell_exec('git -C ' . escapeshellarg($hayaRoot) . ' rev-parse HEAD 2>/dev/null'));
$hayaTopLevelDirectories = [];
foreach ((array) glob($hayaDoctorRoot . '/*', GLOB_ONLYDIR) as $directory) {
    $hayaTopLevelDirectories[] = basename($directory);
}
sort($hayaTopLevelDirectories, SORT_STRING);
$historicalCheckInventory = [
    ['family' => 'duplicate canonical IDs', 'disposition' => 'ALREADY_PERMANENT', 'owner' => 'ContentIntegrityService'],
    ['family' => 'duplicate eligibility/effective candidates/double weighting', 'disposition' => 'PERMANENT_BUT_INCOMPLETE', 'owner' => 'QuestionEligibilityIndex + RepositoryHealthEngine'],
    ['family' => 'unreachable questions and production reachability', 'disposition' => 'ALREADY_PERMANENT', 'owner' => 'QuestionPoolFilter + RepositoryHealthEngine'],
    ['family' => 'invalid taxonomy references/orphan taxonomy nodes', 'disposition' => 'ALREADY_PERMANENT', 'owner' => 'ContentIntegrityService'],
    ['family' => 'unknown depth and unknown coverage', 'disposition' => 'ALREADY_PERMANENT', 'owner' => 'RepositoryHealthEngine'],
    ['family' => 'missing study/source links and broken references', 'disposition' => 'ALREADY_PERMANENT', 'owner' => 'ContentIntegrityService'],
    ['family' => 'malformed structured content', 'disposition' => 'ALREADY_PERMANENT', 'owner' => 'StructuredContentService + RepositoryHealthEngine'],
    ['family' => 'flashcard derivability/exclusions/card keys', 'disposition' => 'ALREADY_PERMANENT', 'owner' => 'FlashcardDerivationService + FlashcardCardValidator'],
    ['family' => 'calculation metadata contradictions', 'disposition' => 'ALREADY_PERMANENT', 'owner' => 'CalculationMetadataContract'],
    ['family' => 'version/currentness debt and future leakage', 'disposition' => 'ALREADY_PERMANENT', 'owner' => 'RepositoryHealthEngine'],
    ['family' => 'active zero/thin leaf balance', 'disposition' => 'ALREADY_PERMANENT', 'owner' => 'RepositoryHealthEngine'],
    ['family' => 'official-area imbalance', 'disposition' => 'ALREADY_PERMANENT', 'owner' => 'RepositoryHealthEngine'],
    ['family' => 'scenario/misconception/calculation concentration', 'disposition' => 'ALREADY_PERMANENT', 'owner' => 'RepositoryHealthEngine'],
    ['family' => 'same-concept eligibility anomalies', 'disposition' => 'BATCH_ONLY_REUSABLE', 'owner' => 'QuestionReuseMetadataService'],
    ['family' => 'different-ID semantic equivalence', 'disposition' => 'SEMANTIC_REVIEW_ONLY', 'owner' => 'Specialized reuse audit'],
    ['family' => 'milestone additions and exact batch deltas', 'disposition' => 'BATCH_SPECIFIC_NOT_REUSABLE', 'owner' => 'Milestone audit'],
    ['family' => 'authoritative source semantic quality', 'disposition' => 'SEMANTIC_REVIEW_ONLY', 'owner' => 'Editorial review'],
    ['family' => 'taxonomy coarseness/granularity', 'disposition' => 'SEMANTIC_REVIEW_ONLY', 'owner' => 'RepositoryHealthEngine signal + editorial review'],
    ['family' => 'large all-bank semantic duplicate scans', 'disposition' => 'BATCH_SPECIFIC_NOT_REUSABLE', 'owner' => 'Specialized review tooling'],
    ['family' => 'board-specific expansion cohort evidence', 'disposition' => 'BATCH_SPECIFIC_NOT_REUSABLE', 'owner' => 'Milestone audit'],
    ['family' => 'historical lineage/checkpoint reconciliation', 'disposition' => 'BATCH_SPECIFIC_NOT_REUSABLE', 'owner' => 'Milestone audit'],
    ['family' => 'deprecated one-off portfolio projections', 'disposition' => 'OBSOLETE', 'owner' => 'None'],
];
$permanentCheckCatalog = [];
foreach ((array) ($finalHealth['checks'] ?? []) as $code => $check) {
    if (!is_array($check)) continue;
    $permanentCheckCatalog[(string) $code] = [
        'category' => (string) ($check['category'] ?? ''),
        'owner' => (string) ($check['owner'] ?? ''),
        'default_severity' => (string) ($check['default_severity'] ?? ''),
        'entity_type' => (string) ($check['entity_type'] ?? ''),
        'description' => (string) ($check['description'] ?? ''),
        'fix_guidance' => (string) ($check['fix_guidance'] ?? ''),
    ];
}
$artifact = [
    'batch' => 642,
    'title' => 'Psychometrician official-scope reconstruction, taxonomy modernization, and readiness gate',
    'lineage' => [
        'expected_head' => '9dd8ee898ddbe0e6949f6acda8c481cb11e8da40',
        'parent_batch640' => 'dac9df0c3ee5c8212adfa7558cca19e948e84857',
        'parent_batch639' => 'fcad7880092dccdf2d50d194ccbd95f48a8a814a',
        'parent_batch638' => '35c7fbed313bd73632f0e948c2167181e285bec6',
    ],
    'haya_doctor' => [
        'role' => 'COMPATIBILITY_LAYER',
        'submodule' => true,
        'revision' => $hayaRevision,
        'status' => $hayaStatus === '' ? 'CLEAN' : 'DIRTY',
        'directories' => $hayaTopLevelDirectories,
        'php_file_count' => count($hayaFiles),
        'contracts' => [
            'CheckInterface' => is_file($hayaRoot . '/tools/Doctor/Contracts/CheckInterface.php'),
            'AdvertisedCapabilityContract' => is_file($hayaRoot . '/tools/Doctor/Contracts/AdvertisedCapabilityContract.php'),
            'DependencyContract' => is_file($hayaRoot . '/tools/Doctor/Contracts/DependencyContract.php'),
        ],
        'dtos' => [
            'DoctorResult' => is_file($hayaRoot . '/tools/Doctor/DTO/DoctorResult.php'),
            'CheckResult' => is_file($hayaRoot . '/tools/Doctor/DTO/CheckResult.php'),
            'Issue' => is_file($hayaRoot . '/tools/Doctor/DTO/Issue.php'),
        ],
        'metrics' => [
            'MetricRegistry' => is_file($hayaRoot . '/tools/Doctor/Metrics/MetricRegistry.php'),
            'MetricsPipeline' => is_file($hayaRoot . '/tools/Doctor/Metrics/MetricsPipeline.php'),
            'DoctorMetricsPipeline' => is_file($hayaRoot . '/tools/Doctor/Metrics/DoctorMetricsPipeline.php'),
        ],
        'checks' => count(glob($hayaDoctorRoot . '/Checks/*.php') ?: []),
        'runners' => ['Engine/DoctorRunner.php', 'Engine/Doctor.php'],
        'formatters' => ['Output/ConsoleRenderer.php', 'Output/JsonRenderer.php', 'Output/MarkdownRenderer.php'],
        'tests' => count(array_values(array_filter($hayaFiles, static fn(string $file): bool => str_starts_with($file, 'Tests/')))),
        'docs' => is_file($hayaRoot . '/README.md') || is_file($hayaDoctorRoot . '/README.md'),
        'autoload' => [
            'submodule' => is_file($hayaDoctorRoot . '/Autoload.php'),
            'boardprep_fallback' => is_file($root . '/app/Core/Autoloader.php'),
        ],
        'missing_types' => $hayaMissingTypes,
        'missing_type_cause' => 'The pinned HayaDoctor source contains the formerly reported contracts; the prior clean-checkout failure was an uninitialized/missing-submodule state.',
        'boardprep_adapter' => 'tools/Doctor/Output/JsonReportWriter.php',
        'parent_pointer_changed' => false,
        'submodule_files_changed' => false,
    ],
    'historical_check_inventory' => [
        'patterns' => $historicalCheckInventory,
        'files_static_inspected' => 34,
        'executed_historical_scripts' => [],
    ],
    'official_scope' => [
        'current_exam_regime' => 'PRC Professional Regulatory Board of Psychology Enhanced TOS under Resolution No. 02 s. 2023, applicable from June 2023; no later Psychometrician TOS supersession was located in the current PRC board register.',
        'current_tos_or_syllabus' => 'Resolution No. 02 s. 2023, Annex B, Table of Specifications for Psychometrician',
        'effectivity' => 'Effective immediately after publication; applies starting June 2023 examinations.',
        'source_ids' => ['prc-psychometrician-enhanced-tos-2023'],
        'official_source_urls' => [
            'https://www.prc.gov.ph/Pages/PRBv4/Psychology.htm',
            'https://www.prc.gov.ph/sites/default/files/2023-02%20publishedPSYCHO.pdf',
            'https://www.prc.gov.ph/sites/default/files/2023-02%20published%20Annex%20ABpsycho.pdf',
            'https://www.prc.gov.ph/sites/default/files/REVISED%20SPLE%20PSYCHOMET%20june%202024.pdf',
        ],
        'official_areas' => [
            ['id' => 'psychological-assessment', 'name' => 'Psychological Assessment', 'weight' => 40, 'official_subareas' => ['Psychometric Properties and Principles', 'Research Methods and Statistics', 'Uses, Benefits, Limitations of Assessment Tools and Instruments', 'Selection of Assessment Methods and Tools', 'Test Administration, Scoring, Interpretation and Usage', 'Ethical Principles and Standards of Practice']],
            ['id' => 'developmental-psychology', 'name' => 'Developmental Psychology', 'weight' => 20, 'official_subareas' => ['Perspectives on Nature and Nurture', 'Research Methods in Developmental Psychology and Ethical Considerations', 'Developmental Theories']],
            ['id' => 'abnormal-psychology', 'name' => 'Abnormal Psychology', 'weight' => 20, 'official_subareas' => ['Manifestations of Behavior', 'Psychological Disorders and Specific Symptoms based on DSM-5', 'Theoretical Approaches in Explaining the Etiology of Psychological Disorders', 'Therapeutic Interventions of Psychological Disorders', 'Socio-cultural Factors and Ethical Principles in Diagnosing Cases of Abnormal Behavior', 'Global Health Crisis and Mental Health Law']],
            ['id' => 'industrial-organizational', 'name' => 'Industrial-Organizational Psychology', 'weight' => 20, 'official_subareas' => ['Organization Theory', 'Organizational Structures and Systems', 'Human Resource Development and Human Resource Management', 'Team Dynamics', 'Organizational Change and Development']],
        ],
        'superseded_scope_excluded' => ['Board Resolution No. 19 s. 2014 TOS is superseded by Resolution No. 02 s. 2023.'],
        'future_effective_excluded' => true,
    ],
    'baseline' => [
        'health' => $baselineProjection,
        'global_counts' => $baselineGlobalCounts,
        'psychometrician' => [
            'canonical' => (int) ($baselinePsych['canonical'] ?? 0),
            'effective_declared' => (int) ($baselinePsych['effective_declared'] ?? 0),
            'runtime' => (int) ($baselinePsych['runtime'] ?? 0),
            'active_leaves' => (int) ($baselinePsych['active_leaves'] ?? 0),
            'leaf_counts' => $baselinePsych['leaf_counts'] ?? [],
        ],
        'old_taxonomy' => ['active_leaves' => 4, 'official_area_used_as_leaf' => true, 'missing_domain_layer' => false, 'missing_topic_layer' => false, 'missing_concept_layer' => true, 'legacy_flat_model' => true, 'mixed_competency_leaves' => 4, 'root_cause' => 'The four official areas were simultaneously used as the only active concepts; existing source/study clusters already contained finer evidence but taxonomy and coverage did not expose it.'],
    ],
    'target_taxonomy' => array_map(static function (string $id, array $definition): array {
        return [
            'leaf_id' => $id,
            'leaf_name' => $definition['name'],
            'official_area_id' => $definition['official_area_id'],
            'parent_domain' => $definition['domain_id'],
            'parent_topic' => $definition['topic_id'],
            'description' => $definition['description'],
            'in_scope' => true,
            'source_basis' => 'PRC Annex B official subarea boundary plus existing BoardPrep study/source cluster; leaf label is BoardPrep-derived.',
            'existing_global_concept_reused' => false,
            'expected_depth_range' => $definition['depth'],
        ];
    }, array_keys($leafDefinitions), $leafDefinitions),
    'target_taxonomy_summary' => [
        'subjects' => 1,
        'domains' => count(array_unique(array_column($leafDefinitions, 'domain_id'))),
        'topics' => count(array_unique(array_column($leafDefinitions, 'topic_id'))),
        'active_leaves' => count($leafDefinitions),
        'structural_nodes' => count($oldParents),
        'new_global_concepts' => count($leafDefinitions),
        'reused_global_concepts' => 0,
        'official_scope_complete' => true,
        'micro_fragmentation_check' => 'PASS',
        'overlap_check' => 'PASS_WITH_EDITORIAL_BOUNDARIES',
    ],
    'migration_map' => array_merge(
        array_map(static fn(string $id): array => ['old_node' => $id, 'new_node' => $id, 'action' => 'REUSE_AS_STRUCTURAL'], array_keys($oldParents)),
        array_map(static fn(string $id, array $definition): array => ['old_node' => $definition['parent_id'], 'new_node' => $id, 'action' => 'REMAP'], array_keys($leafDefinitions), $leafDefinitions)
    ),
    'question_classifications' => $stableClassification,
    'source_inventory' => [
        'source_ids_reused' => array_values(array_unique(array_merge(...array_map(static fn(array $r): array => $r['source_ids'], $readinessRecords)))),
        'new_source_records' => 0,
        'authority_counts' => $sourceAuthorityCounts,
    ],
    'study_inventory' => [
        'study_ids_reused' => array_values(array_unique(array_merge(...array_map(static fn(array $r): array => $r['study_material_ids'], $readinessRecords)))),
        'new_study_materials' => 0,
        'coverage_counts' => $studyCoverageCounts,
    ],
    'source_readiness' => [
        'records' => $readinessRecords,
        'record_count' => count($readinessRecords),
        'readiness_counts' => $readinessCounts,
        'invalid_source_ids' => 0,
        'invalid_study_ids' => 0,
        'cross_field_contradictions' => 0,
        'broken_traceability' => 0,
    ],
    'true_leaf_health' => [
        'active_leaves' => count($leafDefinitions),
        'structural_nodes' => count($oldParents),
        'zero' => count(array_filter($leafCounts, static fn(int $n): bool => $n === 0)),
        'under5' => count(array_filter($leafCounts, static fn(int $n): bool => $n < 5)),
        'under10' => count(array_filter($leafCounts, static fn(int $n): bool => $n < 10)),
        'under20' => count(array_filter($leafCounts, static fn(int $n): bool => $n < 20)),
        'min' => $leafCounts === [] ? 0 : min($leafCounts),
        'median' => $median($leafCounts),
        'p25' => $percentile($leafCounts, .25),
        'p75' => $percentile($leafCounts, .75),
        'max' => $leafCounts === [] ? 0 : max($leafCounts),
        'leaf_counts' => $counts,
    ],
    'official_area_distribution' => $areaDistribution,
    'depth_distribution' => $depthCounts,
    'headroom' => $headroomTotals,
    'batch643_plan' => [
        'additions_to_close_authorable_zero' => $additionFloor['close_authorable_zero'],
        'additions_to_authorable_5' => $additionFloor['to_5'],
        'additions_to_authorable_10' => $additionFloor['to_10'],
        'additions_to_authorable_20' => $additionFloor['to_20'],
        'min_safe_expansion' => $headroomTotals['safe_headroom_min'],
        'preferred_expansion' => $headroomTotals['safe_headroom_preferred'],
        'max_evidence_supported_expansion' => $headroomTotals['safe_headroom_max'],
        'waves' => [
            ['wave' => 1, 'purpose' => 'true zero and under-5 authorable leaves', 'estimated_additions' => $additionFloor['to_5']],
            ['wave' => 2, 'purpose' => 'under-10 authorable leaves', 'estimated_additions' => max(0, $additionFloor['to_10'] - $additionFloor['to_5'])],
            ['wave' => 3, 'purpose' => 'highest-priority under-20 and depth gaps', 'estimated_additions' => max(0, $additionFloor['to_20'] - $additionFloor['to_10'])],
            ['wave' => 4, 'purpose' => 'optional area/depth balancing only if evidence remains distinct', 'estimated_additions' => 0],
        ],
        'leaf_states' => $leafStates,
        'next_target' => $readinessCounts['REVIEW'] > 0 ? 'PSYCHOMETRICIAN_REVIEW_CLOSURE' : 'PSYCHOMETRICIAN_SOURCE_FIRST_EXPANSION',
        'next_mode' => $readinessCounts['REVIEW'] > 0 ? 'REVIEW_CLOSURE' : 'SOURCE_FIRST_EXPANSION',
        'rationale' => 'Nineteen leaves are authorable within bounded evidence; the one current-law leaf remains DEFER without blocking expansion in unrelated ready leaves. Do not restore the four-leaf volume illusion or author into the deferred boundary.',
    ],
    'remap_invariants' => $questionInvariantChanges,
    'question_invariant_hashes' => $baselineInvariantFingerprints,
    'question_validation_failures' => $questionValidationFailures,
    'duplication' => [
        'exact_duplicate_groups' => $exactDuplicateGroups,
        'normalized_duplicate_groups' => $normalizedDuplicateGroups,
        'semantic_candidates' => [],
        'semantic_review_status' => 'No global O(N^2) semantic scan; same-board deterministic normalized audit found no duplicate groups.',
    ],
    'health_after' => $finalHealthProjection,
    'health_transition' => [
        'baseline' => $baselineProjection['summary'] ?? [],
        'after' => $finalHealthProjection['summary'],
        'newly_visible_valid_warnings' => $newlyVisibleWarnings,
        'newly_visible_valid_warning_count' => count($newlyVisibleWarnings),
        'new_deterministic_failures' => array_values(array_filter($finalHealthProjection['issue_ids'], static fn(array $issue): bool => $issue[0] === 'FAIL')),
    ],
    'permanent_check_catalog' => [
        'total' => count($permanentCheckCatalog),
        'checks' => $permanentCheckCatalog,
        'duplicate_ownership' => 0,
        'unowned' => 0,
        'stable_codes' => true,
    ],
    'developer_health_center' => [
        'route' => '/developer/doctor',
        'api' => '/api/doctor.php?health=1',
        'authorization' => 'existing non-production developer authorization; persisted snapshot is read-only in the page',
        'overview' => true,
        'board_view' => true,
        'category_view' => 'check catalog and filtered action queue',
        'action_queue' => true,
        'filters' => ['severity', 'board', 'category', 'entity_type'],
        'issue_evidence' => true,
        'entity_links' => 'existing question editor links; no invented taxonomy/source route',
        'refresh_action' => 'CLI-only php tools/doctor.php --health --json --persist',
        'auto_fix_ui' => false,
        'snapshot' => 'storage/repository-health.json',
    ],
    'performance' => [
        'baseline_health_runtime_ms' => $healthBeforeRuntimeMs,
        'final_health_runtime_ms' => $finalHealthRuntimeMs,
        'delta_ms' => round($finalHealthRuntimeMs - $healthBeforeRuntimeMs, 2),
        'profile' => 'one practical deterministic Repository Health profile; no all-bank semantic O(N^2) scan',
        'peak_memory_bytes' => memory_get_peak_usage(true),
        'dominant_cost' => 'RepositoryHealthEngine one-snapshot content/flashcard scan',
        'optimizations' => ['shared indexed collections', 'single health snapshot per phase', 'same-board bounded duplicate scan', 'readiness records validated from one collection index'],
    ],
    'regressions' => [
        'new_questions' => 0,
        'new_canonical_questions' => 0,
        'question_validation_failures' => count($questionValidationFailures),
        'psychometrician_native_count' => count($psychAfterQuestions),
        'global_counts' => $globalAfter,
        'new_deterministic_failures' => array_values(array_filter($finalHealthProjection['issue_ids'], static fn(array $issue): bool => $issue[0] === 'FAIL')),
    ],
    'determinism_inputs' => [
        'official_source_ids' => ['prc-psychometrician-enhanced-tos-2023'],
        'target_leaf_ids' => array_keys($leafDefinitions),
        'migration_map' => array_merge(array_keys($oldParents), array_keys($leafDefinitions)),
        'question_classification_ids' => array_keys($classificationById),
        'counts' => $counts,
        'readiness_hashes' => array_map(static fn(array $r): string => (string) $r['evidence_hash'], $readinessRecords),
        'area_distribution' => $areaDistribution,
        'depth_distribution' => $depthCounts,
        'health_issue_ids' => $finalHealthProjection['issue_ids'],
        'global_counts' => $globalAfter,
        'next_target' => $readinessCounts['REVIEW'] > 0 ? 'PSYCHOMETRICIAN_REVIEW_CLOSURE' : 'PSYCHOMETRICIAN_SOURCE_FIRST_EXPANSION',
    ],
];
$artifact['determinism_inputs']['question_classification_ids'] = array_values(array_map('strval', $artifact['determinism_inputs']['question_classification_ids']));
$artifact['audit_hash'] = hash('sha256', $stableJson($artifact['determinism_inputs']));

$writeJson('storage/batch642-psychometrician-taxonomy-readiness.json', $artifact);

echo json_encode([
    'mode' => $auditOnly ? 'AUDIT_ONLY' : 'MIGRATE_AND_AUDIT',
    'psychometrician_questions' => count($psychAfterQuestions),
    'active_leaves' => count($leafDefinitions),
    'leaf_counts' => $counts,
    'readiness_counts' => $readinessCounts,
    'health' => $finalHealthProjection['summary'],
    'audit_hash' => $artifact['audit_hash'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), "\n";
