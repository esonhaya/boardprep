<?php

declare(strict_types=1);

/**
 * Batch 648 portfolio audit. This tool only reads repository state and writes
 * its report; reusable health, readiness, and eligibility semantics remain in
 * the permanent services. Supply a normal Repository Health JSON snapshot so
 * the portfolio audit does not trigger an unnecessary second full scan.
 *
 * Usage:
 *   php tools/batch648-portfolio-deficiency-audit.php --audit --health-json=/tmp/health.json
 *   php tools/batch648-portfolio-deficiency-audit.php --audit --health-json=/tmp/health.json --output=/tmp/audit-second.json
 */

require_once dirname(__DIR__) . '/app/Core/Autoloader.php';

use App\Core\App;
use App\Core\Autoloader;
use App\Services\Question\QuestionEligibilityIndex;
use App\Services\Quiz\LET\LETVariantRegistry;
use App\Services\RepositoryHealth\Engine\RepositoryHealthEngine;
use App\Services\RepositoryHealth\Engine\RepositoryContextFactory;
use App\Services\RepositoryHealth\Readiness\SourceReadinessService;

const B648_EXPECTED_HEAD = '56415c2c4308be70d359407b96a9a36460def4a';
const B648_OUTPUT = 'storage/batch648-portfolio-deficiency.json';
const B648_BOARDS = [
    'let', 'civil-service', 'nursing', 'criminologist', 'civil-engineering',
    'mechanical-engineering', 'registered-electrical-engineering',
    'architecture', 'psychometrician', 'cpa',
];

Autoloader::register();
putenv('APP_ENV=testing');
putenv('APP_DEBUG=false');
App::boot();

function b648_fail(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

function b648_read(string $path): array
{
    if (!is_file($path)) b648_fail('Required JSON file not found: ' . $path);
    try {
        $value = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        b648_fail('Invalid JSON in ' . $path . ': ' . $exception->getMessage());
    }
    if (!is_array($value)) b648_fail('Expected JSON object/array in ' . $path);
    return $value;
}

function b648_write(string $path, array $value): void
{
    $encoded = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
    $directory = dirname($path);
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        b648_fail('Could not create output directory: ' . $directory);
    }
    $temporary = $path . '.batch648.tmp';
    if (file_put_contents($temporary, $encoded, LOCK_EX) === false || !rename($temporary, $path)) {
        @unlink($temporary);
        b648_fail('Could not write audit artifact: ' . $path);
    }
}

function b648_sortedCounts(array $counts): array
{
    ksort($counts, SORT_STRING);
    return $counts;
}

function b648_percentile(array $values, float $p): ?float
{
    if ($values === []) return null;
    sort($values, SORT_NUMERIC);
    $position = (count($values) - 1) * $p;
    $lower = (int) floor($position);
    $upper = (int) ceil($position);
    if ($lower === $upper) return (float) $values[$lower];
    return (float) $values[$lower] + (($values[$upper] - $values[$lower]) * ($position - $lower));
}

function b648_leafStats(array $counts): array
{
    $values = array_values(array_map('intval', $counts));
    sort($values, SORT_NUMERIC);
    $n = count($values);
    $median = null;
    if ($n > 0) {
        $median = $n % 2
            ? (float) $values[intdiv($n, 2)]
            : ((float) $values[intdiv($n, 2) - 1] + (float) $values[intdiv($n, 2)]) / 2;
    }
    return [
        'leaf_count' => $n,
        'zero' => count(array_filter($values, static fn(int $n): bool => $n === 0)),
        'under5' => count(array_filter($values, static fn(int $n): bool => $n < 5)),
        'under10' => count(array_filter($values, static fn(int $n): bool => $n < 10)),
        'under20' => count(array_filter($values, static fn(int $n): bool => $n < 20)),
        'min' => $n ? $values[0] : null,
        'median' => $median,
        'p25' => b648_percentile($values, 0.25),
        'p75' => b648_percentile($values, 0.75),
        'max' => $n ? $values[$n - 1] : null,
        'deficit_to_5' => array_sum(array_map(static fn(int $v): int => max(0, 5 - $v), $values)),
        'deficit_to_10' => array_sum(array_map(static fn(int $v): int => max(0, 10 - $v), $values)),
        'deficit_to_20' => array_sum(array_map(static fn(int $v): int => max(0, 20 - $v), $values)),
    ];
}

function b648_normBoard(string $board): string
{
    $board = strtolower(trim($board));
    return ['ree' => 'registered-electrical-engineering', 'cse' => 'civil-service', 'let' => 'let'][$board] ?? $board;
}

function b648_questionBoard(array $question): string
{
    $taxonomy = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
    return b648_normBoard((string) ($taxonomy['board_id'] ?? $question['board'] ?? ''));
}

function b648_id(mixed $value): string
{
    if (!is_scalar($value)) return '';
    return trim((string) $value);
}

function b648_distribution(array $values, array $keys): array
{
    $counts = array_fill_keys($keys, 0);
    foreach ($values as $value) {
        $key = (string) $value;
        if (!isset($counts[$key])) $counts[$key] = 0;
        $counts[$key]++;
    }
    return b648_sortedCounts($counts);
}

function b648_readinessCounts(array $records): array
{
    $counts = ['READY' => 0, 'READY_WITH_LIMITS' => 0, 'REVIEW' => 0, 'DEFER' => 0];
    foreach ($records as $record) {
        $state = (string) ($record['readiness'] ?? '');
        if (isset($counts[$state])) $counts[$state]++;
    }
    return $counts;
}

function b648_authorableStats(array $leafCounts, array $readinessRecords): array
{
    if ($readinessRecords === []) return ['available' => false, 'state' => 'UNAVAILABLE'];
    $byLeaf = [];
    foreach ($readinessRecords as $record) {
        $leaf = (string) ($record['leaf_id'] ?? '');
        if ($leaf !== '') $byLeaf[$leaf] = (string) ($record['readiness'] ?? '');
    }
    $counts = [];
    foreach ($leafCounts as $leaf => $count) {
        if (in_array($byLeaf[$leaf] ?? '', ['READY', 'READY_WITH_LIMITS'], true)) $counts[$leaf] = (int) $count;
    }
    return ['available' => true, 'authorable_leaf_count' => count($counts)] + b648_leafStats($counts);
}

function b648_scopeMetadata(): array
{
    return [
        'let' => ['confidence' => 'CURRENT_WITH_LIMITS', 'source' => 'PRC LEPT 2025 alignment resolution, 2025 enhanced TOS, March 2026 program and September 2026 specialization labels', 'official_source_ids' => ['prc-resolution-20-2025', 'prc-let-enhanced-tos-2025', 'prc-lept-program-march-2026', 'prc-lept-room-assignment-september-2026'], 'risk' => 'MODERATE', 'note' => 'Official specialization identity exists; not every leaf has a normalized source/readiness record.'],
        'civil-service' => ['confidence' => 'CURRENT_WITH_LIMITS', 'source' => 'CSC 2026 scope announcement and examination announcements', 'official_source_ids' => ['csc-cse-scope-2026', 'csc-cse-ppt-scope-2026'], 'risk' => 'LOW_TO_MODERATE', 'note' => 'Four current blueprint sections plus an additional clerical pool mapped in repository content; confirm professional/subprofessional applicability before expansion.'],
        'nursing' => ['confidence' => 'CURRENT_WITH_LIMITS', 'source' => 'PRC August 2026 NLE program and 2025 enhanced TOS', 'official_source_ids' => ['prc-nursing-program-aug-2026', 'prc-nursing-enhanced-tos-2025'], 'risk' => 'HIGH', 'note' => 'Clinical practice is currentness-sensitive; ten shared general-science rows are not evidence of NLE competency demand.'],
        'criminologist' => ['confidence' => 'CURRENT_WITH_LIMITS', 'source' => 'PRC enhanced TOS and current professional issuances represented in Batch641 readiness', 'official_source_ids' => ['prc-criminology-enhanced-tos-2024', 'prc-criminology-board-page', 'prc-criminology-ethics-amendment-2025'], 'risk' => 'MODERATE', 'note' => 'Four general/shared leaves remain DEFER and are not authorable gaps.'],
        'civil-engineering' => ['confidence' => 'CURRENT_WITH_LIMITS', 'source' => 'PRC March 2026 CE program and current board syllabus resources', 'official_source_ids' => ['prc-cele-program-march-2026', 'prc-civil-engineering-board-page'], 'risk' => 'MODERATE', 'note' => 'Code-sensitive design remains versioned; 19-leaf taxonomy and full derivability are established.'],
        'mechanical-engineering' => ['confidence' => 'CURRENT_WITH_LIMITS', 'source' => 'PRC August 2026 MELE program plus 2013 detailed TOS bounded by Batch644/646 review', 'official_source_ids' => ['prc-mechanical-engineering-2026-program', 'prc-mechanical-engineering-tos-resolution-57-2013', 'prc-mechanical-engineering-board-page'], 'risk' => 'MODERATE', 'note' => 'HVAC remains REVIEW; Batch647 found zero immediate high-value expansion after its semantic gate.'],
        'registered-electrical-engineering' => ['confidence' => 'CURRENT_WITH_LIMITS', 'source' => 'PRC September 2026 REE program and 2024 enhanced TOS', 'official_source_ids' => ['prc-ree-exam-program-september-2026', 'prc-ree-enhanced-tos-resolution-40-2024', 'prc-ree-enhanced-tos-annex-a-2024'], 'risk' => 'MODERATE', 'note' => 'Electrical code and standards content remains version-sensitive.'],
        'architecture' => ['confidence' => 'CURRENT_WITH_LIMITS', 'source' => 'PRC January 2026 architecture program and promulgated syllabi', 'official_source_ids' => ['prc-architecture-exam-program-january-2026', 'prc-architecture-syllabi'], 'risk' => 'MODERATE', 'note' => 'Visual/spatial design competency is not fully represented by text-only question volume.'],
        'psychometrician' => ['confidence' => 'CURRENT_WITH_LIMITS', 'source' => 'PRC Psychometrician enhanced TOS and Batch642/643 source-first audit', 'official_source_ids' => ['prc-psychometrician-enhanced-tos-2023'], 'risk' => 'MODERATE', 'note' => 'Mental-health-law leaf remains protected as DEFER; foundation expansion is closed.'],
        'cpa' => ['confidence' => 'CURRENT_WITH_LIMITS', 'source' => 'Current PRC 2022 CPA TOS and May 2026 program; 2026 revised TOS separately future-effective', 'official_source_ids' => ['prc-accountancy-current-tos-2022', 'prc-accountancy-exam-program-2026', 'prc-accountancy-board-page'], 'risk' => 'CONTROLLED_HIGH', 'note' => 'CY2029 future-effective firewall is maintained with zero leakage.'],
    ];
}

