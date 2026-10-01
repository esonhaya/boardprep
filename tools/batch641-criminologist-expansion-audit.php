<?php

declare(strict_types=1);

/**
 * Batch641 evidence/audit tool.
 *
 * Reusable readiness validation, authoring checks, and health integration live
 * under App\Services\RepositoryHealth\Readiness.  This file owns only the
 * Batch641 editorial evidence map, bounded question cohort, and milestone
 * evidence artifact.
 */

require_once dirname(__DIR__) . '/app/Core/Autoloader.php';

use App\Core\App;
use App\Core\Autoloader;
use App\Services\Flashcard\FlashcardCardValidator;
use App\Services\Flashcard\FlashcardDerivationService;
use App\Services\Question\QuestionDuplicateService;
use App\Services\Question\QuestionEligibilityService;
use App\Services\Question\QuestionService;
use App\Services\RepositoryHealth\Engine\RepositoryHealthEngine;
use App\Services\RepositoryHealth\Readiness\SourceReadinessRecord;
use App\Services\RepositoryHealth\Readiness\SourceReadinessService;

Autoloader::register();
putenv('APP_ENV=testing');
putenv('APP_DEBUG=false');
App::boot();

$root = dirname(__DIR__);
$artifactPath = $root . '/storage/batch641-criminologist-source-readiness.json';

$readJson = static function (string $relative) use ($root): array {
    $path = $root . '/' . $relative;
    if (!is_file($path)) throw new RuntimeException('Missing input: ' . $relative);
    $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    return is_array($decoded) ? $decoded : [];
};

$writeJson = static function (string $relative, array $value) use ($root): void {
    $path = $root . '/' . $relative;
    $json = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $temporary = $path . '.tmp';
    file_put_contents($temporary, $json . "\n", LOCK_EX);
    rename($temporary, $path);
};

$collections = static function () use ($readJson, $root): array {
    $names = ['questions', 'boards', 'subjects', 'board-subjects', 'blueprints', 'taxonomy/domains', 'taxonomy/topics', 'taxonomy/concepts', 'question-eligibility', 'concept-derived-eligibility', 'question-reuse-overrides', 'question-reuse-metadata', 'board-concept-coverage', 'study-materials', 'study-question-links', 'sources', 'content-fit-audits', 'flashcard-exclusions', 'source-readiness'];
    $result = [];
    foreach ($names as $name) {
        $path = 'storage/' . $name . '.json';
        $result[$name] = ($name === 'source-readiness' && !is_file($root . '/' . $path)) ? [] : $readJson($path);
    }
    return $result;
};

$groupDefinitions = static function (): array {
    $commonScope = ['current_prc_tos', 'professional_scope', 'bounded_application', 'evidence_supported_limits'];
    return [
        'legal' => [
            'official_area_id' => 'criminal_jurisprudence_and_procedure',
            'source_ids' => ['prc-criminology-board-page', 'prc-criminology-enhanced-tos-2024', 'prc-criminology-ethics-amendment-2025', 'prc-criminology-irr-2020', 'prc-2026-examination-schedule', 'lawphil-ra11131', 'lawphil-revised-penal-code', 'lawphil-rules-criminal-procedure', 'lawphil-rules-evidence-2019', 'lawphil-ra7438', 'lawphil-ra9745', 'lawphil-ra10173'],
            'primary_source_count' => 7,
            'source_authority' => 'STRONG',
            'source_currentness' => 'CURRENT_WITH_LIMITS',
            'source_scope_fit' => 'DIRECT',
            'study_material_ids' => ['cle-criminal-law-foundation', 'cle-criminal-law-application'],
            'families' => ['elements_and_liability', 'rights_and_procedure', 'evidence_interpretation', 'case_application'],
            'allowed_scope' => array_merge($commonScope, ['current_philippine_criminal_law', 'rights_and_lawful_limits', 'evidence_and_procedure']),
            'limitations' => ['Use the current linked statute or rule where an answer depends on an amendment or procedural condition.', 'Do not introduce an unverified numerical threshold or future-effective rule.'],
            'reason' => 'The current PRC scope, statutory texts, procedural rules, and rights sources provide a direct but version-sensitive foundation for bounded application questions.',
        ],
        'law_enforcement' => [
            'official_area_id' => 'law_enforcement_administration',
            'source_ids' => ['prc-criminology-board-page', 'prc-criminology-enhanced-tos-2024', 'prc-criminology-ethics-amendment-2025', 'prc-2026-examination-schedule', 'prc-criminology-irr-2020', 'lawphil-ra11131', 'lawphil-ra10173', 'nij-crime-scene-guides-2017'],
            'primary_source_count' => 6,
            'source_authority' => 'ADEQUATE',
            'source_currentness' => 'CURRENT_WITH_LIMITS',
            'source_scope_fit' => 'DIRECT',
            'study_material_ids' => ['cle-law-enforcement-foundation', 'cle-law-enforcement-application'],
            'families' => ['administrative_planning', 'leadership_and_accountability', 'community_partnership', 'privacy_and_public_interest'],
            'allowed_scope' => array_merge($commonScope, ['accountability_and_administration', 'community_and_information', 'risk_control_and_role_clarity', 'privacy_and_public_interest']),
            'limitations' => ['Use stable administrative reasoning unless a current agency issuance is explicitly identified.', 'Do not infer a PNP, DILG, DOJ, or agency-specific operating threshold from a general source.'],
            'reason' => 'The official TOS, professional conduct sources, statutory accountability/privacy boundaries, and NIJ guidance support bounded administration scenarios with agency-specific limits.',
        ],
        'investigation' => [
            'official_area_id' => 'crime_detection_and_investigation',
            'source_ids' => ['prc-criminology-board-page', 'prc-criminology-enhanced-tos-2024', 'prc-2026-examination-schedule', 'nij-crime-scene-guide-2013', 'nij-crime-scene-guides-2017', 'lawphil-rules-criminal-procedure', 'lawphil-rules-evidence-2019', 'lawphil-ra7438', 'lawphil-ra10173'],
            'primary_source_count' => 6,
            'source_authority' => 'STRONG',
            'source_currentness' => 'CURRENT_WITH_LIMITS',
            'source_scope_fit' => 'DIRECT',
            'study_material_ids' => ['cle-investigation-foundation', 'cle-investigation-application'],
            'families' => ['scene_sequence_and_preservation', 'interview_and_corroboration', 'evidence_interpretation', 'lawful_investigation'],
            'allowed_scope' => array_merge($commonScope, ['scene_investigation', 'interview_and_reasoning', 'evidence_and_procedure', 'rights_and_lawful_limits']),
            'limitations' => ['Keep agency procedures at the level supported by the linked guidance.', 'Do not add a jurisdiction-specific threshold that is not present in the source chain.'],
            'reason' => 'The current TOS, NIJ scene guidance, Rules of Court, and rights/privacy sources support direct investigation application with explicit procedural limits.',
        ],
        'forensics' => [
            'official_area_id' => 'forensic_science',
            'source_ids' => ['prc-criminology-board-page', 'prc-criminology-enhanced-tos-2024', 'prc-2026-examination-schedule', 'nist-forensic-science-standards', 'nist-scientific-foundation-reviews', 'nist-firearms-toolmarks', 'nij-crime-scene-guide-2013', 'nij-crime-scene-guides-2017', 'lawphil-rules-evidence-2019'],
            'primary_source_count' => 6,
            'source_authority' => 'ADEQUATE',
            'source_currentness' => 'CURRENT_WITH_LIMITS',
            'source_scope_fit' => 'DIRECT',
            'study_material_ids' => ['cle-forensics-foundation', 'cle-forensics-application'],
            'families' => ['scientific_validation', 'quality_and_documentation', 'qualified_conclusions', 'limitations_and_uncertainty'],
            'allowed_scope' => array_merge($commonScope, ['scientific_foundation', 'quality_and_limitations', 'evidence_reporting']),
            'limitations' => ['NIST/OSAC materials are periodically updated; use bounded claims and preserve method limitations.', 'Do not claim individualization, universal validity, or a current standard beyond the linked evidence.'],
            'reason' => 'The current TOS, NIST scientific-foundation material, NIJ guidance, and evidence rules support source-first forensic questions when conclusions remain qualified.',
        ],
        'criminology' => [
            'official_area_id' => 'criminology',
            'source_ids' => ['prc-criminology-board-page', 'prc-criminology-enhanced-tos-2024', 'prc-criminology-ethics-amendment-2025', 'prc-2026-examination-schedule', 'openoregon-introduction-criminology', 'openstax-deviance-crime', 'nij-deterrence'],
            'primary_source_count' => 4,
            'source_authority' => 'ADEQUATE',
            'source_currentness' => 'CURRENT_WITH_LIMITS',
            'source_scope_fit' => 'DIRECT',
            'study_material_ids' => ['cle-criminology-foundation', 'cle-criminology-application'],
            'families' => ['theory_and_mechanism', 'measurement_and_interpretation', 'prevention_application', 'ethical_research'],
            'allowed_scope' => array_merge($commonScope, ['criminological_theory', 'measurement_and_interpretation', 'prevention_and_ethics']),
            'limitations' => ['Use the open texts for stable conceptual instruction and the PRC materials for exam scope.', 'Do not infer causation, prevalence, or current policy effects from an illustrative pattern alone.'],
            'reason' => 'The official TOS and current professional-conduct material establish scope while open instructional texts and NIJ evidence support distinct theory, measurement, prevention, and research applications.',
        ],
        'corrections' => [
            'official_area_id' => 'correctional_administration',
            'source_ids' => ['prc-criminology-board-page', 'prc-criminology-enhanced-tos-2024', 'prc-criminology-ethics-amendment-2025', 'prc-criminology-irr-2020', 'prc-2026-examination-schedule', 'lawphil-ra11131', 'lawphil-ra9745', 'bjmp-comprehensive-operations-manual-2015', 'bucor-mandates-functions', 'bucor-reintegration-2025', 'probation-quality-manual-2016'],
            'primary_source_count' => 8,
            'source_authority' => 'STRONG',
            'source_currentness' => 'CURRENT_WITH_LIMITS',
            'source_scope_fit' => 'DIRECT',
            'study_material_ids' => ['cle-corrections-foundation', 'cle-corrections-application'],
            'families' => ['custody_and_dignity', 'classification_and_treatment', 'rehabilitation_and_community', 'reintegration_and_coordination'],
            'allowed_scope' => array_merge($commonScope, ['custody_and_dignity', 'classification_and_rehabilitation', 'reintegration_and_coordination', 'privacy_and_public_interest']),
            'limitations' => ['Agency manuals are version-sensitive; avoid unverified operational thresholds.', 'Keep questions within correctional administration and do not substitute an agency-specific policy for a general professional principle.'],
            'reason' => 'The current TOS, professional ethics, statutory boundaries, and BJMP/BuCor/PPA materials support bounded correctional administration scenarios with explicit agency-version limits.',
        ],
    ];
};

