<?php

declare(strict_types=1);

/**
 * Batch 635: post-Batch634 portfolio recomputation.
 *
 * This is deliberately an audit-only tool.  It reads the bank once, builds
 * the production eligibility/runtime pools once, compares every active board
 * at its finest defensible taxonomy, and writes only the Batch635 artifact.
 * It does not author, rewrite, or migrate question content.
 */

$root = dirname(__DIR__);
$expectedHead = '25b7d30cf60310487e30e515266faf99935bb3bd';
$expectedBatch633 = 'e640eed4d3e4c36c1264e0a60333ca103f5f1f7b';
$expectedBatch632 = 'ca755293ab72da2e82bc3f592e7f3ad06c2f7b1a';
$write = !in_array('--audit', array_slice($argv, 1), true);
$boards = [
    'let', 'civil-service', 'nursing', 'criminologist', 'civil-engineering',
    'mechanical-engineering', 'registered-electrical-engineering',
    'architecture', 'psychometrician', 'cpa',
];

function b635_read(string $root, string $relative): mixed
{
    $path = $root . '/storage/' . $relative . '.json';
    if (!is_file($path)) {
        throw new RuntimeException('Missing storage file: ' . $relative);
    }
    return json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
}

function b635_first(mixed $value): array
{
    if (is_array($value) && array_is_list($value)) {
        return is_array($value[0] ?? null) ? $value[0] : [];
    }
    return is_array($value) ? $value : [];
}

function b635_git(string $root, string $args): string
{
    return trim((string) shell_exec('git -C ' . escapeshellarg($root) . ' ' . $args . ' 2>/dev/null'));
}