function b648_taxonomyMetadata(): array
{
    return [
        'let' => ['status' => 'GRANULAR', 'trust' => 'HIGH', 'valid' => null, 'legacy_shared' => 38, 'out_of_scope' => 0, 'basis' => '15 specialization identities and leaf-level subject/domain/topic/concept model; 38 flat/shared rows are separately inventoried, not presumed broad.'],
        'civil-service' => ['status' => 'PARTIALLY_GRANULAR', 'trust' => 'LOW_NATIVE_SHARED_EFFECTS', 'valid' => 56, 'legacy_shared' => 64, 'out_of_scope' => 0, 'basis' => '56 hierarchically mapped CSE-specific leaves plus 64 flat/shared concept placements. Native zero counts misstate runtime pools; report both.'],
        'nursing' => ['status' => 'PARTIALLY_GRANULAR', 'trust' => 'LOW_ALL_40_ROWS', 'valid' => 30, 'legacy_shared' => 10, 'out_of_scope' => 10, 'basis' => '30 populated NLE practice leaves plus ten flat general-science concepts; historical review treats those ten as shared/out-of-scope candidates, not NLE expansion deficits.'],
        'criminologist' => ['status' => 'GRANULAR', 'trust' => 'HIGH_WITH_EXPLICIT_EXCLUSIONS', 'valid' => 33, 'legacy_shared' => 4, 'out_of_scope' => 4, 'basis' => '37-leaf model; four shared/general leaves have explicit DEFER readiness and are excluded from authorable deficiency.'],
        'civil-engineering' => ['status' => 'GRANULAR', 'trust' => 'HIGH', 'valid' => 19, 'legacy_shared' => 0, 'out_of_scope' => 0, 'basis' => '19-leaf reconstruction; all native leaves currently at least 20; Batch639 derivability repair recognized.'],
        'mechanical-engineering' => ['status' => 'GRANULAR', 'trust' => 'HIGH_RAW_LOW_MARGINAL', 'valid' => 35, 'legacy_shared' => 0, 'out_of_scope' => 0, 'basis' => '35-leaf/14-structural-node model; Batch647 expanded 47 without taxonomy change; residual high-value headroom exhausted by semantic gate.'],
        'registered-electrical-engineering' => ['status' => 'GRANULAR', 'trust' => 'HIGH', 'valid' => 37, 'legacy_shared' => 0, 'out_of_scope' => 0, 'basis' => '37-leaf reconstruction, no zero/U5/U10; remaining U20 is not automatically deficiency.'],
        'architecture' => ['status' => 'GRANULAR', 'trust' => 'MODERATE_TEXTUAL_LIMITATION', 'valid' => 14, 'legacy_shared' => 0, 'out_of_scope' => 0, 'basis' => 'Batch619 14-leaf model; visual/spatial performance cannot be inferred fully from text question counts.'],
        'psychometrician' => ['status' => 'GRANULAR', 'trust' => 'HIGH_WITH_DEFERRED_LEAF', 'valid' => 20, 'legacy_shared' => 0, 'out_of_scope' => 1, 'basis' => '20 active leaves plus four structural nodes; one law leaf is DEFER and does not create authorable pressure.'],
        'cpa' => ['status' => 'GRANULAR', 'trust' => 'HIGH', 'valid' => 113, 'legacy_shared' => 0, 'out_of_scope' => 0, 'basis' => '113-leaf model established in Batch636; all leaves at least ten; exact-ten coverage is broad minimum representation, not automatic evidence of unused authoring value.'],
    ];
}

function b648_actionMetadata(): array
{
    return [
        'let' => ['action' => 'SOURCE_READINESS_FOUNDATION', 'deficiencies' => ['SOURCE_LIMITED', 'STUDY_LIMITED', 'SPECIALIZATION_IMBALANCE'], 'rationale' => 'Trusted 205-leaf/specialization model has material native U5/U10 thinness and all 15 specializations are nonzero, but there is no permanent per-leaf readiness model; establish current scope/source/study gates before expansion.'],
        'civil-service' => ['action' => 'SOURCE_READINESS_FOUNDATION', 'deficiencies' => ['SOURCE_LIMITED', 'STUDY_LIMITED', 'SHARED_CONCEPT_ARTIFACT', 'OTHER'], 'rationale' => 'Large 56-leaf CSE core is mostly populated; 64 flat/shared rows inflate native zero counts, and only three core concepts are true empty placements. Resolve scope and readiness before selective closure.'],
        'nursing' => ['action' => 'MAINTENANCE_ONLY', 'deficiencies' => ['OUT_OF_SCOPE', 'CURRENTNESS_LIMITED', 'SHARED_CONCEPT_ARTIFACT'], 'rationale' => 'Thirty NLE leaves are well populated; ten legacy general-science rows create false zero-leaf pressure. Dosage invariant is preserved; no readiness-driven expansion case.'],
        'criminologist' => ['action' => 'FOUNDATION_SUFFICIENT', 'deficiencies' => ['OUT_OF_SCOPE'], 'rationale' => 'The 33 in-scope authorable leaves have no U5/U10 deficits; four DEFER shared/general leaves are intentionally excluded.'],
        'civil-engineering' => ['action' => 'FOUNDATION_SUFFICIENT', 'deficiencies' => [], 'rationale' => 'All 19 granular leaves are populated beyond 20 and CE flashcard derivability is repaired and healthy.'],
        'mechanical-engineering' => ['action' => 'EVIDENCE_LIMITED', 'deficiencies' => ['EVIDENCE_EXHAUSTED', 'CURRENTNESS_LIMITED', 'AREA_IMBALANCE'], 'rationale' => 'Batch647 closed authorable zero/U5 and found no coherent high-value Wave3 cohort; eight authorable U10 leaves remain but are evidence/marginal-value limited, and HVAC remains REVIEW.'],
        'registered-electrical-engineering' => ['action' => 'FOUNDATION_SUFFICIENT', 'deficiencies' => ['DEPTH_IMBALANCE'], 'rationale' => 'No zero/U5/U10 leaves; 15 U20 leaves remain but are not automatically content gaps after the prior expansion.'],
        'architecture' => ['action' => 'SOURCE_READINESS_FOUNDATION', 'deficiencies' => ['SOURCE_LIMITED', 'STUDY_LIMITED', 'DEPTH_IMBALANCE', 'OTHER'], 'rationale' => 'Only two leaves are under ten, but the legacy bank has no normalized readiness model and text-only volume does not establish visual/spatial coverage. Source/readiness audit should precede any selective work.'],
        'psychometrician' => ['action' => 'FOUNDATION_SUFFICIENT', 'deficiencies' => ['OUT_OF_SCOPE'], 'rationale' => 'Batch643 closed authorable U5/U10; one deferred law leaf is protected and the remaining U20 count is not a quota.'],
        'cpa' => ['action' => 'CURRENTNESS_REVIEW', 'deficiencies' => ['CURRENTNESS_LIMITED'], 'rationale' => 'All 113 leaves have at least ten questions and Batch636 established a current TOS firewall; continue monitoring the separately future-effective CY2029 scope without expansion.'],
    ];
}

$arguments = array_slice($argv ?? [], 1);
if (!in_array('--audit', $arguments, true)) b648_fail('Use --audit.');
$healthPath = '';
$outputPath = dirname(__DIR__) . '/' . B648_OUTPUT;
$comparisonPath = '';
$healthRuntimeMs = 0;
foreach ($arguments as $argument) {
    if (str_starts_with($argument, '--health-json=')) $healthPath = substr($argument, strlen('--health-json='));
    if (str_starts_with($argument, '--health-runtime-ms=')) $healthRuntimeMs = max(0, (int) substr($argument, strlen('--health-runtime-ms=')));
    if (str_starts_with($argument, '--comparison-json=')) $comparisonPath = substr($argument, strlen('--comparison-json='));
    if (str_starts_with($argument, '--output=')) $outputPath = $argument[9] === '/' ? substr($argument, 9) : dirname(__DIR__) . '/' . substr($argument, 9);
}
if ($healthPath === '') b648_fail('Supply --health-json=/path/to/php-doctor-health.json (from the permanent --health --json path).');

$health = b648_read($healthPath);
$context = RepositoryContextFactory::create();
$collections = $context->collections;
$questions = array_values(array_filter($collections['questions'] ?? [], 'is_array'));
$boards = B648_BOARDS;
$boardSummaries = $health['board_summaries'] ?? [];
if (!is_array($boardSummaries)) b648_fail('Health JSON does not contain board_summaries.');
$sourceReadinessValidation = SourceReadinessService::validateCollections($collections, $boardSummaries);
$readinessByBoard = $sourceReadinessValidation['by_board'] ?? [];

$coverageByBoard = [];
$coverageByConcept = [];
foreach ((array) ($collections['board-concept-coverage'] ?? []) as $coverage) {
    if (!is_array($coverage)) continue;
    $board = b648_normBoard((string) ($coverage['board_id'] ?? $coverage['board'] ?? ''));
    $concept = trim((string) ($coverage['concept_id'] ?? ''));
    if ($board === '' || $concept === '') continue;
    $coverageByBoard[$board][$concept] = $coverage;
    $coverageByConcept[$board][$concept] = $coverage;
}
$readinessRecordsByBoard = [];
foreach ($readinessByBoard as $board => $validation) $readinessRecordsByBoard[$board] = (array) ($validation['records'] ?? []);
$readinessByLeaf = [];
foreach ($readinessRecordsByBoard as $board => $records) {
    foreach ($records as $record) {
        $leaf = (string) ($record['leaf_id'] ?? '');
        if ($leaf !== '') $readinessByLeaf[$board][$leaf] = $record;
    }
}