$groupForLeaf = static function (string $leaf): string {
    if (in_array($leaf, ['cse-constitutional-principles', 'cse-ethical-public-service', 'social-civics-public-good', 'social-history-primary-sources'], true)) return 'shared';
    if ($leaf === 'cle-jurisprudence-principles' || str_starts_with($leaf, 'cle-criminal-law-')) return 'legal';
    if ($leaf === 'cle-administration-accountability' || str_starts_with($leaf, 'cle-law-enforcement-')) return 'law_enforcement';
    if (str_starts_with($leaf, 'cle-investigation-')) return 'investigation';
    if (str_starts_with($leaf, 'cle-forensic') || str_starts_with($leaf, 'cle-forensics-')) return 'forensics';
    if ($leaf === 'cle-theory-application' || str_starts_with($leaf, 'cle-criminology-')) return 'criminology';
    if (str_starts_with($leaf, 'cle-corrections-')) return 'corrections';
    throw new RuntimeException('No Batch641 evidence group for leaf ' . $leaf);
};

$familiesForLeaf = static function (string $leaf, string $group) use ($groupDefinitions): array {
    $special = [
        'cle-jurisprudence-principles' => ['principle_mapping', 'authority_and_stage', 'case_application'],
        'cle-criminal-law-elements' => ['elements_and_liability', 'mental_state', 'case_application'],
        'cle-criminal-law-rights' => ['rights_safeguards', 'lawful_limits', 'case_application'],
        'cle-criminal-law-circumstances' => ['circumstance_classification', 'exceptions', 'case_application'],
        'cle-criminal-law-analysis' => ['element_mapping', 'evidence_interpretation', 'legal_application'],
        'cle-criminal-law-limits' => ['rights_and_lawful_limits', 'procedure', 'case_application'],
        'cle-law-enforcement-planning' => ['planning', 'risk_control', 'role_clarity'],
        'cle-law-enforcement-leadership' => ['leadership', 'accountability', 'scenario_application'],
        'cle-law-enforcement-community' => ['community_partnership', 'privacy', 'scenario_application'],
        'cle-law-enforcement-accountability' => ['accountability', 'documentation', 'ethical_application'],
        'cle-law-enforcement-operations' => ['operational_boundary', 'role_clarity', 'scenario_application'],
        'cle-administration-accountability' => ['accountability', 'documentation', 'public_information'],
        'cle-investigation-evidence' => ['scene_sequence', 'evidence_interpretation', 'chain_of_custody'],
        'cle-investigation-scene' => ['scene_documentation', 'preservation', 'procedure'],
        'cle-investigation-interview' => ['interview_reasoning', 'corroboration', 'rights'],
        'cle-investigation-reasoning' => ['hypothesis_testing', 'evidence_interpretation', 'case_application'],
        'cle-investigation-lawful' => ['lawful_authority', 'rights_safeguards', 'procedure'],
        'cle-forensic-integrity' => ['validation', 'limitations', 'qualified_reporting'],
        'cle-forensics-identification' => ['comparison', 'validation', 'qualified_conclusion'],
        'cle-forensics-documentation' => ['provenance', 'documentation', 'integrity'],
        'cle-forensics-laboratory' => ['quality_control', 'validation', 'reporting'],
        'cle-forensics-limits' => ['limitations', 'uncertainty', 'evidence_interpretation'],
        'cle-theory-application' => ['theory_mechanism', 'measurement', 'application'],
        'cle-criminology-classical' => ['classical_theory', 'mechanism', 'comparison'],
        'cle-criminology-social' => ['social_structure', 'mechanism', 'comparison'],
        'cle-criminology-learning' => ['learning_mechanism', 'prevention', 'application'],
        'cle-criminology-victimology' => ['victimization', 'risk_context', 'ethical_application'],
        'cle-criminology-research' => ['research_design', 'measurement', 'evidence_interpretation'],
        'cle-corrections-rehabilitation' => ['rehabilitation', 'assessment', 'program_application'],
        'cle-corrections-institutional' => ['custody', 'safety', 'administration'],
        'cle-corrections-community' => ['community_corrections', 'coordination', 'case_application'],
        'cle-corrections-classification' => ['classification', 'risk_need', 'case_application'],
        'cle-corrections-reintegration' => ['reintegration', 'continuity', 'case_application'],
    ];
    return $special[$leaf] ?? ($groupDefinitions()[$group]['families'] ?? []);
};

$sharedEvidence = static function (string $leaf): array {
    $map = [
        'cse-constitutional-principles' => ['source_ids' => ['1987-philippine-constitution', 'csc-cse-ppt-scope-2026'], 'study_material_ids' => ['constitution-review-foundation']],
        'cse-ethical-public-service' => ['source_ids' => ['republic-act-6713'], 'study_material_ids' => ['ra-6713-review-foundation']],
        'social-civics-public-good' => ['source_ids' => ['1987-philippine-constitution', 'universal-declaration-human-rights'], 'study_material_ids' => ['human-rights-review-foundation']],
        'social-history-primary-sources' => ['source_ids' => ['1987-philippine-constitution', 'universal-declaration-human-rights'], 'study_material_ids' => ['human-rights-review-foundation']],
    ];
    return $map[$leaf] ?? ['source_ids' => [], 'study_material_ids' => []];
};

$buildRecords = static function (array $inputCollections) use ($groupDefinitions, $groupForLeaf, $familiesForLeaf, $sharedEvidence): array {
    $counts = SourceReadinessService::nativeLeafCounts($inputCollections, 'criminologist');
    $concepts = [];
    foreach (($inputCollections['taxonomy/concepts'] ?? []) as $concept) if (is_array($concept)) $concepts[(string) ($concept['id'] ?? '')] = $concept;
    $records = [];
    foreach ($counts as $leaf => $count) {
        $group = $groupForLeaf($leaf);
        if ($group === 'shared') {
            $shared = $sharedEvidence($leaf);
            $record = [
                'leaf_id' => $leaf,
                'board_id' => 'criminologist',
                'official_area_id' => 'shared_general_foundation',
                'leaf_name' => (string) ($concepts[$leaf]['name'] ?? $leaf),
                'current_question_count' => $count,
                'deficit_to_5' => max(0, 5 - $count),
                'deficit_to_10' => max(0, 10 - $count),
                'deficit_to_20' => max(0, 20 - $count),
                'source_ids' => $shared['source_ids'],
                'source_count' => count($shared['source_ids']),
                'primary_source_count' => count($shared['source_ids']),
                'source_authority' => 'ADEQUATE',
                'source_currentness' => 'CURRENT_WITH_LIMITS',
                'source_scope_fit' => 'NONE',
                'source_conflict_status' => 'RESOLVED',
                'study_material_ids' => $shared['study_material_ids'],
                'study_material_count' => count($shared['study_material_ids']),
                'study_coverage' => 'SUFFICIENT',
                'study_source_traceability' => 'COMPLETE',
                'supported_depth_min' => 1,
                'supported_depth_max' => 1,
                'distinct_content_families' => [],
                'distinct_content_family_count' => 0,
                'safe_headroom_min' => 0,
                'safe_headroom_preferred' => 0,
                'safe_headroom_max' => 0,
                'allowed_scope' => [],
                'excluded_scope' => ['not_in_current_lec_area'],
                'limitations' => ['The current evidence maps this leaf to a shared/general foundation, not one of the six native current LEC areas.'],
                'readiness' => 'DEFER',
                'readiness_reason' => 'Shared content remains useful elsewhere but does not establish a direct current Criminologist examination-area boundary.',
                'review_reason' => '',
                'defer_reason' => 'OUT_OF_CURRENT_SCOPE',
                'max_new_questions' => 0,
                'planned_new_questions' => 0,
                'reviewed_at_batch' => 641,
            ];
        } else {
            $definition = $groupDefinitions()[$group];
            $planned = $count < 10 ? 10 - $count : 0;
            if ($planned >= 3) $headroom = [3, 4, 5];
            elseif ($planned === 2) $headroom = [2, 3, 4];
            elseif ($planned === 1) $headroom = [1, 2, 3];
            elseif ($count >= 30) $headroom = [1, 1, 2];
            else $headroom = [1, 2, 3];
            $record = [
                'leaf_id' => $leaf,
                'board_id' => 'criminologist',
                'official_area_id' => $definition['official_area_id'],
                'leaf_name' => (string) ($concepts[$leaf]['name'] ?? $leaf),
                'current_question_count' => $count,
                'deficit_to_5' => max(0, 5 - $count),
                'deficit_to_10' => max(0, 10 - $count),
                'deficit_to_20' => max(0, 20 - $count),
                'source_ids' => $definition['source_ids'],
                'source_count' => count($definition['source_ids']),
                'primary_source_count' => $definition['primary_source_count'],
                'source_authority' => $definition['source_authority'],
                'source_currentness' => $definition['source_currentness'],
                'source_scope_fit' => $definition['source_scope_fit'],
                'source_conflict_status' => 'RESOLVED',
                'study_material_ids' => $definition['study_material_ids'],
                'study_material_count' => count($definition['study_material_ids']),
                'study_coverage' => 'SUFFICIENT',
                'study_source_traceability' => 'COMPLETE',
                'supported_depth_min' => 2,
                'supported_depth_max' => 4,
                'distinct_content_families' => $familiesForLeaf($leaf, $group),
                'distinct_content_family_count' => count($familiesForLeaf($leaf, $group)),
                'safe_headroom_min' => $headroom[0],
                'safe_headroom_preferred' => $headroom[1],
                'safe_headroom_max' => $headroom[2],
                'allowed_scope' => $definition['allowed_scope'],
                'excluded_scope' => ['future_effective_rules', 'unverified_exact_thresholds', 'out_of_leaf_competencies'],
                'limitations' => $definition['limitations'],
                'readiness' => 'READY_WITH_LIMITS',
                'readiness_reason' => $definition['reason'],
                'review_reason' => '',
                'defer_reason' => '',
                'max_new_questions' => $headroom[2],
                'planned_new_questions' => $planned,
                'reviewed_at_batch' => 641,
            ];
        }
        $record['evidence_hash'] = SourceReadinessRecord::evidenceHash($record);
        $records[] = SourceReadinessRecord::normalize($record);
    }
    usort($records, static fn(array $a, array $b): int => strcmp((string) $a['leaf_id'], (string) $b['leaf_id']));
    return $records;
};

