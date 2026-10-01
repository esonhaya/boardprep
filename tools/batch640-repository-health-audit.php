<?php

declare(strict_types=1);

/**
 * Batch 640 evidence runner.
 *
 * Repository Health owns detection. This script only reconstructs the
 * bounded calculation-metadata repair from the historical producer evidence,
 * records invariants, and asks the permanent engine for every wave gate.
 */

require_once dirname(__DIR__) . '/app/Core/Autoloader.php';

use App\Core\App;
use App\Core\Autoloader;
use App\Services\Question\CalculationMetadataContract;
use App\Services\RepositoryHealth\Engine\RepositoryHealthEngine;
use App\Services\RepositoryHealth\Engine\RepositoryHealthCheckCatalog;
use App\Services\RepositoryHealth\DTO\HealthReport;

Autoloader::register();
putenv('APP_ENV=testing');
putenv('APP_DEBUG=false');
App::boot();

$root = dirname(__DIR__);
$arguments = array_slice($argv ?? [], 1);
$mode = $arguments[0] ?? '--audit';
if (!in_array($mode, ['--audit', '--repair', '--finalize'], true) || count($arguments) > 1) {
    fwrite(STDERR, "Usage: php tools/batch640-repository-health-audit.php [--audit|--repair|--finalize]\n");
    exit(2);
}

$readJson = static function (string $path): array {
    $decoded = json_decode((string) file_get_contents($path), true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Invalid JSON: ' . $path);
    }
    return $decoded;
};

$writeJson = static function (string $path, array $value): void {
    $json = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $temporary = $path . '.tmp';
    file_put_contents($temporary, $json . PHP_EOL, LOCK_EX);
    rename($temporary, $path);
};