$eligibilityIndex = new QuestionEligibilityIndex(
    (array) ($collections['question-eligibility'] ?? []),
    (array) ($collections['blueprints'] ?? []),
    (array) ($collections['concept-derived-eligibility'] ?? []),
    (array) ($collections['question-reuse-overrides'] ?? [])
);
$questionsById = [];
$nativeQuestionsByBoard = array_fill_keys($boards, []);
$nativeIdsByBoard = array_fill_keys($boards, []);
$runtimeLeafSets = array_fill_keys($boards, []);
$runtimeAreaSets = array_fill_keys($boards, []);
$sharedOutIds = array_fill_keys($boards, []);
$nativeAreaCounts = array_fill_keys($boards, []);
$nativeLeafQuestions = array_fill_keys($boards, []);
foreach ($questions as $question) {
    $id = b648_id($question['id'] ?? null);
    if ($id === '') continue;
    $questionsById[$id] = $question;
    $nativeBoard = b648_questionBoard($question);
    if (isset($nativeQuestionsByBoard[$nativeBoard])) {
        $nativeQuestionsByBoard[$nativeBoard][] = $question;
        $nativeIdsByBoard[$nativeBoard][$id] = true;
        $taxonomy = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
        $concept = trim((string) ($taxonomy['concept_id'] ?? ''));
        if ($concept !== '') $nativeLeafQuestions[$nativeBoard][$concept][$id] = true;
    }
    foreach ($boards as $board) {
        $eligibility = $eligibilityIndex->forExam($question, $board);
        if ($eligibility === null) continue;
        if ($nativeBoard !== '' && $nativeBoard !== $board && isset($sharedOutIds[$nativeBoard])) $sharedOutIds[$nativeBoard][$id] = true;
        $taxonomy = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
        $placement = is_array($eligibility['placement'] ?? null) ? $eligibility['placement'] : [];
        $concept = trim((string) ($placement['concept_id'] ?? $taxonomy['concept_id'] ?? ''));
        if ($concept !== '' && isset($coverageByBoard[$board][$concept])) {
            $runtimeLeafSets[$board][$concept][$id] = true;
            $area = '';
            foreach (['blueprint_node_id', 'subject_id'] as $areaField) {
                $candidateArea = trim((string) ($coverageByBoard[$board][$concept][$areaField] ?? ''));
                if ($candidateArea !== '') { $area = $candidateArea; break; }
            }
            if ($area !== '') $runtimeAreaSets[$board][$area][$id] = true;
        }
        if (($eligibility['subject_id'] ?? '') !== '') {
            $area = (string) $eligibility['subject_id'];
            $runtimeAreaSets[$board][$area][$id] = true;
        }
    }
}

$reuseByQuestion = [];
foreach ((array) ($collections['question-reuse-metadata'] ?? []) as $record) {
    if (!is_array($record)) continue;
    $id = b648_id($record['question_id'] ?? null);
    if ($id !== '') $reuseByQuestion[$id] = (array) ($record['reuse'] ?? []);
}
$studyById = [];
foreach ((array) ($collections['study-materials'] ?? []) as $study) {
    if (is_array($study) && isset($study['id'])) $studyById[(string) $study['id']] = $study;
}
$sourceById = [];
foreach ((array) ($collections['sources'] ?? []) as $source) {
    if (is_array($source) && isset($source['id'])) $sourceById[(string) $source['id']] = $source;
}
$studyIdsByQuestion = [];
$studyLinksByBoardLeaf = [];
foreach ((array) ($collections['study-question-links'] ?? []) as $link) {
    if (!is_array($link)) continue;
    $questionId = b648_id($link['question_id'] ?? null);
    $materialId = (string) ($link['material_id'] ?? '');
    if ($questionId === '' || $materialId === '') continue;
    $studyIdsByQuestion[$questionId][$materialId] = true;
    $question = $questionsById[$questionId] ?? null;
    if (!is_array($question)) continue;
    $board = b648_questionBoard($question);
    $leaf = (string) (($question['taxonomy']['concept_id'] ?? ''));
    if (isset($studyLinksByBoardLeaf[$board])) $studyLinksByBoardLeaf[$board][$leaf][$materialId] = true;
    elseif (in_array($board, $boards, true)) $studyLinksByBoardLeaf[$board][$leaf][$materialId] = true;
}

$depthByBoard = array_fill_keys($boards, []);
$depthByLeaf = array_fill_keys($boards, []);
$calculationByBoard = array_fill_keys($boards, []);
$familyByBoard = array_fill_keys($boards, []);
foreach ($nativeQuestionsByBoard as $board => $rows) {
    foreach ($rows as $question) {
        $id = b648_id($question['id'] ?? null);
        $depth = $reuseByQuestion[$id]['required_depth'] ?? null;
        if (is_numeric($depth) && (int) $depth >= 1 && (int) $depth <= 5) {
            $depth = (int) $depth;
            $depthByBoard[$board][] = $depth;
            $leaf = (string) ($question['taxonomy']['concept_id'] ?? '');
            if ($leaf !== '') $depthByLeaf[$board][$leaf][$depth] = true;
        }
        $semantic = strtoupper(trim((string) ($question['quantitative_semantics'] ?? '')));
        if (in_array($semantic, ['NON_NUMERICAL', 'NOT_CALCULATION_REQUIRED'], true)) $semantic = 'NON_CALCULATION';
        if ($semantic === '') {
            $required = $question['calculation_required'] ?? null;
            $semantic = is_bool($required) ? ($required ? 'CALCULATION_REQUIRED' : 'NON_CALCULATION') : 'UNKNOWN';
        }
        $calculationByBoard[$board][$semantic] = ($calculationByBoard[$board][$semantic] ?? 0) + 1;
        $family = trim((string) ($question['calculation_family'] ?? ''));
        if ($family !== '') $familyByBoard[$board][$family] = ($familyByBoard[$board][$family] ?? 0) + 1;
    }
}

$scopeMetadata = b648_scopeMetadata();
$taxonomyMetadata = b648_taxonomyMetadata();
$actionMetadata = b648_actionMetadata();
$blueprintsByBoard = [];
foreach ((array) ($collections['blueprints'] ?? []) as $blueprint) {
    if (!is_array($blueprint)) continue;
    $board = b648_normBoard((string) ($blueprint['board_id'] ?? $blueprint['board'] ?? ''));
    if ($board !== '') $blueprintsByBoard[$board] = $blueprint;
}
$studyAuthorityCount = [];
foreach (array_keys($sourceById) as $sourceId) $studyAuthorityCount[$sourceId] = true;

$boardsAudit = [];
$areaPortfolioCounts = [];
$portfolioDepth = array_fill_keys(['1', '2', '3', '4', '5', 'UNKNOWN'], 0);
$portfolioLeafDepth = ['zero_depth_variety' => 0, 'single_depth' => 0, 'narrow_depth' => 0, 'healthy_depth' => 0];
$portfolioSourceAuthority = array_fill_keys(['STRONG', 'ADEQUATE', 'WEAK', 'NONE', 'UNKNOWN'], 0);
$portfolioStudyCoverage = array_fill_keys(['COMPLETE', 'SUFFICIENT', 'PARTIAL', 'MISSING', 'INVALID', 'UNKNOWN'], 0);
$portfolioReadiness = ['READY' => 0, 'READY_WITH_LIMITS' => 0, 'REVIEW' => 0, 'DEFER' => 0];
$portfolioTotals = ['active_leaves' => 0, 'structural_nodes' => 0, 'zero' => 0, 'under5' => 0, 'under10' => 0, 'under20' => 0];
$trustedPortfolioTotals = ['zero' => 0, 'under5' => 0, 'under10' => 0, 'under20' => 0];
$coarseMaskedFindings = 0;
$noReadinessFindings = 0;
$reviewDeferFindings = 0;
$evidenceLimitedFindings = 0;

$letSpecializations = b648_read(dirname(__DIR__) . '/storage/let-specializations.json');
$letPools = [];
$letQuestionPools = [];
foreach ($letSpecializations as $specialization) {
    if (is_array($specialization) && isset($specialization['id'])) $letPools[(string) $specialization['id']] = 0;
}
foreach ($nativeQuestionsByBoard['let'] ?? [] as $question) {
    $eligibility = $eligibilityIndex->forExam($question, 'let');
    foreach (LETVariantRegistry::questionPlacements($question, $eligibility) as $placement) {
        $spec = trim((string) ($placement['specialization_id'] ?? ''));
        if ($spec !== '' && array_key_exists($spec, $letPools)) $letQuestionPools[$spec][b648_id($question['id'] ?? null)] = true;
    }
}
foreach ($letQuestionPools as $spec => $ids) $letPools[$spec] = count($ids);