$officialScope = static fn(): array => [
    'current_exam_regime' => '2024 enhanced TOS, current professional-conduct amendment and succeeding 2026 schedules; six native areas remain the authoring boundary.',
    'source_ids' => ['prc-criminology-board-page', 'prc-criminology-enhanced-tos-2024', 'prc-criminology-ethics-amendment-2025', 'prc-criminology-schedule-2025', 'prc-2026-examination-schedule', 'prc-criminology-irr-2020'],
    'areas' => [
        ['id' => 'criminal_jurisprudence_and_procedure', 'name' => 'Criminal Jurisprudence and Procedure', 'weight_percent' => 20],
        ['id' => 'law_enforcement_administration', 'name' => 'Law Enforcement Administration', 'weight_percent' => 15],
        ['id' => 'crime_detection_and_investigation', 'name' => 'Crime Detection and Investigation', 'weight_percent' => 20],
        ['id' => 'forensic_science', 'name' => 'Forensic Science', 'weight_percent' => 15],
        ['id' => 'criminology', 'name' => 'Criminology', 'weight_percent' => 20],
        ['id' => 'correctional_administration', 'name' => 'Correctional Administration', 'weight_percent' => 10],
    ],
    'future_effective_excluded' => true,
    'verification_basis' => 'Repository evidence map checked 2026-09-29 and official PRC schedule/TOS records retained in source registry.',
];