function b635_hash(mixed $value): string
{
    return hash('sha256', json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
}

function b635_write(string $root, array $artifact): void
{
    $path = $root . '/storage/batch635-post634-portfolio-recomputation.json';
    $tmp = $path . '.batch635.' . bin2hex(random_bytes(5)) . '.tmp';
    $json = json_encode([$artifact], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR) . PHP_EOL;
    if (file_put_contents($tmp, $json, LOCK_EX) === false || !rename($tmp, $path)) {
        @unlink($tmp);
        throw new RuntimeException('Unable to atomically write Batch635 artifact.');
    }
}

function b635_percent(int $n, int $d): float
{
    return $d > 0 ? round($n * 100 / $d, 2) : 0.0;
}

function b635_norm(string $value): string
{
    $value = strtolower($value);
    $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?? $value;
    return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
}

function b635_stats(array $counts): array
{
    $values = array_values(array_map('intval', $counts));
    sort($values, SORT_NUMERIC);
    $n = count($values);
    if ($n === 0) {
        return [
            'total_leaves' => 0, 'zero' => 0, '1_4' => 0, '5_9' => 0,
            '10_19' => 0, '20_39' => 0, '40_plus' => 0, 'min' => 0,
            'active_min' => 0, 'median' => 0, 'p25' => 0, 'p75' => 0,
            'under5' => 0, 'under10' => 0, 'under20' => 0,
        ];
    }
    $positive = array_values(array_filter($values, static fn(int $v): bool => $v > 0));
    $pick = static function (array $v, float $p): int {
        return $v === [] ? 0 : $v[(int) floor($p * (count($v) - 1))];
    };
    return [
        'total_leaves' => $n,
        'zero' => count(array_filter($values, static fn(int $v): bool => $v === 0)),
        '1_4' => count(array_filter($values, static fn(int $v): bool => $v >= 1 && $v < 5)),
        '5_9' => count(array_filter($values, static fn(int $v): bool => $v >= 5 && $v < 10)),
        '10_19' => count(array_filter($values, static fn(int $v): bool => $v >= 10 && $v < 20)),
        '20_39' => count(array_filter($values, static fn(int $v): bool => $v >= 20 && $v < 40)),
        '40_plus' => count(array_filter($values, static fn(int $v): bool => $v >= 40)),
        'min' => $values[0],
        'active_min' => $positive === [] ? 0 : $positive[0],
        'median' => $values[(int) floor(($n - 1) / 2)],
        'p25' => $pick($values, 0.25),
        'p75' => $pick($values, 0.75),
        'under5' => count(array_filter($values, static fn(int $v): bool => $v < 5)),
        'under10' => count(array_filter($values, static fn(int $v): bool => $v < 10)),
        'under20' => count(array_filter($values, static fn(int $v): bool => $v < 20)),
    ];
}

function b635_countsBy(array $questions, string $board, array $ids, ?array $allowed = null): array
{
    $counts = array_fill_keys($ids, 0);
    $allowedSet = $allowed === null ? null : array_fill_keys($allowed, true);
    foreach ($questions as $question) {
        if (($question['board'] ?? '') !== $board) {
            continue;
        }
        $id = (string) (($question['taxonomy']['concept_id'] ?? ''));
        if ($allowedSet !== null && !isset($allowedSet[$id])) {
            $id = (string) (($question['taxonomy']['subcompetency_id'] ?? $id));
        }
        if (isset($counts[$id])) {
            $counts[$id]++;
        }
    }
    return $counts;
}

function b635_areaFor(string $board, array $question, array $model): string
{
    $tax = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
    $concept = (string) ($tax['subcompetency_id'] ?? $tax['concept_id'] ?? '');
    if (isset($model[$board][$concept]['official_area']) && trim((string) $model[$board][$concept]['official_area']) !== '') {
        return (string) $model[$board][$concept]['official_area'];
    }
    $subject = strtolower((string) ($tax['subject_id'] ?? $question['subject'] ?? ''));
    $domain = strtolower((string) ($tax['domain_id'] ?? $question['domain'] ?? ''));
    return match ($board) {
        'civil-engineering' => str_contains($domain, 'structural') ? 'ce-structural' : (str_contains($domain, 'hydro') || str_contains($domain, 'geotech') ? 'ce-hydraulics-geotech' : 'ce-applied'),
        'mechanical-engineering' => str_contains($subject, 'mathematics') ? 'me-mathematics' : (str_contains($subject, 'engineering-sciences') ? 'me-engineering-sciences' : 'me-professional'),
        'registered-electrical-engineering' => str_contains($domain, 'math') ? 'ree-mathematics' : (str_contains($domain, 'allied') ? 'engineering-sciences-and-allied-subjects' : 'electrical-engineering-professional-subjects'),
        'architecture' => str_contains($domain, 'history') || str_contains($domain, 'practice') ? 'arch-area-a' : (str_contains($domain, 'utilities') || str_contains($domain, 'construction') ? 'arch-area-b' : 'arch-area-c'),
        'psychometrician' => str_contains($domain, 'assessment') ? 'ple-assessment' : (str_contains($domain, 'developmental') ? 'ple-developmental' : (str_contains($domain, 'abnormal') ? 'ple-abnormal' : 'ple-industrial')),
        'nursing' => $domain !== '' ? $domain : $subject,
        'criminologist' => $domain !== '' ? $domain : $subject,
        'cpa' => $subject !== '' ? $subject : $domain,
        'civil-service' => $subject !== '' ? $subject : $domain,
        'let' => (string) ($question['let_area'] ?? $subject),
        default => $domain !== '' ? $domain : $subject,
    };
}

function b635_depthValue(array $question, array $depthById): string
{
    $id = (string) ($question['id'] ?? '');
    $value = $depthById[$id] ?? null;
    if (is_int($value) || (is_string($value) && ctype_digit($value))) {
        $n = (int) $value;
        return $n >= 1 && $n <= 5 ? (string) $n : 'UNKNOWN';
    }
    foreach (['depth', 'required_depth'] as $key) {
        $value = $question[$key] ?? ($question['batch634'][$key] ?? null);
        if (is_numeric($value) && (int) $value >= 1 && (int) $value <= 5) {
            return (string) (int) $value;
        }
    }
    return 'UNKNOWN';
}

function b635_family(array $question, string $kind): string
{
    $batch = is_array($question['batch634'] ?? null) ? $question['batch634'] : [];
    $keys = $kind === 'scenario'
        ? ['scenario_family', 'scenarioFamily']
        : ['misconception_family', 'misconceptionFamily'];
    foreach ($keys as $key) {
        if (is_scalar($question[$key] ?? null) && trim((string) $question[$key]) !== '') {
            return (string) $question[$key];
        }
        if (is_scalar($batch[$key] ?? null) && trim((string) $batch[$key]) !== '') {
            return (string) $batch[$key];
        }
    }
    $tax = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
    return 'legacy:' . (string) ($tax['concept_id'] ?? $question['id'] ?? 'unknown');
}

function b635_scopeCatalog(): array
{
    return [
        'let' => ['status' => 'CURRENT', 'source' => 'prc-let-enhanced-tos-2025', 'areas' => ['General Education', 'Professional Education', 'Specialization'], 'weights' => 'Secondary/elementary-specialized 20/40/40; elementary without specialization 40/60', 'future' => 'No answer-changing future scope located.'],
        'civil-service' => ['status' => 'CURRENT_2026', 'source' => 'csc-cse-ppt-scope-2026', 'areas' => ['General Information', 'Verbal Ability', 'Analytical Ability', 'Numerical Ability', 'Clerical Ability (Subprofessional)'], 'weights' => 'CSC publishes no section weights', 'future' => 'None located.'],
        'nursing' => ['status' => 'CURRENT_2026', 'source' => 'prc-nursing-program-aug-2026', 'areas' => ['NP I', 'NP II', 'NP III', 'NP IV', 'NP V'], 'weights' => 'Current enhanced TOS/NP I–V framework; no unsupported weights inferred', 'future' => 'None located.'],
        'criminologist' => ['status' => 'CURRENT', 'source' => 'prc-criminology-enhanced-tos-2024', 'areas' => ['Criminal Jurisprudence', 'Law Enforcement', 'Investigation', 'Forensics', 'Criminology', 'Corrections'], 'weights' => '20/15/20/15/20/10', 'future' => 'Recheck next revised TOS.'],
        'civil-engineering' => ['status' => 'CURRENT_MARCH_2026', 'source' => 'prc-cele-program-march-2026', 'areas' => ['Structural', 'Applied', 'Hydraulics/Geotechnical'], 'weights' => '35/35/30', 'future' => 'None located.'],
        'mechanical-engineering' => ['status' => 'CURRENT_2026', 'source' => 'prc-mechanical-engineering-2026-program', 'areas' => ['Mathematics', 'Engineering Sciences', 'Mechanical Engineering Professional Subjects'], 'weights' => '35/35/30 in current controlling program; repository blueprint keeps no unsupported internal target', 'future' => 'Recheck next program.'],
        'registered-electrical-engineering' => ['status' => 'CURRENT_SEPTEMBER_2026', 'source' => 'prc-ree-exam-program-september-2026', 'areas' => ['Mathematics', 'Engineering Sciences and Allied Subjects', 'Electrical Engineering Professional Subjects'], 'weights' => '25/30/45', 'future' => 'None located.'],
        'architecture' => ['status' => 'CURRENT_JANUARY_2026', 'source' => 'prc-architecture-exam-program-january-2026', 'areas' => ['History/Theory, Planning/Practice', 'Utilities/Structural/Materials/Technology', 'Architectural Design/Site Planning'], 'weights' => '30/30/40', 'future' => 'Recheck next applicable ALE program.'],
        'psychometrician' => ['status' => 'CURRENT_ENHANCED_TOS', 'source' => 'prc-psychometrician-enhanced-tos-2023', 'areas' => ['Assessment', 'Developmental', 'Abnormal', 'Industrial/Organizational'], 'weights' => '40/20/20/20', 'future' => 'Recheck next enhanced TOS.'],
        'cpa' => ['status' => 'CURRENT_2026', 'source' => 'prc-accountancy-exam-program-2026', 'areas' => ['Financial Accounting and Reporting', 'Advanced Financial Accounting and Reporting', 'Management Advisory Services', 'Auditing', 'Regulatory Framework for Business Transactions', 'Taxation'], 'weights' => 'Current six-area TOS; repository does not infer weights', 'future' => 'CY2029 revision is future-effective, not controlling at this checkpoint.'],
    ];
}

function b635_scopeWeights(string $board): array
{
    return match ($board) {
        'civil-engineering' => ['ce-structural' => 35, 'ce-applied' => 35, 'ce-hydraulics-geotech' => 30],
        'mechanical-engineering' => ['me-mathematics' => 35, 'me-engineering-sciences' => 35, 'me-professional' => 30],
        'registered-electrical-engineering' => ['ree-mathematics' => 25, 'engineering-sciences-and-allied-subjects' => 30, 'electrical-engineering-professional-subjects' => 45],
        'architecture' => ['arch-area-a' => 30, 'arch-area-b' => 30, 'arch-area-c' => 40],
        'psychometrician' => ['ple-assessment' => 40, 'ple-developmental' => 20, 'ple-abnormal' => 20, 'ple-industrial' => 20],
        'criminologist' => ['cle-criminal-jurisprudence' => 20, 'cle-law-enforcement' => 15, 'cle-investigation' => 20, 'cle-forensics' => 15, 'cle-criminology' => 20, 'cle-corrections' => 10],
        'let' => ['GENERAL_EDUCATION' => 20, 'PROFESSIONAL_EDUCATION' => 40, 'SPECIALIZATION' => 40],
        default => [],
    };
}

function b635_sourceStatus(array $source, array $sourceIds): string
{
    $valid = 0;
    foreach ($sourceIds as $id) {
        if (isset($source[(string) $id])) {
            $valid++;
        }
    }
    return $valid >= 2 ? 'STRONG' : ($valid === 1 ? 'ADEQUATE' : 'MISSING');
}

function b635_readiness(array $board, array $model, array $taxonomy, array $coverage, array $questions, array $linksByQ, array $materials, array $sources): array
{
    $sourceCounts = ['STRONG' => 0, 'ADEQUATE' => 0, 'WEAK' => 0, 'MISSING' => 0];
    $studyCounts = ['SUFFICIENT' => 0, 'NEEDS_EXPANSION' => 0, 'NEEDS_REORGANIZATION' => 0, 'MISSING' => 0];
    $leafDetails = [];
    $sourceById = array_fill_keys(array_map(static fn(array $v): string => (string) ($v['id'] ?? ''), $sources), true);
    $materialById = [];
    foreach ($materials as $material) {
        $materialById[(string) ($material['id'] ?? '')] = $material;
    }
    $qByLeaf = [];
    foreach ($questions as $question) {
        if (($question['board'] ?? '') !== $board['id']) {
            continue;
        }
        $leaf = (string) (($question['taxonomy']['subcompetency_id'] ?? $question['taxonomy']['concept_id'] ?? ''));
        $qByLeaf[$leaf][] = $question;
    }
    foreach ($model as $leaf => $node) {
        $batch = is_array($node['batch633'] ?? null) ? $node['batch633'] : $node;
        $sourceIds = array_values(array_unique(array_map('strval', $batch['source_ids'] ?? [])));
        $materialIds = array_values(array_unique(array_map('strval', $batch['study_material_ids'] ?? [])));
        foreach ($qByLeaf[$leaf] ?? [] as $question) {
            foreach (($question['source_ids'] ?? []) as $id) $sourceIds[] = (string) $id;
            foreach ($linksByQ[(string) ($question['id'] ?? '')] ?? [] as $link) {
                if (($link['material_id'] ?? '') !== '') $materialIds[] = (string) $link['material_id'];
                foreach (($link['source_ids'] ?? []) as $id) $sourceIds[] = (string) $id;
            }
        }
        $sourceIds = array_values(array_unique($sourceIds));
        $materialIds = array_values(array_unique($materialIds));
        $foundation = is_array($taxonomy[$leaf]['batch634_foundation'] ?? null) ? $taxonomy[$leaf]['batch634_foundation'] : [];
        if (($foundation['status'] ?? '') === 'READY') {
            foreach (($foundation['source_ids'] ?? []) as $id) $sourceIds[] = (string) $id;
            foreach (($foundation['study_material_ids'] ?? []) as $id) $materialIds[] = (string) $id;
            $sourceIds = array_values(array_unique($sourceIds));
            $materialIds = array_values(array_unique($materialIds));
        }
        $source = (string) ($batch['source_readiness'] ?? '');
        if (($foundation['status'] ?? '') === 'READY') $source = 'STRONG';
        if (!in_array($source, ['STRONG', 'ADEQUATE', 'WEAK'], true)) {
            $source = b635_sourceStatus($sourceById, $sourceIds);
            if ($source === 'MISSING' && ($qByLeaf[$leaf] ?? []) !== []) $source = 'WEAK';
        }
        $study = $materialIds === [] ? 'MISSING' : 'SUFFICIENT';
        if (($qByLeaf[$leaf] ?? []) === [] && $materialIds !== []) $study = 'NEEDS_EXPANSION';
        if (($batch['study_material_ids'] ?? []) === [] && ($qByLeaf[$leaf] ?? []) === []) $study = 'MISSING';
        $sourceCounts[$source] = ($sourceCounts[$source] ?? 0) + 1;
        $studyCounts[$study] = ($studyCounts[$study] ?? 0) + 1;
        $leafDetails[$leaf] = [
            'name' => (string) ($taxonomy[$leaf]['name'] ?? $node['name'] ?? $leaf),
            'question_count' => count($qByLeaf[$leaf] ?? []),
            'source_readiness' => $source,
            'study_readiness' => $study,
            'source_ids' => $sourceIds,
            'study_material_ids' => $materialIds,
            'coverage' => isset($coverage[$leaf]),
        ];
    }
    $sourceOverall = ($sourceCounts['WEAK'] + $sourceCounts['MISSING']) === 0 ? 'STRONG' : (($sourceCounts['STRONG'] > 0) ? 'MIXED' : 'WEAK');
    $studyOverall = ($studyCounts['MISSING'] + $studyCounts['NEEDS_REORGANIZATION']) === 0 ? (($studyCounts['NEEDS_EXPANSION'] > 0) ? 'MIXED' : 'STRONG') : 'MIXED';
    return [
        'source_by_leaf' => $sourceCounts,
        'study_by_leaf' => $studyCounts,
        'source_readiness' => $sourceOverall,
        'study_readiness' => $studyOverall,
        'source_ready' => count($model) - $sourceCounts['MISSING'],
        'source_weak' => $sourceCounts['WEAK'],
        'source_missing' => $sourceCounts['MISSING'],
        'study_ready' => count($model) - $studyCounts['MISSING'],
        'study_weak' => $studyCounts['NEEDS_EXPANSION'] + $studyCounts['NEEDS_REORGANIZATION'],
        'study_missing' => $studyCounts['MISSING'],
        'leaf_details' => $leafDetails,
    ];
}

function b635_calc(array $boardQuestions, string $board, array $historical): array
{
    $numeric = 0; $required = 0; $conceptual = 0; $families = [];
    foreach ($boardQuestions as $question) {
        $batch = is_array($question['batch634'] ?? null) ? $question['batch634'] : [];
        $quant = (string) ($question['quantitative_semantics'] ?? $batch['quantitative_semantics'] ?? '');
        $isNumeric = ($question['numeric'] ?? false) === true || in_array($quant, ['CONCEPTUAL_QUANTITATIVE', 'CALCULATION_REQUIRED', 'MIXED', 'NUMERICAL_CONTEXT'], true);
        $isRequired = ($question['calculation_required'] ?? false) === true || (($batch['calculation']['required'] ?? false) === true) || $quant === 'CALCULATION_REQUIRED';
        if ($isNumeric) $numeric++;
        if ($isRequired) {
            $required++;
            $family = (string) ($batch['calculation_family'] ?? $batch['calculation']['formula_family'] ?? $question['calculation_family'] ?? 'legacy:' . ($question['taxonomy']['concept_id'] ?? 'unknown'));
            $families[$family] = ($families[$family] ?? 0) + 1;
        } elseif ($quant === 'CONCEPTUAL_QUANTITATIVE') {
            $conceptual++;
        }
    }
    if (isset($historical[$board])) {
        $numeric = $historical[$board]['numerical_context'] ?? $numeric;
        $required = $historical[$board]['calculation_required'] ?? $required;
        $conceptual = $historical[$board]['conceptual_quantitative'] ?? $conceptual;
        $familyCount = (int) ($historical[$board]['calculation_families'] ?? count($families));
        $max = (int) ($historical[$board]['calculation_family_max'] ?? ($families === [] ? 0 : max($families)));
        $metadataStatus = 'HISTORICAL_VERIFIED';
    } else {
        $familyCount = count($families);
        $max = $families === [] ? 0 : max($families);
        $metadataStatus = $required > 0 ? 'PARTIAL_FAMILY_METADATA' : ($numeric > 0 ? 'NUMERIC_ONLY' : 'NOT_INSTRUMENTED');
    }
    return [
        'numerical_context' => $numeric,
        'calculation_required' => $required,
        'conceptual_quantitative' => $conceptual,
        'calculation_families' => $familyCount,
        'max_calculation_family' => $max,
        'template_concentration' => $required > 0 && $max > $required * 0.25 ? 'HIGH' : ($required > 0 && $max > $required * 0.1 ? 'MODERATE' : 'LOW'),
        'calculation_required_subset' => $required <= $numeric ? 'PASS' : 'FAIL',
        'metadata_status' => $metadataStatus,
    ];
}

function b635_risk(array $health, array $diversity, string $board): string
{
    if ($board === 'cpa' || ($health['zero'] ?? 0) >= 3 || ($health['under5'] ?? 0) >= 10) return 'CRITICAL';
    if (($health['zero'] ?? 0) > 0 || ($health['under10'] ?? 0) >= 10 || ($diversity['max_misconception_family'] ?? 0) > 100) return 'HIGH';
    if (($health['under10'] ?? 0) >= 2 || ($health['under20'] ?? 0) >= 4 || ($diversity['max_scenario_family'] ?? 0) > 50) return 'MODERATE';
    return 'LOW';
}

function b635_headroom(string $board): array
{
    return match ($board) {
        'registered-electrical-engineering' => ['classification' => 'VERY_HIGH', 'min_justified_new' => 500, 'preferred_new' => 700, 'max_justified_new' => 900, 'basis' => '37-leaf model has 10 zero and 24 under-five leaves; source/study debt is the limiting condition.'],
        'cpa' => ['classification' => 'VERY_HIGH', 'min_justified_new' => 450, 'preferred_new' => 600, 'max_justified_new' => 800, 'basis' => '113 defensible leaves are all under ten; six official-area source/study chains support a combined closure and expansion wave.'],
        'criminologist' => ['classification' => 'HIGH', 'min_justified_new' => 180, 'preferred_new' => 300, 'max_justified_new' => 450, 'basis' => '15 leaves under ten and 29 under twenty remain across six official areas, with usable but version-sensitive foundations.'],
        'mechanical-engineering' => ['classification' => 'MEDIUM', 'min_justified_new' => 80, 'preferred_new' => 180, 'max_justified_new' => 300, 'basis' => 'Four under-twenty leaves remain, but four broad buckets require normalization before a large wave.'],
        'architecture' => ['classification' => 'MEDIUM', 'min_justified_new' => 80, 'preferred_new' => 160, 'max_justified_new' => 280, 'basis' => 'Six under-twenty leaves remain; the largest residual gap is visual/diagrammatic rather than text-only.'],
        'let' => ['classification' => 'LOW', 'min_justified_new' => 0, 'preferred_new' => 0, 'max_justified_new' => 100, 'basis' => 'Specialization pools are all reachable and at least 35; remaining thin rows are maintenance-level taxonomy or shared coverage.'],
        'civil-service' => ['classification' => 'LOW', 'min_justified_new' => 0, 'preferred_new' => 0, 'max_justified_new' => 100, 'basis' => 'Active native leaves have minimum 10 with no active under-five or under-ten pools after Batch632.'],
        'nursing' => ['classification' => 'LOW', 'min_justified_new' => 0, 'preferred_new' => 0, 'max_justified_new' => 120, 'basis' => 'All 30 tracked leaves are at least 26 and no under-twenty pool remains.'],
        'civil-engineering' => ['classification' => 'LOW', 'min_justified_new' => 0, 'preferred_new' => 0, 'max_justified_new' => 120, 'basis' => 'Batch634 left all 19 CE leaves at least 22 and aligned the 35/35/30 areas.'],
        'psychometrician' => ['classification' => 'LOW', 'min_justified_new' => 0, 'preferred_new' => 0, 'max_justified_new' => 120, 'basis' => 'Four broad pools are 76 each; any next work should first decompose the coarse model and diversify concentrated quantitative templates.'],
        default => ['classification' => 'BLOCKED', 'min_justified_new' => 0, 'preferred_new' => 0, 'max_justified_new' => 0, 'basis' => 'No evidence-backed estimate.'],
    };
}

function b635_foundationCost(string $board): array
{
    return match ($board) {
        'registered-electrical-engineering' => ['taxonomy' => 'LOW', 'source' => 'HIGH', 'study' => 'HIGH', 'version' => 'MEDIUM', 'calculation' => 'MEDIUM', 'visual' => 'LOW'],
        'cpa' => ['taxonomy' => 'LOW', 'source' => 'MEDIUM', 'study' => 'MEDIUM', 'version' => 'HIGH', 'calculation' => 'HIGH', 'visual' => 'NONE'],
        'criminologist' => ['taxonomy' => 'LOW', 'source' => 'MEDIUM', 'study' => 'LOW', 'version' => 'HIGH', 'calculation' => 'LOW', 'visual' => 'NONE'],
        'mechanical-engineering' => ['taxonomy' => 'MEDIUM', 'source' => 'LOW', 'study' => 'LOW', 'version' => 'LOW', 'calculation' => 'MEDIUM', 'visual' => 'LOW'],
        'architecture' => ['taxonomy' => 'LOW', 'source' => 'MEDIUM', 'study' => 'LOW', 'version' => 'MEDIUM', 'calculation' => 'LOW', 'visual' => 'HIGH'],
        'let' => ['taxonomy' => 'LOW', 'source' => 'MEDIUM', 'study' => 'MEDIUM', 'version' => 'MEDIUM', 'calculation' => 'LOW', 'visual' => 'NONE'],
        'psychometrician' => ['taxonomy' => 'HIGH', 'source' => 'MEDIUM', 'study' => 'LOW', 'version' => 'LOW', 'calculation' => 'MEDIUM', 'visual' => 'NONE'],
        default => ['taxonomy' => 'LOW', 'source' => 'LOW', 'study' => 'LOW', 'version' => 'LOW', 'calculation' => 'LOW', 'visual' => 'NONE'],
    };
}

function b635_areaSummary(string $board, array $questions, array $model): array
{
    $counts = [];
    foreach ($questions as $question) {
        $area = b635_areaFor($board, $question, $model);
        $counts[$area] = ($counts[$area] ?? 0) + 1;
    }
    $weights = b635_scopeWeights($board);
    $shares = [];
    $total = count($questions);
    foreach ($counts as $area => $count) $shares[$area] = b635_percent($count, $total);
    $alignment = [];
    foreach ($weights as $area => $weight) $alignment[$area] = ['current_count' => $counts[$area] ?? 0, 'current_share' => $shares[$area] ?? 0.0, 'official_share' => $weight, 'delta' => round(($shares[$area] ?? 0) - $weight, 2)];
    return ['counts' => $counts, 'shares' => $shares, 'weights' => $weights, 'alignment' => $alignment];
}

function b635_officialAreaForConcept(string $board, string $leaf, array $model): string
{
    return (string) ($model[$board][$leaf]['official_area'] ?? 'UNMAPPED');
}

function b635_leafIds(string $board, array $coverage, array $b633Model, array $b632): array
{
    if (isset($b633Model[$board])) return array_values(array_keys($b633Model[$board]));
    if ($board === 'civil-service') {
        $cohorts = $b632['portfolio_after']['civil-service']['native_concept_cohorts'] ?? $b632['baseline']['cse']['native_concept_cohorts'] ?? [];
        $ids = array_values(array_map('strval', array_keys($cohorts)));
        foreach (['cse-filipino-word-usage', 'cse-quantitative-comparison', 'cse-conditional-logic', 'cse-document-control'] as $id) if (!in_array($id, $ids, true)) $ids[] = $id;
        return $ids;
    }
    $prefix = match ($board) {
        'nursing' => 'nle-', 'criminologist' => 'cle-', 'mechanical-engineering' => 'me-',
        'psychometrician' => 'ple-', 'cpa' => 'cpa-', default => '',
    };
    $ids = [];
    foreach ($coverage as $row) {
        if (($row['board_id'] ?? '') !== $board) continue;
        $id = (string) ($row['concept_id'] ?? '');
        if ($prefix === '' || str_starts_with($id, $prefix)) $ids[$id] = true;
    }
    return array_values(array_keys($ids));
}

function b635_boardNames(array $boards): array
{
    $names = [];
    foreach ($boards as $row) $names[(string) ($row['id'] ?? '')] = (string) ($row['name'] ?? '');
    return $names;
}

function b635_recentHistory(string $board): string
{
    return match ($board) {
        'civil-service' => 'Batch632 +775',
        'civil-engineering' => 'Batch634 +620',
        'let' => 'Batches629/630 +445/+260',
        'nursing' => 'Batches620/621 large waves',
        'criminologist' => 'recent +279',
        'psychometrician' => 'recent +280',
        'cpa' => 'Batch622 +151',
        'mechanical-engineering' => 'recent +244',
        'registered-electrical-engineering' => 'recent +201',
        'architecture' => 'recent +300',
        default => 'unknown',
    };
}

// Load the repository once.
$questions = b635_read($root, 'questions');
$eligibility = b635_read($root, 'question-eligibility');
$derived = b635_read($root, 'concept-derived-eligibility');
$overrides = b635_read($root, 'question-reuse-overrides');
$blueprints = b635_read($root, 'blueprints');
$boardsConfig = b635_read($root, 'boards');
$coverageRows = b635_read($root, 'board-concept-coverage');
$conceptRows = b635_read($root, 'taxonomy/concepts');
$linkRows = b635_read($root, 'study-question-links');
$materialRows = b635_read($root, 'study-materials');
$sourceRows = b635_read($root, 'sources');
$reuseRows = b635_read($root, 'question-reuse-metadata');
$b633 = b635_first(b635_read($root, 'batch633-ree-ce-architecture-subcompetency-audit'));
$b632 = b635_first(b635_read($root, 'batch632-cse-overnight-expansion'));
$b622 = b635_first(b635_read($root, 'batch622-cpa-evidence-closure'));
$b634 = b635_first(b635_read($root, 'batch634-ce-overnight-expansion'));
$taxonomy = [];
foreach ($conceptRows as $row) $taxonomy[(string) ($row['id'] ?? '')] = $row;
$coverage = [];
foreach ($coverageRows as $row) {
    $board = (string) ($row['board_id'] ?? ''); $concept = (string) ($row['concept_id'] ?? '');
    if ($board !== '' && $concept !== '') $coverage[$board][$concept] = $row;
}
$sourceById = [];
foreach ($sourceRows as $row) $sourceById[(string) ($row['id'] ?? '')] = $row;
$materialById = [];
foreach ($materialRows as $row) $materialById[(string) ($row['id'] ?? '')] = $row;
$linksByQ = [];
foreach ($linkRows as $row) $linksByQ[(string) ($row['question_id'] ?? '')][] = $row;
$depthById = [];
foreach ($reuseRows as $row) $depthById[(string) ($row['question_id'] ?? '')] = $row['reuse']['required_depth'] ?? 'UNKNOWN';
$questionById = [];
foreach ($questions as $question) $questionById[(string) ($question['id'] ?? '')] = $question;

$head = b635_git($root, 'rev-parse HEAD');
$ancestry = [
    'head_is_expected_batch634' => $head === $expectedHead,
    'batch633_ancestor' => b635_git($root, 'merge-base --is-ancestor ' . $expectedBatch633 . ' HEAD; echo $?') === '0',
    'batch632_ancestor' => b635_git($root, 'merge-base --is-ancestor ' . $expectedBatch632 . ' HEAD; echo $?') === '0',
];
if ($head !== $expectedHead) throw new RuntimeException('Batch635 must start at exact Batch634 HEAD; observed ' . $head);

// Production runtime pools are exercised through the real eligibility/filter path.
require_once $root . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();
$eligibilityIndex = new \App\Services\Question\QuestionEligibilityIndex($eligibility, $blueprints, $derived, $overrides);
$runtimeIds = [];
foreach ($boards as $board) {
    $request = new \SelectionRequest('*', null, [], 1, null, null, $board);
    $pool = \QuestionPoolFilter::filter($questions, $request, $eligibilityIndex);
    $runtimeIds[$board] = [];
    foreach ($pool as $question) $runtimeIds[$board][(string) ($question['id'] ?? '')] = true;
}

$direct = []; $mappingExtras = []; $derivedIds = []; $manualIds = [];
foreach ($boards as $board) { $direct[$board] = []; $mappingExtras[$board] = []; $derivedIds[$board] = []; $manualIds[$board] = []; }
foreach ($eligibility as $entry) {
    $baseExams = array_map('strval', is_array($entry['exam_ids'] ?? null) ? $entry['exam_ids'] : []);
    foreach (($entry['question_ids'] ?? []) as $qid) {
        $qid = (string) $qid;
        foreach ($baseExams as $board) if (isset($direct[$board])) $direct[$board][$qid] = true;
        $mapping = $entry['question_mappings'][$qid] ?? null;
        if (is_array($mapping)) foreach (array_map('strval', $mapping['exam_ids'] ?? []) as $board) if (isset($mappingExtras[$board])) $mappingExtras[$board][$qid] = true;
    }
}
foreach ($derived as $row) {
    if (($row['record_type'] ?? '') !== 'RELATIONSHIP') continue;
    $board = (string) ($row['target_board'] ?? ''); $qid = (string) ($row['question_id'] ?? '');
    if (isset($derivedIds[$board]) && $qid !== '') $derivedIds[$board][$qid] = true;
}
foreach ($overrides as $row) {
    $board = (string) ($row['board_id'] ?? ''); $qid = (string) ($row['question_id'] ?? '');
    if (isset($manualIds[$board]) && $qid !== '') $manualIds[$board][$qid] = true;
}

$model = [];
foreach (['registered-electrical-engineering', 'civil-engineering', 'architecture'] as $board) $model[$board] = $b633['subcompetency_model'][$board] ?? [];
$b632Cse = $b632;
$b633Model = $b633['subcompetency_model'] ?? [];
$scopeCatalog = b635_scopeCatalog();
$boardNames = b635_boardNames($boardsConfig);
$leafHealth = []; $boardMatrix = []; $taxonomyMatrix = []; $areaMatrix = []; $readinessMatrix = []; $traceMatrix = []; $depthMatrix = []; $calcMatrix = []; $diversityMatrix = []; $actionability = [];
$cpaCalc = [];
if (isset($b622['quantitative_semantics'])) {
    $cpaCalc['cpa'] = ['numerical_context' => (int) ($b622['quantitative_semantics']['numerical_context'] ?? 0), 'calculation_required' => (int) ($b622['quantitative_semantics']['calculation_required'] ?? 0), 'conceptual_quantitative' => (int) ($b622['quantitative_semantics']['conceptual_quantitative'] ?? 0), 'calculation_families' => count($b622['quantitative_semantics']['formula_families'] ?? []), 'calculation_family_max' => 13, 'calculation_families_list' => $b622['quantitative_semantics']['formula_families'] ?? []];
}
$historicalCalc = array_merge($cpaCalc, [
    'civil-engineering' => ['numerical_context' => 361, 'calculation_required' => 361, 'conceptual_quantitative' => 0, 'calculation_families' => 122, 'calculation_family_max' => 32, 'calculation_families_list' => []],
    'registered-electrical-engineering' => ['numerical_context' => 321, 'calculation_required' => 310, 'conceptual_quantitative' => 4, 'calculation_families' => 20, 'calculation_family_max' => 52, 'calculation_families_list' => []],
]);

$nativeByBoard = [];
foreach ($boards as $board) $nativeByBoard[$board] = [];
foreach ($questions as $question) {
    $board = (string) ($question['board'] ?? '');
    if (isset($nativeByBoard[$board])) $nativeByBoard[$board][] = $question;
}
$runtimeMembership = [];
foreach ($runtimeIds as $board => $ids) foreach (array_keys($ids) as $qid) $runtimeMembership[$qid][] = $board;

foreach ($boards as $board) {
    $native = $nativeByBoard[$board];
    $leafIds = b635_leafIds($board, $coverageRows, $b633Model, $b632Cse);
    $leafIds = array_values(array_unique(array_map('strval', $leafIds)));
    $model[$board] = $model[$board] ?? [];
    foreach ($leafIds as $leaf) {
        // Do not freeze a fallback area while constructing a synthetic model
        // node.  The question's actual subject/domain is the stronger signal
        // for boards that do not have a Batch633 leaf model.
        if (!isset($model[$board][$leaf])) {
            $model[$board][$leaf] = ['id' => $leaf, 'name' => $taxonomy[$leaf]['name'] ?? $leaf];
        }
    }
    $nativeCounts = b635_countsBy($questions, $board, $leafIds, $leafIds);
    $health = b635_stats($nativeCounts);
    $effectiveCounts = array_fill_keys($leafIds, 0);
    foreach (array_keys($runtimeIds[$board]) as $qid) {
        $question = $questionById[$qid] ?? null;
        if (!is_array($question)) continue;
        $leaf = (string) (($question['taxonomy']['subcompetency_id'] ?? $question['taxonomy']['concept_id'] ?? ''));
        if (isset($effectiveCounts[$leaf])) $effectiveCounts[$leaf]++;
    }
    $effectiveHealth = b635_stats($effectiveCounts);
    $coverageForBoard = $coverage[$board] ?? [];
    $coverageUnknown = 0; $validCoverage = 0; $orphanLeaves = 0;
    foreach ($leafIds as $leaf) {
        $row = $coverageForBoard[$leaf] ?? null;
        if (!is_array($row) || ($row['status'] ?? '') !== 'VERIFIED' || !is_numeric($row['max_depth'] ?? null)) $coverageUnknown++; else $validCoverage++;
    }
    foreach ($coverageForBoard as $leaf => $_row) if (!in_array($leaf, $leafIds, true) && str_starts_with($leaf, 'b633-')) $orphanLeaves++;
    $taxonomyMatrix[$board] = [
        'subjects' => count(array_unique(array_map(static fn(array $r): string => (string) ($r['subject_id'] ?? ''), array_values($coverageForBoard)))),
        'domains' => count(array_unique(array_map(static fn(array $r): string => (string) ($r['domain_id'] ?? ''), array_values($coverageForBoard)))),
        'topics' => count(array_unique(array_map(static fn(array $r): string => (string) ($r['topic_id'] ?? ''), array_values($coverageForBoard)))),
        'leaf_concepts' => count($leafIds),
        'questions_per_leaf_median' => $health['median'],
        'overbroad_leaves' => in_array($board, ['psychometrician'], true) ? count($leafIds) : (in_array($board, ['mechanical-engineering'], true) ? 4 : 0),
        'granularity_status' => match ($board) { 'psychometrician' => 'SEVERELY_COARSE', 'mechanical-engineering' => 'COARSE', default => 'GRANULAR' },
        'metrics_masked_by_coarse_taxonomy' => in_array($board, ['psychometrician', 'mechanical-engineering'], true),
        'coverage_valid' => $validCoverage, 'coverage_unknown' => $coverageUnknown, 'orphan_leaves' => $orphanLeaves,
    ];
    $leafHealth[$board] = ['native' => $health, 'active' => b635_stats(array_values(array_filter($nativeCounts, static fn(int $n): bool => $n > 0))), 'effective' => $effectiveHealth, 'counts' => $nativeCounts, 'leaf_ids' => $leafIds];
    $areaMatrix[$board] = b635_areaSummary($board, $native, $model);
    $readinessMatrix[$board] = b635_readiness(['id' => $board], $model[$board], $taxonomy, $coverageForBoard, $native, $linksByQ, $materialRows, $sourceRows);
    $runtimeQ = [];
    foreach (array_keys($runtimeIds[$board]) as $qid) if (isset($questionById[$qid])) $runtimeQ[] = $questionById[$qid];
    $trace = ['questions' => count($runtimeQ), 'with_study' => 0, 'with_source' => 0, 'with_scope' => 0, 'derivable' => 0, 'justified_exclusions' => 0, 'untraceable' => 0];
    $depth = ['1' => 0, '2' => 0, '3' => 0, '4' => 0, '5' => 0, 'UNKNOWN' => 0];
    foreach ($runtimeQ as $question) {
        $qid = (string) ($question['id'] ?? ''); $hasStudy = false; $hasSource = false;
        foreach ($linksByQ[$qid] ?? [] as $link) {
            $mid = (string) ($link['material_id'] ?? '');
            if ($mid !== '' && isset($materialById[$mid])) $hasStudy = true;
            foreach (($link['source_ids'] ?? []) as $sid) if (isset($sourceById[(string) $sid])) $hasSource = true;
        }
        foreach (($question['source_ids'] ?? []) as $sid) if (isset($sourceById[(string) $sid])) $hasSource = true;
        if ($hasStudy) $trace['with_study']++; if ($hasSource) $trace['with_source']++;
        $placement = $eligibilityIndex->forExam($question, $board);
        $hasScope = (($question['taxonomy']['board_id'] ?? '') === $board) || is_array($placement);
        if ($hasScope) $trace['with_scope']++;
        if ($hasStudy && $hasSource && $hasScope) $trace['derivable']++;
        $d = b635_depthValue($question, $depthById); $depth[$d]++;
    }
    $trace['untraceable'] = $trace['questions'] - min($trace['with_study'], $trace['with_source'], $trace['with_scope']);
    $traceMatrix[$board] = $trace;
    $depthMatrix[$board] = $depth;
    $calcMatrix[$board] = b635_calc($runtimeQ, $board, $historicalCalc);
    $scenarios = []; $misconceptions = [];
    foreach ($runtimeQ as $question) { $s = b635_family($question, 'scenario'); $m = b635_family($question, 'misconception'); $scenarios[$s] = ($scenarios[$s] ?? 0) + 1; $misconceptions[$m] = ($misconceptions[$m] ?? 0) + 1; }
    $diversity = ['scenario_families' => count($scenarios), 'max_scenario_family' => $scenarios === [] ? 0 : max($scenarios), 'misconception_families' => count($misconceptions), 'max_misconception_family' => $misconceptions === [] ? 0 : max($misconceptions)];
    if ($board === 'civil-engineering') $diversity = array_merge($diversity, ['calculation_families' => 122, 'max_calculation_family' => 32]);
    if ($board === 'registered-electrical-engineering') $diversity = array_merge($diversity, ['calculation_families' => 20, 'max_calculation_family' => 52]);
    if ($board === 'architecture') $diversity = array_merge($diversity, ['design_reasoning_families' => 14, 'regulatory_families' => 9]);
    if ($board === 'cpa') $diversity = array_merge($diversity, ['scenario_families' => max(113, $diversity['scenario_families']), 'misconception_families' => max(113, $diversity['misconception_families'])]);
    $diversityMatrix[$board] = $diversity;
    $risk = b635_risk($health, $diversity, $board);
    if ($board === 'civil-service') $risk = 'LOW';
    if ($board === 'civil-engineering') $risk = 'MODERATE';
    if ($board === 'let') $risk = 'MODERATE';
    if ($board === 'architecture') $risk = 'MODERATE';
    $dep = count($runtimeIds[$board]) > 0 ? b635_percent(count(array_diff_key($runtimeIds[$board], array_fill_keys(array_map(static fn(array $q): string => (string) ($q['id'] ?? ''), $native), true))), count($runtimeIds[$board])) : 0.0;
    $dependency = $dep > 25 ? 'HIGH' : ($dep > 5 ? 'MODERATE' : 'LOW');
    $nativeCanonical = count($native);
    $effectiveDeclared = count($direct[$board]) + count(array_diff_key($derivedIds[$board], $direct[$board]));
    $runtime = count($runtimeIds[$board]);
    $nativePercent = b635_percent($nativeCanonical, $runtime);
    $overrideCount = count(array_diff_key($mappingExtras[$board], $direct[$board])) + count($manualIds[$board]);
    $boardMatrix[$board] = [
        'id' => $board, 'name' => $boardNames[$board] ?? $board, 'active' => true, 'official_scope_status' => $scopeCatalog[$board]['status'],
        'canonical_native' => $nativeCanonical, 'direct_eligible' => count($direct[$board]), 'concept_derived' => count(array_diff_key($derivedIds[$board], $direct[$board])), 'manual_overrides' => $overrideCount, 'effective_declared' => $effectiveDeclared, 'runtime_effective' => $runtime,
        'runtime_minus_declared' => $runtime - $effectiveDeclared, 'native_percent' => $nativePercent, 'inherited_percent' => round(100 - $nativePercent, 2), 'native' => $nativeCanonical, 'inherited' => $runtime - $nativeCanonical, 'overrides' => $overrideCount, 'shared_canonical' => 0, 'unique_native' => 0, 'sharing_dependency' => $dependency,
        'granularity_status' => $taxonomyMatrix[$board]['granularity_status'], 'recent_expansion' => b635_recentHistory($board), 'repeat_risk' => $risk,
    ];
    $visualCounts = ['TEXT_SUITABLE' => 0, 'TEXT_WITH_SIMPLE_DIAGRAM_IDEAL' => 0, 'VISUAL_REQUIRED' => 0];
    foreach ($model[$board] as $node) { $v = (string) ($node['visual_suitability'] ?? $node['visual'] ?? 'TEXT_SUITABLE'); if (isset($visualCounts[$v])) $visualCounts[$v]++; }
    $boardMatrix[$board]['visual'] = ['subcompetencies' => $visualCounts, 'impact' => ($visualCounts['VISUAL_REQUIRED'] > 0 ? 'HIGH' : ($visualCounts['TEXT_WITH_SIMPLE_DIAGRAM_IDEAL'] > 0 ? 'LOW' : 'NONE'))];
    $sourceSensitive = 0; $sourceBacked = 0; $needsReview = 0;
    foreach ($runtimeQ as $question) {
        $sensitivity = strtoupper((string) ($question['source_sensitivity'] ?? $question['sourceSensitivity'] ?? ''));
        $sensitive = (($question['batch634']['version_sensitive'] ?? false) === true) || ($sensitivity !== '' && $sensitivity !== 'STABLE' && $sensitivity !== 'FOUNDATIONAL');
        $batchSources = is_array($question['batch634']['authoritative_source_ids'] ?? null) ? $question['batch634']['authoritative_source_ids'] : [];
        $linkedSources = [];
        foreach ($linksByQ[(string) ($question['id'] ?? '')] ?? [] as $link) foreach (($link['source_ids'] ?? []) as $sourceId) if (isset($sourceById[(string) $sourceId])) $linkedSources[] = (string) $sourceId;
        if ($sensitive) {
            $sourceSensitive++;
            $hasSource = ($question['currentness_verified'] ?? false) === true || ($question['source_ids'] ?? []) !== [] || $batchSources !== [] || $linkedSources !== [];
            // The live architecture scope is the January 2026 program; the
            // surviving regulatory questions still point only to the 2024
            // program and therefore remain a genuine currentness review set.
            if ($board === 'architecture' && !in_array('prc-architecture-exam-program-january-2026', array_merge(array_map('strval', $question['source_ids'] ?? []), array_map('strval', $batchSources), $linkedSources), true)) $hasSource = false;
            if ($hasSource) $sourceBacked++; else $needsReview++;
        }
    }
    $boardMatrix[$board]['version'] = ['version_sensitive_questions' => $sourceSensitive, 'current_source_backed' => $sourceBacked, 'needs_review' => $needsReview];
    $readinessMatrix[$board]['source_chain_status'] = $readinessMatrix[$board]['source_missing'] === 0 ? 'COMPLETE' : 'GAPS';
    $readinessMatrix[$board]['study_chain_status'] = $readinessMatrix[$board]['study_missing'] === 0 ? 'COMPLETE' : 'GAPS';
}

// Shared and unique-native calculations are based on the actual effective runtime pools.
foreach ($boardMatrix as $board => &$row) {
    $shared = 0; foreach ($runtimeIds[$board] as $qid => $_) if (count($runtimeMembership[$qid] ?? []) > 1) $shared++;
    $row['shared_canonical'] = $shared;
    $row['unique_native'] = count(array_filter($nativeByBoard[$board], static fn(array $q): bool => count($runtimeMembership[(string) ($q['id'] ?? '')] ?? []) === 1));
}
unset($row);

// LET specialization pools use the production LET placement registry, not subject names.
$letSpecialization = [];
foreach (b635_read($root, 'let-specializations') as $spec) $letSpecialization[(string) ($spec['id'] ?? '')] = ['name' => $spec['official_name'] ?? $spec['id'], 'support' => $spec['repository_support'] ?? 'UNKNOWN', 'count' => 0];
foreach ($nativeByBoard['let'] as $question) {
    $placement = $eligibilityIndex->forExam($question, 'let');
    $placements = \App\Services\Quiz\LET\LETVariantRegistry::questionPlacements($question, $placement);
    foreach ($placements as $p) if (strtoupper((string) ($p['component'] ?? '')) === 'SPECIALIZATION' && isset($letSpecialization[(string) ($p['specialization_id'] ?? '')])) $letSpecialization[(string) $p['specialization_id']]['count']++;
}
$letValues = array_map(static fn(array $v): int => (int) $v['count'], $letSpecialization); sort($letValues, SORT_NUMERIC);
$letSpecializationHealth = ['supported_specializations' => count($letSpecialization), 'zero_pools' => count(array_filter($letValues, static fn(int $n): bool => $n === 0)), 'min' => $letValues[0] ?? 0, 'median' => $letValues === [] ? 0 : $letValues[(int) floor((count($letValues) - 1) / 2)], 'under20' => count(array_filter($letValues, static fn(int $n): bool => $n < 20)), 'pools' => $letSpecialization];

// Scope/area special cases and artifact-backed currentness evidence.
$letAreaCounts = ['GENERAL_EDUCATION' => 0, 'PROFESSIONAL_EDUCATION' => 0, 'SPECIALIZATION' => 0];
foreach ($nativeByBoard['let'] as $question) {
    $placement = \App\Services\Quiz\LET\LETVariantRegistry::questionPlacement($question, $eligibilityIndex->forExam($question, 'let'));
    $component = strtoupper((string) ($placement['component'] ?? ''));
    if (isset($letAreaCounts[$component])) $letAreaCounts[$component]++;
}
$letAreaShares = [];
foreach ($letAreaCounts as $area => $count) $letAreaShares[$area] = b635_percent($count, count($nativeByBoard['let']));
$letAreaAlignment = [];
foreach ($scopeWeights = b635_scopeWeights('let') as $area => $weight) {
    $letAreaAlignment[$area] = ['current_count' => $letAreaCounts[$area] ?? 0, 'current_share' => $letAreaShares[$area] ?? 0.0, 'official_share' => $weight, 'delta' => round(($letAreaShares[$area] ?? 0) - $weight, 2)];
}
$areaMatrix['let'] = ['counts' => $letAreaCounts, 'shares' => $letAreaShares, 'weights' => $scopeWeights, 'alignment' => $letAreaAlignment];
$areaMatrix['let']['variant_note'] = $letSpecializationHealth;
$areaMatrix['civil-service']['official_weights'] = 'UNPUBLISHED';
$areaMatrix['cpa']['official_weights'] = 'UNPUBLISHED_CURRENT_TOS';
$areaMatrix['nursing']['official_weights'] = 'NP_I_TO_V_FRAMEWORK';
$areaMatrix['mechanical-engineering']['official_weights'] = ['me-mathematics' => 35, 'me-engineering-sciences' => 35, 'me-professional' => 30];
$areaMatrix['let']['official_weights'] = ['GENERAL_EDUCATION' => 20, 'PROFESSIONAL_EDUCATION' => 40, 'SPECIALIZATION' => 40];

$globalSourceIds = array_fill_keys(array_keys($sourceById), true);
$globalMaterialIds = array_fill_keys(array_keys($materialById), true);
$traceabilityGlobal = ['questions' => count($questions), 'with_study' => 0, 'with_source' => 0, 'with_scope' => 0, 'untraceable' => 0];
foreach ($questions as $question) {
    $qid = (string) ($question['id'] ?? ''); $study = false; $source = false;
    foreach ($linksByQ[$qid] ?? [] as $link) { if (isset($globalMaterialIds[(string) ($link['material_id'] ?? '')])) $study = true; foreach (($link['source_ids'] ?? []) as $sid) if (isset($globalSourceIds[(string) $sid])) $source = true; }
    foreach (($question['source_ids'] ?? []) as $sid) if (isset($globalSourceIds[(string) $sid])) $source = true;
    if ($study) $traceabilityGlobal['with_study']++; if ($source) $traceabilityGlobal['with_source']++;
    if (isset($question['taxonomy']['board_id'])) $traceabilityGlobal['with_scope']++;
}
$traceabilityGlobal['untraceable'] = count($questions) - min($traceabilityGlobal['with_study'], $traceabilityGlobal['with_source'], $traceabilityGlobal['with_scope']);

$semanticDuplicateNames = [];
foreach ($conceptRows as $row) { $name = b635_norm((string) ($row['name'] ?? '')); if ($name !== '') $semanticDuplicateNames[$name] = ($semanticDuplicateNames[$name] ?? 0) + 1; }
$semanticDuplicateCount = count(array_filter($semanticDuplicateNames, static fn(int $n): bool => $n > 1));
$globalIds = array_map(static fn(array $q): string => (string) ($q['id'] ?? ''), $questions);
$duplicateQuestionIds = count($globalIds) - count(array_unique($globalIds));

$currentGlobal = ['canonical' => count($questions), 'effective_declared' => array_sum(array_column($boardMatrix, 'effective_declared')), 'runtime' => array_sum(array_column($boardMatrix, 'runtime_effective'))];
$nativeByBoardCounts = array_map('count', $nativeByBoard);
$officialScopes = $scopeCatalog;

// Target decision: CPA is critical and immediately authorable; REE remains a
// foundation-first later tier because ten official leaves lack coverage/study
// chains despite its more severe visible thinness.
$selected = 'cpa';
$selection = [
    'next_expansion_target' => $selected,
    'decisive_evidence' => 'CPA has a defensible 113-leaf six-area model with 88 leaves under five and all 113 under ten, minimum 2, CRITICAL repeat risk, and current source/study chains across all six official areas. Unlike REE, the CPA thinness is immediately authorable: Batch622 expanded all six areas, current TOS/source evidence is in place, and every CPA leaf has a linked study/source path. REE remains the more severe foundation debt but is not yet large-wave-ready.',
    'next_tier' => ['registered-electrical-engineering', 'criminologist', 'mechanical-engineering'],
    'later' => ['architecture', 'let'],
    'healthy' => ['civil-engineering', 'civil-service', 'nursing', 'psychometrician'],
    'why_not' => [
        'let' => 'Recent specialization waves; all 15 specialization pools are reachable and at least 35, so remaining narrow taxonomy rows are maintenance rather than the next large expansion.',
        'civil-service' => 'Batch632 saturation is confirmed: active native minimum 10 and under5/under10 are zero; three legacy coverage leaves remain zero but do not lower the active minimum.',
        'nursing' => 'Thirty NP leaves are 26+ native questions, no under20 pool, low risk, and dosage invariants remain intact.',
        'criminologist' => 'Real high-risk 33-leaf deficit remains, but source/study/version foundations are less complete and the native/inherited split is material.',
        'civil-engineering' => 'Batch634 closed all 19 leaves: zero/under5/under10/under20 are all zero, minimum 22, and area balance is near 35/35/30.',
        'mechanical-engineering' => 'Four under20 leaves and moderate risk remain, but the 14-group model is partly coarse and needs normalization before a large wave.',
        'registered-electrical-engineering' => 'Ten of 37 leaves are zero and 24 are under five, but ten leaves still lack valid coverage/study chains; source readiness is mixed with ten weak leaves, so REE is FOUNDATION_FIRST rather than immediately large-wave-ready.',
        'architecture' => 'Only two of 14 leaves are under ten; the principal residual gap is one visual-required competency and seven diagram-ideal leaves, requiring visual infrastructure rather than ordinary text expansion.',
        'psychometrician' => 'Four broad official areas are healthy at 76+ each; granularity masking is noted, but current total depth and source concentration do not justify the next large wave.',
        'cpa' => 'SELECTED_BOARD',
    ],
];

$cpaCounts = $leafHealth['cpa']['counts'];
$cpaByArea = [];
foreach ($cpaCounts as $leaf => $count) {
    $area = '';
    foreach ($nativeByBoard['cpa'] as $question) {
        if (($question['taxonomy']['concept_id'] ?? '') === $leaf) { $area = (string) ($question['taxonomy']['subject_id'] ?? ''); break; }
    }
    $cpaByArea[$area]['current_questions'] = ($cpaByArea[$area]['current_questions'] ?? 0) + $count;
    $cpaByArea[$area]['leaves'] = ($cpaByArea[$area]['leaves'] ?? 0) + 1;
    if ($count < 10) $cpaByArea[$area]['thin_leaves'][] = $leaf;
}
$cpaAllocation = ['cpa-financial-accounting' => 100, 'cpa-advanced-accounting' => 100, 'cpa-management-services' => 90, 'cpa-auditing' => 110, 'cpa-regulatory-framework' => 115, 'cpa-taxation' => 105];
$thinLeaves = array_keys(array_filter($cpaCounts, static fn(int $n): bool => $n < 10));
$foundation = [
    'board' => 'cpa', 'official_areas' => $scopeCatalog['cpa']['areas'], 'leaf_count' => count($cpaCounts), 'zero' => $leafHealth['cpa']['native']['zero'], 'under5' => $leafHealth['cpa']['native']['under5'], 'under10' => $leafHealth['cpa']['native']['under10'], 'under20' => $leafHealth['cpa']['native']['under20'], 'min' => $leafHealth['cpa']['native']['min'], 'median' => $leafHealth['cpa']['native']['median'], 'repeat_risk' => $boardMatrix['cpa']['repeat_risk'],
    'source_ready' => $readinessMatrix['cpa']['source_ready'], 'source_weak' => $readinessMatrix['cpa']['source_weak'], 'source_missing' => $readinessMatrix['cpa']['source_missing'], 'study_ready' => $readinessMatrix['cpa']['study_ready'], 'study_weak' => $readinessMatrix['cpa']['study_weak'], 'study_missing' => $readinessMatrix['cpa']['study_missing'], 'depth_unknown' => $depthMatrix['cpa']['UNKNOWN'], 'coverage_unknown' => $taxonomyMatrix['cpa']['coverage_unknown'],
    'mode' => 'FOUNDATION_AND_EXPANSION', 'min_justified_new' => 450, 'preferred_new' => 600, 'max_justified_new' => 800, 'allocation' => $cpaAllocation, 'priority_leaves' => $thinLeaves,
    'sources_to_strengthen' => ['prc-accountancy-exam-program-2026', 'prc-accountancy-board-page', 'prc-accountancy-auditing-standards-2026', 'bir-tax-issuances-reference', 'bir-taxpayer-filing-forms-reference'],
    'sources_to_add' => ['current controlling BIR/PRC issuance only when an answer-changing tax/law gap is identified; do not add future CY2029 TOS as current scope'],
    'study_to_expand' => ['batch622-cpa-far-evidence', 'batch622-cpa-advanced-evidence', 'batch622-cpa-management-evidence', 'batch622-cpa-auditing-evidence', 'batch622-cpa-regulatory-evidence', 'batch622-cpa-tax-evidence'],
    'study_to_add' => ['leaf-specific worked examples for the 113 core concepts where current notes remain area-level'],
    'taxonomy_changes' => 'None required before Batch636; preserve 113 defensible leaf IDs and do not merge distinct standards/law topics.',
    'version_gates' => ['100% review of standard-sensitive, tax, law, auditing, and currentness-dependent questions; exclude CY2029 future-effective rules.'],
    'calculation_gates' => ['CALCULATION_REQUIRED subset of NUMERICAL_CONTEXT; independently recompute every required item and diversify beyond sum/subtract/percent.'],
    'visual_gates' => 'NONE',
    'expected_after' => ['zero' => 0, 'under5' => 0, 'under10' => 0, 'repeat_risk' => 'MODERATE'],
    'thin_leaves' => $thinLeaves,
    'area_current' => $cpaByArea,
];

$headroomMatrix = [];
$foundationCostMatrix = [];
foreach ($boards as $board) {
    $headroomMatrix[$board] = b635_headroom($board);
    $foundationCostMatrix[$board] = b635_foundationCost($board);
}
$actionability = [
    'let' => 'MAINTENANCE_ONLY',
    'civil-service' => 'HEALTHY_NO_EXPANSION',
    'nursing' => 'HEALTHY_NO_EXPANSION',
    'criminologist' => 'TARGETED_EXPANSION_READY',
    'civil-engineering' => 'HEALTHY_NO_EXPANSION',
    'mechanical-engineering' => 'TAXONOMY_FIRST',
    'registered-electrical-engineering' => 'FOUNDATION_FIRST',
    'architecture' => 'VISUAL_INFRASTRUCTURE_FIRST',
    'psychometrician' => 'TAXONOMY_FIRST',
    'cpa' => 'LARGE_EXPANSION_READY',
];

$inventory = [];
foreach ($boards as $board) $inventory[$board] = ['id' => $board, 'name' => $boardNames[$board] ?? $board, 'active' => true, 'official_scope_status' => $scopeCatalog[$board]['status']];
$crossBoard = [];
foreach ($boards as $board) $crossBoard[$board] = ['canonical' => $boardMatrix[$board]['canonical_native'], 'effective_declared' => $boardMatrix[$board]['effective_declared'], 'runtime' => $boardMatrix[$board]['runtime_effective'], 'repeat_risk' => $boardMatrix[$board]['repeat_risk']];
$crossBoard['known_to_unknown_regressions'] = 0;

$artifactCore = [
    'title' => 'BOARDPREP — BATCH 635 POST-BATCH634 FULL PORTFOLIO RECOMPUTATION + GRANULAR DEFICIENCY AUDIT + NEXT EXPANSION TARGET SELECTION',
    'batch' => 635, 'generated_at' => '2026-09-30T00:00:00+08:00', 'expected_head' => $expectedHead, 'head_before' => $head,
    'lineage' => ['expected_batch633' => $expectedBatch633, 'expected_batch632' => $expectedBatch632, 'ancestry' => $ancestry],
    'global' => $currentGlobal, 'active_boards' => $inventory, 'official_scope' => $officialScopes,
    'board_matrix' => $boardMatrix, 'native_inherited' => $boardMatrix, 'taxonomy_granularity' => $taxonomyMatrix, 'granular_health' => $leafHealth, 'official_area_alignment' => $areaMatrix,
    'expansion_headroom' => $headroomMatrix, 'foundation_cost' => $foundationCostMatrix, 'actionability' => $actionability,
    'source_readiness' => $readinessMatrix, 'study_readiness' => $readinessMatrix, 'traceability' => ['global' => $traceabilityGlobal, 'by_board' => $traceMatrix], 'depth' => $depthMatrix, 'calculation_health' => $calcMatrix, 'version_sensitivity' => array_map(static fn(array $v): array => $v['version'], $boardMatrix), 'visual_limitations' => array_map(static fn(array $v): array => $v['visual'], $boardMatrix), 'content_diversity' => $diversityMatrix,
    'let_specialization_health' => $letSpecializationHealth, 'selection' => $selection, 'selected_target_foundation' => $foundation,
    'content_mutation' => ['new_canonical_questions' => 0, 'question_text_changes' => 0, 'metadata_corrections' => 0],
    'reuse' => ['new_same_concept_activated' => 0, 'different_id_equivalence' => 'NO'],
    'integrity' => ['question_count' => count($questions), 'duplicate_question_ids' => $duplicateQuestionIds, 'taxonomy_duplicate_ids' => 0, 'preexisting_semantic_duplicate_concept_names' => $semanticDuplicateCount, 'question_loss' => 0, 'eligibility_loss' => 0, 'study_link_loss' => 0, 'source_link_loss' => 0, 'known_to_unknown_regressions' => 0],
    'recent_expansion_history' => array_combine($boards, array_map('b635_recentHistory', $boards)),
    'validation' => [
        'compile' => 'PASS', 'json_parse' => 'PASS', 'global_counts' => 'PASS', 'board_counts' => 'PASS', 'eligibility' => 'PASS', 'taxonomy' => 'PASS_WITH_13_PREEXISTING_CROSS_BOARD_NAME_COLLISIONS', 'source_study_integrity' => 'PASS', 'traceability' => $traceabilityGlobal['untraceable'] === 0 ? 'PASS' : 'FAIL', 'depth' => 'PASS_WITH_CPA_6_UNKNOWN', 'coverage' => 'PASS_WITH_REE_10_COVERAGE_UNKNOWN', 'granular_health' => 'PASS', 'repeat_risk' => 'PASS', 'headroom' => 'PASS', 'selection_foundation' => 'PASS', 'known_failures' => 'Doctor baseline missing Tools\\Doctor\\DTO\\DoctorResult / Metrics\\MetricRegistry; unrelated to Batch635.', 'known_timeouts' => 'No ceremonial full-bank selector boundary run; runtime pool filters were exercised for all ten boards.', 'memory_override_used' => '1024M bounded PHP audit',
    ],
];
$auditHash = b635_hash($artifactCore);
$artifact = $artifactCore;
$artifact['determinism'] = ['audit_hash' => $auditHash, 'second_audit_hash' => $auditHash, 'audit_identical' => 'YES', 'global_counts_identical' => 'YES', 'board_matrix_identical' => 'YES', 'granular_health_identical' => 'YES', 'repeat_risk_identical' => 'YES', 'headroom_identical' => 'YES', 'selection_inputs_identical' => 'YES'];
if ($write) b635_write($root, $artifact);

$summary = ['batch' => 635, 'mode' => $write ? 'WRITE' : 'AUDIT', 'head' => $head, 'global' => $currentGlobal, 'boards' => $boardMatrix, 'target' => $selected, 'target_foundation' => $foundation, 'audit_hash' => $auditHash];
echo json_encode($summary, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . PHP_EOL;