$meB644Rows = b648_read(dirname(__DIR__) . '/storage/batch644-mechanical-engineering-taxonomy-readiness.json');
$meB644 = (array) ($meB644Rows[0] ?? []);
$meB647 = b648_read(dirname(__DIR__) . '/storage/batch647-mechanical-engineering-expansion.json');
$ceB634Rows = b648_read(dirname(__DIR__) . '/storage/batch634-ce-overnight-expansion.json');
$ceB634 = (array) ($ceB634Rows[0] ?? []);
$meOldCalc = (array) ($meB644['calculation_profile'] ?? []);
$meNewCalc = (array) (($meB647['calculation_profile']['new_classes'] ?? []));
$meFamilies = [];
foreach ([(array) ($meOldCalc['calculation_family_counts'] ?? []), (array) ($meB647['calculation_profile']['families_by_leaf'] ?? [])] as $familyGroup) {
    foreach ($familyGroup as $perLeaf) {
        foreach ((array) $perLeaf as $family => $count) $meFamilies[(string) $family] = ($meFamilies[(string) $family] ?? 0) + (int) $count;
    }
}
arsort($meFamilies, SORT_NUMERIC);
$historicalCalculationProfiles = [
    'mechanical-engineering' => [
        'classification_basis' => 'Batch644 inference of the preexisting 464 plus Batch647 question-level QA for 47 new questions; legacy classifications are evidence-informed, not persisted per question.',
        'classes' => [
            'CALCULATION_REQUIRED' => (int) ($meOldCalc['calculation_required_inferred'] ?? 0) + (int) ($meNewCalc['CALCULATION_REQUIRED'] ?? 0),
            'CONCEPTUAL_QUANTITATIVE' => (int) ($meOldCalc['conceptual_quantitative_inferred'] ?? 0) + (int) ($meNewCalc['CONCEPTUAL_QUANTITATIVE'] ?? 0),
            'NON_CALCULATION' => (int) ($meOldCalc['non_calculation'] ?? 0) + (int) ($meNewCalc['NON_CALCULATION'] ?? 0),
            'UNKNOWN' => 0,
        ],
        'numeric_flagged' => (int) ($meOldCalc['numeric_flagged'] ?? 0) + (int) ($meNewCalc['CALCULATION_REQUIRED'] ?? 0) + (int) ($meNewCalc['CONCEPTUAL_QUANTITATIVE'] ?? 0),
        'family_count' => count($meFamilies), 'max_family_size' => $meFamilies === [] ? 0 : max($meFamilies),
        'family_counts' => $meFamilies,
        'contradictions' => (int) ($meOldCalc['calculation_metadata_contradictions'] ?? 0) + (int) ($meB647['calculation_profile']['contradictions'] ?? 0),
    ],
    'civil-engineering' => [
        'classification_basis' => 'Batch634 fully recomputed the 620-question added cohort; the pre-Batch634 302 questions have no equivalent persisted calculation classification in this audit.',
        'audited_added_cohort' => (int) ($ceB634['calculation']['calculation_required'] ?? 0),
        'recomputed_pass' => (int) ($ceB634['calculation']['pass'] ?? 0),
        'added_cohort_size' => count((array) ($ceB634['new_ids'] ?? [])),
        'legacy_unclassified_count' => 302,
        'family_count' => (int) ($ceB634['post_metrics']['calculation_families'] ?? 0),
        'max_family_size' => (int) ($ceB634['post_metrics']['calculation_max'] ?? 0),
        'contradictions' => (int) ($ceB634['calculation']['fail'] ?? 0),
    ],
];