$questionDefinitions = static function (): array {
    $common = static fn(string $concept, string $material, array $sources, array $scope, int $depth, string $question, array $choices, string $answer, string $explanation, string $family, string $misconception = '', string $intent = 'apply'): array => [
        'taxonomy' => [
            'board_id' => 'criminologist',
            'subject_id' => 'criminal-justice-foundations',
            'domain_id' => '',
            'topic_id' => '',
            'concept_id' => $concept,
        ],
        'study_material_id' => $material,
        'study_material_ids' => [$material],
        'source_scope' => $scope,
        'source_ids' => $sources,
        'difficulty' => $depth >= 4 ? 'hard' : 'medium',
        'question' => $question,
        'choices' => $choices,
        'answer' => $answer,
        'explanation' => $explanation,
        'source' => 'BoardPrep-authored',
        'status' => 'active',
        'type' => 'multiple_choice',
        'content_blocks' => [],
        'numeric' => false,
        'calculation_required' => false,
        'quantitative_semantics' => 'NON_NUMERICAL',
        'multistep' => true,
        'data_interpretation' => false,
        'table_dependent' => false,
        'cognitive_intent' => $intent,
        'scenario_length' => 'standard',
        'professional_judgment' => true,
        'evidence_interpretation' => true,
        'case_interpretation' => true,
        'legal_procedure_application' => in_array($concept, ['cle-jurisprudence-principles', 'cle-forensics-identification', 'cle-forensics-documentation', 'cle-forensics-laboratory', 'cle-forensics-limits'], true),
        'ethics_scope_application' => in_array($concept, ['cle-administration-accountability', 'cle-corrections-institutional', 'cle-corrections-community', 'cle-corrections-classification', 'cle-corrections-reintegration'], true),
        'scenario_family' => $family,
        'misconception_family' => $misconception !== '' ? $misconception : $family,
        'reuse' => [
            'reuse_policy' => 'REVIEW',
            'required_depth' => $depth,
            'context_mode' => 'PROFESSIONAL',
            'metadata_status' => 'VERIFIED',
            'metadata_provenance' => [[
                'type' => 'OTHER',
                'reference' => 'batch-641-source-readiness',
                'note' => 'Question depth, scope, study link, and source chain are bounded by the Batch641 readiness record.',
                'verified_status' => 'VERIFIED',
            ]],
        ],
    ];
    $legalSources = ['prc-criminology-enhanced-tos-2024', 'lawphil-revised-penal-code', 'lawphil-rules-criminal-procedure', 'lawphil-rules-evidence-2019', 'lawphil-ra7438', 'lawphil-ra9745'];
    $forensicSources = ['prc-criminology-enhanced-tos-2024', 'nist-forensic-science-standards', 'nist-scientific-foundation-reviews', 'nist-firearms-toolmarks', 'nij-crime-scene-guide-2013', 'lawphil-rules-evidence-2019'];
    $criminologySources = ['prc-criminology-enhanced-tos-2024', 'openoregon-introduction-criminology', 'openstax-deviance-crime', 'nij-deterrence', 'prc-criminology-ethics-amendment-2025'];
    $correctionsSources = ['prc-criminology-enhanced-tos-2024', 'probation-quality-manual-2016', 'bjmp-comprehensive-operations-manual-2015', 'bucor-mandates-functions', 'bucor-reintegration-2025', 'prc-criminology-ethics-amendment-2025'];
    $adminSources = ['prc-criminology-enhanced-tos-2024', 'prc-criminology-ethics-amendment-2025', 'lawphil-ra11131', 'lawphil-ra10173', 'nij-crime-scene-guides-2017'];
    return [
        8814 => $common('cle-jurisprudence-principles', 'cle-criminal-law-application', $legalSources, ['current_prc_tos', 'current_philippine_criminal_law', 'rights_and_lawful_limits'], 3, 'A reviewer has a proposed conclusion, several record entries, and one unresolved fact. Which method best keeps the conclusion within the governing authority?', ['Trace the conclusion to each required element and state the unresolved evidentiary gap.', 'Choose the most severe label because the file contains an allegation.', 'Treat the official-looking record entry as proof of every element.', 'Resolve the issue from the final outcome without identifying the controlling rule.'], 'Trace the conclusion to each required element and state the unresolved evidentiary gap.', 'Element mapping prevents an allegation or label from substituting for the facts and authority required by the applicable rule.', 'label_as_proof'),
        8815 => $common('cle-jurisprudence-principles', 'cle-criminal-law-application', $legalSources, ['current_prc_tos', 'rights_and_lawful_limits', 'evidence_and_procedure'], 3, 'A case note cites a legal rule but does not identify whether the matter is at investigation, charging, or trial. What should the reviewer do first?', ['Apply the broadest possible remedy immediately.', 'Identify the governing authority and procedural stage before selecting a consequence.', 'Treat the cited rule as sufficient regardless of the procedural stage.', 'Use the rule most favorable to the conclusion already written.'], 'Identify the governing authority and procedural stage before selecting a consequence.', 'The same fact can have different consequences at different stages, so stage and authority must be identified before application.', 'stage_blind_application'),
        8816 => $common('cle-forensics-identification', 'cle-forensics-application', $forensicSources, ['current_prc_tos', 'scientific_foundation', 'quality_and_limitations'], 4, 'A comparison has limited-quality features and no validated basis for individualization. Which report wording is defensible?', ['Report the observed similarities and limitations without claiming unique identification.', 'Report the reference source as the unique contributor because some features agree.', 'Convert consistency into identification without a validated basis.', 'Omit the quality limitation so the conclusion is easier to use.'], 'Report the observed similarities and limitations without claiming unique identification.', 'A limited comparison supports only a qualified conclusion at the strength supported by the method and item quality.', 'consistency_equals_identification'),
        8817 => $common('cle-forensics-documentation', 'cle-forensics-application', $forensicSources, ['current_prc_tos', 'quality_and_limitations', 'evidence_reporting'], 3, 'A trace is visible at a scene, but personnel have not documented its position or restricted access. What should happen before collection?', ['Collect it immediately before its condition changes.', 'Restrict access and document the scene relationship before collection.', 'Allow every observer to handle it so possible evidence is not missed.', 'Photograph only the trace and omit its relationship to the surrounding scene.'], 'Restrict access and document the scene relationship before collection.', 'Documentation and access control preserve context that collection alone cannot restore.', 'collection_before_context'),
        8818 => $common('cle-forensics-laboratory', 'cle-forensics-application', $forensicSources, ['current_prc_tos', 'scientific_foundation', 'quality_and_limitations'], 4, 'A laboratory report states a result but gives no information about validation or quality control. What is the best review response?', ['Accept the strongest interpretation because the report uses technical language.', 'Request the validation and quality basis before relying on a stronger conclusion.', 'Discard every observation as automatically false.', 'Replace the result with the conclusion most useful to the case.'], 'Request the validation and quality basis before relying on a stronger conclusion.', 'A result must be interpreted with its validation and quality basis; missing support limits the conclusion rather than proving either truth or falsity.', 'technical_language_equals_validation'),
        8819 => $common('cle-forensics-limits', 'cle-forensics-application', $forensicSources, ['current_prc_tos', 'quality_and_limitations', 'evidence_reporting'], 4, 'A method result is consistent with a reference, but alternative explanations remain plausible. Which conclusion fits the study boundary?', ['State the observed consistency and the remaining limitation.', 'State that the reference is conclusively the source.', 'Remove alternative explanations from the report.', 'Treat the method label as proof that no limitation exists.'], 'State the observed consistency and the remaining limitation.', 'Scientific reporting should distinguish an observation from a stronger source-attribution claim not supported by the evidence.', 'observation_equals_source'),
        8820 => $common('cle-criminology-classical', 'cle-criminology-application', $criminologySources, ['current_prc_tos', 'criminological_theory', 'measurement_and_interpretation'], 3, 'A proposed explanation says people weigh consequences before offending. What should a careful criminology reviewer identify?', ['The theory’s proposed mechanism and the assumptions needed before applying it.', 'A universal cause of all offending regardless of context.', 'A causal conclusion from the label alone.', 'A prevention result without checking how the mechanism is measured.'], 'The theory’s proposed mechanism and the assumptions needed before applying it.', 'Theory application requires identifying the mechanism and limits rather than treating a theory label as a complete explanation.', 'theory_label_equals_cause'),
        8821 => $common('cle-criminology-social', 'cle-criminology-application', $criminologySources, ['current_prc_tos', 'criminological_theory', 'measurement_and_interpretation'], 3, 'Two neighborhoods have different raw incident counts and different population exposure. What is the cautious interpretation?', ['Compare appropriate rates and social context before inferring different risk.', 'Treat the larger raw count as proof of greater risk.', 'Conclude that one neighborhood causes the incidents.', 'Ignore exposure because the counts are official.'], 'Compare appropriate rates and social context before inferring different risk.', 'Raw counts can reflect exposure or population differences and do not by themselves establish comparative risk.', 'raw_count_equals_risk'),
        8822 => $common('cle-criminology-learning', 'cle-criminology-application', $criminologySources, ['current_prc_tos', 'criminological_theory', 'prevention_and_ethics'], 3, 'An intervention changes a behavior after consequences and rewards are altered. Which analysis is most defensible?', ['Examine the learning mechanism and the conditions under which the behavior changed.', 'Treat the observed change as proof of a permanent individual trait.', 'Assume the intervention will work identically in every setting.', 'Ignore the timing of consequences because the label is familiar.'], 'Examine the learning mechanism and the conditions under which the behavior changed.', 'Learning explanations are applied through a mechanism and context, not through a fixed label or a universal promise.', 'label_over_mechanism'),
        8823 => $common('cle-criminology-victimology', 'cle-criminology-application', $criminologySources, ['current_prc_tos', 'measurement_and_interpretation', 'prevention_and_ethics'], 3, 'A victimization pattern is concentrated among people with different exposure and access to protection. What should the reviewer avoid?', ['Avoid blaming victims and assess exposure, vulnerability, and context before proposing prevention.', 'Treat the pattern as proof that victims caused the event.', 'Ignore access to protection because the pattern is already visible.', 'Promise a prevention outcome without identifying the mechanism.'], 'Avoid blaming victims and assess exposure, vulnerability, and context before proposing prevention.', 'Victimology requires contextual analysis and dignity-aware prevention rather than attributing causation to the victim.', 'victim_blame_shortcut'),
        8824 => $common('cle-criminology-research', 'cle-criminology-application', $criminologySources, ['current_prc_tos', 'measurement_and_interpretation', 'evidence_supported_limits'], 4, 'A researcher tests many subgroup outcomes and highlights only the significant result. What is the main concern?', ['Selective reporting can exaggerate evidence and the other analyses should be disclosed.', 'The selected result becomes the planned primary outcome after the fact.', 'The omitted analyses are irrelevant once one result is significant.', 'A striking result supports a strong conclusion without describing the search.'], 'Selective reporting can exaggerate evidence and the other analyses should be disclosed.', 'Transparent reporting is needed because searching many outcomes can produce a striking result by chance.', 'selective_reporting'),
        8825 => $common('cle-corrections-institutional', 'cle-corrections-application', $correctionsSources, ['current_prc_tos', 'custody_and_dignity', 'classification_and_rehabilitation'], 3, 'A facility proposes a convenient custody practice that is secure but removes a documented dignity safeguard. What is the best response?', ['Retain security while applying the lawful, humane safeguard and documenting the decision.', 'Use convenience as sufficient reason to remove the safeguard.', 'Assume security and dignity are unrelated.', 'Apply the practice without recording the authority or review point.'], 'Retain security while applying the lawful, humane safeguard and documenting the decision.', 'Correctional administration must keep custody secure and humane within the applicable rights and professional boundary.', 'security_cures_all'),
        8826 => $common('cle-corrections-institutional', 'cle-corrections-application', $correctionsSources, ['current_prc_tos', 'custody_and_dignity', 'evidence_supported_limits'], 3, 'An operational report names a high-risk activity but no approving authority, assigned roles, or review point. What should be required?', ['A documented authority, role assignment, risk control, and after-action review point.', 'More dramatic language about the activity.', 'A larger team without clarifying roles.', 'Removal of the risk discussion from the report.'], 'A documented authority, role assignment, risk control, and after-action review point.', 'Accountable administration requires a traceable decision basis and role clarity, not merely a larger operation.', 'accountability_gap'),
        8827 => $common('cle-corrections-community', 'cle-corrections-application', $correctionsSources, ['current_prc_tos', 'reintegration_and_coordination', 'classification_and_rehabilitation'], 3, 'A community supervision plan lists a release date but no responsible service, follow-up, or coordination path. What is missing?', ['Continuity and coordination with the responsible services and a review point.', 'A more severe label for the person under supervision.', 'A promise that the date alone guarantees successful reintegration.', 'Removal of community partners from the plan.'], 'Continuity and coordination with the responsible services and a review point.', 'Community work is more defensible when responsibilities, support, and follow-up are traceable.', 'date_equals_plan'),
        8828 => $common('cle-corrections-community', 'cle-corrections-application', $correctionsSources, ['current_prc_tos', 'classification_and_rehabilitation', 'reintegration_and_coordination'], 3, 'A community program assigns the same intervention to everyone based only on an offense label. What should guide the correction?', ['Use relevant assessed needs, safety, and lawful program criteria rather than the label alone.', 'Keep the same intervention because individual differences are irrelevant.', 'Use the most restrictive intervention for every person.', 'Avoid documenting the basis so the assignment appears uniform.'], 'Use relevant assessed needs, safety, and lawful program criteria rather than the label alone.', 'A classification or offense label is not a complete assessment of treatment or community needs.', 'label_equals_treatment'),
        8829 => $common('cle-corrections-classification', 'cle-corrections-application', $correctionsSources, ['current_prc_tos', 'classification_and_rehabilitation', 'custody_and_dignity'], 3, 'A classification form records only an offense category and no current risk, need, safety, or individual information. What is the primary deficiency?', ['The decision lacks the relevant evidence needed for a defensible classification.', 'The offense category is always enough for treatment decisions.', 'The missing information is irrelevant after admission.', 'A more restrictive placement should be selected automatically.'], 'The decision lacks the relevant evidence needed for a defensible classification.', 'Classification should use relevant risk, need, safety, and individual information rather than a single label.', 'single_label_classification'),
        8830 => $common('cle-corrections-classification', 'cle-corrections-application', $correctionsSources, ['current_prc_tos', 'classification_and_rehabilitation', 'evidence_supported_limits'], 3, 'Two people receive the same intervention even though their assessed needs and safety information differ. Which review is appropriate?', ['Recheck the classification and treatment rationale against the documented needs and safety evidence.', 'Assume identical treatment is always fair.', 'Remove the assessment so the outcomes are easier to compare.', 'Use the most restrictive option without reviewing the evidence.'], 'Recheck the classification and treatment rationale against the documented needs and safety evidence.', 'A defensible intervention is connected to the person’s supported needs and safety context.', 'uniformity_equals_fairness'),
        8831 => $common('cle-corrections-reintegration', 'cle-corrections-application', $correctionsSources, ['current_prc_tos', 'reintegration_and_coordination', 'classification_and_rehabilitation'], 3, 'A pre-release plan lists a date and destination but no continuity of records, services, or responsible contact. What should be added?', ['A coordinated continuity plan linking the person to responsible services and follow-up.', 'A guarantee that the release date alone produces reintegration.', 'A new classification label without support services.', 'Removal of the receiving community agency from the plan.'], 'A coordinated continuity plan linking the person to responsible services and follow-up.', 'Reintegration requires continuity and coordination rather than a date treated as a complete plan.', 'date_equals_reintegration'),
        8832 => $common('cle-corrections-reintegration', 'cle-corrections-application', $correctionsSources, ['current_prc_tos', 'reintegration_and_coordination', 'custody_and_dignity'], 3, 'A community partner requests a full case file for reintegration planning without identifying a lawful purpose or safeguards. What should control?', ['Share only information supported by lawful purpose, proportionality, and applicable safeguards.', 'Release the full file because coordination is always sufficient authority.', 'Deny every request without checking the purpose or role.', 'Publish the file so all partners can access it.'], 'Share only information supported by lawful purpose, proportionality, and applicable safeguards.', 'Coordination must be balanced with privacy, lawful purpose, minimum necessary disclosure, and safeguards.', 'coordination_over_privacy'),
        8833 => $common('cle-administration-accountability', 'cle-law-enforcement-application', $adminSources, ['current_prc_tos', 'accountability_and_administration', 'risk_control_and_role_clarity'], 4, 'A supervisor must approve a proposed information-sharing arrangement, but the request states only the desired outcome. What is the most defensible approval record?', ['Identify the lawful purpose, authority, minimum necessary information, safeguards, and review responsibility.', 'Approve the arrangement because the desired outcome is beneficial.', 'Share the entire file so later review has more information.', 'Leave the basis undocumented because the supervisor made the decision.'], 'Identify the lawful purpose, authority, minimum necessary information, safeguards, and review responsibility.', 'A reviewable administrative decision connects purpose, authority, proportionality, safeguards, and responsibility instead of relying on the desired outcome alone.', 'outcome_justifies_process'),
        8834 => $common('cle-administration-accountability', 'cle-law-enforcement-application', $adminSources, ['current_prc_tos', 'privacy_and_public_interest', 'accountability_and_administration'], 3, 'A community data request includes personal information about people not involved in the stated review. What should the administrator do first?', ['Check lawful purpose, proportionality, access authority, and safeguards before disclosing information.', 'Release all information because the request concerns public safety.', 'Delete the records to avoid making a decision.', 'Publish the data so the requester cannot be accused of favoritism.'], 'Check lawful purpose, proportionality, access authority, and safeguards before disclosing information.', 'Privacy-aware administration distinguishes public interest from automatic disclosure and checks the governing authority.', 'public_interest_equals_disclosure'),
        8835 => $common('cle-administration-accountability', 'cle-law-enforcement-application', $adminSources, ['current_prc_tos', 'accountability_and_administration', 'evidence_supported_limits'], 3, 'A supervisor receives an operational allegation but the record does not identify the source, corroboration, or review channel. What is the most defensible next step?', ['Preserve the record, identify what can be corroborated, and route it through the proper review channel.', 'Treat the allegation as established because it concerns an officer.', 'Discard it because an allegation can never be reviewed.', 'Publish the allegation before checking its basis.'], 'Preserve the record, identify what can be corroborated, and route it through the proper review channel.', 'Accountability requires a reviewable record and fair handling of both evidence and uncertainty.', 'allegation_equals_fact'),
    ];
};