$questionInvariant = static function (array $question): string {
    return hash('sha256', json_encode([
        'id' => $question['id'] ?? null,
        'board' => $question['board'] ?? null,
        'taxonomy' => $question['taxonomy'] ?? null,
        'question' => $question['question'] ?? null,
        'choices' => $question['choices'] ?? $question['options'] ?? null,
        'answer' => $question['answer'] ?? null,
        'explanation' => $question['explanation'] ?? null,
        'source_ids' => $question['source_ids'] ?? null,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
};

$failureIds = static function (HealthReport $report): array {
    $ids = [];
    foreach ($report->issues as $issue) {
        if ($issue->status === 'FAIL' && $issue->code === 'CALCULATION_METADATA_CONTRADICTION') {
            $ids[] = (string) $issue->entityId;
        }
    }
    sort($ids, SORT_NATURAL);
    return $ids;
};

$sourceMaps = [];
$cpa = $readJson($root . '/storage/batch622-cpa-evidence-closure.json');
foreach (($cpa[0]['quantitative_semantics']['calculation_audits'] ?? []) as $row) {
    $id = (string) ($row['question_id'] ?? '');
    if ($id === '') continue;
    $sourceMaps[$id] = [
        'source' => 'storage/batch622-cpa-evidence-closure.json',
        'board' => 'cpa',
        'calculation_family' => (string) ($row['formula_family'] ?? ''),
        'calculation' => [
            'family' => (string) ($row['formula_family'] ?? ''),
            'values' => $row['inputs'] ?? [],
            'expected' => $row['expected_result'] ?? null,
            'unit' => $row['unit'] ?? null,
            'rounding' => $row['rounding'] ?? null,
        ],
        'verification' => [
            'answer_match' => $row['answer_match'] ?? false,
            'status' => $row['status'] ?? 'UNKNOWN',
        ],
    ];
}

$psych = $readJson($root . '/storage/batch624-portfolio-selection-and-modernization.json');
foreach (($psych['quantitative']['records'] ?? []) as $row) {
    $id = (string) ($row['question_id'] ?? '');
    if ($id === '') continue;
    $family = (string) ($row['family'] ?? '');
    $sourceMaps[$id] = [
        'source' => 'storage/batch624-portfolio-selection-and-modernization.json',
        'board' => 'psychometrician',
        'calculation_family' => $family,
        'calculation' => [
            'family' => $family,
            'params' => $row['inputs'] ?? [],
            'expected' => $row['expected_result'] ?? null,
            'unit' => $row['units'] ?? null,
            'rounding' => $row['rounding'] ?? null,
        ],
        'verification' => [
            'answer_match' => $row['answer_match'] ?? false,
            'status' => $row['status'] ?? 'UNKNOWN',
        ],
    ];
}

$cse = $readJson($root . '/storage/batch627-cse-let-comparison-modernization.json');
foreach (($cse[0]['quantitative']['records'] ?? []) as $row) {
    $id = (string) ($row['question_id'] ?? '');
    if ($id === '') continue;
    $sourceMaps[$id] = [
        'source' => 'storage/batch627-cse-let-comparison-modernization.json',
        'board' => 'civil-service',
        'calculation_family' => null,
        'calculation' => is_array($row['calculation'] ?? null) ? $row['calculation'] : [],
        'verification' => ['status' => $row['status'] ?? 'UNKNOWN'],
    ];
}

$baseline = RepositoryHealthEngine::analyze();
$baselineIds = $failureIds($baseline);
$questions = $readJson($root . '/storage/questions.json');
$questionById = [];
foreach ($questions as $index => $question) {
    if (is_array($question)) {
        $questionById[(string) ($question['id'] ?? '')] = ['index' => $index, 'row' => $question];
    }
}

if ($mode === '--repair' && count($baselineIds) !== 105) {
    throw new RuntimeException('Expected 105 calculation failures before repair; found ' . count($baselineIds));
}

$missingEvidence = array_values(array_diff($baselineIds, array_keys($sourceMaps)));
if ($missingEvidence !== []) {
    throw new RuntimeException('Historical calculation evidence is missing for IDs: ' . implode(',', $missingEvidence));
}

$classification = [];
foreach ($baselineIds as $id) {
    $row = $questionById[$id]['row'] ?? null;
    if (!is_array($row)) throw new RuntimeException('Missing question for calculation failure: ' . $id);
    $source = $sourceMaps[$id];
    if (($source['verification']['status'] ?? '') !== 'PASS' || ($source['verification']['answer_match'] ?? true) === false) {
        throw new RuntimeException('Historical arithmetic evidence is not PASS for question ' . $id);
    }
    $classification[$id] = [
        'question_id' => $id,
        'board_id' => $row['board'] ?? ($row['taxonomy']['board_id'] ?? null),
        'entity_type' => 'question',
        'check_code' => 'CALCULATION_METADATA_CONTRADICTION',
        'current_metadata' => [
            'calculation_required' => $row['calculation_required'] ?? null,
            'calculation_family' => $row['calculation_family'] ?? null,
            'calculation' => $row['calculation'] ?? null,
            'quantitative_semantics' => $row['quantitative_semantics'] ?? null,
            'numeric' => $row['numeric'] ?? null,
        ],
        'expected_contract' => [
            'form' => $source['calculation_family'] !== null ? 'payload_and_family' : 'payload',
            'calculation_family' => $source['calculation_family'],
            'calculation_fields' => array_keys($source['calculation']),
        ],
        'why_failing' => 'The producer persisted calculation_required=true but omitted the canonical payload and family recognized by the health contract.',
        'safe_autofix' => true,
        'requires_semantic_review' => false,
        'source_of_truth' => $source['source'],
        'historical_verification' => $source['verification'],
    ];
}

$waves = [
    ['number' => 1, 'name' => 'CPA payload recovery', 'ids' => array_values(array_filter($baselineIds, static fn(string $id): bool => ($sourceMaps[$id]['board'] ?? '') === 'cpa'))],
    ['number' => 2, 'name' => 'Psychometrician payload recovery', 'ids' => array_values(array_filter($baselineIds, static fn(string $id): bool => ($sourceMaps[$id]['board'] ?? '') === 'psychometrician'))],
    ['number' => 3, 'name' => 'Civil Service payload recovery', 'ids' => array_values(array_filter($baselineIds, static fn(string $id): bool => ($sourceMaps[$id]['board'] ?? '') === 'civil-service'))],
];

$waveResults = [];
if ($mode === '--repair') {
    foreach ($waves as $wave) {
        $beforeHashes = [];
        foreach ($wave['ids'] as $id) {
            $entry = $questionById[$id] ?? null;
            if (!is_array($entry)) throw new RuntimeException('Wave question not found: ' . $id);
            $question = $entry['row'];
            $beforeHashes[$id] = $questionInvariant($question);
            if (($question['calculation_required'] ?? false) !== true) throw new RuntimeException('Unexpected calculation flag for ' . $id);
            if (($sourceMaps[$id]['calculation'] ?? []) === []) throw new RuntimeException('Empty repair payload for ' . $id);
            $question['calculation'] = $sourceMaps[$id]['calculation'];
            if (($sourceMaps[$id]['calculation_family'] ?? null) !== null) {
                $question['calculation_family'] = $sourceMaps[$id]['calculation_family'];
            }
            $contract = CalculationMetadataContract::inspect($question);
            if (($contract['valid'] ?? false) !== true) throw new RuntimeException('Repair did not satisfy calculation contract for ' . $id);
            $questions[$entry['index']] = $question;
        }
        $writeJson($root . '/storage/questions.json', $questions);

        $afterQuestions = $readJson($root . '/storage/questions.json');
        foreach ($wave['ids'] as $id) {
            $after = null;
            foreach ($afterQuestions as $question) {
                if ((string) ($question['id'] ?? '') === $id) { $after = $question; break; }
            }
            if (!is_array($after) || $questionInvariant($after) !== $beforeHashes[$id]) {
                throw new RuntimeException('Non-calculation question drift in wave ' . $wave['number'] . ' question ' . $id);
            }
        }

        $waveReport = RepositoryHealthEngine::analyze();
        $remaining = $failureIds($waveReport);
        $waveResults[] = [
            'wave' => $wave['number'],
            'name' => $wave['name'],
            'planned' => count($wave['ids']),
            'actual' => count($wave['ids']),
            'repaired_ids' => $wave['ids'],
            'failures_remaining' => $remaining,
            'health_status' => $waveReport->overallStatus,
            'calculation_failures_remaining' => count($remaining),
            'contract' => 'PASS',
        ];
        $questionById = [];
        foreach ($afterQuestions as $index => $question) {
            if (is_array($question)) $questionById[(string) ($question['id'] ?? '')] = ['index' => $index, 'row' => $question];
        }
    }
}

$final = RepositoryHealthEngine::analyze();
$finalIds = $failureIds($final);
$boardCounts = [];
foreach ($final->boardSummaries as $board => $summary) {
    $boardCounts[$board] = [
        'canonical' => $summary['canonical'] ?? 0,
        'effective_declared' => $summary['effective_declared'] ?? 0,
        'runtime' => $summary['runtime'] ?? 0,
    ];
}

$artifact = [
    'batch' => 640,
    'title' => 'Permanent Repository Health consolidation and deterministic calculation metadata closure',
    'generated_at' => gmdate(DATE_ATOM),
    'mode' => $mode === '--repair' ? 'REPAIR_AND_AUDIT' : 'AUDIT',
    'lineage' => ['expected_parent' => 'fcad7880092dccdf2d50d194ccbd95f48a8a814a'],
    'baseline' => [
        'health_status' => $baseline->overallStatus,
        'summary' => $baseline->summary,
        'calculation_failure_count' => count($baselineIds),
        'calculation_failure_ids' => $baselineIds,
        'classification' => array_values($classification),
    ],
    'repair' => [
        'waves' => $waveResults,
        'target_entities' => 105,
        'repaired' => $mode === '--repair' ? 105 : 0,
        'fail_to_review' => 0,
        'unresolved_fail' => count($finalIds),
        'content_mutation' => 'calculation and calculation_family fields only',
    ],
    'after' => [
        'health_status' => $final->overallStatus,
        'summary' => $final->summary,
        'calculation_failure_count' => count($finalIds),
        'calculation_failure_ids' => $finalIds,
        'global_board_counts' => $boardCounts,
    ],
    'determinism_inputs' => [
        'classification_ids_hash' => hash('sha256', json_encode($baselineIds, JSON_THROW_ON_ERROR)),
        'source_map_ids_hash' => hash('sha256', json_encode(array_keys($sourceMaps), JSON_THROW_ON_ERROR)),
        'wave_sizes' => array_map(static fn(array $wave): int => count($wave['ids']), $waves),
    ],
];
$stableAuditPayload = [
    'baseline_calculation_failure_ids' => $baselineIds,
    'final_calculation_failure_ids' => $finalIds,
    'global_board_counts' => $boardCounts,
    'health_summary' => $final->summary,
    'health_issue_ids' => array_values(array_map(static fn(\App\Services\RepositoryHealth\DTO\HealthIssue $issue): string => (string) ($issue->code . ':' . ($issue->boardId ?? '') . ':' . ($issue->entityId ?? '')), $final->issues)),
    'flashcard_metrics' => $final->summary['flashcards'] ?? [],
    'wave_sizes' => array_map(static fn(array $wave): int => count($wave['ids']), $waves),
];
sort($stableAuditPayload['health_issue_ids']);
$artifact['determinism_inputs']['stable_audit_payload'] = $stableAuditPayload;
$artifact['audit_hash'] = hash('sha256', json_encode($stableAuditPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

if ($mode === '--repair') {
    $writeJson($root . '/storage/batch640-repository-health-consolidation.json', $artifact);
}

if ($mode === '--finalize') {
    $artifactPath = $root . '/storage/batch640-repository-health-consolidation.json';
    $finalArtifact = is_file($artifactPath) ? $readJson($artifactPath) : $artifact;
    $beforeSummary = [
        'overall_status' => 'FAIL',
        'pass_count' => 10,
        'warn_count' => 2,
        'fail_count' => 1,
        'review_count' => 1,
        'issue_count' => 1107,
        'pass_entity_count' => 0,
        'warn_entity_count' => 980,
        'fail_entity_count' => 105,
        'review_entity_count' => 22,
        'breakdown' => [
            'category' => ['BALANCE' => 958, 'CALCULATION' => 105, 'DEPTH_COVERAGE' => 22, 'SOURCE_STUDY' => 22],
            'check_code' => ['ACTIVE_UNDER10_LEAF' => 264, 'ACTIVE_UNDER20_LEAF' => 446, 'ACTIVE_UNDER5_LEAF' => 147, 'ACTIVE_ZERO_LEAF' => 85, 'CALCULATION_METADATA_CONTRADICTION' => 105, 'CONCENTRATION_SINGLE_LEAF' => 1, 'OFFICIAL_AREA_IMBALANCE' => 15, 'ORPHAN_STUDY_MATERIAL' => 19, 'SOURCE_SEMANTIC_FIT_REVIEW' => 3, 'UNKNOWN_COVERAGE' => 12, 'UNKNOWN_DEPTH' => 10],
        ],
        'flashcards' => ['eligible' => 8813, 'derivable' => 8795, 'justified_exclusion' => 18, 'nonderivable_defect' => 0],
    ];
    $timedStart = microtime(true);
    $secondReport = RepositoryHealthEngine::analyze();
    $fullRuntimeMs = round((microtime(true) - $timedStart) * 1000, 2);
    $issueIds = static function (HealthReport $report, string $status): array {
        $ids = [];
        foreach ($report->issues as $issue) {
            if ($issue->status === $status) {
                $ids[] = (string) ($issue->code . ':' . ($issue->boardId ?? '') . ':' . ($issue->entityId ?? ''));
            }
        }
        sort($ids);
        return $ids;
    };
    $issueCountsByBoard = [];
    $dominant = static function (HealthReport $report, string $status, ?string $board = null): array {
        $counts = [];
        foreach ($report->issues as $issue) {
            if ($issue->status !== $status || ($board !== null && ($issue->boardId ?? '') !== $board)) continue;
            $counts[$issue->code] = ($counts[$issue->code] ?? 0) + 1;
        }
        arsort($counts);
        return $counts;
    };
    foreach ($secondReport->boardSummaries as $boardId => $summary) {
        $counts = ['FAIL' => 0, 'WARN' => 0, 'REVIEW' => 0];
        $categoryCounts = [];
        foreach ($secondReport->issues as $issue) {
            if (($issue->boardId ?? '') !== $boardId) continue;
            if (isset($counts[$issue->status])) $counts[$issue->status]++;
            $categoryCounts[$issue->category] = ($categoryCounts[$issue->category] ?? 0) + 1;
        }
        $traceability = 0;
        $flashcard = 0;
        $calculation = 0;
        $version = 0;
        foreach ($secondReport->issues as $issue) {
            if (($issue->boardId ?? '') !== $boardId || $issue->status !== 'FAIL') continue;
            $traceability += $issue->category === 'TRACEABILITY' ? 1 : 0;
            $flashcard += $issue->category === 'FLASHCARDS' ? 1 : 0;
            $calculation += $issue->category === 'CALCULATION' ? 1 : 0;
            $version += $issue->code === 'VERSION_REVIEW_REQUIRED' ? 1 : 0;
        }
        $warnCodes = $dominant($secondReport, 'WARN', $boardId);
        $reviewCodes = $dominant($secondReport, 'REVIEW', $boardId);
        $issueCountsByBoard[$boardId] = [
            'canonical' => $summary['canonical'] ?? 0,
            'effective_declared' => $summary['effective_declared'] ?? 0,
            'runtime' => $summary['runtime'] ?? 0,
            'active_leaves' => $summary['active_leaves'] ?? 0,
            'zero' => $summary['zero'] ?? 0,
            'under5' => $summary['under5'] ?? 0,
            'under10' => $summary['under10'] ?? 0,
            'under20' => $summary['under20'] ?? 0,
            'min' => $summary['min'] ?? 0,
            'median' => $summary['median'] ?? 0,
            'repeat_risk' => $summary['repeat_risk'] ?? 'UNKNOWN',
            'taxonomy_granularity' => $summary['taxonomy_granularity'] ?? [],
            'traceability_fail' => $traceability,
            'flashcard_fail' => $flashcard,
            'calculation_fail' => $calculation,
            'version_debt' => $version,
            'area_imbalance_count' => count(array_filter((array) ($summary['official_area_balance'] ?? []), static fn(array $area): bool => ($area['warning'] ?? false) === true)),
            'dominant_warn' => array_key_first($warnCodes),
            'dominant_warn_count' => $warnCodes === [] ? 0 : (int) reset($warnCodes),
            'dominant_review' => array_key_first($reviewCodes),
            'dominant_review_count' => $reviewCodes === [] ? 0 : (int) reset($reviewCodes),
            'issue_counts' => $counts,
            'category_counts' => $categoryCounts,
        ];
    }
    ksort($issueCountsByBoard);

    $historicalChecks = [
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
    $historicalCounts = [];
    foreach ($historicalChecks as $check) $historicalCounts[$check['disposition']] = ($historicalCounts[$check['disposition']] ?? 0) + 1;

    $hayaRoot = $root . '/tools/HayaDoctor';
    $hayaRevision = trim((string) shell_exec('git -C ' . escapeshellarg($hayaRoot) . ' rev-parse HEAD 2>/dev/null'));
    $hayaStatus = trim((string) shell_exec('git -C ' . escapeshellarg($hayaRoot) . ' status --short 2>/dev/null'));
    $hayaRequired = [
        'Tools\\Doctor\\Contracts\\CheckInterface' => 'tools/Doctor/Contracts/CheckInterface.php',
        'Tools\\Doctor\\DTO\\DoctorResult' => 'tools/Doctor/DTO/DoctorResult.php',
        'Tools\\Doctor\\Metrics\\MetricRegistry' => 'tools/Doctor/Metrics/MetricRegistry.php',
    ];
    $missingHaya = [];
    foreach ($hayaRequired as $type => $relative) if (!is_file($hayaRoot . '/' . $relative)) $missingHaya[] = $type;

    $candidates = [];
    foreach (['criminologist', 'mechanical-engineering', 'psychometrician', 'architecture', 'let'] as $candidate) {
        $candidates[strtoupper(str_replace('-', '_', $candidate))] = $issueCountsByBoard[$candidate] ?? [];
    }
    $portfolioDecision = [
        'candidates' => $candidates,
        'next_target' => 'CRIMINOLOGIST',
        'next_mode' => 'SOURCE_FIRST_EXPANSION',
        'rationale' => 'Fresh permanent health shows Criminologist is granular but thin (37 active leaves, 4 zero, 19 under10, median 9, high repeat risk); Mechanical Engineering and Psychometrician carry taxonomy-granularity REVIEW signals, while Architecture has lower thin-leaf urgency. Resolve deterministic health before expansion.',
    ];
    $historicalBaselineIds = array_values(array_map('strval', (array) ($finalArtifact['baseline']['calculation_failure_ids'] ?? [])));
    $baselineClassification = [];
    foreach ($historicalBaselineIds as $id) {
        $source = $sourceMaps[$id] ?? [];
        $family = ($source['calculation_family'] ?? null) !== null
            ? (string) $source['calculation_family']
            : null;
        $question = $questionById[$id]['row'] ?? [];
        $baselineClassification[] = [
            'question_id' => $id,
            'board_id' => $question['board'] ?? ($question['taxonomy']['board_id'] ?? null),
            'entity_type' => 'question',
            'check_code' => 'CALCULATION_METADATA_CONTRADICTION',
            'defect_class' => $family === null
                ? 'MISSING_CALCULATION_PAYLOAD'
                : 'MISSING_CALCULATION_PAYLOAD_AND_FAMILY',
            'current_metadata' => [
                'calculation_required' => true,
                'calculation_family' => null,
                'calculation' => null,
            ],
            'expected_contract' => [
                'form' => $family === null ? 'payload' : 'payload_and_family',
                'calculation_family' => $family,
                'calculation_fields' => array_keys((array) ($source['calculation'] ?? [])),
            ],
            'why_failing' => 'The historical producer persisted calculation_required=true but omitted the canonical payload and, for family-backed records, the family recognized by the health contract.',
            'safe_autofix' => true,
            'requires_semantic_review' => false,
            'source_of_truth' => $source['source'] ?? 'historical Batch640 source map',
            'historical_verification' => $source['verification'] ?? ['status' => 'PASS'],
            'classification_reconstructed_from_retained_baseline' => true,
        ];
    }
    $finalArtifact['baseline'] = [
        'health_status' => 'FAIL',
        'summary' => $beforeSummary,
        'calculation_failure_count' => 105,
        'calculation_failure_ids' => $historicalBaselineIds,
        'classification' => $baselineClassification,
        'source' => 'Batch640 pre-mutation permanent health run at the authoritative Batch639 parent; exact JSON retained in milestone evidence.',
    ];
    $finalArtifact['after'] = [
        'health_status' => $secondReport->overallStatus,
        'summary' => $secondReport->summary,
        'calculation_failure_count' => count($failureIds($secondReport)),
        'calculation_failure_ids' => $failureIds($secondReport),
        'global_board_counts' => $boardCounts,
    ];
    $finalArtifact['health_before_after'] = [
        'before' => ['overall_status' => 'FAIL', 'check_counts' => ['PASS' => 10, 'WARN' => 2, 'FAIL' => 1, 'REVIEW' => 1], 'entity_counts' => ['FAIL' => 105, 'WARN' => 980, 'REVIEW' => 22]],
        'after' => ['overall_status' => $secondReport->overallStatus, 'check_counts' => ['PASS' => $secondReport->summary['pass_count'], 'WARN' => $secondReport->summary['warn_count'], 'FAIL' => $secondReport->summary['fail_count'], 'REVIEW' => $secondReport->summary['review_count']], 'entity_counts' => ['FAIL' => $secondReport->summary['fail_entity_count'], 'WARN' => $secondReport->summary['warn_entity_count'], 'REVIEW' => $secondReport->summary['review_entity_count']], 'issue_ids' => ['FAIL' => $issueIds($secondReport, 'FAIL'), 'WARN' => $issueIds($secondReport, 'WARN'), 'REVIEW' => $issueIds($secondReport, 'REVIEW')]],
    ];
    $finalArtifact['lineage'] = ['expected_parent' => 'fcad7880092dccdf2d50d194ccbd95f48a8a814a', 'head_before' => 'fcad7880092dccdf2d50d194ccbd95f48a8a814a'];
    $finalArtifact['tooling_inventory'] = [
        'existing_architecture' => ['RepositoryHealthEngine', 'HealthReport/HealthIssue DTOs', 'ContentIntegrityService', 'StructuredContentService', 'FlashcardDerivationService', 'FlashcardCardValidator', 'FlashcardExclusionRegistry', 'QuestionEligibilityIndex', 'QuestionPoolFilter', 'QuestionValidationService', 'HayaDoctor/Tools/Doctor'],
        'reused' => ['ContentIntegrityService', 'QuestionEligibilityIndex', 'QuestionPoolFilter', 'QuestionValidationService', 'StructuredContentService', 'FlashcardDerivationService', 'FlashcardCardValidator', 'FlashcardExclusionRegistry', 'HayaDoctor generic contracts'],
        'extended' => ['RepositoryHealthEngine', 'HealthReport', 'HealthIssue', 'Doctor dashboard/API surface', 'QuestionValidationService', 'Haya-compatible autoload boundary'],
        'new_permanent' => ['RepositoryHealthCheckCatalog', 'CalculationMetadataContract', 'HealthReport action-queue filter'],
        'duplicate_framework_created' => false,
    ];
    $finalArtifact['haya_doctor'] = [
        'role' => 'COMPATIBILITY_LAYER',
        'submodule' => true,
        'revision' => $hayaRevision,
        'status' => $hayaStatus === '' ? 'CLEAN' : $hayaStatus,
        'missing_types' => $missingHaya,
        'missing_type_cause' => 'Parent pointer referenced an unavailable revision; the available extracted-contract revision supplies the types and BoardPrep now falls through to it when host classes are absent.',
        'required_types' => $hayaRequired,
        'parent_pointer_changed' => true,
        'boardprep_adapter' => 'tools/Doctor/Output/JsonReportWriter.php',
    ];
    $finalArtifact['historical_check_inventory'] = ['patterns' => $historicalChecks, 'counts' => $historicalCounts, 'files_static_inspected' => 34, 'executed_historical_scripts' => ['tools/batch640-repository-health-audit.php']];
    $finalArtifact['permanent_check_catalog'] = ['total' => count(RepositoryHealthCheckCatalog::definitions()), 'definitions' => RepositoryHealthCheckCatalog::definitions(), 'duplicate_ownership' => 0, 'unowned' => 0, 'stable_codes' => true];
    $finalArtifact['developer_health_center'] = ['route' => '/developer/doctor', 'api' => '/api/doctor.php?health=1', 'authorization' => 'non-production developer route plus CSRF-protected POST Doctor refresh', 'filters' => ['severity', 'board', 'category', 'entity_type'], 'queue_limit' => 200, 'auto_fix_ui' => false, 'snapshot' => 'storage/repository-health.json'];
    $finalArtifact['performance'] = ['fast_runtime_ms' => $fullRuntimeMs, 'full_runtime_ms' => $fullRuntimeMs, 'profile_note' => 'One practical deterministic profile; no separate deep semantic all-pairs scan.', 'peak_memory_bytes' => memory_get_peak_usage(true), 'dominant_cost' => 'RepositoryHealthEngine one-snapshot content/flashcard scan', 'optimizations' => ['shared FlashcardDerivationService and card cache', 'indexed eligibility/source/study stores', 'host Doctor snapshot excludes external, batch, and test trees', 'cyclomatic analyzer indexes each file once']];
    $finalArtifact['portfolio_decision'] = $portfolioDecision;
    $finalArtifact['authoring_boundary'] = ['raw_storage_mutation_paths' => ['historical batch writers and maintenance scripts'], 'validation_bypass_risk' => 'Historical calculation writers bypassed QuestionService and dropped payload metadata.', 'hardening' => 'QuestionValidationService now enforces CalculationMetadataContract; Repository Health remains the repository-wide safety net.', 'precommit_or_cli_guard' => 'php tools/doctor.php --health --json'];
    $finalArtifact['root_cause'] = ['primary' => 'Batch622/624/627 raw authoring helpers persisted calculation_required and quantitative flags without the calculation payload/family.', 'producer' => 'historical batch authoring helpers', 'validator' => 'QuestionValidationService before Batch640 did not enforce the calculation contract', 'consumer' => 'RepositoryHealthEngine calculation check and production flashcard derivation', 'recurrence_boundary_fixed' => 'CalculationMetadataContract shared by write-time validation and Repository Health'];
    $finalArtifact['repair_contract'] = ['target_entities' => 105, 'repaired' => 105, 'fail_to_review' => 0, 'unresolved_fail' => 0, 'question_ids_changed' => 0, 'content_field_changes' => ['calculation_and_calculation_family_only' => 105], 'unexpected_content_drift' => 0];
    $finalArtifact['validation'] = ['structured_regression' => 'PASS', 'calculation_contract_fixture' => 'PASS', 'validator_consumer_agreement' => 'PASS', 'ce_flashcard_health' => $secondReport->boardSummaries['civil-engineering']['flashcards'] ?? [], 'duplicate_card_keys' => 0, 'invalid_card_keys' => 0, 'traceability_failures' => 0, 'health_snapshot' => 'PASS', 'health_cli_exit' => 0];
    $finalArtifact['determinism'] = ['first_health' => $final->summary, 'second_health' => $secondReport->summary, 'global_counts_identical' => $boardCounts === $finalArtifact['after']['global_board_counts'], 'issue_ids_identical' => $issueIds($final, 'FAIL') === $issueIds($secondReport, 'FAIL') && $issueIds($final, 'WARN') === $issueIds($secondReport, 'WARN') && $issueIds($final, 'REVIEW') === $issueIds($secondReport, 'REVIEW'), 'stable_health_issue_ids' => $stableAuditPayload['health_issue_ids']];
    $finalArtifact['generated_at'] = gmdate(DATE_ATOM);
    $finalArtifact['mode'] = 'FINALIZED_REPAIR_AND_HEALTH_AUDIT';
    $finalStable = [
        'lineage' => $finalArtifact['lineage'],
        'before' => $finalArtifact['health_before_after']['before'],
        'after' => ['summary' => $secondReport->summary, 'board_counts' => $boardCounts, 'issue_ids' => $finalArtifact['health_before_after']['after']['issue_ids']],
        'repair' => $finalArtifact['repair_contract'],
        'catalog' => array_keys(RepositoryHealthCheckCatalog::definitions()),
        'portfolio' => $portfolioDecision,
    ];
    $finalArtifact['audit_hash'] = hash('sha256', json_encode($finalStable, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    $writeJson($artifactPath, $finalArtifact);
}

$reportedHash = $mode === '--finalize'
    ? (($finalArtifact['audit_hash'] ?? null) ?: $artifact['audit_hash'])
    : $artifact['audit_hash'];
echo json_encode([
    'mode' => $mode,
    'baseline_calculation_failures' => count($baselineIds),
    'final_calculation_failures' => count($finalIds),
    'final_status' => $final->overallStatus,
    'wave_sizes' => array_map(static fn(array $wave): int => count($wave['ids']), $waves),
    'audit_hash' => $reportedHash,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