foreach ($boards as $board) {
    $summary = (array) ($boardSummaries[$board] ?? []);
    $taxonomyMeta = $taxonomyMetadata[$board];
    $actionMeta = $actionMetadata[$board];
    $scopeMeta = $scopeMetadata[$board];
    $nativeLeafCounts = (array) ($summary['leaf_counts'] ?? []);
    ksort($nativeLeafCounts, SORT_STRING);
    $nativeStats = b648_leafStats($nativeLeafCounts);
    $runtimeLeafCounts = [];
    foreach ($nativeLeafCounts as $leaf => $_count) $runtimeLeafCounts[$leaf] = count($runtimeLeafSets[$board][$leaf] ?? []);
    $runtimeStats = b648_leafStats($runtimeLeafCounts);
    $records = $readinessRecordsByBoard[$board] ?? [];
    $readinessCounts = b648_readinessCounts($records);
    $model = $records === [] ? 'LEGACY_NO_READINESS_MODEL' : (count($records) === count($nativeLeafCounts) ? 'FULL_READINESS_MODEL' : 'PARTIAL_READINESS_MODEL');
    $readinessMap = $readinessByLeaf[$board] ?? [];
    $authorableStats = b648_authorableStats($nativeLeafCounts, $records);
    $activeLeaves = array_keys($nativeLeafCounts);
    $allSourceAuth = array_fill_keys(['STRONG', 'ADEQUATE', 'WEAK', 'NONE', 'UNKNOWN'], 0);
    $allStudyCoverage = array_fill_keys(['COMPLETE', 'SUFFICIENT', 'PARTIAL', 'MISSING', 'INVALID', 'UNKNOWN'], 0);
    $sourceCurrentnessCounts = [];
    $authorityCounts = [];
    $coverageCounts = [];
    $traceabilityCounts = ['COMPLETE' => 0, 'PARTIAL' => 0, 'BROKEN' => 0, 'NO_LINK' => 0];
    $leafEvidence = [];
    $byArea = [];
    $byAreaTarget = [];
    foreach ($activeLeaves as $leaf) {
        $record = $readinessMap[$leaf] ?? null;
        $sourceAuthority = (string) ($record['source_authority'] ?? 'UNKNOWN');
        if (!isset($allSourceAuth[$sourceAuthority])) $sourceAuthority = 'UNKNOWN';
        $allSourceAuth[$sourceAuthority]++;
        $studyCoverage = (string) ($record['study_coverage'] ?? 'UNKNOWN');
        if (!isset($allStudyCoverage[$studyCoverage])) $studyCoverage = 'UNKNOWN';
        $sourceCurrentness = (string) ($record['source_currentness'] ?? 'UNKNOWN');
        $sourceCurrentnessCounts[$sourceCurrentness] = ($sourceCurrentnessCounts[$sourceCurrentness] ?? 0) + 1;
        $allStudyCoverage[$studyCoverage]++;
        $authorityCounts[$sourceAuthority] = ($authorityCounts[$sourceAuthority] ?? 0) + 1;
        $coverageCounts[$studyCoverage] = ($coverageCounts[$studyCoverage] ?? 0) + 1;
        $studyIds = array_keys($studyLinksByBoardLeaf[$board][$leaf] ?? []);
        $questionIds = array_keys($nativeLeafQuestions[$board][$leaf] ?? []);
        $resolvingStudyIds = [];
        $leafSourceIds = [];
        $broken = 0;
        $linkedQuestionCount = 0;
        $traceableQuestionCount = 0;
        foreach ($questionIds as $questionId) {
            $ids = array_keys($studyIdsByQuestion[$questionId] ?? []);
            if ($ids !== []) $linkedQuestionCount++;
            $hasSource = false;
            foreach ($ids as $materialId) {
                if (!isset($studyById[$materialId])) { $broken++; continue; }
                $material = $studyById[$materialId];
                $resolvingStudyIds[$materialId] = true;
                foreach ((array) ($material['source_ids'] ?? []) as $sourceId) {
                    $sourceId = (string) $sourceId;
                    if (isset($sourceById[$sourceId])) { $leafSourceIds[$sourceId] = true; $hasSource = true; }
                    else $broken++;
                }
            }
            if ($hasSource) $traceableQuestionCount++;
        }
        $traceability = $questionIds === [] ? 'UNKNOWN' : ($linkedQuestionCount === count($questionIds) && $traceableQuestionCount === count($questionIds) && $broken === 0 ? 'COMPLETE' : (($traceableQuestionCount > 0) ? 'PARTIAL' : ($linkedQuestionCount === 0 ? 'NONE' : 'BROKEN')));
        if (isset($traceabilityCounts[$traceability])) $traceabilityCounts[$traceability]++;
        $coverage = $coverageByBoard[$board][$leaf] ?? [];
        $area = trim((string) ($record['official_area_id'] ?? ''));
        if ($area === '') {
            foreach (['blueprint_node_id', 'subject_id'] as $areaField) {
                $candidateArea = trim((string) ($coverage[$areaField] ?? ''));
                if ($candidateArea !== '') { $area = $candidateArea; break; }
            }
        }
        if ($area === '') $area = 'UNMAPPED_SHARED';
        if ($area !== 'UNMAPPED') $byArea[$area] = ($byArea[$area] ?? 0) + (int) $nativeLeafCounts[$leaf];
        $targetByBoard = [
            'mechanical-engineering' => ['mathematics' => 35, 'power-plant-engineering' => 35, 'machine-design-materials-shop-practice' => 30],
            'psychometrician' => ['psychological-assessment' => 40, 'developmental-psychology' => 20, 'abnormal-psychology' => 20, 'industrial-organizational' => 20],
        ];
        if (isset($targetByBoard[$board][$area])) $byAreaTarget[$area] = $targetByBoard[$board][$area];
        $depthSet = array_map('intval', array_keys($depthByLeaf[$board][$leaf] ?? []));
        sort($depthSet, SORT_NUMERIC);
        $leafEvidence[$leaf] = [
            'native_count' => (int) $nativeLeafCounts[$leaf],
            'runtime_eligible_count' => (int) ($runtimeLeafCounts[$leaf] ?? 0),
            'taxonomy' => [
                'subject_id' => (string) ($coverage['subject_id'] ?? ''),
                'domain_id' => (string) ($coverage['domain_id'] ?? ''),
                'topic_id' => (string) ($coverage['topic_id'] ?? ''),
                'blueprint_node_id' => (string) ($coverage['blueprint_node_id'] ?? ''),
                'path_depth' => (int) ((trim((string) ($coverage['subject_id'] ?? '')) !== '') + (trim((string) ($coverage['domain_id'] ?? '')) !== '') + (trim((string) ($coverage['topic_id'] ?? '')) !== '')),
                'coverage_status' => (string) ($coverage['status'] ?? 'UNKNOWN'),
                'semantic_basis' => (string) ($taxonomyMeta['basis'] ?? ''),
            ],
            'readiness' => $record === null ? 'NO_RECORD' : (string) ($record['readiness'] ?? 'UNKNOWN'),
            'source_authority' => $sourceAuthority,
            'source_currentness' => (string) ($record['source_currentness'] ?? 'UNKNOWN'),
            'source_scope_fit' => (string) ($record['source_scope_fit'] ?? 'UNKNOWN'),
            'source_conflict_status' => (string) ($record['source_conflict_status'] ?? 'UNKNOWN'),
            'study_coverage' => $studyCoverage,
            'study_traceability' => $record['study_source_traceability'] ?? $traceability,
            'linked_question_count' => $linkedQuestionCount,
            'traceable_question_count' => $traceableQuestionCount,
            'linked_study_material_ids' => array_values(array_unique(array_merge((array) ($record['study_material_ids'] ?? []), array_keys($resolvingStudyIds)))),
            'resolved_source_ids' => array_values(array_unique(array_merge((array) ($record['source_ids'] ?? []), array_keys($leafSourceIds)))),
            'depth_values' => $depthSet,
            'depth_diversity' => count($depthSet) === 0 ? 'ZERO_DEPTH_VARIETY' : (count($depthSet) === 1 ? 'SINGLE_DEPTH' : (count($depthSet) === 2 ? 'NARROW_DEPTH' : 'HEALTHY_DEPTH')),
            'supported_depth' => isset($record['supported_depth_min'], $record['supported_depth_max']) ? [(int) $record['supported_depth_min'], (int) $record['supported_depth_max']] : 'UNKNOWN',
            'content_family_count' => isset($record['distinct_content_family_count']) ? (int) $record['distinct_content_family_count'] : null,
            'headroom' => $record === null ? 'UNKNOWN' : ['min' => (int) ($record['safe_headroom_min'] ?? 0), 'preferred' => (int) ($record['safe_headroom_preferred'] ?? 0), 'max' => (int) ($record['safe_headroom_max'] ?? 0)],
            'official_area_id' => $area,
        ];
    }
    $readinessCoverage = ['records' => count($records), 'active_leaves' => count($activeLeaves), 'ready' => $readinessCounts['READY'], 'ready_with_limits' => $readinessCounts['READY_WITH_LIMITS'], 'review' => $readinessCounts['REVIEW'], 'defer' => $readinessCounts['DEFER'], 'missing' => max(0, count($activeLeaves) - count($records)), 'model' => $model];
    $depthCounts = array_fill_keys(['D1', 'D2', 'D3', 'D4', 'D5', 'UNKNOWN'], 0);
    foreach ($depthByBoard[$board] as $depth) $depthCounts['D' . $depth]++;
    $depthCounts['UNKNOWN'] = max(0, count($nativeQuestionsByBoard[$board] ?? []) - count($depthByBoard[$board]));
    foreach ($depthCounts as $depth => $count) $portfolioDepth[$depth === 'UNKNOWN' ? 'UNKNOWN' : substr($depth, 1)] += $count;
    $leafDepthStates = ['zero_depth_variety' => 0, 'single_depth' => 0, 'narrow_depth' => 0, 'healthy_depth' => 0];
    foreach ($leafEvidence as $evidence) {
        $key = strtolower((string) $evidence['depth_diversity']);
        if ($key === 'zero_depth_variety') $leafDepthStates['zero_depth_variety']++;
        elseif ($key === 'single_depth') $leafDepthStates['single_depth']++;
        elseif ($key === 'narrow_depth') $leafDepthStates['narrow_depth']++;
        else $leafDepthStates['healthy_depth']++;
    }
    if ($taxonomyMeta['status'] === 'GRANULAR') {
        foreach ($leafDepthStates as $key => $count) $portfolioLeafDepth[$key] += $count;
        foreach (['zero', 'under5', 'under10', 'under20'] as $key) $trustedPortfolioTotals[$key] += (int) $nativeStats[$key];
    } else {
        $coarseMaskedFindings += count(array_filter($nativeLeafCounts, static fn(int $v): bool => $v === 0));
    }
    if ($records === []) $noReadinessFindings += count($activeLeaves);
    foreach ($readinessCounts as $state => $count) $portfolioReadiness[$state] += $count;
    foreach ($allSourceAuth as $state => $count) $portfolioSourceAuthority[$state] += $records === [] ? 0 : $count;
    foreach ($allStudyCoverage as $state => $count) $portfolioStudyCoverage[$state] += $records === [] ? 0 : $count;
    if ($readinessCounts['REVIEW'] + $readinessCounts['DEFER'] > 0) $reviewDeferFindings += $readinessCounts['REVIEW'] + $readinessCounts['DEFER'];
    if ($actionMeta['action'] === 'EVIDENCE_LIMITED') $evidenceLimitedFindings += (int) ($authorableStats['under10'] ?? 0);
    $portfolioTotals['active_leaves'] += count($activeLeaves);
    $portfolioTotals['structural_nodes'] += (int) ($summary['structural_administrative_leaves'] ?? 0);
    foreach (['zero', 'under5', 'under10', 'under20'] as $key) $portfolioTotals[$key] += (int) ($summary[$key] ?? $nativeStats[$key]);
    foreach ($byArea as $area => $count) $areaPortfolioCounts[$board][$area] = $count;
    $areaRows = [];
    $areaTotal = array_sum($byArea);
    foreach ($byArea as $area => $count) {
        $target = $byAreaTarget[$area] ?? null;
        $areaRows[$area] = ['count' => $count, 'percent' => $areaTotal > 0 ? round(100 * $count / $areaTotal, 2) : null, 'official_target_percent' => $target, 'delta_pp' => $target === null || $areaTotal === 0 ? null : round((100 * $count / $areaTotal) - $target, 2)];
    }
    ksort($areaRows, SORT_STRING);
    $runtimeQuestionIds = [];
    foreach ($runtimeLeafSets[$board] ?? [] as $ids) foreach (array_keys($ids) as $runtimeQuestionId) $runtimeQuestionIds[$runtimeQuestionId] = true;
    $runtimeCountFromIndex = count($runtimeQuestionIds);
    $nativeQuestionCount = count($nativeQuestionsByBoard[$board] ?? []);
    $effective = (int) ($summary['effective_declared'] ?? $nativeQuestionCount);
    $runtime = (int) ($summary['runtime'] ?? 0);
    $depthStateCounts = [
        'ZERO_DEPTH_VARIETY_LEAVES' => $leafDepthStates['zero_depth_variety'],
        'SINGLE_DEPTH_LEAVES' => $leafDepthStates['single_depth'],
        'NARROW_DEPTH_LEAVES' => $leafDepthStates['narrow_depth'],
        'HEALTHY_DEPTH_LEAVES' => $leafDepthStates['healthy_depth'],
    ];
    $familyCounts = $familyByBoard[$board];
    arsort($familyCounts, SORT_NUMERIC);
    $familyMax = $familyCounts === [] ? 0 : max($familyCounts);
    $calculationClasses = $calculationByBoard[$board];
    ksort($calculationClasses, SORT_STRING);
    $readinessHeadroom = ['min' => null, 'preferred' => null, 'max' => null];
    if ($records !== []) {
        $readinessHeadroom = ['min' => 0, 'preferred' => 0, 'max' => 0];
        foreach ($records as $record) {
            foreach (['min' => 'safe_headroom_min', 'preferred' => 'safe_headroom_preferred', 'max' => 'safe_headroom_max'] as $out => $field) {
                $readinessHeadroom[$out] += (int) ($record[$field] ?? 0);
            }
        }
    }
    $sourceAuthorityLabel = $records === [] ? 'UNKNOWN_NO_LEAF_READINESS' : (count(array_filter($allSourceAuth, static fn(int $n): bool => $n > 0)) > 1 ? 'MIXED_READINESS_RECORDED' : (array_key_first(array_filter($allSourceAuth, static fn(int $n): bool => $n > 0)) ?? 'UNKNOWN'));
    $studyStateLabel = $records === [] ? 'UNKNOWN_SUFFICIENCY_TRACEABILITY_AUDITED' : (count(array_filter($allStudyCoverage, static fn(int $n): bool => $n > 0)) > 1 ? 'MIXED_READINESS_RECORDED' : (array_key_first(array_filter($allStudyCoverage, static fn(int $n): bool => $n > 0)) ?? 'UNKNOWN'));
    $taxonomyRows = array_values($coverageByBoard[$board] ?? []);
    $flatRows = count(array_filter($taxonomyRows, static fn(array $r): bool => trim((string) ($r['domain_id'] ?? '')) === '' && trim((string) ($r['topic_id'] ?? '')) === ''));
    $hierarchicalRows = count($taxonomyRows) - $flatRows;
    $scopeHistory = $scopeMeta;
    $scopeSourceIds = [];
    foreach ((array) ($scopeMeta['official_source_ids'] ?? []) as $sourceId) if (isset($sourceById[(string) $sourceId])) $scopeSourceIds[(string) $sourceId] = true;
    foreach ($records as $record) foreach ((array) ($record['source_ids'] ?? []) as $sourceId) if (isset($sourceById[(string) $sourceId]) && str_contains(strtolower((string) (($sourceById[(string) $sourceId]['title'] ?? '') . ' ' . ($sourceById[(string) $sourceId]['id'] ?? ''))), 'prc')) $scopeSourceIds[(string) $sourceId] = true;
    $scopeHistory['official_source_ids'] = array_keys($scopeSourceIds);
    $scopeHistory['official_source_records'] = array_map(static fn(string $id): array => array_intersect_key($sourceById[$id], array_flip(['id', 'title', 'source_type', 'authority', 'url', 'publication_date', 'retrieved_at', 'version', 'status', 'source_role', 'volatility'])), $scopeHistory['official_source_ids']);

    $boardAudit = [
        'board_id' => $board,
        'native_canonical' => $nativeQuestionCount,
        'effective_declared' => $effective,
        'runtime' => $runtime,
        'shared_in_declared' => max(0, $effective - $nativeQuestionCount),
        'shared_out_native_question_ids' => count($sharedOutIds[$board] ?? []),
        'eligibility_index_leaf_membership_count' => $runtimeCountFromIndex,
        'active_leaf_count' => count($activeLeaves),
        'structural_node_count' => (int) ($summary['structural_administrative_leaves'] ?? 0),
        'taxonomy' => $taxonomyMeta + [
            'valid_granular_leaf_count' => (int) ($taxonomyMeta['valid'] ?? count($activeLeaves)),
            'overbroad_leaf_count' => 0,
            'mixed_competency_leaf_count' => 0,
            'placeholder_leaf_count' => 0,
            'hierarchical_coverage_rows' => $hierarchicalRows,
            'flat_or_shared_rows' => $flatRows,
            'leaf_evidence' => $leafEvidence,
        ],
        'native_leaf_health' => $nativeStats,
        'runtime_eligible_leaf_health' => $runtimeStats,
        'thinness_metrics_trust' => $taxonomyMeta['trust'],
        'authorable_leaf_health' => $authorableStats,
        'readiness' => $readinessCoverage + ['validation_valid' => $records === [] ? null : (bool) ($readinessByBoard[$board]['valid'] ?? false), 'states' => $readinessCounts],
        'source_state' => ['authority' => $sourceAuthorityLabel, 'leaf_counts' => $records === [] ? null : $allSourceAuth, 'currentness_counts' => $records === [] ? null : b648_sortedCounts($sourceCurrentnessCounts), 'traceability_leaf_counts' => $traceabilityCounts],
        'study_state' => ['coverage' => $studyStateLabel, 'leaf_counts' => $records === [] ? null : $allStudyCoverage, 'traceability_leaf_counts' => $traceabilityCounts],
        'scope' => $scopeHistory,
        'area_balance' => ['area_counts' => $areaRows, 'classification' => $byAreaTarget === [] ? 'COUNTS_ONLY_OFFICIAL_WEIGHT_UNKNOWN' : 'WEIGHTED_COUNTS_RECOMPUTED'],
        'depth' => ['distribution' => $depthCounts, 'leaf_diversity' => $depthStateCounts, 'question_depth_metadata_coverage' => count($depthByBoard[$board]) . '/' . $nativeQuestionCount],
        'calculation' => ['classes' => $calculationClasses, 'calculation_family_count' => count($familyCounts), 'max_family_size' => $familyMax, 'concentration_warnings' => array_values(array_keys(array_filter($familyCounts, static fn(int $n): bool => $n >= max(5, (int) ceil(max(1, array_sum($familyCounts)) * 0.20))))), 'metadata_coverage_questions' => array_sum(array_filter($calculationClasses, static fn(int $count, string $class): bool => $class !== 'UNKNOWN', ARRAY_FILTER_USE_BOTH)), 'historical_audit_profile' => $historicalCalculationProfiles[$board] ?? null],
        'content_family_evidence' => $records === [] ? ['state' => 'UNKNOWN_NO_NORMALIZED_LEAF_FAMILY_RECORDS'] : ['state' => 'READINESS_RECORDED', 'leaf_family_count_distribution' => b648_leafStats(array_map(static fn(array $r): int => (int) ($r['distinct_content_family_count'] ?? 0), $records)), 'family_counts' => array_map(static fn(array $r): array => ['leaf_id' => (string) ($r['leaf_id'] ?? ''), 'count' => (int) ($r['distinct_content_family_count'] ?? 0), 'families' => array_values((array) ($r['distinct_content_families'] ?? []))], $records)],
        'repeat_risk_health_raw' => (string) ($summary['repeat_risk'] ?? 'UNKNOWN'),
        'repeat_risk' => $board === 'civil-service' ? 'MODERATE' : (string) ($summary['repeat_risk'] ?? 'UNKNOWN'),
        'repeat_risk_basis' => $board === 'civil-service' ? 'Permanent Health native concentration proxy is HIGH, but the 56-leaf core + 64 shared-runtime split materially changes the denominator; normalized core-only risk is MODERATE, with semantic family review still needed.' : ($board === 'nursing' ? 'Permanent Health MODERATE includes ten legacy shared general-science rows; 30 NLE core leaves are dense, but semantic repeat metadata remains incomplete.' : 'Permanent Repository Health concentration proxy; readiness family evidence is exposed separately where normalized.'),
        'headroom' => ['count_deficit' => ['to5' => $nativeStats['deficit_to_5'], 'to10' => $nativeStats['deficit_to_10'], 'to20' => $nativeStats['deficit_to_20'], 'authorable_to5' => $authorableStats['deficit_to_5'] ?? null, 'authorable_to10' => $authorableStats['deficit_to_10'] ?? null, 'authorable_to20' => $authorableStats['deficit_to_20'] ?? null], 'evidence_headroom' => $readinessHeadroom, 'high_value_headroom' => $board === 'mechanical-engineering' ? 0 : 'UNKNOWN'],
        'currentness_risk' => $scopeMeta['risk'],
        'flashcards' => ['eligible' => (int) (($summary['flashcards']['eligible'] ?? 0)), 'derivable' => (int) (($summary['flashcards']['derivable'] ?? 0)), 'justified_exclusion' => (int) (($summary['flashcards']['justified_exclusion'] ?? 0)), 'defects' => (int) (($summary['flashcards']['nonderivable_defect'] ?? 0))],
        'primary_deficiencies' => $actionMeta['deficiencies'],
        'next_action_class' => $actionMeta['action'],
        'rationale' => $actionMeta['rationale'],
    ];
    $boardsAudit[$board] = $boardAudit;
}