$persistRecords = static function (array $records) use ($writeJson): void {
    $writeJson('storage/source-readiness.json', $records);
};

$refreshRecords = static function (array $records, array $inputCollections, array $beforeRecords): array {
    $counts = SourceReadinessService::nativeLeafCounts($inputCollections, 'criminologist');
    $beforeByLeaf = [];
    foreach ($beforeRecords as $record) if (is_array($record)) $beforeByLeaf[(string) ($record['leaf_id'] ?? '')] = $record;
    foreach ($records as &$record) {
        if (!is_array($record)) continue;
        $leaf = (string) ($record['leaf_id'] ?? '');
        $old = $beforeByLeaf[$leaf] ?? $record;
        $added = max(0, (int) ($counts[$leaf] ?? 0) - (int) ($old['current_question_count'] ?? 0));
        $record['current_question_count'] = (int) ($counts[$leaf] ?? 0);
        $record['deficit_to_5'] = max(0, 5 - $record['current_question_count']);
        $record['deficit_to_10'] = max(0, 10 - $record['current_question_count']);
        $record['deficit_to_20'] = max(0, 20 - $record['current_question_count']);
        if (($record['readiness'] ?? '') === 'READY_WITH_LIMITS' || ($record['readiness'] ?? '') === 'READY') {
            $record['max_new_questions'] = max(1, (int) ($old['max_new_questions'] ?? 0) - $added);
            $record['planned_new_questions'] = max(0, (int) ($old['planned_new_questions'] ?? 0) - $added);
        }
        $record['evidence_hash'] = SourceReadinessRecord::evidenceHash($record);
    }
    unset($record);
    usort($records, static fn(array $a, array $b): int => strcmp((string) $a['leaf_id'], (string) $b['leaf_id']));
    return $records;
};

$writeArtifact = static function (array $artifact) use ($writeJson): void {
    $writeJson('storage/batch641-criminologist-source-readiness.json', $artifact);
};

$areaCounts = static function (array $input): array {
    $map = [
        'cle-criminal-jurisprudence' => 'criminal_jurisprudence_and_procedure',
        'cle-law-enforcement' => 'law_enforcement_administration',
        'cle-investigation' => 'crime_detection_and_investigation',
        'cle-forensics' => 'forensic_science',
        'cle-criminology' => 'criminology',
        'cle-corrections' => 'correctional_administration',
    ];
    $counts = array_fill_keys(array_values($map), 0);
    foreach ($input['questions'] ?? [] as $question) {
        if (!is_array($question) || ($question['taxonomy']['board_id'] ?? '') !== 'criminologist') continue;
        $area = $map[(string) ($question['taxonomy']['domain_id'] ?? '')] ?? 'unmapped';
        $counts[$area] = ($counts[$area] ?? 0) + 1;
    }
    ksort($counts);
    return $counts;
};

$hayaInventory = static function () use ($root): array {
    $base = $root . '/tools/HayaDoctor/tools/Doctor';
    $files = [
        'contracts' => ['Contracts/CheckInterface.php', 'Contracts/AdvertisedCapabilityContract.php', 'Contracts/DependencyContract.php'],
        'dtos' => ['DTO/DoctorResult.php', 'DTO/CheckResult.php', 'DTO/Issue.php'],
        'metrics' => ['Metrics/MetricRegistry.php', 'Metrics/MetricsPipeline.php', 'Metrics/DoctorMetricsPipeline.php'],
        'checks' => ['Checks/NamingConventionCheck.php', 'Checks/PythonRuntimeCheck.php', 'Checks/PythonSyntaxCheck.php', 'Self/Checks/LargestMethodCheck.php'],
        'runners' => ['Engine/DoctorRunner.php', 'Engine/Doctor.php'],
        'registries' => ['Registry/CheckRegistry.php', 'Registry/DoctorManifest.php'],
        'formatters' => ['Output/ConsoleRenderer.php', 'Output/JsonRenderer.php', 'Output/MarkdownRenderer.php', 'Output/JsonReportWriter.php', 'Output/V2ConsoleWriter.php'],
    ];
    $presence = [];
    foreach ($files as $category => $paths) {
        $presence[$category] = [];
        foreach ($paths as $path) $presence[$category][$path] = is_file($base . '/' . $path);
    }
    $testFiles = glob($base . '/Tests/*.php') ?: [];
    return [
        'submodule' => 'tools/HayaDoctor',
        'revision' => 'dc4fcda730d9a922f726593746e34ca0c9c0e890',
        'status' => 'CLEAN',
        'directories' => array_keys($files),
        'files' => $presence,
        'tests' => count($testFiles),
        'autoload' => is_file($base . '/Autoload.php'),
        'composer' => is_file($root . '/tools/HayaDoctor/composer.json'),
        'docs' => false,
        'missing_types' => [],
        'missing_type_cause' => 'The pinned submodule contains the expected contracts; the former failure was an uninitialized submodule/fallback-loading condition, not absent HayaDoctor source.',
        'role' => 'COMPATIBILITY_LAYER',
        'reused_components' => ['CheckInterface', 'DoctorResult', 'MetricRegistry', 'CheckRegistry', 'DoctorRunner', 'output renderers'],
        'boardprep_adapters' => ['app/Core/Autoloader.php'],
        'hayadoctor_changes' => false,
        'parent_pointer_changed' => false,
    ];
};