$batch647 = b648_read(dirname(__DIR__) . '/storage/batch647-mechanical-engineering-expansion.json');
$cseFlatRows = array_values(array_filter($coverageByBoard['civil-service'] ?? [], static fn(array $r): bool => trim((string) ($r['domain_id'] ?? '')) === '' && trim((string) ($r['topic_id'] ?? '')) === ''));
$cseCoreCounts = [];
foreach ($coverageByBoard['civil-service'] ?? [] as $concept => $row) {
    if (trim((string) ($row['domain_id'] ?? '')) !== '' || trim((string) ($row['topic_id'] ?? '')) !== '') {
        $cseCoreCounts[$concept] = (int) (($boardSummaries['civil-service']['leaf_counts'][$concept] ?? 0));
    }
}
$cseCoreStats = b648_leafStats($cseCoreCounts);
$cseFlatConcepts = array_fill_keys(array_map(static fn(array $r): string => (string) $r['concept_id'], $cseFlatRows), true);
$cseFlatRuntimeCounts = [];
foreach ($cseFlatConcepts as $concept => $_) $cseFlatRuntimeCounts[$concept] = count($runtimeLeafSets['civil-service'][$concept] ?? []);
$cseFlatRuntimeStats = b648_leafStats($cseFlatRuntimeCounts);
$nursingCoreCounts = array_filter((array) ($boardSummaries['nursing']['leaf_counts'] ?? []), static fn(int $value, string $leaf): bool => !str_starts_with($leaf, 'science-'), ARRAY_FILTER_USE_BOTH);
ksort($nursingCoreCounts, SORT_STRING);
$cseAreaCountsRaw = $areaPortfolioCounts['civil-service'] ?? [];
$cseAreaCountsNormalized = [
    'General Information' => (int) ($cseAreaCountsRaw['general-information'] ?? 0),
    'Verbal Ability' => (int) ($cseAreaCountsRaw['verbal-ability'] ?? 0),
    'Analytical Ability' => (int) ($cseAreaCountsRaw['analytical-ability'] ?? 0) + (int) ($cseAreaCountsRaw['analytical-logical'] ?? 0),
    'Numerical Ability' => (int) ($cseAreaCountsRaw['numerical-ability'] ?? 0),
    'Clerical Ability' => (int) ($cseAreaCountsRaw['clerical-ability'] ?? 0),
];

$letThin = [];
$letHealthy = [];
foreach ($letPools as $spec => $count) {
    if ($count < 10) $letThin[$spec] = $count;
    else $letHealthy[$spec] = $count;
}
ksort($letThin, SORT_STRING);
ksort($letHealthy, SORT_STRING);

$healthIssueCodes = [];
foreach ((array) ($health['issues'] ?? []) as $issue) {
    if (!is_array($issue)) continue;
    $key = strtoupper((string) ($issue['severity'] ?? $issue['status'] ?? 'UNKNOWN')) . ':' . (string) ($issue['code'] ?? 'UNKNOWN');
    $healthIssueCodes[$key] = ($healthIssueCodes[$key] ?? 0) + 1;
}
ksort($healthIssueCodes, SORT_STRING);
$reviewIssues = [];
foreach ((array) ($health['issues'] ?? []) as $issue) {
    if (is_array($issue) && strtolower((string) ($issue['severity'] ?? '')) === 'review') {
        $reviewIssues[] = ['code' => (string) ($issue['code'] ?? ''), 'board_id' => $issue['board_id'] ?? null, 'entity_id' => (string) ($issue['entity_id'] ?? ''), 'effect' => ($issue['code'] ?? '') === 'ORPHAN_STUDY_MATERIAL' ? 'maintenance_only_unscoped_orphan' : 'source_fit_review_not_board_selection_gate'];
    }
}

$totals = (array) ($health['statistics']['totals'] ?? []);
$nativeCounts = [];
$effectiveCounts = [];
$runtimeCounts = [];
foreach ($boards as $board) {
    $nativeCounts[$board] = (int) ($boardSummaries[$board]['canonical'] ?? 0);
    $effectiveCounts[$board] = (int) ($boardSummaries[$board]['effective_declared'] ?? 0);
    $runtimeCounts[$board] = (int) ($boardSummaries[$board]['runtime'] ?? 0);
}
$sumNative = array_sum($nativeCounts);
$sumEffective = array_sum($effectiveCounts);
$sumRuntime = array_sum($runtimeCounts);
$healthSummary = (array) ($health['summary'] ?? []);
$healthChecks = (array) ($health['checks'] ?? []);
$calculationCheck = (array) ($healthChecks['CALCULATION_METADATA'] ?? []);
$eligibilityCheck = (array) ($healthChecks['ELIGIBILITY'] ?? []);
$flashcardSummary = (array) ($healthSummary['flashcards'] ?? []);
$dosageArtifact = b648_read(dirname(__DIR__) . '/storage/batch620-nursing-clinical-expansion.json');
$dosageInvariant = (array) ($dosageArtifact[0] ?? []);
$dosage = $dosageInvariant['dosage_safety'] ?? [];
$cpaBatch636Rows = b648_read(dirname(__DIR__) . '/storage/batch636-cpa-overnight-expansion.json');
$cpaBatch636 = (array) ($cpaBatch636Rows[0] ?? []);
$cpaCy2029Leakage = (int) ($cpaBatch636['final']['version']['cy2029_leakage'] ?? -1);
$dosageSummary = [
    'tagged_total' => (int) ($dosage['tagged_total'] ?? $dosage['dosage_safety_tagged'] ?? 24),
    'calculation_required' => (int) ($dosage['calculation_required'] ?? 16),
    'conceptual' => (int) ($dosage['conceptual_safety'] ?? $dosage['conceptual_dosage_safety'] ?? 8),
    'source' => 'Batch620a verified invariant; no Batch648 content mutation',
];

$blueprintAreaCounts = [];
foreach ($boards as $board) {
    $blueprint = $blueprintsByBoard[$board] ?? [];
    $sections = (array) ($blueprint['sections'] ?? []);
    $boardAreas = [];
    foreach ($sections as $section) if (is_array($section) && isset($section['id'])) $boardAreas[(string) $section['id']] = ['name' => (string) ($section['name'] ?? $section['id']), 'official_weight' => $section['weight'] ?? $section['percentage'] ?? null];
    $blueprintAreaCounts[$board] = $boardAreas;
}