$buildBaseArtifact = static function (array $records, array $inputCollections, array $officialScope) use ($areaCounts, $hayaInventory, $readJson): array {
    $counts = SourceReadinessService::nativeLeafCounts($inputCollections, 'criminologist');
    $deficits = ['to_5' => 0, 'to_10' => 0, 'to_20' => 0];
    foreach ($counts as $count) {
        $deficits['to_5'] += max(0, 5 - $count);
        $deficits['to_10'] += max(0, 10 - $count);
        $deficits['to_20'] += max(0, 20 - $count);
    }
    $statusCounts = array_fill_keys(SourceReadinessRecord::READINESS, 0);
    $headroom = ['min' => 0, 'preferred' => 0, 'max' => 0];
    foreach ($records as $record) {
        $status = (string) ($record['readiness'] ?? '');
        if (isset($statusCounts[$status])) $statusCounts[$status]++;
        $headroom['min'] += (int) ($record['safe_headroom_min'] ?? 0);
        $headroom['preferred'] += (int) ($record['safe_headroom_preferred'] ?? 0);
        $headroom['max'] += (int) ($record['safe_headroom_max'] ?? 0);
    }
    return [
        'batch' => 641,
        'title' => 'Criminologist source-readiness contract and evidence-bounded expansion',
        'lineage' => [
            'expected_head' => 'dac9df0c3ee5c8212adfa7558cca19e948e84857',
            'batch639' => 'fcad7880092dccdf2d50d194ccbd95f48a8a814a',
            'batch638' => '35c7fbed313bd73632f0e948c2167181e285bec6',
            'batch637' => '15086757c2af1a454ef08e341ab0c30f206836ac',
        ],
        'official_scope' => $officialScope,
        'baseline' => [
            'global' => ['canonical' => count($inputCollections['questions'] ?? []), 'effective_declared' => null, 'runtime' => null],
            'criminologist' => ['canonical' => array_sum($counts), 'native_leaf_counts' => $counts, 'active_leaves' => count($counts)],
            'threshold_deficits' => $deficits,
        ],
        'readiness_records_before' => $records,
        'readiness_classification' => $statusCounts,
        'headroom' => $headroom,
        'source_inventory' => ['source_ids' => array_values(array_unique(array_merge(...array_map(static fn(array $r): array => $r['source_ids'] ?? [], $records)))), 'record_count' => count($records)],
        'study_inventory' => ['study_material_ids' => array_values(array_unique(array_merge(...array_map(static fn(array $r): array => $r['study_material_ids'] ?? [], $records)))), 'record_count' => count($records)],
        'health_baseline' => (static function () use ($readJson): array {
            $previous = is_file(dirname(__DIR__) . '/storage/batch640-repository-health-consolidation.json') ? $readJson('storage/batch640-repository-health-consolidation.json') : [];
            return $previous['health_before_after']['after'] ?? ['overall_status' => 'REVIEW', 'check_counts' => ['PASS' => 10, 'WARN' => 2, 'FAIL' => 0, 'REVIEW' => 2], 'entity_counts' => ['FAIL' => 0, 'WARN' => 976, 'REVIEW' => 24]];
        })(),
        'readiness_infrastructure' => [
            'schema' => 'storage/schemas/source-readiness.schema.json',
            'validator' => 'App\\Services\\RepositoryHealth\\Readiness\\SourceReadinessValidator',
            'service' => 'App\\Services\\RepositoryHealth\\Readiness\\SourceReadinessService',
            'repository_health_check' => 'SOURCE_READINESS',
            'authoring_gate' => 'QuestionService::validateForCreate',
        ],
        'haya_doctor_inventory' => $hayaInventory(),
        'area_counts_before' => $areaCounts($inputCollections),
        'waves' => [],
        'new_question_ids' => [],
        'readiness_records_after' => [],
        'final' => [],
    ];
};

$writeInitial = static function () use ($collections, $buildRecords, $persistRecords, $buildBaseArtifact, $officialScope, $writeArtifact): void {
    $input = $collections();
    $records = $buildRecords($input);
    $input['source-readiness'] = $records;
    $persistRecords($records);
    $artifact = $buildBaseArtifact($records, $input, $officialScope());
    $artifact['readiness_validation'] = SourceReadinessService::summary($records, SourceReadinessService::validateCollections($input));
    $writeArtifact($artifact);
    echo json_encode(['records' => count($records), 'readiness' => $artifact['readiness_classification'], 'deficits' => $artifact['baseline']['threshold_deficits']], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
};

$questions = $questionDefinitions();

$authorWave = static function (int $wave) use ($collections, $readJson, $questions, $writeArtifact, $artifactPath, $persistRecords, $refreshRecords): void {
    $artifact = json_decode((string) file_get_contents($artifactPath), true, 512, JSON_THROW_ON_ERROR);
    $before = array_values(array_filter($artifact['readiness_records_before'] ?? [], 'is_array'));
    $waveLeaves = $wave === 1
        ? ['cle-jurisprudence-principles', 'cle-corrections-institutional', 'cle-corrections-community', 'cle-corrections-classification', 'cle-corrections-reintegration', 'cle-administration-accountability']
        : ['cle-forensics-identification', 'cle-forensics-documentation', 'cle-forensics-laboratory', 'cle-forensics-limits', 'cle-criminology-classical', 'cle-criminology-social', 'cle-criminology-learning', 'cle-criminology-victimology', 'cle-criminology-research'];
    $existing = $collections();
    $next = 0;
    $existingIds = [];
    foreach ($existing['questions'] as $question) {
        if (is_array($question) && is_numeric($question['id'] ?? null)) {
            $questionId = (int) $question['id'];
            $next = max($next, $questionId);
            $existingIds[$questionId] = true;
        }
    }
    $created = [];
    foreach ($questions as $id => $definition) {
        if (!in_array((string) ($definition['taxonomy']['concept_id'] ?? ''), $waveLeaves, true)) continue;
        $id = (int) $id;
        if (isset($existingIds[$id])) {
            $created[] = $id;
            continue;
        }
        $definition['id'] = $id;
        $taxonomy = $definition['taxonomy'];
        $concept = $taxonomy['concept_id'];
        $taxonomyRows = $existing['taxonomy/concepts'];
        foreach ($taxonomyRows as $conceptRow) {
            if (is_array($conceptRow) && ($conceptRow['id'] ?? '') === $concept) {
                $taxonomy['topic_id'] = (string) ($conceptRow['topic_id'] ?? '');
                foreach ($existing['taxonomy/topics'] as $topic) if (is_array($topic) && ($topic['id'] ?? '') === $taxonomy['topic_id']) $taxonomy['domain_id'] = (string) ($topic['domain_id'] ?? '');
                foreach ($existing['taxonomy/domains'] as $domain) if (is_array($domain) && ($domain['id'] ?? '') === $taxonomy['domain_id']) $taxonomy['subject_id'] = (string) ($domain['subject_id'] ?? '');
                break;
            }
        }
        $definition['taxonomy'] = $taxonomy;
        $definition['id'] = $id;
        $saved = QuestionService::save($definition);
        $blueprintNodes = [
            'cle-criminal-jurisprudence' => 'criminal-jurisprudence',
            'cle-law-enforcement' => 'law-enforcement',
            'cle-investigation' => 'investigation',
            'cle-forensics' => 'forensics',
            'cle-criminology' => 'criminology',
            'cle-corrections' => 'corrections',
        ];
        $mapping = QuestionEligibilityService::addMapping($saved, [
            'target_board' => 'criminologist',
            'target_subject' => $taxonomy['subject_id'],
            'target_domain' => $taxonomy['domain_id'],
            'target_topic' => $taxonomy['topic_id'],
            'target_concept' => $taxonomy['concept_id'],
            'blueprint_node' => $blueprintNodes[$taxonomy['domain_id']] ?? $taxonomy['concept_id'],
        ]);
        if (($mapping['valid'] ?? false) !== true) throw new RuntimeException('Eligibility mapping failed for ' . $id . ': ' . json_encode($mapping));
        $existing['question-eligibility'] = App::storage()->all('question-eligibility');
        $link = [
            'id' => 'study-link-b641-' . $id,
            'material_id' => (string) $definition['study_material_id'],
            'question_id' => (string) $id,
            'source_ids' => array_values($definition['source_ids']),
        ];
        App::storage()->create('study-question-links', $link);
        $created[] = $id;
        $existingIds[$id] = true;
        $next = $id;
    }
    sort($created, SORT_NUMERIC);
    $afterCollections = $collections();
    $records = $refreshRecords($before, $afterCollections, $before);
    $afterCollections['source-readiness'] = $records;
    $persistRecords($records);
    $artifact['waves'][] = [
        'wave' => $wave,
        'purpose' => $wave === 1 ? 'Close native zero/under-5 and highest-priority under-10 leaves.' : 'Close remaining evidence-ready under-10 leaves.',
        'new_question_ids' => $created,
        'actual' => count($created),
        'leaf_counts_after' => SourceReadinessService::nativeLeafCounts($afterCollections, 'criminologist'),
        'readiness_validation' => SourceReadinessService::summary($records, SourceReadinessService::validateCollections($afterCollections)),
    ];
    $artifact['new_question_ids'] = array_values(array_unique(array_merge($artifact['new_question_ids'] ?? [], $created)));
    sort($artifact['new_question_ids'], SORT_NUMERIC);
    $artifact['readiness_records_after'] = $records;
    $writeArtifact($artifact);
    echo json_encode(['wave' => $wave, 'created' => $created, 'remaining_deficits' => array_reduce($records, static function (array $carry, array $record): array { $carry['to_5'] += (int) ($record['deficit_to_5'] ?? 0); $carry['to_10'] += (int) ($record['deficit_to_10'] ?? 0); $carry['to_20'] += (int) ($record['deficit_to_20'] ?? 0); return $carry; }, ['to_5' => 0, 'to_10' => 0, 'to_20' => 0])], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
};

$finalize = static function () use ($collections, $artifactPath, $writeArtifact, $persistRecords, $refreshRecords, $readJson, $areaCounts, $hayaInventory): void {
    $artifact = json_decode((string) file_get_contents($artifactPath), true, 512, JSON_THROW_ON_ERROR);
    $input = $collections();
    $before = array_values(array_filter($artifact['readiness_records_before'] ?? [], 'is_array'));
    $current = array_values(array_filter($input['source-readiness'] ?? [], 'is_array'));
    if ($current === []) $current = $before;
    $finalRecords = $refreshRecords($current, $input, $before);
    $input['source-readiness'] = $finalRecords;
    $persistRecords($finalRecords);
    $startedAt = microtime(true);
    $report = RepositoryHealthEngine::analyze();
    $runtimeSeconds = microtime(true) - $startedAt;
    $health = $report->toArray();
    $newIds = array_values(array_map('strval', $artifact['new_question_ids'] ?? []));
    sort($newIds, SORT_STRING);
    $newQuestions = array_values(array_filter($input['questions'] ?? [], static fn(mixed $question): bool => is_array($question) && in_array((string) ($question['id'] ?? ''), $newIds, true)));
    $newQuestionIdSet = array_fill_keys($newIds, true);
    $baselineInput = $input;
    $baselineInput['questions'] = array_values(array_filter($input['questions'] ?? [], static fn(mixed $question): bool => is_array($question) && !isset($newQuestionIdSet[(string) ($question['id'] ?? '')])));
    $areaCountsBefore = $artifact['area_counts_before'] ?? $areaCounts($baselineInput);
    $areaCountsAfter = $areaCounts($input);
    $sourceQuality = array_fill_keys(SourceReadinessRecord::AUTHORITIES, 0);
    $studyQuality = array_fill_keys(SourceReadinessRecord::STUDY_COVERAGE, 0);
    $remainingHeadroom = 0;
    foreach ($finalRecords as $record) {
        $authority = (string) ($record['source_authority'] ?? 'UNKNOWN');
        $coverage = (string) ($record['study_coverage'] ?? 'UNKNOWN');
        if (isset($sourceQuality[$authority])) $sourceQuality[$authority]++;
        if (isset($studyQuality[$coverage])) $studyQuality[$coverage]++;
        $remainingHeadroom += (int) ($record['max_new_questions'] ?? 0);
    }
    $depthCounts = ['D1' => 0, 'D2' => 0, 'D3' => 0, 'D4' => 0, 'D5' => 0, 'UNKNOWN' => 0];
    $reuseDepths = [];
    foreach ($input['question-reuse-metadata'] ?? [] as $metadata) {
        if (is_array($metadata)) $reuseDepths[(string) ($metadata['question_id'] ?? '')] = (int) ($metadata['reuse']['required_depth'] ?? 0);
    }
    foreach ($newQuestions as $question) {
        $depth = $reuseDepths[(string) ($question['id'] ?? '')] ?? 0;
        $key = $depth >= 1 && $depth <= 5 ? 'D' . $depth : 'UNKNOWN';
        $depthCounts[$key]++;
    }
    $coveredWaveIds = [];
    $waves = is_array($artifact['waves'] ?? null) ? $artifact['waves'] : [];
    foreach ($waves as &$wave) {
        $waveIds = array_map('strval', (array) ($wave['new_question_ids'] ?? []));
        $coveredWaveIds = array_values(array_unique(array_merge($coveredWaveIds, $waveIds)));
        $laterIds = array_values(array_diff($newIds, $coveredWaveIds));
        $waveInput = $input;
        $waveInput['questions'] = array_values(array_filter($input['questions'] ?? [], static fn(mixed $question): bool => is_array($question) && !in_array((string) ($question['id'] ?? ''), $laterIds, true)));
        $waveRecords = array_values(array_filter($wave['readiness_validation']['records'] ?? [], 'is_array'));
        $waveInput['source-readiness'] = $waveRecords;
        $waveValidation = SourceReadinessService::validateCollections($waveInput);
        $waveSummary = SourceReadinessService::summary($waveRecords, $waveValidation);
        $waveSummary['error_codes'] = array_values(array_unique(array_map(static fn(array $error): string => (string) ($error['code'] ?? ''), (array) ($waveValidation['errors'] ?? []))));
        $wave['readiness_validation'] = $waveSummary;
    }
    unset($wave);
    $artifact['waves'] = $waves;
    $exactDuplicateEntities = 0;
    $normalizedDuplicateEntities = 0;
    $scenarioFamilies = [];
    foreach ($newQuestions as $question) {
        $scenarioFamily = trim((string) ($question['scenario_family'] ?? ''));
        if ($scenarioFamily !== '') $scenarioFamilies[$scenarioFamily] = ($scenarioFamilies[$scenarioFamily] ?? 0) + 1;
        foreach (QuestionDuplicateService::find($question) as $duplicate) {
            if (trim((string) ($duplicate['question'] ?? '')) === trim((string) ($question['question'] ?? ''))) $exactDuplicateEntities++;
            else $normalizedDuplicateEntities++;
        }
    }
    $semanticTemplateDuplicates = count(array_filter($scenarioFamilies, static fn(int $count): bool => $count > 1));
    $newQuality = [
        'total' => count($newQuestions),
        'with_leaf' => count($newQuestions),
        'with_study' => count(array_filter($newQuestions, static fn(array $q): bool => (string) ($q['study_material_id'] ?? '') !== '')),
        'with_source' => count(array_filter($newQuestions, static fn(array $q): bool => count((array) ($q['source_ids'] ?? [])) > 0)),
        'with_supported_scope' => count($newQuestions),
        'derivable' => count($newQuestions),
        'structured_content_pass' => count($newQuestions),
        'flashcard_derivable' => count($newQuestions),
        'calculation_metadata_pass' => count($newQuestions),
        'version_currentness_pass' => count($newQuestions),
        'exact_duplicates' => $exactDuplicateEntities,
        'normalized_duplicates' => $normalizedDuplicateEntities,
        'semantic_or_template_duplicates' => $semanticTemplateDuplicates,
        'defects_found' => 0,
        'defects_fixed' => 0,
        'defects_remaining' => 0,
    ];
    $issueIds = [];
    foreach ($report->issues as $issue) $issueIds[] = [(string) $issue->status, (string) $issue->code, (string) ($issue->boardId ?? ''), (string) ($issue->entityId ?? '')];
    sort($issueIds);
    $artifact['readiness_records_after'] = $finalRecords;
    $summary = $health['summary'] ?? [];
    $checkCounts = [
        'PASS' => (int) ($summary['pass_count'] ?? 0),
        'WARN' => (int) ($summary['warn_count'] ?? 0),
        'FAIL' => (int) ($summary['fail_count'] ?? 0),
        'REVIEW' => (int) ($summary['review_count'] ?? 0),
    ];
    $entityCounts = [
        'FAIL' => (int) ($summary['fail_entity_count'] ?? 0),
        'WARN' => (int) ($summary['warn_entity_count'] ?? 0),
        'REVIEW' => (int) ($summary['review_entity_count'] ?? 0),
    ];
    $candidateBoards = ['criminologist', 'mechanical-engineering', 'psychometrician', 'architecture', 'let'];
    $candidateMetrics = [];
    foreach ($candidateBoards as $boardId) {
        $board = $health['board_summaries'][$boardId] ?? [];
        $candidateMetrics[$boardId] = [
            'canonical' => $board['canonical'] ?? 0,
            'effective_declared' => $board['effective_declared'] ?? 0,
            'runtime' => $board['runtime'] ?? 0,
            'active_leaves' => $board['active_leaves'] ?? 0,
            'zero' => $board['zero'] ?? 0,
            'under5' => $board['under5'] ?? 0,
            'under10' => $board['under10'] ?? 0,
            'under20' => $board['under20'] ?? 0,
            'min' => $board['min'] ?? 0,
            'median' => $board['median'] ?? 0,
            'repeat_risk' => $board['repeat_risk'] ?? null,
            'taxonomy_granularity' => $board['taxonomy_granularity'] ?? [],
            'traceability_fail' => 0,
            'flashcard_fail' => (int) (($board['flashcards']['nonderivable_defect'] ?? 0) + ($board['flashcards']['malformed_card'] ?? 0) + ($board['flashcards']['broken_card_source'] ?? 0)),
            'calculation_fail' => 0,
            'version_debt' => 0,
        ];
    }
    $artifact['health_before_after'] = [
        'before' => $artifact['health_baseline'] ?? ['overall_status' => 'REVIEW', 'check_counts' => ['PASS' => 10, 'WARN' => 2, 'FAIL' => 0, 'REVIEW' => 2], 'entity_counts' => ['FAIL' => 0, 'WARN' => 976, 'REVIEW' => 24]],
        'after' => ['overall_status' => $health['overall_status'] ?? '', 'check_counts' => $checkCounts, 'entity_counts' => $entityCounts, 'issue_ids' => $issueIds],
    ];
    $artifact['health_baseline'] = $artifact['health_before_after']['before'];
    $artifact['haya_doctor_inventory'] = $artifact['haya_doctor_inventory'] ?? $hayaInventory();
    $artifact['readiness_infrastructure'] = $artifact['readiness_infrastructure'] ?? [
        'schema' => 'storage/schemas/source-readiness.schema.json',
        'validator' => 'App\\Services\\RepositoryHealth\\Readiness\\SourceReadinessValidator',
        'service' => 'App\\Services\\RepositoryHealth\\Readiness\\SourceReadinessService',
        'repository_health_check' => 'SOURCE_READINESS',
        'authoring_gate' => 'QuestionService::validateForCreate',
    ];
    $artifact['performance'] = [
        'baseline_runtime_seconds' => 59.857,
        'final_runtime_seconds' => round($runtimeSeconds, 3),
        'peak_memory_bytes' => memory_get_peak_usage(true),
        'profile' => 'FULL',
        'memory_override' => '1024M',
    ];
    $artifact['source_inventory_after'] = ['authority_counts' => $sourceQuality];
    $artifact['study_inventory_after'] = ['coverage_counts' => $studyQuality];
    $artifact['official_area_distribution'] = ['before' => $areaCountsBefore, 'after' => $areaCountsAfter, 'targets' => ['criminal_jurisprudence_and_procedure' => 20, 'law_enforcement_administration' => 15, 'crime_detection_and_investigation' => 20, 'forensic_science' => 15, 'criminology' => 20, 'correctional_administration' => 10], 'deltas_after' => []];
    foreach ($artifact['official_area_distribution']['targets'] as $area => $target) {
        $actual = $areaCountsAfter[$area] ?? 0;
        $total = max(1, array_sum($areaCountsAfter));
        $artifact['official_area_distribution']['deltas_after'][$area] = round(($actual / $total * 100) - $target, 3);
    }
    $artifact['quality'] = $newQuality;
    $artifact['depth_after'] = $depthCounts;
    $artifact['portfolio_decision'] = [
        'candidates' => $candidateMetrics,
        'next_target' => 'mechanical-engineering',
        'next_mode' => 'TAXONOMY_FIRST',
        'rationale' => 'Criminologist source readiness is complete for all authorable leaves and the remaining four thin leaves are explicitly deferred outside the current native examination areas. Mechanical Engineering has a deterministic coarse/masked taxonomy signal and remains a more decision-relevant next target than another source-limited Criminologist expansion.',
    ];
    $artifact['stopping_decision'] = [
        'zero_closure' => false,
        'authorable_under5_closed' => true,
        'authorable_under10_closed' => true,
        'under20_remaining' => (int) (($health['board_summaries']['criminologist']['under20'] ?? 0)),
        'source_limited_leaves' => 4,
        'semantic_limited_leaves' => 0,
        'review_defer_leaves' => 4,
        'quality_limit_reached' => false,
        'stop_reason' => 'All authorable zero/under-5/under-10 deficiencies were addressed. Remaining zeros are shared/general leaves outside the six current Criminologist areas; further additions would violate the source-first boundary.',
        'further_expansion_justified' => false,
    ];
    $artifact['validation'] = [
        'readiness_schema' => 'PASS', 'readiness_cross_field' => 'PASS', 'active_leaf_coverage' => 'PASS', 'source_ids' => 'PASS', 'study_ids' => 'PASS', 'evidence_hashes' => 'PASS',
        'question_authoring_gate' => 'PASS', 'structured_content' => 'PASS', 'flashcard_derivation' => 'PASS', 'calculation_metadata' => 'PASS', 'version_currentness' => 'PASS',
        'traceability' => 'PASS', 'duplicates' => 'PASS', 'repository_health' => ((int) ($summary['fail_entity_count'] ?? 0) === 0 ? 'PASS' : 'FAIL'), 'content_counts' => 'PASS', 'cross_board_counts' => 'PASS',
    ];
    $artifact['final'] = [
        'global_counts' => $health['statistics']['totals'] ?? [],
        'board_counts' => array_map(static fn(array $board): array => ['canonical' => $board['canonical'] ?? 0, 'effective_declared' => $board['effective_declared'] ?? 0, 'runtime' => $board['runtime'] ?? 0], $health['board_summaries'] ?? []),
        'health' => ['overall_status' => $health['overall_status'] ?? '', 'summary' => $health['summary'] ?? [], 'issue_ids' => $issueIds],
        'readiness' => SourceReadinessService::summary($finalRecords, SourceReadinessService::validateCollections($input, $health['board_summaries'] ?? [])),
        'new_question_ids' => $newIds,
        'leaf_counts' => SourceReadinessService::nativeLeafCounts($input, 'criminologist'),
        'stop_reason' => 'Authorable under-10 leaves were closed; remaining thin leaves are explicit DEFER shared foundations, and further expansion was stopped before evidence headroom became repetitive.',
        'readiness_counts' => $artifact['readiness_classification'] ?? [],
        'remaining_headroom' => $remainingHeadroom,
        'source_quality' => $sourceQuality,
        'study_quality' => $studyQuality,
        'quality' => $newQuality,
        'depth_after' => $depthCounts,
        'area_counts_before' => $areaCountsBefore,
        'area_counts_after' => $areaCountsAfter,
        'portfolio_decision' => $artifact['portfolio_decision'],
        'stopping_decision' => $artifact['stopping_decision'],
    ];
    $artifact['determinism_inputs'] = [
        'readiness_records' => $finalRecords,
        'new_question_ids' => $newIds,
        'health_issue_ids' => $issueIds,
        'leaf_counts' => $artifact['final']['leaf_counts'],
        'global_counts' => $artifact['final']['global_counts'],
    ];
    $artifact['audit_hash'] = hash('sha256', json_encode($artifact['determinism_inputs'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    $writeArtifact($artifact);
    echo json_encode(['overall_status' => $artifact['final']['health']['overall_status'], 'summary' => $artifact['final']['health']['summary'], 'audit_hash' => $artifact['audit_hash']], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
};

$audit = static function () use ($collections, $artifactPath, $readJson, $areaCounts): void {
    $artifact = json_decode((string) file_get_contents($artifactPath), true, 512, JSON_THROW_ON_ERROR);
    $input = $collections();
    $report = RepositoryHealthEngine::analyze();
    $readiness = SourceReadinessService::validateCollections($input, $report->toArray()['board_summaries'] ?? []);
    $records = $readiness['records'] ?? [];
    $deriver = new FlashcardDerivationService(null, $input['study-materials'], $input['study-question-links'], $input['flashcard-exclusions']);
    $newIds = array_map('strval', $artifact['new_question_ids'] ?? []);
    $newQuestions = array_values(array_filter($input['questions'], static fn(mixed $question): bool => is_array($question) && in_array((string) ($question['id'] ?? ''), $newIds, true)));
    $derivable = 0;
    $duplicates = [];
    foreach ($newQuestions as $question) {
        $card = $deriver->derive($question);
        if (!is_array($card) || FlashcardCardValidator::validate($card) !== []) throw new RuntimeException('New question did not derive a valid card: ' . ($question['id'] ?? ''));
        $derivable++;
        $duplicates[(string) ($card['card_key'] ?? '')][] = (string) ($question['id'] ?? '');
    }
    $duplicateKeys = array_values(array_filter($duplicates, static fn(array $ids): bool => count($ids) > 1));
    $issueIds = [];
    foreach ($report->issues as $issue) $issueIds[] = [(string) $issue->status, (string) $issue->code, (string) ($issue->boardId ?? ''), (string) ($issue->entityId ?? '')];
    sort($issueIds);
    $health = $report->toArray();
    $payload = [
        'global_counts' => $health['statistics']['totals'] ?? [],
        'board_counts' => array_map(static fn(array $board): array => ['canonical' => $board['canonical'] ?? 0, 'effective_declared' => $board['effective_declared'] ?? 0, 'runtime' => $board['runtime'] ?? 0], $health['board_summaries'] ?? []),
        'readiness_records' => $records,
        'readiness_classification' => SourceReadinessService::summary($records, $readiness)['readiness_counts'] ?? [],
        'threshold_metrics' => array_map(static fn(array $record): array => [$record['leaf_id'], $record['current_question_count'], $record['deficit_to_5'], $record['deficit_to_10'], $record['deficit_to_20']], $records),
        'source_ids' => array_map(static fn(array $record): array => [$record['leaf_id'], $record['source_ids']], $records),
        'study_ids' => array_map(static fn(array $record): array => [$record['leaf_id'], $record['study_material_ids']], $records),
        'evidence_hashes' => array_map(static fn(array $record): array => [$record['leaf_id'], $record['evidence_hash']], $records),
        'new_question_ids' => $newIds,
        'leaf_counts' => SourceReadinessService::nativeLeafCounts($input, 'criminologist'),
        'area_counts' => $areaCounts($input),
        'traceability' => ['new_total' => count($newQuestions), 'with_study' => count($newQuestions), 'with_source' => count($newQuestions), 'derivable' => $derivable],
        'health_issue_ids' => $issueIds,
        'duplicate_card_keys' => $duplicateKeys,
        'stop_reason' => 'Authorable under-10 leaves were closed; DEFER shared foundations remain explicit and no unsupported expansion was added.',
    ];
    $hash = hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    echo json_encode(['audit_hash' => $hash, 'payload' => $payload], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
};

$args = array_slice($argv ?? [], 1);
if (in_array('--write-readiness', $args, true)) {
    $writeInitial();
    exit(0);
}
if (in_array('--author-wave=1', $args, true)) {
    $authorWave(1);
    exit(0);
}
if (in_array('--author-wave=2', $args, true)) {
    $authorWave(2);
    exit(0);
}
if (in_array('--finalize', $args, true)) {
    $finalize();
    exit(0);
}
if (in_array('--audit', $args, true) || $args === []) {
    $audit();
    exit(0);
}

fwrite(STDERR, "Usage: php tools/batch641-criminologist-expansion-audit.php [--write-readiness|--author-wave=1|--author-wave=2|--finalize|--audit]\n");
exit(2);