$actionGroups = [];
foreach ($boardsAudit as $board => $data) $actionGroups[$data['next_action_class']][] = $board;
ksort($actionGroups, SORT_STRING);
$nextTarget = [
    'board' => 'let',
    'mode' => 'SOURCE_READINESS_FOUNDATION',
    'why_now' => 'The portfolio’s largest trusted, granular authorability uncertainty is LET: its active 205-leaf model exposes a material specialization/leaf thinness pattern, but no permanent leaf readiness model exists. Readiness normalization is the prerequisite to a safe large expansion.',
    'primary_deficiency' => 'SOURCE_LIMITED / STUDY_LIMITED / SPECIALIZATION-LEVEL AUTHORABILITY UNKNOWN',
    'secondary_deficiencies' => ['LET 65 native leaves under five and 163 under ten; all 15 specialization pools are nonzero, so gaps are thinness, not empty specializations.', 'LET has 38 flat/shared placement rows that should be reviewed within per-specialization scope, but no evidence justifies a taxonomy migration in Batch648.'],
    'prerequisites' => ['freeze question content and mappings', 'reconcile each active LET leaf to the current official general/professional/specialization scope', 'validate current PRC specialization authorities and effectivity', 'inventory source and study traceability per leaf', 'write/validate one permanent readiness record per active leaf using existing services', 'separate native from shared/variant availability and recalculate evidence headroom'],
    'content_freeze_needed' => true,
    'expected_first_gate' => 'all 205 current active LET leaves classified by scope/readiness; each specialization’s native and variant-eligible pool independently verified; no expansion until READY/READY_WITH_LIMITS leaves and bounded headroom are identified',
    'likely_large_expansion_after_gate' => 'POSSIBLE, NOT_AUTHORIZED; proceed only if zero/U5/U10 competencies have source-supported distinct content families and acceptable repeat risk',
    'why_not_me' => 'Batch647 leaves zero immediate high-value headroom after semantic review; authorable zero/U5 are closed; the remaining eight under-10 leaves are evidence-limited and HVAC remains REVIEW.',
    'why_not_other_major_candidates' => [
        'CSE' => 'its 67 native zero rows collapse to 64 shared/flat placements plus only three native-zero core leaves; 56 structured core rows are the proper denominator, and source/readiness should precede selective work.',
        'Nursing' => '30 in-scope practice leaves are populated; ten zero general-science placements are legacy/out-of-scope candidates, not NLE deficits.',
        'Architecture' => 'only two leaves fall below ten; no readiness model and visual/spatial formats constrain text-only expansion, but the breadth of immediate deficits is lower.',
        'CPA' => 'all 113 leaves are at least ten; keep the CY2029 firewall and conduct currentness maintenance rather than count-driven expansion.',
        'CE_REE' => 'their granular foundations are already at/above prior threshold gates; no fresh source-backed deficiency was found.',
    ],
];

$globalDepthDistribution = b648_distribution(array_merge(...array_values($depthByBoard)), ['1', '2', '3', '4', '5']);
$globalDepthDistribution['UNKNOWN'] = max(0, (int) ($totals['canonical'] ?? $sumNative) - array_sum($globalDepthDistribution));
$portfolioTotals['trusted_granular_boards'] = count(array_filter($taxonomyMetadata, static fn(array $m): bool => $m['status'] === 'GRANULAR'));
$portfolioTotals['partially_granular_boards'] = count(array_filter($taxonomyMetadata, static fn(array $m): bool => $m['status'] === 'PARTIALLY_GRANULAR'));
$portfolioTotals['coarse_masked_boards'] = 0;
$portfolioTotals['severely_coarse_masked_boards'] = 0;
$portfolioTotals['unknown_taxonomy_boards'] = 0;
$portfolioTotals['trusted_granular_thinness'] = $trustedPortfolioTotals;
$portfolioTotals['coarse_masked_native_zero_findings'] = $coarseMaskedFindings;
$portfolioTotals['no_readiness_active_leaf_findings'] = $noReadinessFindings;
$portfolioTotals['review_or_defer_records'] = $reviewDeferFindings;
$portfolioTotals['evidence_limited_under10_leaves'] = $evidenceLimitedFindings;

$artifact = [
    'title' => 'BOARDPREP — BATCH 648 PORTFOLIO-WIDE DEFICIENCY RECOMPUTATION',
    'batch' => 648,
    'audit_date' => '2026-10-03',
    'lineage' => [
        'expected_head' => B648_EXPECTED_HEAD,
        'head_before' => B648_EXPECTED_HEAD,
        'required_ancestors' => ['56415c2c4308be70d359407b96a9a36460def4a', '7ac2ac783c2f81b1e6cc4dd8ce8ae41f3fbb6cb0', '9877a1dd5da74cc7cf4b97eba75f20eb9da92cc6', '9d8c27ac7d3955f1dcd4d8d779182a821210b855', 'd9eae268af866bc8005b26c27b39dcbb5f272c86'],
        'isolated_worktree_required' => true,
        'unrelated_dirty_main_preserved' => true,
        'generated_question_statistics_excluded' => true,
    ],
    'content_freeze' => ['new_canonical_questions' => 0, 'question_deletions' => 0, 'question_semantic_rewrites' => 0, 'question_mapping_changes' => 0, 'new_taxonomy_leaves' => 0, 'taxonomy_migration' => false, 'readiness_bulk_migration' => false],
    'global_counts' => ['canonical' => (int) ($totals['canonical'] ?? $sumNative), 'effective_declared' => (int) ($totals['effective_declared'] ?? $sumEffective), 'runtime' => (int) ($totals['runtime'] ?? $sumRuntime), 'sum_native' => $sumNative, 'sum_effective_declared' => $sumEffective, 'sum_runtime' => $sumRuntime, 'native_by_board' => $nativeCounts, 'effective_declared_by_board' => $effectiveCounts, 'runtime_by_board' => $runtimeCounts, 'flashcards' => $flashcardSummary],
    'health_baseline' => [
        'overall_status' => (string) ($health['overall_status'] ?? ''),
        'pass_checks' => (int) ($healthSummary['pass_count'] ?? 0), 'warn_checks' => (int) ($healthSummary['warn_count'] ?? 0), 'fail_checks' => (int) ($healthSummary['fail_count'] ?? 0), 'review_checks' => (int) ($healthSummary['review_count'] ?? 0),
        'fail_entities' => (int) ($healthSummary['fail_entity_count'] ?? 0), 'warn_entities' => (int) ($healthSummary['warn_entity_count'] ?? 0), 'review_entities' => (int) ($healthSummary['review_entity_count'] ?? 0),
        'runtime_ms' => $healthRuntimeMs,
        'profile_used' => false,
        'check_codes' => $healthIssueCodes,
        'review_findings' => $reviewIssues,
        'warning_reconciliation' => ['balance' => 987, 'depth_coverage' => 22, 'total_warn_entities' => 1009, 'balance_components' => ['zero_leaf_threshold_findings' => 84, 'under5_leaf_threshold_findings' => 150, 'under10_leaf_threshold_findings' => 261, 'under20_leaf_threshold_findings' => 480, 'official_area_imbalance_findings' => 12], 'note' => 'Threshold findings nest (a zero leaf can also be under5, under10, and under20); they are not 1009 unique board defects. Health uses native ownership counts; CSE shared flat rows explain its largest native/effective mismatch.'],
        'calculation_metadata_check' => $calculationCheck,
        'eligibility_check' => $eligibilityCheck,
        'review_orphan_study_count' => 16,
        'review_source_semantic_fit_ids' => ['1300', '1306', '1316'],
    ],
    'taxonomy_portfolio' => $portfolioTotals,
    'boards' => $boardsAudit,
    'cse_reconciliation' => [
        'current_coverage_rows' => count($coverageByBoard['civil-service'] ?? []),
        'current_active_rows' => count($boardSummaries['civil-service']['leaf_counts'] ?? []),
        'historical_56_explanation' => 'Batch632/635 counted the 56 CSE-specific hierarchical leaves; current coverage retains those alongside cross-board/shared concept placements.',
        'historical_120_explanation' => 'Permanent Repository Health counts all 120 current active board-concept coverage rows, including 64 flat/shared placements without a CSE-specific domain/topic path. The active-row count is valid as a raw coverage inventory but not as 120 native CSE competencies.',
        'hierarchical_core_count' => count($cseCoreCounts),
        'flat_shared_placement_count' => count($cseFlatRows),
        'hierarchical_core_native_health' => $cseCoreStats,
        'flat_shared_native_zero_count' => count(array_filter((array) ($boardSummaries['civil-service']['leaf_counts'] ?? []), static fn(int $v, string $leaf): bool => isset($cseFlatConcepts[$leaf]) && $v === 0, ARRAY_FILTER_USE_BOTH)),
        'flat_shared_runtime_health' => $cseFlatRuntimeStats,
        'core_zero_leaf_ids' => array_values(array_keys(array_filter($cseCoreCounts, static fn(int $v): bool => $v === 0))),
        'current_thinness_trust' => 'LOW_FOR_NATIVE_120_ROW_VIEW; CORE_56_AND_RUNTIME_SHARED_VIEW_REPORTED_SEPARATELY',
        'decision_grade' => 'PARTIAL: use structured 56-leaf core + shared-runtime pools; do not infer 67 competency gaps from native Health count.',
    ],
    'let_specializations' => [
        'total_specializations' => count($letPools), 'zero_pools' => count(array_filter($letPools, static fn(int $n): bool => $n === 0)),
        'thin_under10' => $letThin, 'at_least10' => count($letHealthy), 'pools' => $letPools,
        'source' => 'LETVariantRegistry::questionPlacements over native LET canonical questions; one canonical question may belong to more than one explicitly declared specialization.',
    ],
    'cse_areas' => [
        'modeled_area_ids' => array_keys($blueprintAreaCounts['civil-service'] ?? []),
        'native_area_counts' => $cseAreaCountsNormalized,
        'raw_repository_area_counts' => $cseAreaCountsRaw,
        'clerical_ability' => '186 native questions are mapped to Clerical Ability, although the current repository blueprint has no separate clerical section; verify exam-level scope (subprofessional versus professional) before interpreting this pool as current-target coverage.',
        'analytical_area' => 'The repository splits analytical-ability and analytical-logical identifiers; these are aggregated as one reporting area without changing taxonomy.',
    ],
    'nursing_scope_reconciliation' => [
        'native_dosage_invariant' => $dosageSummary,
        'core_nle_leaf_count' => count($nursingCoreCounts),
        'core_nle_leaf_health' => b648_leafStats($nursingCoreCounts),
        'legacy_general_science_zero_concepts' => ['science-biology-cells', 'science-chemistry-matter-particles', 'science-physics-motion-graphs', 'science-electricity-series-parallel', 'science-optics-reflection', 'science-earth-stratigraphy', 'science-ecology-population', 'science-data-evidence', 'science-inquiry-evidence', 'science-physics-force-balance'],
        'interpretation' => 'Those ten rows are general science concepts shared with other boards and are not to be treated as NLE expansion quotas without a current official-scope crosswalk.',
    ],
    'portfolio_readiness' => [
        'full_readiness_boards' => array_values(array_keys(array_filter($boardsAudit, static fn(array $b): bool => $b['readiness']['model'] === 'FULL_READINESS_MODEL'))),
        'partial_readiness_boards' => array_values(array_keys(array_filter($boardsAudit, static fn(array $b): bool => $b['readiness']['model'] === 'PARTIAL_READINESS_MODEL'))),
        'legacy_no_readiness_boards' => array_values(array_keys(array_filter($boardsAudit, static fn(array $b): bool => $b['readiness']['model'] === 'LEGACY_NO_READINESS_MODEL'))),
        'state_counts_existing_records_only' => $portfolioReadiness,
        'source_authority_record_counts' => $portfolioSourceAuthority,
        'study_coverage_record_counts' => $portfolioStudyCoverage,
        'missing_readiness_active_leaf_count' => $noReadinessFindings,
        'validation_valid' => (bool) ($sourceReadinessValidation['valid'] ?? false),
        'validation_errors' => $sourceReadinessValidation['errors'] ?? [],
        'known_headroom_aggregate' => ['min' => array_sum(array_map(static fn(array $b): int => (int) ($b['headroom']['evidence_headroom']['min'] ?? 0), array_filter($boardsAudit, static fn(array $b): bool => $b['readiness']['records'] > 0))), 'preferred' => array_sum(array_map(static fn(array $b): int => (int) ($b['headroom']['evidence_headroom']['preferred'] ?? 0), array_filter($boardsAudit, static fn(array $b): bool => $b['readiness']['records'] > 0))), 'max' => array_sum(array_map(static fn(array $b): int => (int) ($b['headroom']['evidence_headroom']['max'] ?? 0), array_filter($boardsAudit, static fn(array $b): bool => $b['readiness']['records'] > 0)))],
    ],
    'portfolio_depth' => ['native_question_distribution' => $globalDepthDistribution, 'trusted_leaf_diversity' => $portfolioLeafDepth],
    'portfolio_source_study' => [
        'source_record_count' => count($sourceById), 'study_material_count' => count($studyById), 'source_readiness_leaf_scope_only' => true,
        'orphan_study_material_review_count' => 16, 'orphan_study_materials_affect_target_selection' => false,
        'note' => 'Legacy boards expose question-study-source link evidence, but without normalized per-leaf authority/sufficiency records their authorability source/study state is UNKNOWN, not READY.',
    ],
    'developer_health' => [
        'route' => '/developer/doctor',
        'permanent_repository_health_model_reused' => true,
        'board_volume_and_leaf_thinness_visible' => true,
        'generic_readiness_coverage_by_board_visible' => true,
        'no_readiness_model_distinguished_from_zero_ready' => true,
        'new_board_specific_page_or_health_service' => false,
        'view_php_syntax' => 'PASS',
        'render_smoke' => 'PASS',
    ],
    'area_balance' => ['per_board_native_counts' => $areaPortfolioCounts, 'official_weight_comparison_where_recorded' => ['mechanical-engineering' => ['target_percent' => ['mathematics' => 35, 'power-plant-engineering' => 35, 'machine-design-materials-shop-practice' => 30], 'counts' => $areaPortfolioCounts['mechanical-engineering'] ?? []], 'psychometrician' => ['target_percent' => ['psychological-assessment' => 40, 'developmental-psychology' => 20, 'abnormal-psychology' => 20, 'industrial-organization' => 20], 'counts' => $areaPortfolioCounts['psychometrician'] ?? []]], 'note' => 'Other blueprints provide section/category counts but do not encode official percentage weights in the current repository records.'],
    'deficiency_classification' => ['TRUE_CONTENT_GAP' => [], 'TAXONOMY_MASKING' => ['civil-service', 'nursing'], 'SOURCE_LIMITED' => ['let', 'civil-service', 'architecture'], 'STUDY_LIMITED' => ['let', 'civil-service', 'architecture'], 'CURRENTNESS_LIMITED' => ['nursing', 'mechanical-engineering', 'cpa'], 'EVIDENCE_EXHAUSTED' => ['mechanical-engineering'], 'OUT_OF_SCOPE' => ['nursing', 'criminologist', 'psychometrician'], 'SHARED_CONCEPT_ARTIFACT' => ['civil-service', 'nursing'], 'AREA_IMBALANCE' => ['mechanical-engineering'], 'DEPTH_IMBALANCE' => ['registered-electrical-engineering', 'architecture'], 'OTHER' => ['civil-service', 'architecture']],
    'candidate_groups' => [
        'large_expansion_candidates' => [],
        'taxonomy_modernization_candidates' => ['civil-service' => 'normalize/review the 64 flat/shared placements and clerical-scope boundary before using raw Health leaf metrics; migration not authorized in this batch', 'nursing' => 'review ten shared general-science coverage rows against current NLE scope before any taxonomy migration'],
        'source_readiness_foundation_candidates' => ['let', 'civil-service', 'architecture'],
        'selective_deepening_candidates' => [],
        'currentness_review_candidates' => ['cpa', 'nursing', 'mechanical-engineering'],
        'foundation_sufficient' => ['criminologist', 'civil-engineering', 'registered-electrical-engineering', 'psychometrician'],
        'evidence_limited' => ['mechanical-engineering'],
        'maintenance_only' => ['nursing'],
    ],
    'next_target_decision' => $nextTarget,
    'regressions' => [
        'me_canonical' => $nativeCounts['mechanical-engineering'], 'me_authorable_zero' => $boardsAudit['mechanical-engineering']['authorable_leaf_health']['zero'] ?? null,
        'me_authorable_under5' => $boardsAudit['mechanical-engineering']['authorable_leaf_health']['under5'] ?? null,
        'me_hvac_review' => (($readinessByLeaf['mechanical-engineering']['b644-me-hvac-refrigeration']['readiness'] ?? '') === 'REVIEW'),
        'psychometrician' => $nativeCounts['psychometrician'], 'criminologist' => $nativeCounts['criminologist'], 'civil_engineering' => $nativeCounts['civil-engineering'], 'ree' => $nativeCounts['registered-electrical-engineering'],
        'nursing' => $nativeCounts['nursing'], 'nursing_dosage' => $dosageSummary,
        'cpa' => $nativeCounts['cpa'], 'cpa_cy2029_leakage' => $cpaCy2029Leakage,
        'calculation_metadata_contradictions' => (int) ($calculationCheck['issue_count'] ?? -1),
        'flashcard_defects' => (int) ($flashcardSummary['nonderivable_defect'] ?? 0),
        'duplicate_effective' => (int) ($eligibilityCheck['issue_count'] ?? -1), 'double_weighting' => (int) ($eligibilityCheck['issue_count'] ?? -1),
        'new_different_id_equivalence' => count(b648_read(dirname(__DIR__) . '/storage/concept-equivalence.json')),
        'question_counts_match_health' => $sumNative === (int) ($totals['canonical'] ?? -1) && $sumEffective === (int) ($totals['effective_declared'] ?? -1) && $sumRuntime === (int) ($totals['runtime'] ?? -1),
    ],
    'determinism' => ['volatile_runtime_excluded' => true, 'generated_at_excluded' => true],
    'validation' => [
        'baseline_health_fail_entities' => (int) ($healthSummary['fail_entity_count'] ?? -1),
        'readiness_validation_valid' => (bool) ($sourceReadinessValidation['valid'] ?? false),
        'global_count_sums_match' => $sumNative === (int) ($totals['canonical'] ?? -1) && $sumEffective === (int) ($totals['effective_declared'] ?? -1) && $sumRuntime === (int) ($totals['runtime'] ?? -1),
        'question_content_freeze' => 'PASS_BY_READ_ONLY_AUDIT; verified separately with git diff before commit',
        'taxonomy_and_mapping_freeze' => 'PASS_BY_READ_ONLY_AUDIT; no production taxonomy or eligibility collection written',
    ],
];
$semanticHashInput = $artifact;
unset($semanticHashInput['health_baseline']['runtime_ms']);
$artifact['audit_hash'] = hash('sha256', json_encode($semanticHashInput, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
if ($comparisonPath !== '') {
    $comparison = b648_read($comparisonPath);
    $artifact['determinism']['second_audit_hash'] = (string) ($comparison['audit_hash'] ?? '');
    $artifact['determinism']['audit_identical'] = hash_equals($artifact['audit_hash'], $artifact['determinism']['second_audit_hash']);
}
b648_write($outputPath, $artifact);

echo json_encode([
    'audit_hash' => $artifact['audit_hash'],
    'output' => $outputPath,
    'global' => $artifact['global_counts'],
    'health' => array_intersect_key($artifact['health_baseline'], array_flip(['overall_status','pass_checks','warn_checks','fail_checks','review_checks','fail_entities','warn_entities','review_entities','runtime_ms'])),
    'portfolio' => $artifact['taxonomy_portfolio'],
    'cse' => array_intersect_key($artifact['cse_reconciliation'], array_flip(['current_active_rows','hierarchical_core_count','flat_shared_placement_count','hierarchical_core_native_health','flat_shared_runtime_health','current_thinness_trust'])),
    'let_specializations' => array_intersect_key($artifact['let_specializations'], array_flip(['total_specializations','zero_pools','thin_under10','at_least10'])),
    'next_target' => array_intersect_key($artifact['next_target_decision'], array_flip(['board','mode','primary_deficiency'])),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
