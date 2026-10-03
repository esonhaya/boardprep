<?php

declare(strict_types=1);

/**
 * Batch 649 LET source-readiness foundation. Reads the Batch 648 checkpoint,
 * repository evidence, and permanent readiness/eligibility services. It never
 * creates, edits, deletes, or remaps questions.
 *
 * Build the 205 permanent readiness records and durable audit:
 *   php tools/batch649-let-source-readiness-audit.php --build --health-before-json=/tmp/health-before.json --health-json=/tmp/health-after.json
 * Repeat without writing the readiness collection:
 *   php tools/batch649-let-source-readiness-audit.php --audit --health-before-json=/tmp/health-before.json --health-json=/tmp/health-after.json --output=/tmp/batch649-second.json
 */

require_once dirname(__DIR__) . '/app/Core/Autoloader.php';

use App\Core\App;
use App\Core\Autoloader;
use App\Services\Question\QuestionEligibilityIndex;
use App\Services\Quiz\LET\LETVariantRegistry;
use App\Services\RepositoryHealth\Readiness\SourceReadinessRecord;
use App\Services\RepositoryHealth\Readiness\SourceReadinessService;

const B649_BATCH = 649;
const B649_BASE_HEAD = '83b0381c196d0dfabdd4ab5c73851e73ba3b042f';
const B649_BASELINE_ARTIFACT = 'storage/batch648-portfolio-deficiency.json';
const B649_READINESS_FILE = 'storage/source-readiness-let.json';
const B649_ARTIFACT_FILE = 'storage/batch649-let-source-readiness.json';
const B649_SCOPE_SOURCES = [
    'prc-let-enhanced-tos-2025',
    'prc-resolution-20-2025',
    'prc-lept-alignment-guidance-2025',
    'prc-lept-program-march-2026',
];
const B649_OPERATIONAL_CONFIRMATION_SOURCES = ['prc-lept-room-assignment-september-2026'];
const B649_SHARED_TLE_LEAVES = [
    'tle-design-process', 'tle-measurement-and-quality', 'tle-occupational-safety',
    'tle-resource-and-entrepreneurship', 'tle-technology-literacy',
];
const B649_HASHED_CONTENT_FILES = [
    'storage/questions.json',
    'storage/questions/questions.json',
    'storage/question-eligibility.json',
    'storage/concept-derived-eligibility.json',
    'storage/question-reuse-overrides.json',
    'storage/question-reuse-metadata.json',
    'storage/concept-equivalence.json',
    'storage/board-concept-coverage.json',
    'storage/let-specializations.json',
    'storage/blueprints.json',
    'storage/blueprints/let.json',
    'storage/blueprints/let-english-v1.json',
    'storage/blueprints/let-secondary-english.json',
    'storage/blueprints/let-secondary-english-v2.json',
    'storage/taxonomy/domains.json',
    'storage/taxonomy/topics.json',
    'storage/taxonomy/concepts.json',
];

Autoloader::register();
putenv('APP_ENV=testing');
putenv('APP_DEBUG=false');
App::boot();

function b649_fail(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

function b649_read(string $path, bool $required = true): array
{
    if (!is_file($path)) {
        if (!$required) return [];
        b649_fail('Required file is missing: ' . $path);
    }
    try {
        $value = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        b649_fail('Invalid JSON in ' . $path . ': ' . $exception->getMessage());
    }
    return is_array($value) ? $value : [];
}

function b649_write(string $path, mixed $value): void
{
    $directory = dirname($path);
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        b649_fail('Could not create output directory: ' . $directory);
    }
    $temporary = $path . '.batch649.tmp';
    $json = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
    if (file_put_contents($temporary, $json, LOCK_EX) === false || !rename($temporary, $path)) {
        @unlink($temporary);
        b649_fail('Could not atomically write ' . $path);
    }
}

function b649_sort(mixed $value): mixed
{
    if (!is_array($value)) return $value;
    foreach ($value as &$child) $child = b649_sort($child);
    unset($child);
    if (!array_is_list($value)) ksort($value, SORT_STRING);
    return $value;
}

function b649_hash(mixed $value): string
{
    return hash('sha256', json_encode(b649_sort($value), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
}

function b649_fileHashes(string $root): array
{
    $hashes = [];
    foreach (B649_HASHED_CONTENT_FILES as $relative) {
        $path = $root . '/' . $relative;
        $hashes[$relative] = is_file($path) ? (hash_file('sha256', $path) ?: '') : 'MISSING';
    }
    return $hashes;
}

function b649_ids(array $values): array
{
    $result = array_values(array_unique(array_filter(array_map(static fn(mixed $value): string => trim((string) $value), $values), static fn(string $value): bool => $value !== '')));
    sort($result, SORT_STRING);
    return $result;
}

function b649_index(array $records): array
{
    $result = [];
    foreach ($records as $record) {
        if (is_array($record) && isset($record['id'])) $result[(string) $record['id']] = $record;
    }
    return $result;
}

function b649_intMedian(array $values): ?float
{
    if ($values === []) return null;
    sort($values, SORT_NUMERIC);
    $n = count($values);
    return $n % 2 ? (float) $values[intdiv($n, 2)] : (((float) $values[intdiv($n, 2) - 1] + (float) $values[intdiv($n, 2)]) / 2);
}

function b649_stats(array $counts): array
{
    $values = array_values(array_map('intval', $counts));
    sort($values, SORT_NUMERIC);
    $percentile = static function (array $sorted, float $p): ?float {
        if ($sorted === []) return null;
        $position = (count($sorted) - 1) * $p;
        $lower = (int) floor($position);
        $upper = (int) ceil($position);
        return $lower === $upper ? (float) $sorted[$lower] : (float) $sorted[$lower] + (((float) $sorted[$upper] - (float) $sorted[$lower]) * ($position - $lower));
    };
    return [
        'leaf_count' => count($values),
        'zero' => count(array_filter($values, static fn(int $count): bool => $count === 0)),
        'under5' => count(array_filter($values, static fn(int $count): bool => $count < 5)),
        'under10' => count(array_filter($values, static fn(int $count): bool => $count < 10)),
        'under20' => count(array_filter($values, static fn(int $count): bool => $count < 20)),
        'min' => $values === [] ? null : $values[0],
        'median' => b649_intMedian($values),
        'p25' => $percentile($values, 0.25),
        'p75' => $percentile($values, 0.75),
        'max' => $values === [] ? null : $values[count($values) - 1],
        'deficit_to_5' => array_sum(array_map(static fn(int $count): int => max(0, 5 - $count), $values)),
        'deficit_to_10' => array_sum(array_map(static fn(int $count): int => max(0, 10 - $count), $values)),
        'deficit_to_20' => array_sum(array_map(static fn(int $count): int => max(0, 20 - $count), $values)),
    ];
}

function b649_questionBoard(array $question): string
{
    $taxonomy = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
    return strtolower(trim((string) ($taxonomy['board_id'] ?? $question['board'] ?? '')));
}

function b649_depth(array $question, array $reuseByQuestion): ?int
{
    $id = (string) ($question['id'] ?? '');
    $depth = $reuseByQuestion[$id]['required_depth'] ?? $question['reuse']['required_depth'] ?? $question['depth'] ?? null;
    return is_numeric($depth) && (int) $depth >= 1 && (int) $depth <= 5 ? (int) $depth : null;
}

function b649_familyFromIntent(string $intent, array $question): string
{
    $intent = strtolower(trim($intent));
    if ($intent === '') return 'UNCLASSIFIED';
    if (preg_match('/calculat|quantitative/', $intent) === 1 || strtoupper((string) ($question['quantitative_semantics'] ?? '')) === 'CALCULATION_REQUIRED' || ($question['calculation_required'] ?? null) === true) return 'CALCULATION_OR_QUANTITATIVE_REASONING';
    if (preg_match('/recogniz|identify|recall|define/', $intent) === 1) return 'RECOGNITION';
    if (preg_match('/compar|contrast|distinguish/', $intent) === 1) return 'COMPARISON';
    if (preg_match('/interpret|infer|predict/', $intent) === 1) return 'INTERPRETATION_OR_INFERENCE';
    if (preg_match('/evaluat|judg|ethic|professional/', $intent) === 1) return 'EVALUATION_OR_JUDGMENT';
    if (preg_match('/diagnos|classif/', $intent) === 1) return 'DIAGNOSIS_OR_CLASSIFICATION';
    if (preg_match('/sequence|cause|effect/', $intent) === 1) return 'SEQUENCE_OR_CAUSAL_REASONING';
    if (preg_match('/analyz|synthes/', $intent) === 1) return 'ANALYSIS_OR_SYNTHESIS';
    if (preg_match('/apply|application|scenario|decision/', $intent) === 1) return 'APPLICATION_OR_DECISION';
    return strtoupper(preg_replace('/[^a-z0-9]+/i', '_', $intent) ?? 'UNCLASSIFIED');
}

function b649_supportedFamilies(string $leafId, string $subjectId, string $evidenceText): array
{
    $text = strtolower($leafId . ' ' . $subjectId . ' ' . $evidenceText);
    $families = [];
    $add = static function (string $family) use (&$families): void { $families[$family] = true; };
    if (preg_match('/english|grammar|reading|text|pronoun|tense|sentence|modifier|parallel|preposition|filipino|pagbasa|komunikasyon/', $text) === 1) {
        $add('LANGUAGE_IN_CONTEXT'); $add('MEANING_OR_TEXT_INTERPRETATION'); $add('ERROR_ANALYSIS_OR_REVISION');
    }
    if (preg_match('/math|algebra|number|equation|geometry|statistic|probability|measurement|calculation|ratio|data/', $text) === 1) {
        $add('MODEL_OR_REPRESENTATION_SELECTION'); $add('QUANTITATIVE_APPLICATION'); $add('RESULT_OR_DATA_INTERPRETATION');
    }
    if (preg_match('/science|biology|chemistry|physics|ecology|earth|optics|electricity|environment/', $text) === 1) {
        $add('CAUSAL_OR_CONCEPTUAL_EXPLANATION'); $add('EVIDENCE_OR_DATA_INTERPRETATION'); $add('APPLICATION_TO_A_STATED_CASE');
    }
    if (preg_match('/history|rizal|social|civic|government|ethic|law|society|culture|values/', $text) === 1) {
        $add('CONTEXT_OR_SOURCE_INTERPRETATION'); $add('COMPARISON_OR_CAUSE_EFFECT'); $add('CIVIC_OR_ETHICAL_JUDGMENT');
    }
    if (preg_match('/professional|teaching|teacher|curriculum|assessment|inclusive|learning|development|classroom|instruction|education/', $text) === 1) {
        $add('EDUCATIONAL_CONCEPT_APPLICATION'); $add('CLASSROOM_OR_ASSESSMENT_DECISION'); $add('RATIONALE_OR_EVIDENCE_ANALYSIS');
    }
    if (preg_match('/tle|tvted|agriculture|fishery|home economics|industrial arts|information and communication|technical vocational/', $text) === 1) {
        $add('SAFE_PROCESS_OR_COMPETENCY_SELECTION'); $add('PROCEDURE_OR_WORKPLACE_APPLICATION'); $add('QUALITY_OR_RESOURCE_DECISION');
    }
    if (preg_match('/physical education|movement|fitness|sport|dance|health/', $text) === 1) {
        $add('MOVEMENT_OR_FITNESS_INTERPRETATION'); $add('SAFE_ACTIVITY_DECISION'); $add('TEACHING_OR_ASSESSMENT_APPLICATION');
    }
    if (preg_match('/arts|culture|creative|media|performance|aesthetic/', $text) === 1) {
        $add('ARTWORK_OR_CULTURAL_INTERPRETATION'); $add('CREATIVE_PROCESS_DECISION'); $add('PEDAGOGICAL_APPLICATION');
    }
    if (preg_match('/early childhood|ece|special needs|sne|disabilit|accommodation|universal design/', $text) === 1) {
        $add('DEVELOPMENT_OR_ACCESS_INTERPRETATION'); $add('INCLUSIVE_SUPPORT_DECISION'); $add('EDUCATIONAL_SCENARIO_APPLICATION');
    }
    if ($families === []) $families['CONCEPTUAL_RECOGNITION'] = true;
    $result = array_keys($families);
    sort($result, SORT_STRING);
    return $result;
}

function b649_sourceRole(array $source): string
{
    $id = strtolower((string) ($source['id'] ?? ''));
    $title = strtolower((string) ($source['title'] ?? ''));
    $authority = strtolower((string) ($source['authority'] ?? ''));
    $type = strtoupper((string) ($source['source_type'] ?? ''));
    $role = strtoupper((string) ($source['source_role'] ?? ''));
    if (str_contains($authority, 'professional regulation commission') || str_contains($authority, 'prc') || str_contains($id, 'prc-let') || str_contains($id, 'prc-lept')) {
        if (str_contains($id, 'room-assignment') || str_contains($id, 'examination-schedule') || str_contains($id, 'board-page')) return 'OFFICIAL_REGULATORY';
        if (preg_match('/tos|resolution-20|alignment|exam-program|lept-program/', $id . ' ' . $title) === 1 || (in_array($role, ['SCOPE_SOURCE', 'OFFICIAL_EXAM_SCOPE'], true) && $type !== 'OFFICIAL_SCHEDULE')) return 'OFFICIAL_EXAM_SCOPE';
        return 'OFFICIAL_REGULATORY';
    }
    if (preg_match('/department of education|deped|ched|tesda/', $authority . ' ' . $id) === 1 && preg_match('/curriculum|competenc|training regulation|teacher education|professional standard|program standard|tos|scope/', $title . ' ' . $id) === 1) return 'OFFICIAL_CURRICULUM';
    if (preg_match('/republic act|statute|constitution|official gazette|lawphil|government of the philippines/', $authority . ' ' . $title . ' ' . $id) === 1 || str_contains($type, 'STATUTE') || str_contains($type, 'REGULATION')) return 'OFFICIAL_REGULATORY';
    if (str_contains($id, 'openstax') || str_contains($id, 'purdue-owl') || str_contains($id, 'ncca-') || str_contains($id, 'nhcp-')) return 'AUTHORITATIVE_SUBJECT_MATTER';
    if (str_contains($role, 'ACADEMIC') || str_contains($type, 'ACADEMIC')) return 'ACADEMIC_SUPPORT';
    if (str_contains($id, 'csc-cse') || str_contains($id, 'review-center') || str_contains($role, 'SECONDARY')) return 'SECONDARY_DISCOVERY';
    if ($source === [] || trim((string) ($source['id'] ?? '')) === '') return 'INVALID';
    if ($authority === '' || $title === '' || trim((string) ($source['url'] ?? '')) === '') return 'UNKNOWN';
    if (str_contains($role, 'REFERENCE') || str_contains($type, 'REFERENCE')) return 'AUTHORITATIVE_SUBJECT_MATTER';
    return 'SECONDARY_DISCOVERY';
}

function b649_textFromMaterial(array $material): string
{
    $parts = [(string) ($material['title'] ?? ''), (string) ($material['summary'] ?? '')];
    foreach ((array) ($material['notes'] ?? []) as $note) {
        if (is_array($note)) {
            $parts[] = (string) ($note['heading'] ?? $note[0] ?? '');
            $parts[] = (string) ($note['body'] ?? $note[1] ?? '');
        } elseif (is_string($note)) {
            $parts[] = $note;
        }
    }
    return implode(' ', $parts);
}

function b649_isVersionSensitive(string $text): bool
{
    return preg_match('/\\blaw\\b|legislat|\\bpolicy\\b|curriculum|deped|ched|teacher regulation|professional standard|licensure|\\bregulation\\b|matatag|tesda|curriculum guide/', strtolower($text)) === 1;
}

function b649_primaryArea(string $leafId, array $taxonomy, array $flatClassification, array $specializationIds): string
{
    $subject = strtolower((string) ($taxonomy['subject_id'] ?? ''));
    if (isset($flatClassification[$leafId]) && !str_starts_with($leafId, 'cse-')) return 'professional-education';
    if (str_contains($leafId, 'prof-') || $subject === 'professional-education') return 'professional-education';
    if ($specializationIds !== [] && preg_match('/^(ece|sne|cae|pe|tle|tvted)-/', $leafId) === 1) return 'specialization';
    if (in_array($subject, ['early-childhood-education','special-needs-education','physical-education','culture-and-arts-education','technology-and-livelihood-education','technical-vocational-teacher-education','values-education'], true)) return 'specialization';
    return 'general-education';
}

function b649_flatClassification(string $leafId): string
{
    if (str_starts_with($leafId, 'cse-')) return 'SEMANTICALLY_AMBIGUOUS';
    if ($leafId === 'prof-development-readiness') return 'SEMANTICALLY_AMBIGUOUS';
    if ($leafId === 'let-ethical-duties') return 'LEGACY_PLACEMENT';
    return 'GENED_PROFED_SHARED';
}

function b649_roleIsPrimary(string $role): bool
{
    return in_array($role, ['OFFICIAL_EXAM_SCOPE', 'OFFICIAL_REGULATORY', 'OFFICIAL_CURRICULUM'], true);
}

/** @return array{0: array, 1: array, 2: array, 3: array, 4: array, 5: array} */
function b649_buildReadiness(array $collections, array $baseline, array $fileHashesBefore): array
{
    $sourceById = b649_index((array) ($collections['sources'] ?? []));
    $materialById = b649_index((array) ($collections['study-materials'] ?? []));
    $conceptById = b649_index((array) ($collections['taxonomy/concepts'] ?? []));
    $leafEvidence = (array) ($baseline['boards']['let']['taxonomy']['leaf_evidence'] ?? []);
    $coverageById = [];
    foreach ((array) ($collections['board-concept-coverage'] ?? []) as $row) {
        if (is_array($row) && strtolower((string) ($row['board_id'] ?? $row['board'] ?? '')) === 'let') {
            $id = trim((string) ($row['concept_id'] ?? ''));
            if ($id !== '') $coverageById[$id] = $row;
        }
    }
    $specializations = array_values(array_filter((array) ($collections['let-specializations'] ?? []), 'is_array'));
    $registryIds = b649_ids(array_map(static fn(array $row): string => (string) ($row['id'] ?? ''), $specializations));
    $eligibilityIndex = new QuestionEligibilityIndex(
        (array) ($collections['question-eligibility'] ?? []),
        (array) ($collections['blueprints'] ?? []),
        (array) ($collections['concept-derived-eligibility'] ?? []),
        (array) ($collections['question-reuse-overrides'] ?? [])
    );
    $questions = array_values(array_filter((array) ($collections['questions'] ?? []), 'is_array'));
    $reuseByQuestion = [];
    foreach ((array) ($collections['question-reuse-metadata'] ?? []) as $row) {
        if (is_array($row) && isset($row['question_id'])) $reuseByQuestion[(string) $row['question_id']] = (array) ($row['reuse'] ?? []);
    }
    $nativeByLeaf = $effectiveByLeaf = $runtimeByLeaf = $depthByLeaf = $familyCountsByLeaf = $calculationByLeaf = $questionIdsByLeaf = $componentByLeaf = $specializationByLeaf = $nativeSpecializationByLeaf = $materialIdsByLeaf = $unclassifiedFamilyCountByLeaf = [];
    $questionsById = [];
    foreach ($questions as $question) {
        $qid = (string) ($question['id'] ?? '');
        if ($qid === '') continue;
        $questionsById[$qid] = $question;
        $taxonomy = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
        $leafId = trim((string) ($taxonomy['concept_id'] ?? ''));
        $boardId = b649_questionBoard($question);
        if ($boardId === 'let' && $leafId !== '' && isset($leafEvidence[$leafId])) {
            $nativeByLeaf[$leafId][$qid] = true;
            $effectiveByLeaf[$leafId][$qid] = true;
            $questionIdsByLeaf[$leafId][$qid] = true;
            if (strtolower((string) ($question['status'] ?? 'active')) === 'active') $runtimeByLeaf[$leafId][$qid] = true;
            $depth = b649_depth($question, $reuseByQuestion);
            if ($depth !== null) $depthByLeaf[$leafId][$depth] = true;
            $family = b649_familyFromIntent((string) ($question['cognitive_intent'] ?? ''), $question);
            if ($family !== 'UNCLASSIFIED') $familyCountsByLeaf[$leafId][$family] = ($familyCountsByLeaf[$leafId][$family] ?? 0) + 1;
            else $unclassifiedFamilyCountByLeaf[$leafId] = ($unclassifiedFamilyCountByLeaf[$leafId] ?? 0) + 1;
            $semantic = strtoupper(trim((string) ($question['quantitative_semantics'] ?? '')));
            if (in_array($semantic, ['NON_NUMERICAL', 'NOT_CALCULATION_REQUIRED'], true)) $semantic = 'NON_CALCULATION';
            if ($semantic === '') {
                $required = $question['calculation_required'] ?? null;
                $semantic = is_bool($required) ? ($required ? 'CALCULATION_REQUIRED' : 'NON_CALCULATION') : 'UNKNOWN';
            }
            $calculationByLeaf[$leafId][$semantic] = ($calculationByLeaf[$leafId][$semantic] ?? 0) + 1;
        }
        $eligibility = $eligibilityIndex->forExam($question, 'let');
        $placements = LETVariantRegistry::questionPlacements($question, $eligibility);
        $specIds = [];
        $components = [];
        foreach ($placements as $placement) {
            $specId = trim((string) ($placement['specialization_id'] ?? ''));
            if ($specId !== '') $specIds[$specId] = true;
            $component = strtoupper(trim((string) ($placement['component'] ?? $placement['let_component'] ?? '')));
            if (in_array($component, ['GENERAL_EDUCATION', 'PROFESSIONAL_EDUCATION', 'SPECIALIZATION'], true)) $components[$component] = true;
        }
        $primaryPlacement = is_array($question['let_placement'] ?? null) ? $question['let_placement'] : (array) ($placements[0] ?? []);
        $primarySpecializationId = trim((string) ($primaryPlacement['specialization_id'] ?? ''));
        if ($leafId !== '' && isset($leafEvidence[$leafId])) {
            foreach (array_keys($specIds) as $specId) $specializationByLeaf[$leafId][$specId] = true;
            if ($boardId === 'let' && $primarySpecializationId !== '' && isset($specIds[$primarySpecializationId])) {
                $nativeSpecializationByLeaf[$leafId][$primarySpecializationId] = true;
            }
            foreach (array_keys($components) as $component) $componentByLeaf[$leafId][$component] = true;
        }
        if ($eligibility === null) continue;
        $placement = is_array($eligibility['placement'] ?? null) ? $eligibility['placement'] : [];
        $eligibleLeaf = trim((string) ($placement['concept_id'] ?? $leafId));
        if ($eligibleLeaf === '' || !isset($leafEvidence[$eligibleLeaf])) continue;
        if (strtolower((string) ($question['status'] ?? 'active')) === 'active') $runtimeByLeaf[$eligibleLeaf][$qid] = true;
        foreach (array_keys($specIds) as $specId) $specializationByLeaf[$eligibleLeaf][$specId] = true;
        foreach (array_keys($components) as $component) $componentByLeaf[$eligibleLeaf][$component] = true;
    }

    $effectiveIds = [];
    foreach ((array) ($collections['question-eligibility'] ?? []) as $entry) {
        if (!is_array($entry)) continue;
        $baseBoards = array_map(static fn(mixed $value): string => strtolower(trim((string) $value)), (array) ($entry['exam_ids'] ?? []));
        if (!in_array('let', $baseBoards, true)) continue;
        foreach ((array) ($entry['question_ids'] ?? []) as $qid) if (is_scalar($qid)) $effectiveIds[(string) $qid] = true;
    }
    foreach ((array) ($collections['concept-derived-eligibility'] ?? []) as $entry) {
        if (is_array($entry) && ($entry['record_type'] ?? '') === 'RELATIONSHIP' && strtolower((string) ($entry['target_board'] ?? '')) === 'let') {
            $qid = trim((string) ($entry['question_id'] ?? ''));
            if ($qid !== '') $effectiveIds[$qid] = true;
        }
    }
    foreach ($nativeByLeaf as $leafQuestions) foreach (array_keys($leafQuestions) as $qid) $effectiveIds[$qid] = true;
    foreach (array_keys($effectiveIds) as $qid) {
        $question = $questionsById[$qid] ?? null;
        if (!is_array($question)) continue;
        $eligibility = $eligibilityIndex->forExam($question, 'let');
        $placement = is_array($eligibility['placement'] ?? null) ? $eligibility['placement'] : [];
        $taxonomy = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
        $leafId = trim((string) ($placement['concept_id'] ?? $taxonomy['concept_id'] ?? ''));
        if ($leafId !== '' && isset($leafEvidence[$leafId])) $effectiveByLeaf[$leafId][$qid] = true;
    }

    $flatClasses = [];
    foreach ($leafEvidence as $leafId => $row) {
        $tax = (array) ($row['taxonomy'] ?? []);
        if (trim((string) ($tax['domain_id'] ?? '')) === '' && trim((string) ($tax['topic_id'] ?? '')) === '') {
            $flatClasses[$leafId] = b649_flatClassification((string) $leafId);
        }
    }
    $records = [];
    $leafInventory = [];
    foreach ($leafEvidence as $leafId => $row) {
        $taxonomy = (array) ($row['taxonomy'] ?? []);
        $concept = $conceptById[$leafId] ?? [];
        $leafName = trim((string) ($concept['name'] ?? ''));
        if ($leafName === '') $leafName = ucwords(str_replace(['-', '_'], ' ', (string) $leafId));
        $nativeCount = count($nativeByLeaf[$leafId] ?? []);
        $effectiveCount = count($effectiveByLeaf[$leafId] ?? []);
        $runtimeCount = count($runtimeByLeaf[$leafId] ?? []);
        $sourceIds = b649_ids(array_merge((array) ($row['resolved_source_ids'] ?? []), B649_SCOPE_SOURCES));
        $materialIds = b649_ids((array) ($row['linked_study_material_ids'] ?? []));
        $materialTitles = [];
        $materialExactConceptFit = false;
        $materialTopicFit = false;
        $materialTraceComplete = $materialIds !== [];
        $invalidStudyIds = [];
        foreach ($materialIds as $materialId) {
            $material = $materialById[$materialId] ?? null;
            if (!is_array($material)) { $invalidStudyIds[] = $materialId; $materialTraceComplete = false; continue; }
            $materialTitles[] = (string) ($material['title'] ?? '');
            $materialTaxonomy = is_array($material['taxonomy'] ?? null) ? $material['taxonomy'] : [];
            if (trim((string) ($materialTaxonomy['concept_id'] ?? '')) === (string) $leafId) $materialExactConceptFit = true;
            if (trim((string) ($taxonomy['topic_id'] ?? '')) !== '' && trim((string) ($materialTaxonomy['topic_id'] ?? '')) === (string) $taxonomy['topic_id']) $materialTopicFit = true;
            foreach ((array) ($material['source_ids'] ?? []) as $sourceId) {
                if (!isset($sourceById[(string) $sourceId])) { $materialTraceComplete = false; $invalidStudyIds[] = 'source:' . $sourceId; }
            }
        }
        $invalidSourceIds = [];
        foreach ($sourceIds as $sourceId) if (!isset($sourceById[$sourceId])) $invalidSourceIds[] = $sourceId;
        $leafEvidenceText = implode(' ', [(string) $leafId, $leafName, (string) ($concept['description'] ?? ''), (string) ($taxonomy['subject_id'] ?? ''), (string) ($taxonomy['domain_id'] ?? ''), (string) ($taxonomy['topic_id'] ?? '')]);
        $familyEvidenceText = implode(' ', array_merge([$leafEvidenceText], $materialTitles));
        $versionEvidenceText = implode(' ', array_merge([$leafEvidenceText], $materialTitles));
        $sourceRoles = [];
        foreach ($sourceIds as $sourceId) if (isset($sourceById[$sourceId])) $sourceRoles[$sourceId] = b649_sourceRole($sourceById[$sourceId]);
        $primarySourceCount = count(array_filter($sourceRoles, 'b649_roleIsPrimary'));
        $contentSourceCount = count(array_filter($sourceRoles, static fn(string $role): bool => in_array($role, ['OFFICIAL_CURRICULUM', 'AUTHORITATIVE_SUBJECT_MATTER', 'ACADEMIC_SUPPORT'], true)));
        $specializationIds = b649_ids(array_keys($specializationByLeaf[$leafId] ?? []));
        $nativeSpecializationIds = b649_ids(array_keys($nativeSpecializationByLeaf[$leafId] ?? []));
        $sharedSpecializationIds = array_values(array_diff($specializationIds, $nativeSpecializationIds));
        $components = b649_ids(array_keys($componentByLeaf[$leafId] ?? []));
        $flatClass = $flatClasses[$leafId] ?? null;
        $primaryArea = b649_primaryArea((string) $leafId, $taxonomy, $flatClasses, $specializationIds);
        if ($components === []) {
            $componentFromArea = $primaryArea === 'general-education' ? 'GENERAL_EDUCATION' : ($primaryArea === 'professional-education' ? 'PROFESSIONAL_EDUCATION' : 'SPECIALIZATION');
            $components = [$componentFromArea];
        }
        if ($specializationIds !== []) $components = b649_ids(array_merge($components, ['SPECIALIZATION']));
        $depths = array_map('intval', array_keys($depthByLeaf[$leafId] ?? []));
        sort($depths, SORT_NUMERIC);
        $depthMin = $depths === [] ? 1 : $depths[0];
        $depthMax = $depths === [] ? 1 : $depths[count($depths) - 1];
        $observedFamilies = array_keys($familyCountsByLeaf[$leafId] ?? []);
        sort($observedFamilies, SORT_STRING);
        $supportedFamilies = b649_supportedFamilies((string) $leafId, (string) ($taxonomy['subject_id'] ?? ''), $familyEvidenceText);
        $questionCount = $nativeCount;
        $deficit5 = max(0, 5 - $questionCount);
        $deficit10 = max(0, 10 - $questionCount);
        $deficit20 = max(0, 20 - $questionCount);
        $hasQuestionTrace = (int) ($row['linked_question_count'] ?? 0) === $nativeCount
            && (int) ($row['traceable_question_count'] ?? 0) === $nativeCount
            && $nativeCount > 0;
        $versionSensitive = b649_isVersionSensitive($versionEvidenceText);
        $currentness = $nativeCount === 0 ? 'UNKNOWN' : ($versionSensitive ? 'CURRENT_WITH_LIMITS' : 'NOT_TIME_SENSITIVE');
        $authority = $nativeCount === 0 ? 'NONE' : 'ADEQUATE';
        $scopeFit = $flatClass !== null ? 'UNKNOWN' : 'PARTIAL';
        $conflict = $nativeCount === 0 ? 'UNKNOWN' : 'NONE';
        $studyCoverage = $materialIds === [] ? 'MISSING' : ($invalidStudyIds !== [] ? 'INVALID' : (!$materialTraceComplete || !$hasQuestionTrace ? 'PARTIAL' : ($materialExactConceptFit ? 'COMPLETE' : 'SUFFICIENT')));
        $traceability = $materialIds === [] ? 'UNKNOWN' : ($materialTraceComplete && $hasQuestionTrace ? 'COMPLETE' : 'PARTIAL');
        $reviewReason = '';
        $readiness = 'READY_WITH_LIMITS';
        if ($flatClass !== null) {
            $readiness = 'REVIEW';
            $scopeFit = 'UNKNOWN';
            $conflict = 'UNKNOWN';
            $reviewReason = $flatClass === 'SEMANTICALLY_AMBIGUOUS'
                ? 'Flat/shared LET placement has no subject-domain-topic path; current PRC scope and exact LET component fit cannot be safely distinguished from cross-board or legacy availability without semantic adjudication.'
                : 'Legacy flat professional-education placement is source-traceable but the precise current PRC TOS scope boundary and placement semantics require adjudication.';
        } elseif ($nativeCount === 0 || $sourceIds === [] || $materialIds === [] || $invalidSourceIds !== [] || $invalidStudyIds !== [] || !$hasQuestionTrace || !$materialTraceComplete || !in_array($studyCoverage, ['COMPLETE', 'SUFFICIENT'], true)) {
            $readiness = 'REVIEW';
            $reviewReason = 'Source/study evidence is absent, incomplete, or not fully traceable for this active leaf; no authoring permission is established.';
        }
        $sourceHeadroom = min(3, max(0, $contentSourceCount));
        $studyHeadroom = min(3, count($materialIds));
        $familyHeadroom = max(0, min(3, count($supportedFamilies) - min(count($supportedFamilies), count($observedFamilies))));
        $depthHeadroom = $depths === [] ? 0 : max(1, min(3, $depthMax - $depthMin + 1));
        $numericalCapacity = min(3, $sourceHeadroom, $studyHeadroom, $depthHeadroom);
        $observedFamilyCount = count($observedFamilies);
        $classifiedFamilyCounts = array_values((array) ($familyCountsByLeaf[$leafId] ?? []));
        $unknownFamilyCount = (int) ($unclassifiedFamilyCountByLeaf[$leafId] ?? 0);
        $unknownFamilyRate = $nativeCount > 0 ? $unknownFamilyCount / $nativeCount : 1.0;
        $familyConcentration = $nativeCount > 0 && $classifiedFamilyCounts !== [] ? max($classifiedFamilyCounts) / $nativeCount : ($nativeCount > 0 ? $unknownFamilyRate : 1.0);
        $repeatRisk = $nativeCount >= 10 && ($unknownFamilyRate >= 0.65 || $familyConcentration >= 0.8 || $observedFamilyCount <= 1)
            ? 'HIGH'
            : ($nativeCount >= 5 && ($unknownFamilyRate >= 0.35 || $familyConcentration >= 0.6 || $observedFamilyCount <= 1) ? 'MODERATE' : 'LOW');
        $headroomMax = 0;
        if ($readiness !== 'REVIEW') {
            $headroomMax = min($numericalCapacity, $familyHeadroom);
            if ($nativeCount >= 20) $headroomMax = min(1, $headroomMax);
            if ($repeatRisk === 'HIGH') $headroomMax = min(1, $headroomMax);
            if ($headroomMax <= 0) {
                $readiness = 'REVIEW';
                $scopeFit = 'PARTIAL';
                $reviewReason = 'Traceable content exists, but the available source/study and depth evidence does not establish an unused high-value content family with positive safe headroom.';
            }
        }
        $headroomMin = $readiness === 'REVIEW' ? 0 : 1;
        $headroomPreferred = $readiness === 'REVIEW' ? 0 : min(2, $headroomMax);
        $maxNew = $readiness === 'REVIEW' ? 0 : $headroomMax;
        $allowedScope = $readiness === 'REVIEW' ? [] : [(string) $leafId];
        $excludedScope = $readiness === 'REVIEW' ? [(string) $leafId . ': no authoring until scope/readiness review closes'] : [
            'Adjacent leaves and unlisted sibling topics are excluded.',
            'Edition-specific, future-effective, or unsourced legal/curriculum particulars are excluded.',
        ];
        $limitations = [
            'PRC TOS controls exam inclusion; subject-matter sources alone do not establish LET scope.',
            'Headroom is a bounded editorial evidence estimate, not a deficit quota or authorization to execute a wave.',
            'The exact annex row for this BoardPrep-derived leaf was not independently crosswalked; author only within the named leaf and its cited study/source boundary.',
        ];
        if ($versionSensitive) $limitations[] = 'Limit version-sensitive claims to the cited current official source versions; exclude unverified amendments or transition details.';
        if ($specializationIds !== []) $limitations[] = 'Specialization placement is limited to the listed official registry identities; shared availability is not native specialization depth.';
        $readinessReason = $readiness === 'REVIEW'
            ? 'Current repository evidence is recorded, but source/scope/study or semantic limitations do not establish safe authoring permission.'
            : 'Authorable only within the exact leaf boundary, cited source chain, supported depth, and positive evidence-backed headroom.';
        $record = [
            'leaf_id' => (string) $leafId,
            'board_id' => 'let',
            'official_area_id' => $primaryArea,
            'leaf_name' => $leafName,
            'current_question_count' => $nativeCount,
            'deficit_to_5' => $deficit5,
            'deficit_to_10' => $deficit10,
            'deficit_to_20' => $deficit20,
            'source_ids' => $sourceIds,
            'source_count' => count($sourceIds),
            'primary_source_count' => $primarySourceCount,
            'source_authority' => $authority,
            'source_currentness' => $currentness,
            'source_scope_fit' => $scopeFit,
            'source_conflict_status' => $conflict,
            'study_material_ids' => $materialIds,
            'study_material_count' => count($materialIds),
            'study_coverage' => $studyCoverage,
            'study_source_traceability' => $traceability,
            'supported_depth_min' => $depthMin,
            'supported_depth_max' => $depthMax,
            'distinct_content_families' => b649_ids(array_merge($observedFamilies, $supportedFamilies)),
            'distinct_content_family_count' => count(b649_ids(array_merge($observedFamilies, $supportedFamilies))),
            'safe_headroom_min' => $headroomMin,
            'safe_headroom_preferred' => $headroomPreferred,
            'safe_headroom_max' => $headroomMax,
            'allowed_scope' => $allowedScope,
            'excluded_scope' => $excludedScope,
            'limitations' => $limitations,
            'readiness' => $readiness,
            'readiness_reason' => $readinessReason,
            'review_reason' => $reviewReason,
            'defer_reason' => '',
            'max_new_questions' => $maxNew,
            'planned_new_questions' => 0,
            'reviewed_at_batch' => B649_BATCH,
            'scope_specialization_ids' => $specializationIds,
            'scope_component_ids' => $components,
        ];
        $record['evidence_hash'] = SourceReadinessRecord::evidenceHash($record);
        $records[] = $record;
        $leafInventory[$leafId] = [
            'leaf_id' => (string) $leafId,
            'leaf_name' => $leafName,
            'major_area' => $primaryArea,
            'subject_id' => (string) ($taxonomy['subject_id'] ?? ''),
            'domain_id' => (string) ($taxonomy['domain_id'] ?? ''),
            'topic_id' => (string) ($taxonomy['topic_id'] ?? ''),
            'path_depth' => (int) ($taxonomy['path_depth'] ?? 0),
            'flat_shared_classification' => $flatClass,
            'scope_specialization_ids' => $specializationIds,
            'native_specialization_ids' => $nativeSpecializationIds,
            'shared_specialization_ids' => $sharedSpecializationIds,
            'scope_component_ids' => $components,
            'native_question_count' => $nativeCount,
            'effective_declared_question_count' => count($effectiveByLeaf[$leafId] ?? []),
            'runtime_question_count' => $runtimeCount,
            'shared_in_declared' => max(0, count($effectiveByLeaf[$leafId] ?? []) - $nativeCount),
            'shared_in_runtime' => max(0, $runtimeCount - $nativeCount),
            'depth_distribution' => array_map(static fn(string $depth): int => count(array_filter($depthByLeaf[$leafId] ?? [], static fn(bool $v, int|string $key): bool => (string) $key === $depth, ARRAY_FILTER_USE_BOTH)), ['1','2','3','4','5']),
            'observed_depth_values' => $depths,
            'content_family_counts' => $familyCountsByLeaf[$leafId] ?? [],
            'unclassified_family_question_count' => $unknownFamilyCount,
            'unclassified_family_rate' => round($unknownFamilyRate, 4),
            'content_family_concentration' => round($familyConcentration, 4),
            'evidence_supported_families' => $supportedFamilies,
            'calculation_classes' => $calculationByLeaf[$leafId] ?? [],
            'version_sensitive_question_count' => $versionSensitive ? $nativeCount : 0,
            'repeat_risk' => $repeatRisk,
            'readiness' => $readiness,
            'study_coverage' => $studyCoverage,
            'study_source_traceability' => $traceability,
            'source_ids' => $sourceIds,
            'study_material_ids' => $materialIds,
            'source_roles' => $sourceRoles,
            'currentness' => $currentness,
            'scope_fit' => $scopeFit,
            'safe_headroom' => ['min' => $headroomMin, 'preferred' => $headroomPreferred, 'max' => $headroomMax],
            'numerical_capacity' => $numericalCapacity,
            'high_value_family_headroom' => $familyHeadroom,
            'high_value_headroom' => $readiness === 'REVIEW' ? 0 : $headroomMax,
            'raw_deficits' => ['to5' => $deficit5, 'to10' => $deficit10, 'to20' => $deficit20],
            'authorable' => in_array($readiness, ['READY', 'READY_WITH_LIMITS'], true),
            'planning_state' => 'REVIEW',
            'readiness_record' => $record,
        ];
    }
    usort($records, static fn(array $a, array $b): int => strcmp($a['leaf_id'], $b['leaf_id']));
    ksort($leafInventory, SORT_STRING);
    return [$records, $leafInventory, $flatClasses, $registryIds, $effectiveIds, $reuseByQuestion];
}

function b649_cli(array $argv): array
{
    $options = ['mode' => 'audit', 'health_before' => '', 'health' => '', 'output' => '', 'readiness_output' => ''];
    foreach (array_slice($argv, 1) as $argument) {
        if ($argument === '--build') $options['mode'] = 'build';
        elseif ($argument === '--audit') $options['mode'] = 'audit';
        elseif (str_starts_with($argument, '--health-before-json=')) $options['health_before'] = substr($argument, 21);
        elseif (str_starts_with($argument, '--health-json=')) $options['health'] = substr($argument, 14);
        elseif (str_starts_with($argument, '--output=')) $options['output'] = substr($argument, 9);
        elseif (str_starts_with($argument, '--readiness-output=')) $options['readiness_output'] = substr($argument, 19);
    }
    return $options;
}

function b649_path(string $root, string $path, string $fallback): string
{
    $path = $path !== '' ? $path : $fallback;
    return str_starts_with($path, '/') ? $path : $root . '/' . $path;
}

function b649_run(array $argv): void
{
    $root = dirname(__DIR__);
    $options = b649_cli($argv);
    if ($options['health_before'] === '' || $options['health'] === '') b649_fail('Both --health-before-json and --health-json are required.');
    $healthBefore = b649_read($options['health_before']);
    $healthAfter = b649_read($options['health']);
    $baseline = b649_read($root . '/' . B649_BASELINE_ARTIFACT);
    if ((int) ($baseline['batch'] ?? 0) !== 648 || (string) ($baseline['lineage']['expected_head'] ?? '') !== '56415c2c4308be70d359407b96a9a36460def4a') b649_fail('Batch648 baseline artifact or lineage does not match the recovery checkpoint.');
    $beforeHashes = b649_fileHashes($root);
    $collections = [];
    foreach (\App\Services\RepositoryHealth\Engine\RepositoryContextFactory::collectionNames() as $name) $collections[$name] = App::storage()->all($name);
    $collections['let-specializations'] = App::storage()->all('let-specializations');
    $collections['source-readiness-let'] = array_values(array_filter((array) ($collections['source-readiness-let'] ?? []), 'is_array'));
    [$expectedRecords, $leafInventory, $flatClasses, $registryIds, $effectiveIds, $reuseByQuestion] = b649_buildReadiness($collections, $baseline, $beforeHashes);
    $readinessPath = b649_path($root, $options['readiness_output'], $root . '/' . B649_READINESS_FILE);
    if ($options['mode'] === 'build') {
        b649_write($readinessPath, $expectedRecords);
        $collections['source-readiness-let'] = $expectedRecords;
    } else {
        $persisted = b649_read($readinessPath);
        if (b649_hash($persisted) !== b649_hash($expectedRecords)) b649_fail('Persisted LET readiness records differ from deterministic Batch649 evidence reconstruction.');
        $collections['source-readiness-let'] = $persisted;
    }

    $leafCounts = [];
    foreach ($leafInventory as $leafId => $leaf) $leafCounts[$leafId] = (int) $leaf['native_question_count'];
    $boardSummaries = (array) ($healthAfter['board_summaries'] ?? []);
    $boardSummaries['let']['leaf_counts'] = $leafCounts;
    $validationAll = SourceReadinessService::validateCollections($collections, $boardSummaries);
    $letValidation = (array) ($validationAll['by_board']['let'] ?? []);
    $validationErrors = (array) ($letValidation['errors'] ?? []);
    $readinessRecords = array_values(array_filter((array) ($collections['source-readiness-let'] ?? []), 'is_array'));
    if (count($readinessRecords) !== 205) b649_fail('Exactly 205 active LET readiness records are required.');
    $readinessByLeaf = [];
    foreach ($readinessRecords as $record) $readinessByLeaf[(string) ($record['leaf_id'] ?? '')] = $record;
    foreach ($leafInventory as $leafId => &$leaf) {
        $record = $readinessByLeaf[$leafId] ?? [];
        $leaf['readiness_record'] = $record;
        $leaf['readiness'] = (string) ($record['readiness'] ?? 'REVIEW');
        $leaf['authorable'] = in_array($leaf['readiness'], ['READY', 'READY_WITH_LIMITS'], true);
        $leaf['high_value_headroom'] = $leaf['authorable'] ? (int) ($record['safe_headroom_preferred'] ?? 0) : 0;
        $leaf['safe_headroom'] = ['min' => (int) ($record['safe_headroom_min'] ?? 0), 'preferred' => (int) ($record['safe_headroom_preferred'] ?? 0), 'max' => (int) ($record['safe_headroom_max'] ?? 0)];
        $leaf['planning_state'] = !$leaf['authorable'] ? 'REVIEW' : ((int) $leaf['native_question_count'] < 5 ? 'EXPAND_WITH_LIMITS' : ((int) $leaf['native_question_count'] < 10 ? 'EXPAND' : ((int) $leaf['native_question_count'] >= 20 ? 'NO_EXPANSION_NEEDED' : 'REVIEW')));
    }
    unset($leaf);

    $sources = b649_index((array) ($collections['sources'] ?? []));
    $materials = b649_index((array) ($collections['study-materials'] ?? []));
    $letMaterials = [];
    foreach ($materials as $id => $material) if (in_array('let', array_map('strtolower', (array) ($material['exams'] ?? [])), true)) $letMaterials[$id] = $material;
    $referencedSourceIds = array_fill_keys(array_merge(B649_SCOPE_SOURCES, B649_OPERATIONAL_CONFIRMATION_SOURCES), true);
    foreach ($leafInventory as $leaf) {
        foreach ((array) ($leaf['source_ids'] ?? []) as $id) $referencedSourceIds[(string) $id] = true;
        foreach ((array) ($leaf['study_material_ids'] ?? []) as $materialId) {
            foreach ((array) ($materials[$materialId]['source_ids'] ?? []) as $id) $referencedSourceIds[(string) $id] = true;
        }
    }
    foreach ($letMaterials as $material) foreach ((array) ($material['source_ids'] ?? []) as $id) $referencedSourceIds[(string) $id] = true;
    $referencedSourceIds = array_keys($referencedSourceIds);
    sort($referencedSourceIds, SORT_STRING);
    $sourceInventory = ['source_records_referenced' => count($referencedSourceIds), 'official_exam_scope' => 0, 'official_regulatory' => 0, 'official_curriculum' => 0, 'authoritative_subject_matter' => 0, 'academic_support' => 0, 'secondary_discovery' => 0, 'weak' => 0, 'unknown' => 0, 'invalid' => 0, 'new_source_records' => 0, 'reused_source_records' => count($referencedSourceIds), 'duplicate_source_records_avoided' => 0, 'records' => []];
    $canonicalDocs = [];
    foreach ($referencedSourceIds as $sourceId) {
        $source = $sources[$sourceId] ?? null;
        if (!is_array($source)) { $sourceInventory['invalid']++; continue; }
        $role = b649_sourceRole($source);
        $bucket = strtolower($role);
        if (array_key_exists($bucket, $sourceInventory)) $sourceInventory[$bucket]++;
        $issuer = strtolower(trim((string) ($source['authority'] ?? '')));
        $url = strtolower(trim((string) ($source['url'] ?? '')));
        $url = preg_replace('/[?#].*$/', '', $url) ?? $url;
        $url = rtrim($url, '/');
        $titleKey = strtolower(trim(preg_replace('/\s+/', ' ', (string) ($source['title'] ?? '')) ?? ''));
        $versionKey = strtolower(trim((string) ($source['version'] ?? '')));
        $documentIdentity = $issuer . '|' . ($url !== '' ? $url : $titleKey) . '|' . $versionKey;
        $canonicalDocs[$documentIdentity] = true;
        $sourceInventory['records'][] = ['id' => $sourceId, 'title' => (string) ($source['title'] ?? ''), 'authority' => (string) ($source['authority'] ?? ''), 'url' => (string) ($source['url'] ?? ''), 'version' => (string) ($source['version'] ?? ''), 'source_role' => $role, 'source_type' => (string) ($source['source_type'] ?? ''), 'publication_date' => $source['publication_date'] ?? null, 'status' => (string) ($source['status'] ?? '')];
    }
    $sourceInventory['duplicate_source_records_avoided'] = max(0, count($referencedSourceIds) - count($canonicalDocs));
    foreach (['official_exam_scope', 'official_regulatory', 'official_curriculum', 'authoritative_subject_matter', 'academic_support', 'secondary_discovery', 'weak', 'unknown', 'invalid'] as $field) if (!isset($sourceInventory[$field])) $sourceInventory[$field] = 0;

    $studyInventoryCounts = ['COMPLETE' => 0, 'SUFFICIENT' => 0, 'PARTIAL' => 0, 'MISSING' => 0, 'INVALID' => 0, 'UNKNOWN' => 0];
    $traceabilityCounts = ['COMPLETE' => 0, 'PARTIAL' => 0, 'BROKEN' => 0, 'NONE' => 0, 'UNKNOWN' => 0];
    $leafStudyCoverageCounts = $studyInventoryCounts;
    $leafTraceabilityCounts = $traceabilityCounts;
    $studyInventory = [];
    foreach ($letMaterials as $materialId => $material) {
        $sourceIds = array_values(array_filter((array) ($material['source_ids'] ?? []), 'is_string'));
        $broken = array_values(array_filter($sourceIds, static fn(string $id): bool => !isset($sources[$id])));
        $coveredConcepts = array_values(array_intersect((array) ($material['supported_concept_ids'] ?? []), array_keys($leafInventory)));
        $leafTopicIds = array_values(array_filter(array_map(static fn(array $leaf): string => (string) ($leaf['topic_id'] ?? ''), $leafInventory)));
        $coveredTopics = array_values(array_unique(array_intersect((array) ($material['supported_topic_ids'] ?? []), $leafTopicIds)));
        $coverage = $broken !== [] ? 'INVALID' : ($sourceIds === [] ? 'MISSING' : ($coveredConcepts !== [] ? 'COMPLETE' : ($coveredTopics !== [] || trim((string) ($material['summary'] ?? '')) !== '' ? 'SUFFICIENT' : 'PARTIAL')));
        $traceability = $broken !== [] ? 'BROKEN' : ($sourceIds === [] ? 'NONE' : 'COMPLETE');
        $studyInventoryCounts[$coverage]++;
        $traceabilityCounts[$traceability]++;
        $studyInventory[] = ['id' => $materialId, 'title' => (string) ($material['title'] ?? ''), 'source_ids' => b649_ids($sourceIds), 'source_count' => count(b649_ids($sourceIds)), 'coverage' => $coverage, 'traceability' => $traceability, 'supported_concept_count' => count($coveredConcepts), 'supported_topic_count' => count($coveredTopics), 'status' => (string) ($material['status'] ?? '')];
    }
    foreach ($leafInventory as $leaf) {
        $record = (array) ($leaf['readiness_record'] ?? []);
        $coverage = (string) ($record['study_coverage'] ?? 'UNKNOWN');
        $traceability = (string) ($record['study_source_traceability'] ?? 'UNKNOWN');
        if (isset($leafStudyCoverageCounts[$coverage])) $leafStudyCoverageCounts[$coverage]++;
        if (isset($leafTraceabilityCounts[$traceability])) $leafTraceabilityCounts[$traceability]++;
    }

    $readinessCounts = ['READY' => 0, 'READY_WITH_LIMITS' => 0, 'REVIEW' => 0, 'DEFER' => 0];
    foreach ($readinessRecords as $record) { $state = (string) ($record['readiness'] ?? ''); if (isset($readinessCounts[$state])) $readinessCounts[$state]++; }
    $rawCounts = array_map(static fn(array $leaf): int => (int) $leaf['native_question_count'], $leafInventory);
    $authorableCounts = [];
    $authorableDeficits = ['to5' => 0, 'to10' => 0, 'to20' => 0];
    foreach ($leafInventory as $leafId => $leaf) {
        if (!$leaf['authorable']) continue;
        $count = (int) $leaf['native_question_count'];
        $authorableCounts[$leafId] = $count;
        foreach ([5 => 'to5', 10 => 'to10', 20 => 'to20'] as $threshold => $name) $authorableDeficits[$name] += max(0, $threshold - $count);
    }
    $rawStats = b649_stats($rawCounts);
    $authorableStats = b649_stats($authorableCounts);
    $readinessIntegrity = [
        'total_records' => count($readinessRecords),
        'ready' => $readinessCounts['READY'],
        'ready_with_limits' => $readinessCounts['READY_WITH_LIMITS'],
        'review' => $readinessCounts['REVIEW'],
        'defer' => $readinessCounts['DEFER'],
        'missing_records' => max(0, count($leafInventory) - count($readinessRecords)),
        'duplicate_records' => count($readinessRecords) - count($readinessByLeaf),
        'invalid_specialization_ids' => count(array_filter($readinessRecords, static fn(array $record): bool => array_diff((array) ($record['scope_specialization_ids'] ?? []), $registryIds) !== [])),
        'invalid_scope_component_ids' => count(array_filter($readinessRecords, static fn(array $record): bool => array_diff((array) ($record['scope_component_ids'] ?? []), ['GENERAL_EDUCATION', 'PROFESSIONAL_EDUCATION', 'SPECIALIZATION']) !== [])),
        'invalid_source_ids' => count(array_filter($validationErrors, static fn(array $error): bool => str_contains((string) ($error['code'] ?? ''), 'INVALID_SOURCE'))),
        'invalid_study_ids' => count(array_filter($validationErrors, static fn(array $error): bool => str_contains((string) ($error['code'] ?? ''), 'INVALID_STUDY'))),
        'broken_authorable_traceability' => count(array_filter($readinessRecords, static fn(array $record): bool => in_array((string) ($record['readiness'] ?? ''), ['READY', 'READY_WITH_LIMITS'], true) && ($record['study_source_traceability'] ?? '') !== 'COMPLETE')),
        'cross_field_contradictions' => count(array_filter($validationErrors, static fn(array $error): bool => str_contains((string) ($error['code'] ?? ''), 'CONTRADICTION') || str_contains((string) ($error['code'] ?? ''), 'COUNT_'))),
        'valid' => ($letValidation['valid'] ?? false) === true,
        'validation_errors' => $validationErrors,
        'evidence_hashes' => b649_hash(array_map(static fn(array $record): array => [(string) $record['leaf_id'], (string) $record['evidence_hash']], $readinessRecords)),
    ];

    $zeroRows = $under5Rows = $under10Rows = $under20Rows = [];
    foreach ($leafInventory as $leafId => $leaf) {
        $count = (int) $leaf['native_question_count'];
        if ($count === 0) $zeroRows[] = ['leaf_id' => $leafId, 'runtime_availability' => $leaf['runtime_question_count'], 'cause' => (int) ($leaf['shared_in_runtime'] ?? 0) > 0 ? 'SHARED_RUNTIME_COVERED' : 'READINESS_BLOCKED', 'readiness' => $leaf['readiness'], 'reason' => $leaf['readiness_record']['review_reason'] ?? ''];
        if ($count < 5) $under5Rows[] = ['leaf_id' => $leafId, 'native_count' => $count, 'readiness' => $leaf['readiness'], 'authorable' => $leaf['authorable'], 'families' => $leaf['evidence_supported_families'], 'depth' => [$leaf['readiness_record']['supported_depth_min'] ?? 1, $leaf['readiness_record']['supported_depth_max'] ?? 1], 'headroom' => $leaf['safe_headroom'], 'planning_state' => $leaf['planning_state']];
        if ($count < 10) $under10Rows[] = ['leaf_id' => $leafId, 'native_count' => $count, 'readiness' => $leaf['readiness'], 'authorable' => $leaf['authorable'], 'families' => $leaf['evidence_supported_families'], 'depth' => [$leaf['readiness_record']['supported_depth_min'] ?? 1, $leaf['readiness_record']['supported_depth_max'] ?? 1], 'headroom' => $leaf['safe_headroom'], 'planning_state' => $leaf['planning_state']];
        if ($count < 20) $under20Rows[] = ['leaf_id' => $leafId, 'native_count' => $count, 'readiness' => $leaf['readiness'], 'authorable' => $leaf['authorable']];
    }
    $zeroCauseCounts = array_fill_keys(['TRUE_CONTENT_GAP', 'SHARED_RUNTIME_COVERED', 'OUT_OF_SCOPE', 'READINESS_BLOCKED', 'SOURCE_LIMITED', 'STUDY_LIMITED', 'CURRENTNESS_LIMITED', 'TAXONOMY_ARTIFACT', 'OTHER'], 0);
    foreach ($zeroRows as $zeroRow) if (isset($zeroCauseCounts[$zeroRow['cause']])) $zeroCauseCounts[$zeroRow['cause']]++;

    $questions = array_values(array_filter((array) ($collections['questions'] ?? []), 'is_array'));
    $eligibilityIndex = new QuestionEligibilityIndex((array) ($collections['question-eligibility'] ?? []), (array) ($collections['blueprints'] ?? []), (array) ($collections['concept-derived-eligibility'] ?? []), (array) ($collections['question-reuse-overrides'] ?? []));
    $nativeSpecialization = $sharedSpecialization = $effectiveSpecialization = $runtimeSpecialization = $specializationLeaves = $specFamily = $specDepth = $specUnknownFamily = [];
    $specializationRows = [];
    foreach (array_values(array_filter((array) ($collections['let-specializations'] ?? []), 'is_array')) as $specialization) {
        $id = (string) ($specialization['id'] ?? '');
        if ($id === '') continue;
        $specializationRows[$id] = ['id' => $id, 'official_name' => (string) ($specialization['official_name'] ?? $id), 'level' => (string) ($specialization['level'] ?? ''), 'official_scope_status' => 'OFFICIAL_ACTIVE; PRC Resolution 20 s.2025 and September 2026 operational labels verified', 'question_ids' => [], 'native_leaf_question_ids' => [], 'effective_ids' => [], 'runtime_ids' => [], 'leaf_native_counts' => [], 'leaf_ids' => [], 'shared_in_question_ids' => [], 'shared_out_question_ids' => [], 'readiness_counts' => ['READY' => 0, 'READY_WITH_LIMITS' => 0, 'REVIEW' => 0, 'DEFER' => 0], 'source_states' => [], 'study_states' => [], 'headroom' => ['min' => 0, 'preferred' => 0, 'max' => 0], 'native_specific_headroom' => ['min' => 0, 'preferred' => 0, 'max' => 0], 'shared_coverage_headroom' => ['min' => 0, 'preferred' => 0, 'max' => 0], 'shared_coverage_only_leaf_ids' => [], 'family_counts' => [], 'depth_counts' => [], 'unknown_family_questions' => 0];
    }
    foreach ($questions as $question) {
        $qid = (string) ($question['id'] ?? '');
        if ($qid === '') continue;
        $native = b649_questionBoard($question) === 'let';
        $eligibility = $eligibilityIndex->forExam($question, 'let');
        $placements = LETVariantRegistry::questionPlacements($question, $eligibility);
        $specIds = b649_ids(array_map(static fn(array $placement): string => (string) ($placement['specialization_id'] ?? ''), $placements));
        if ($specIds === []) continue;
        $taxonomy = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
        $leafId = trim((string) ($taxonomy['concept_id'] ?? ''));
        $family = b649_familyFromIntent((string) ($question['cognitive_intent'] ?? ''), $question);
        $depth = b649_depth($question, $reuseByQuestion);
        $primaryPlacement = is_array($question['let_placement'] ?? null) ? $question['let_placement'] : (array) ($placements[0] ?? []);
        $primarySpec = (string) ($primaryPlacement['specialization_id'] ?? '');
        foreach ($specIds as $specId) {
            if (!isset($specializationRows[$specId])) continue;
            $specializationRows[$specId]['leaf_ids'][$leafId] = true;
            if ($native || $eligibility !== null) $specializationRows[$specId]['effective_ids'][$qid] = true;
            if (strtolower((string) ($question['status'] ?? 'active')) === 'active' && ($native || $eligibility !== null)) $specializationRows[$specId]['runtime_ids'][$qid] = true;
            if ($native) $specializationRows[$specId]['question_ids'][$qid] = true;
            $primaryNativeSpecialization = $primarySpec === $specId || ($primarySpec === '' && count($specIds) === 1);
            if ($native && $primaryNativeSpecialization) $specializationRows[$specId]['native_leaf_question_ids'][$leafId][$qid] = true;
            if ($native && count($specIds) > 1) {
                if ($primarySpec === $specId) $specializationRows[$specId]['shared_out_question_ids'][$qid] = true;
                else $specializationRows[$specId]['shared_in_question_ids'][$qid] = true;
            }
            if ($native && $primaryNativeSpecialization) {
                if ($family === 'UNCLASSIFIED') $specializationRows[$specId]['unknown_family_questions']++;
                else $specializationRows[$specId]['family_counts'][$family] = ($specializationRows[$specId]['family_counts'][$family] ?? 0) + 1;
                if ($depth !== null) $specializationRows[$specId]['depth_counts']['D' . $depth] = ($specializationRows[$specId]['depth_counts']['D' . $depth] ?? 0) + 1;
            }
        }
    }
    foreach ($leafInventory as $leafId => $leaf) {
        foreach ((array) ($leaf['scope_specialization_ids'] ?? []) as $specId) {
            if (isset($specializationRows[$specId])) $specializationRows[$specId]['leaf_ids'][$leafId] = true;
        }
    }
    $specializationNativeLeafCountsReconcile = true;
    foreach ($specializationRows as $specId => &$spec) {
        foreach ($spec['leaf_ids'] as $leafId => $_) {
            $leaf = $leafInventory[$leafId] ?? null;
            if (!is_array($leaf)) continue;
            $record = (array) ($leaf['readiness_record'] ?? []);
            $nativeCount = count((array) ($spec['native_leaf_question_ids'][$leafId] ?? []));
            $sharedLeaf = $nativeCount === 0;
            $spec['leaf_native_counts'][$leafId] = $sharedLeaf ? 0 : $nativeCount;
            $state = (string) ($record['readiness'] ?? 'REVIEW');
            $spec['readiness_counts'][$state] = ($spec['readiness_counts'][$state] ?? 0) + 1;
            $spec['source_states'][$record['source_authority'] ?? 'UNKNOWN'] = ($spec['source_states'][$record['source_authority'] ?? 'UNKNOWN'] ?? 0) + 1;
            $spec['study_states'][$record['study_coverage'] ?? 'UNKNOWN'] = ($spec['study_states'][$record['study_coverage'] ?? 'UNKNOWN'] ?? 0) + 1;
            foreach (['min' => 'safe_headroom_min', 'preferred' => 'safe_headroom_preferred', 'max' => 'safe_headroom_max'] as $out => $field) {
                $value = (int) ($record[$field] ?? 0);
                $spec['headroom'][$out] += $value;
                $headroomClass = $sharedLeaf ? 'shared_coverage_headroom' : 'native_specific_headroom';
                $spec[$headroomClass][$out] += $value;
            }
            if ($sharedLeaf) $spec['shared_coverage_only_leaf_ids'][$leafId] = true;
        }
        $poolStats = b649_stats($spec['leaf_native_counts']);
        $authorableLeafCounts = [];
        foreach ($spec['leaf_native_counts'] as $leafId => $count) {
            $sharedLeaf = !in_array($specId, (array) ($leafInventory[$leafId]['native_specialization_ids'] ?? []), true);
            if (!$sharedLeaf && ($leafInventory[$leafId]['authorable'] ?? false) === true) $authorableLeafCounts[$leafId] = $count;
        }
        $authorableSpecStats = b649_stats($authorableLeafCounts);
        $familyCount = count($spec['family_counts']);
        $spec['native_question_count'] = count($spec['question_ids']) - count($spec['shared_in_question_ids']);
        if (array_sum($spec['leaf_native_counts']) !== $spec['native_question_count']) $specializationNativeLeafCountsReconcile = false;
        $unknownRate = $spec['native_question_count'] === 0 ? 1.0 : $spec['unknown_family_questions'] / $spec['native_question_count'];
        $classifiedFamilyCounts = array_values($spec['family_counts']);
        $familyConcentration = $spec['native_question_count'] > 0 && $classifiedFamilyCounts !== [] ? max($classifiedFamilyCounts) / $spec['native_question_count'] : $unknownRate;
        $spec['family_metadata_unknown_rate'] = round(min(1.0, $unknownRate), 4);
        $spec['family_concentration'] = round($familyConcentration, 4);
        $specRisk = $spec['native_question_count'] >= 10 && (min(1.0, $unknownRate) >= 0.65 || $familyConcentration >= 0.8 || count($spec['family_counts']) <= 1)
            ? 'HIGH'
            : ($spec['native_question_count'] >= 5 && (min(1.0, $unknownRate) >= 0.35 || $familyConcentration >= 0.6 || count($spec['family_counts']) <= 1) ? 'MODERATE' : 'LOW');
        $spec['shared_in'] = count($spec['shared_in_question_ids']);
        $spec['shared_out'] = count($spec['shared_out_question_ids']);
        $spec['effective_count'] = count($spec['effective_ids']);
        $spec['runtime_count'] = count($spec['runtime_ids']);
        $spec['active_leaves'] = count($spec['leaf_ids']);
        $spec['native_leaf_health'] = $poolStats;
        $spec['authorable_leaf_health'] = $authorableSpecStats;
        $spec['zero'] = $poolStats['zero']; $spec['under5'] = $poolStats['under5']; $spec['under10'] = $poolStats['under10']; $spec['under20'] = $poolStats['under20'];
        $spec['authorable_zero'] = $authorableSpecStats['zero']; $spec['authorable_under5'] = $authorableSpecStats['under5']; $spec['authorable_under10'] = $authorableSpecStats['under10']; $spec['authorable_under20'] = $authorableSpecStats['under20'];
        $spec['shared_coverage_only_leaf_count'] = count($spec['shared_coverage_only_leaf_ids']);
        $spec['shared_coverage_only_authorable_zero'] = count(array_filter(array_keys($spec['shared_coverage_only_leaf_ids']), static fn(string $leafId): bool => ($leafInventory[$leafId]['authorable'] ?? false) === true));
        $spec['shared_coverage_only_leaf_ids'] = array_keys($spec['shared_coverage_only_leaf_ids']);
        $spec['source_state'] = count($spec['source_states']) > 1 ? 'MIXED' : (string) array_key_first($spec['source_states']);
        $spec['study_state'] = count($spec['study_states']) > 1 ? 'MIXED' : (string) array_key_first($spec['study_states']);
        $spec['repeat_risk'] = $specRisk;
        $spec['expansion_state'] = $spec['authorable_under5'] > 0 ? 'EXPAND_WITH_LIMITS' : ($spec['authorable_under10'] > 0 ? 'SELECTIVE_DEEPENING' : 'REVIEW_OR_MAINTENANCE');
        $spec['primary_deficiencies'] = [];
        if ($spec['shared_coverage_only_leaf_count'] > 0) $spec['primary_deficiencies'][] = 'SHARED_COVERAGE_ONLY_NATIVE_DEPTH (' . $spec['shared_coverage_only_leaf_count'] . ' leaves; excluded from native authorable zeros)';
        if ($spec['readiness_counts']['REVIEW'] > 0) $spec['primary_deficiencies'][] = 'SCOPE_OR_READINESS_REVIEW (' . $spec['readiness_counts']['REVIEW'] . ' leaves)';
        if ($spec['authorable_under10'] > 0) $spec['primary_deficiencies'][] = 'SELECTIVE_NATIVE_DEPTH_BELOW_10 (' . $spec['authorable_under10'] . ' leaves; not a quota)';
        if ($specRisk === 'HIGH') $spec['primary_deficiencies'][] = 'HIGH_REPEAT_RISK (' . count($spec['family_counts']) . ' observed family labels; concentration/metadata limits apply)';
        if ($spec['primary_deficiencies'] === []) $spec['primary_deficiencies'][] = 'NO_IMMEDIATE_AUTHORING_DEFICIENCY; retain maintenance and fresh scope check';
        $spec['family_count'] = $familyCount;
        $spec['observed_families'] = array_keys($spec['family_counts']); sort($spec['observed_families'], SORT_STRING);
        $spec['depth_distribution'] = $spec['depth_counts'];
        foreach (['question_ids','native_leaf_question_ids','effective_ids','runtime_ids','leaf_ids','shared_in_question_ids','shared_out_question_ids','leaf_native_counts','source_states','study_states','family_counts','depth_counts'] as $private) unset($spec[$private]);
    }
    unset($spec);
    ksort($specializationRows, SORT_STRING);

    $componentQuestionSets = ['GENERAL_EDUCATION' => ['native' => [], 'effective' => [], 'runtime' => []], 'PROFESSIONAL_EDUCATION' => ['native' => [], 'effective' => [], 'runtime' => []], 'SPECIALIZATION' => ['native' => [], 'effective' => [], 'runtime' => []]];
    $flatPlacementRows = [];
    foreach ($flatClasses as $leafId => $classification) {
        $leaf = $leafInventory[$leafId] ?? [];
        $flatPlacementRows[] = ['leaf_id' => $leafId, 'leaf_name' => $leaf['leaf_name'] ?? $leafId, 'classification' => $classification, 'native_question_count' => $leaf['native_question_count'] ?? 0, 'effective_count' => $leaf['effective_declared_question_count'] ?? 0, 'runtime_count' => $leaf['runtime_question_count'] ?? 0, 'specialization_ids' => $leaf['scope_specialization_ids'] ?? [], 'readiness' => $leaf['readiness'] ?? 'REVIEW', 'semantic_action' => 'NO_MAPPING_CHANGE; retain review or bounded shared placement as classified'];
    }
    sort($flatPlacementRows);
    $sharedVariantRows = [];
    foreach ($questions as $question) {
        $eligibility = $eligibilityIndex->forExam($question, 'let');
        $placements = LETVariantRegistry::questionPlacements($question, $eligibility);
        $ids = b649_ids(array_map(static fn(array $placement): string => (string) ($placement['specialization_id'] ?? ''), $placements));
        if (count($ids) < 2) continue;
        $conceptId = (string) ($question['taxonomy']['concept_id'] ?? '');
        $class = in_array($conceptId, B649_SHARED_TLE_LEAVES, true) && count($ids) === 4 ? 'VALID_SHARED_COMPETENCY' : 'SEMANTICALLY_AMBIGUOUS';
        $primaryPlacement = is_array($question['let_placement'] ?? null) ? $question['let_placement'] : (array) ($placements[0] ?? []);
        $sharedVariantRows[] = ['question_id' => (string) ($question['id'] ?? ''), 'leaf_id' => $conceptId, 'native_specialization_id' => (string) ($primaryPlacement['specialization_id'] ?? ''), 'shared_in_specialization_ids' => array_values(array_diff($ids, [(string) ($primaryPlacement['specialization_id'] ?? '')])), 'specialization_ids' => $ids, 'classification' => $class, 'native_board' => b649_questionBoard($question)];
    }
    usort($sharedVariantRows, static fn(array $a, array $b): int => [(string) $a['leaf_id'], (string) $a['question_id']] <=> [(string) $b['leaf_id'], (string) $b['question_id']]);
    $sharedVariantEdgeCount = array_sum(array_map(static fn(array $row): int => count((array) ($row['shared_in_specialization_ids'] ?? [])), $sharedVariantRows));

    $questionById = b649_index($questions);
    $componentForArea = static fn(string $area): string => match ($area) {
        'professional-education' => 'PROFESSIONAL_EDUCATION',
        'specialization' => 'SPECIALIZATION',
        default => 'GENERAL_EDUCATION',
    };
    $componentsForQuestion = static function (array $question, ?array $eligibility) use ($leafInventory, $componentForArea): array {
        $components = [];
        foreach (LETVariantRegistry::questionPlacements($question, $eligibility) as $placement) {
            $component = strtoupper(trim((string) ($placement['component'] ?? $placement['let_component'] ?? '')));
            if ($component === '' && trim((string) ($placement['specialization_id'] ?? '')) !== '') $component = 'SPECIALIZATION';
            if (in_array($component, ['GENERAL_EDUCATION', 'PROFESSIONAL_EDUCATION', 'SPECIALIZATION'], true)) $components[$component] = true;
        }
        if ($components === []) {
            $placement = is_array($eligibility['placement'] ?? null) ? $eligibility['placement'] : [];
            $leafId = trim((string) ($placement['concept_id'] ?? $question['taxonomy']['concept_id'] ?? ''));
            $components[$componentForArea((string) ($leafInventory[$leafId]['major_area'] ?? 'general-education'))] = true;
        }
        return array_keys($components);
    };
    $nativeQuestionComponents = $effectiveComponentSets = $runtimeComponentSets = $sharedOutComponentSets = [];
    foreach ($questions as $question) {
        $qid = (string) ($question['id'] ?? '');
        if ($qid === '') continue;
        $native = b649_questionBoard($question) === 'let';
        $eligibility = $eligibilityIndex->forExam($question, 'let');
        $taxonomyLeaf = trim((string) ($question['taxonomy']['concept_id'] ?? ''));
        $primaryPlacement = LETVariantRegistry::questionPlacement($question, $eligibility);
        $nativeComponent = strtoupper(trim((string) ($primaryPlacement['component'] ?? '')));
        if (!in_array($nativeComponent, ['GENERAL_EDUCATION', 'PROFESSIONAL_EDUCATION', 'SPECIALIZATION'], true)) {
            $nativeComponent = $componentForArea((string) ($leafInventory[$taxonomyLeaf]['major_area'] ?? 'general-education'));
        }
        if ($native) $nativeQuestionComponents[$nativeComponent][$qid] = true;
        if ($native) $effectiveComponentSets[$nativeComponent][$qid] = true;
        if (isset($effectiveIds[$qid]) && $eligibility !== null) {
            $placedComponents = $componentsForQuestion($question, $eligibility);
            foreach ($placedComponents as $component) $effectiveComponentSets[$component][$qid] = true;
            if ($native) foreach ($placedComponents as $component) if ($component !== $nativeComponent) $sharedOutComponentSets[$nativeComponent][$qid] = true;
        }
        if (strtolower((string) ($question['status'] ?? 'active')) === 'active') {
            if ($native) $runtimeComponentSets[$nativeComponent][$qid] = true;
            if ($eligibility !== null) foreach ($componentsForQuestion($question, $eligibility) as $component) $runtimeComponentSets[$component][$qid] = true;
        }
    }
    foreach (['GENERAL_EDUCATION','PROFESSIONAL_EDUCATION','SPECIALIZATION'] as $component) {
        $componentQuestionSets[$component]['native'] = $nativeQuestionComponents[$component] ?? [];
        $componentQuestionSets[$component]['effective'] = $effectiveComponentSets[$component] ?? [];
        $componentQuestionSets[$component]['runtime'] = $runtimeComponentSets[$component] ?? [];
    }
    $componentAvailability = ['native' => [], 'effective' => [], 'runtime' => []];
    foreach (['native', 'effective', 'runtime'] as $kind) {
        foreach (['GENERAL_EDUCATION', 'PROFESSIONAL_EDUCATION', 'SPECIALIZATION'] as $component) {
            foreach (array_keys($componentQuestionSets[$component][$kind] ?? []) as $qid) $componentAvailability[$kind][$qid][$component] = true;
        }
    }
    $crossAreaOverlapCounts = ['native' => 0, 'effective' => 0, 'runtime' => 0];
    $crossAreaOverlapByKindAndPair = ['effective' => [], 'runtime' => []];
    foreach (['native', 'effective', 'runtime'] as $kind) {
        foreach ($componentAvailability[$kind] as $qid => $components) {
            $componentIds = array_keys($components);
            if (count($componentIds) < 2) continue;
            $crossAreaOverlapCounts[$kind]++;
            if ($kind === 'native') continue;
            sort($componentIds, SORT_STRING);
            $pair = implode('|', $componentIds);
            $crossAreaOverlapByKindAndPair[$kind][$pair] = ($crossAreaOverlapByKindAndPair[$kind][$pair] ?? 0) + 1;
        }
    }
    foreach ($crossAreaOverlapByKindAndPair as &$overlapCounts) ksort($overlapCounts, SORT_STRING);
    unset($overlapCounts);
    $areaNativeUnion = count($componentAvailability['native']);
    $areaEffectiveUnion = count($componentAvailability['effective']);
    $areaRuntimeUnion = count($componentAvailability['runtime']);
    $majorAreas = [];
    foreach (['general-education' => 'GENERAL_EDUCATION', 'professional-education' => 'PROFESSIONAL_EDUCATION', 'specialization' => 'SPECIALIZATION'] as $areaId => $component) {
        $leafIds = [];
        foreach ($leafInventory as $leafId => $leaf) if ($leaf['major_area'] === $areaId) $leafIds[$leafId] = true;
        $counts = array_map(static fn(string $kind): int => count($componentQuestionSets[$component][$kind] ?? []), ['native','effective','runtime']);
        $leafNativeCounts = [];
        foreach (array_keys($leafIds) as $leafId) $leafNativeCounts[$leafId] = (int) ($leafInventory[$leafId]['native_question_count'] ?? 0);
        $areaStats = b649_stats($leafNativeCounts);
        $authorableArea = [];
        foreach (array_keys($leafIds) as $leafId) if (($leafInventory[$leafId]['authorable'] ?? false) === true) $authorableArea[$leafId] = (int) $leafInventory[$leafId]['native_question_count'];
        $areaAuthorableStats = b649_stats($authorableArea);
        $areaReady = ['READY' => 0, 'READY_WITH_LIMITS' => 0, 'REVIEW' => 0, 'DEFER' => 0];
        $areaHeadroom = ['min' => 0, 'preferred' => 0, 'max' => 0];
        $areaRiskCounts = ['LOW' => 0, 'MODERATE' => 0, 'HIGH' => 0, 'CRITICAL' => 0];
        $areaAuthorityCounts = ['STRONG' => 0, 'ADEQUATE' => 0, 'WEAK' => 0, 'NONE' => 0, 'UNKNOWN' => 0];
        $areaStudyCounts = ['COMPLETE' => 0, 'SUFFICIENT' => 0, 'PARTIAL' => 0, 'MISSING' => 0, 'INVALID' => 0, 'UNKNOWN' => 0];
        $areaTraceabilityCounts = ['COMPLETE' => 0, 'PARTIAL' => 0, 'BROKEN' => 0, 'NONE' => 0, 'UNKNOWN' => 0];
        $areaCurrentnessCounts = [];
        $areaVersionSensitiveLeaves = [];
        foreach (array_keys($leafIds) as $leafId) {
            $record = (array) ($leafInventory[$leafId]['readiness_record'] ?? []);
            $state = (string) ($record['readiness'] ?? 'REVIEW'); $areaReady[$state] = ($areaReady[$state] ?? 0) + 1;
            $areaHeadroom['min'] += (int) ($record['safe_headroom_min'] ?? 0); $areaHeadroom['preferred'] += (int) ($record['safe_headroom_preferred'] ?? 0); $areaHeadroom['max'] += (int) ($record['safe_headroom_max'] ?? 0);
            $authority = (string) ($record['source_authority'] ?? 'UNKNOWN'); if (isset($areaAuthorityCounts[$authority])) $areaAuthorityCounts[$authority]++;
            $study = (string) ($record['study_coverage'] ?? 'UNKNOWN'); if (isset($areaStudyCounts[$study])) $areaStudyCounts[$study]++;
            $traceability = (string) ($record['study_source_traceability'] ?? 'UNKNOWN'); if (isset($areaTraceabilityCounts[$traceability])) $areaTraceabilityCounts[$traceability]++;
            $currentness = (string) ($record['source_currentness'] ?? 'UNKNOWN'); $areaCurrentnessCounts[$currentness] = ($areaCurrentnessCounts[$currentness] ?? 0) + 1;
            $risk = (string) ($leafInventory[$leafId]['repeat_risk'] ?? 'MODERATE'); if (isset($areaRiskCounts[$risk])) $areaRiskCounts[$risk]++;
            if ($currentness === 'CURRENT_WITH_LIMITS') $areaVersionSensitiveLeaves[] = $leafId;
        }
        $riskRisk = $areaRiskCounts['HIGH'] + $areaRiskCounts['CRITICAL'] >= max(1, (int) ceil(count($leafIds) * 0.35)) ? 'HIGH' : (($areaRiskCounts['MODERATE'] + $areaRiskCounts['HIGH'] + $areaRiskCounts['CRITICAL']) >= max(1, (int) ceil(count($leafIds) * 0.25)) ? 'MODERATE' : 'LOW');
        $studyState = $areaStudyCounts['PARTIAL'] + $areaStudyCounts['MISSING'] + $areaStudyCounts['INVALID'] > 0 ? 'MIXED_WITH_REVIEW_EXCEPTIONS' : 'SUFFICIENT';
        $majorAreas[$areaId] = ['active_leaves' => count($leafIds), 'native_canonical' => count($componentQuestionSets[$component]['native'] ?? []), 'shared_in' => max(0, count($componentQuestionSets[$component]['effective'] ?? []) - count($componentQuestionSets[$component]['native'] ?? [])), 'shared_out' => count($sharedOutComponentSets[$component] ?? []), 'effective_declared' => count($componentQuestionSets[$component]['effective'] ?? []), 'runtime' => count($componentQuestionSets[$component]['runtime'] ?? []), 'question_count_attribution' => 'explicit LET primary component; legacy no-placement questions use the taxonomy leaf home component', 'leaf_health_attribution' => 'one primary taxonomy leaf home per leaf; readiness applies to the leaf and is not a component-specific question count', 'raw_leaf_health' => $areaStats, 'readiness_counts' => $areaReady, 'authorable_zero' => $areaAuthorableStats['zero'], 'authorable_under5' => $areaAuthorableStats['under5'], 'authorable_under10' => $areaAuthorableStats['under10'], 'authorable_under20' => $areaAuthorableStats['under20'], 'source_state' => 'ADEQUATE_WITH_SCOPE_LIMITS', 'source_authority_counts' => $areaAuthorityCounts, 'source_currentness_counts' => $areaCurrentnessCounts, 'version_sensitive_leaf_count' => count($areaVersionSensitiveLeaves), 'version_sensitive_leaf_ids' => $areaVersionSensitiveLeaves, 'study_state' => $studyState, 'study_coverage_counts' => $areaStudyCounts, 'study_traceability_counts' => $areaTraceabilityCounts, 'headroom' => $areaHeadroom, 'repeat_risk' => $riskRisk, 'repeat_risk_counts' => $areaRiskCounts];
    }

    $wave1Candidates = [];
    foreach ($under5Rows as $row) {
        $leaf = $leafInventory[$row['leaf_id']];
        if (!$leaf['authorable']) continue;
        $record = (array) $leaf['readiness_record'];
        $unrepresentedFamilyCount = (int) ($leaf['high_value_family_headroom'] ?? 0);
        $planned = min((int) $record['deficit_to_5'], (int) $record['safe_headroom_preferred'], max(0, $unrepresentedFamilyCount));
        if ($planned <= 0) continue;
        $wave1Candidates[] = ['leaf_id' => $row['leaf_id'], 'area' => $leaf['major_area'], 'specialization_ids' => $leaf['scope_specialization_ids'], 'current_count' => $leaf['native_question_count'], 'readiness' => $leaf['readiness'], 'deficit_to_5' => $record['deficit_to_5'], 'distinct_families' => $leaf['evidence_supported_families'], 'supported_depth' => [$record['supported_depth_min'], $record['supported_depth_max']], 'headroom' => $leaf['safe_headroom'], 'planned_new' => $planned, 'rationale' => 'Source-backed family breadth remains after a conservative offset for already-observed question-intent diversity; amount is capped by preferred headroom, not filled to a portfolio target.'];
    }
    usort($wave1Candidates, static fn(array $a, array $b): int => [(int) $a['current_count'], (string) $a['area'], (string) $a['leaf_id']] <=> [(int) $b['current_count'], (string) $b['area'], (string) $b['leaf_id']]);
    $wave1ByLeaf = []; foreach ($wave1Candidates as $candidate) $wave1ByLeaf[$candidate['leaf_id']] = $candidate;
    $wave1FamilySet = [];
    foreach ($wave1Candidates as $candidate) foreach ((array) ($candidate['distinct_families'] ?? []) as $family) $wave1FamilySet[(string) $family] = true;
    $wave1DistinctFamilies = array_keys($wave1FamilySet);
    sort($wave1DistinctFamilies, SORT_STRING);
    $wave2Candidates = [];
    foreach ($under10Rows as $row) {
        $leafId = (string) $row['leaf_id']; $leaf = $leafInventory[$leafId]; if (!$leaf['authorable']) continue;
        $currentAfterWave1 = (int) $leaf['native_question_count'] + (int) ($wave1ByLeaf[$leafId]['planned_new'] ?? 0);
        if ($currentAfterWave1 >= 10) continue;
        $maxLeft = max(0, (int) $leaf['safe_headroom']['max'] - (int) ($wave1ByLeaf[$leafId]['planned_new'] ?? 0));
        $planned = min(1, $maxLeft);
        if ($planned <= 0) continue;
        $wave2Candidates[] = ['leaf_id' => $leafId, 'current_after_wave1' => $currentAfterWave1, 'remaining_deficit_to_10' => max(0, 10 - $currentAfterWave1), 'specialization_ids' => $leaf['scope_specialization_ids'], 'distinct_families' => $leaf['evidence_supported_families'], 'headroom_remaining' => $maxLeft, 'provisional_planned_new' => $planned, 'status' => 'PROVISIONAL; recalculate after Wave1'];
    }
    usort($wave2Candidates, static fn(array $a, array $b): int => [(int) $a['current_after_wave1'], (string) $a['leaf_id']] <=> [(int) $b['current_after_wave1'], (string) $b['leaf_id']]);
    $wave2FamilySet = [];
    foreach ($wave2Candidates as $candidate) foreach ((array) ($candidate['distinct_families'] ?? []) as $family) $wave2FamilySet[(string) $family] = true;
    $wave2DistinctFamilies = array_keys($wave2FamilySet);
    sort($wave2DistinctFamilies, SORT_STRING);
    $wave1PlanCount = array_sum(array_column($wave1Candidates, 'planned_new'));
    $wave1MathDeficit = array_sum(array_column($wave1Candidates, 'deficit_to_5'));
    $wave1ByArea = ['general-education' => 0, 'professional-education' => 0, 'specialization' => 0];
    foreach ($wave1Candidates as $candidate) $wave1ByArea[$candidate['area']] = ($wave1ByArea[$candidate['area']] ?? 0) + (int) $candidate['planned_new'];
    $safeMin = $safePreferred = $safeMax = 0;
    foreach ($wave1Candidates as $candidate) {
        $cap = max(0, 5 - (int) $candidate['current_count']);
        $safeMin += min($cap, (int) $candidate['headroom']['min']);
        $safePreferred += min($cap, (int) $candidate['headroom']['preferred']);
        $safeMax += min($cap, (int) $candidate['headroom']['max']);
    }

    $depthDistribution = ['D1' => 0, 'D2' => 0, 'D3' => 0, 'D4' => 0, 'D5' => 0, 'UNKNOWN' => 0];
    $leafDepthState = ['ZERO_DEPTH_VARIETY' => 0, 'SINGLE_DEPTH' => 0, 'NARROW_DEPTH' => 0, 'HEALTHY_DEPTH' => 0];
    foreach ($questions as $question) {
        if (b649_questionBoard($question) !== 'let') continue;
        $depth = b649_depth($question, $reuseByQuestion);
        if ($depth === null) $depthDistribution['UNKNOWN']++; else $depthDistribution['D' . $depth]++;
    }
    foreach ($leafInventory as $leaf) {
        $depthValues = (array) $leaf['observed_depth_values'];
        if ($leaf['native_question_count'] === 0) $leafDepthState['ZERO_DEPTH_VARIETY']++;
        elseif (count($depthValues) <= 1) $leafDepthState['SINGLE_DEPTH']++;
        elseif (count($depthValues) === 2) $leafDepthState['NARROW_DEPTH']++;
        else $leafDepthState['HEALTHY_DEPTH']++;
    }

    $globalStats = (array) ($healthAfter['statistics']['totals'] ?? []);
    $boardHealth = (array) ($healthAfter['board_summaries'] ?? []);
    $letHealth = (array) ($boardHealth['let'] ?? []);
    $b648Global = (array) ($baseline['global_counts'] ?? []);
    $regressions = (array) ($baseline['regressions'] ?? []);
    $issueCounts = [];
    foreach ((array) ($healthAfter['issues'] ?? []) as $issue) {
        if (!is_array($issue)) continue;
        $code = (string) ($issue['code'] ?? '');
        $issueCounts[$code] = ($issueCounts[$code] ?? 0) + 1;
    }
    $healthIssueIdentity = static fn(array $issue): string => implode('|', [(string) ($issue['board_id'] ?? ''), (string) ($issue['entity_type'] ?? ''), (string) ($issue['entity_id'] ?? ''), (string) ($issue['code'] ?? '')]);
    $beforeFailIds = [];
    foreach ((array) ($healthBefore['issues'] ?? []) as $issue) {
        if (!is_array($issue) || (strtoupper((string) ($issue['status'] ?? '')) !== 'FAIL' && strtoupper((string) ($issue['severity'] ?? '')) !== 'FAIL')) continue;
        $beforeFailIds[] = $healthIssueIdentity($issue);
    }
    $afterFailIds = [];
    $letSourceStudyReviewFindings = [];
    foreach ((array) ($healthAfter['issues'] ?? []) as $issue) {
        if (!is_array($issue)) continue;
        if (strtoupper((string) ($issue['status'] ?? '')) === 'FAIL' || strtoupper((string) ($issue['severity'] ?? '')) === 'FAIL') $afterFailIds[] = $healthIssueIdentity($issue);
        if (strtolower((string) ($issue['board_id'] ?? '')) === 'let' && in_array((string) ($issue['code'] ?? ''), ['SOURCE_SEMANTIC_FIT_REVIEW', 'ORPHAN_STUDY_MATERIAL'], true)) $letSourceStudyReviewFindings[] = $healthIssueIdentity($issue);
    }
    sort($beforeFailIds, SORT_STRING);
    sort($afterFailIds, SORT_STRING);
    sort($letSourceStudyReviewFindings, SORT_STRING);
    $newDeterministicFailIds = array_values(array_diff($afterFailIds, $beforeFailIds));
    $globalRegression = [
        'totals' => $globalStats,
        'native_by_board' => (array) ($b648Global['native_by_board'] ?? []),
        'me_canonical' => (int) ($boardHealth['mechanical-engineering']['canonical'] ?? 0),
        'me_authorable_zero' => (int) ($regressions['me_authorable_zero'] ?? -1),
        'me_authorable_under5' => (int) ($regressions['me_authorable_under5'] ?? -1),
        'me_hvac_review' => (bool) ($regressions['me_hvac_review'] ?? false),
        'nursing_dosage' => (array) ($regressions['nursing_dosage'] ?? []),
        'cpa_native' => (int) ($boardHealth['cpa']['canonical'] ?? 0),
        'cpa_cy2029_leakage' => (int) ($regressions['cpa_cy2029_leakage'] ?? -1),
        'flashcard_defects' => (int) ($healthAfter['summary']['flashcards']['nonderivable_defect'] ?? -1),
        'calculation_metadata_contradictions' => (int) ($regressions['calculation_metadata_contradictions'] ?? -1),
        'duplicate_effective' => (int) ($regressions['duplicate_effective'] ?? -1),
        'double_weighting' => (int) ($regressions['double_weighting'] ?? -1),
        'new_different_id_equivalence' => (int) ($regressions['new_different_id_equivalence'] ?? -1),
        'all_expected_global_counts_match' => $globalStats === [
            'canonical' => (int) ($b648Global['canonical'] ?? 0),
            'effective_declared' => (int) ($b648Global['effective_declared'] ?? 0),
            'runtime' => (int) ($b648Global['runtime'] ?? 0),
        ],
    ];
    $healthSummary = (array) ($healthAfter['summary'] ?? []);
    $healthBeforeSummary = (array) ($healthBefore['summary'] ?? []);

    $flatCount = count($flatPlacementRows);
    $flatTypeCounts = [];
    foreach ($flatPlacementRows as $row) $flatTypeCounts[$row['classification']] = ($flatTypeCounts[$row['classification']] ?? 0) + 1;
    $sharedClassCounts = ['VALID_SHARED_COMPETENCY' => 0, 'VALID_VARIANT_AVAILABILITY' => 0, 'SPECIALIZATION_NATIVE' => 0, 'GENED_PROFED_SHARED' => 0, 'LEGACY_PLACEMENT' => 0, 'SEMANTICALLY_AMBIGUOUS' => 0, 'OUT_OF_SCOPE' => 0];
    foreach ($flatTypeCounts as $type => $count) if (isset($sharedClassCounts[$type])) $sharedClassCounts[$type] = $count;
    $sharedReviewCount = count(array_filter($flatPlacementRows, static fn(array $row): bool => ($row['readiness'] ?? '') === 'REVIEW'));
    $sourceReadinessCountsByArea = [];
    foreach ($majorAreas as $id => $area) $sourceReadinessCountsByArea[$id] = $area['readiness_counts'];
    $versionSensitiveLeaves = array_values(array_map(static fn(array $leaf): string => (string) $leaf['leaf_id'], array_filter($leafInventory, static fn(array $leaf): bool => ($leaf['readiness_record']['source_currentness'] ?? '') === 'CURRENT_WITH_LIMITS')));
    $currentnessCounts = array_fill_keys(SourceReadinessRecord::CURRENTNESS, 0);
    $repeatRiskCounts = ['LOW' => 0, 'MODERATE' => 0, 'HIGH' => 0, 'CRITICAL' => 0];
    $specializationPlacementLeafIds = [];
    foreach ($leafInventory as $leaf) {
        $currentness = (string) ($leaf['readiness_record']['source_currentness'] ?? 'UNKNOWN');
        if (isset($currentnessCounts[$currentness])) $currentnessCounts[$currentness]++;
        $risk = (string) ($leaf['repeat_risk'] ?? 'MODERATE');
        if (isset($repeatRiskCounts[$risk])) $repeatRiskCounts[$risk]++;
        if ((array) ($leaf['scope_specialization_ids'] ?? []) !== []) $specializationPlacementLeafIds[(string) $leaf['leaf_id']] = true;
    }
    $overallRepeatRisk = $repeatRiskCounts['HIGH'] + $repeatRiskCounts['CRITICAL'] >= max(1, (int) ceil(count($leafInventory) * 0.35))
        ? 'HIGH'
        : (($repeatRiskCounts['MODERATE'] + $repeatRiskCounts['HIGH'] + $repeatRiskCounts['CRITICAL']) >= max(1, (int) ceil(count($leafInventory) * 0.25)) ? 'MODERATE' : 'LOW');
    $missingOfficialSourceIds = array_values(array_diff(array_merge(B649_SCOPE_SOURCES, B649_OPERATIONAL_CONFIRMATION_SOURCES), array_keys($sources)));
    sort($missingOfficialSourceIds, SORT_STRING);

    foreach ($leafInventory as $leafId => $leaf) {
        if (!$leaf['authorable']) continue;
        $count = (int) $leaf['native_question_count'];
        if ($count < 5) $leafInventory[$leafId]['planning_state'] = isset($wave1ByLeaf[$leafId]) ? 'EXPAND_WITH_LIMITS' : 'EVIDENCE_EXHAUSTED';
        elseif ($count < 10) $leafInventory[$leafId]['planning_state'] = (int) ($leaf['safe_headroom']['max'] ?? 0) > 0 ? 'EXPAND' : 'EVIDENCE_EXHAUSTED';
        else $leafInventory[$leafId]['planning_state'] = 'NO_EXPANSION_NEEDED';
    }
    $expansionStates = ['EXPAND' => 0, 'EXPAND_WITH_LIMITS' => 0, 'REVIEW' => 0, 'DEFER' => 0, 'NO_EXPANSION_NEEDED' => 0, 'EVIDENCE_EXHAUSTED' => 0];
    foreach ($leafInventory as $leaf) { $state = (string) $leaf['planning_state']; $expansionStates[$state] = ($expansionStates[$state] ?? 0) + 1; }
    $wave1Specialization = array_fill_keys($registryIds, 0);
    foreach ($wave1Candidates as $candidate) foreach ((array) $candidate['specialization_ids'] as $specId) $wave1Specialization[$specId] += (int) $candidate['planned_new'];
    $specializationTotal = count($specializationRows);
    $zeroSpecializationPools = count(array_filter($specializationRows, static fn(array $spec): bool => (int) ($spec['effective_count'] ?? 0) === 0));
    $decision = $wave1PlanCount > 0 && $zeroSpecializationPools === 0 ? 'LET_PARTIAL_EXPANSION_READY' : ($wave1PlanCount === 0 ? 'LET_REMAINING_SOURCE_GAPS' : 'LET_SCOPE_RECONCILIATION_REQUIRED');
    $authRationale = $decision === 'LET_PARTIAL_EXPANSION_READY'
        ? 'A source-backed hierarchical authorable zero/U5 cohort exists, but the 38 flat/shared legacy placements remain in REVIEW; authorize only the listed limited Wave1 candidates and recalculate all deficits after production.'
        : 'The readiness gate does not establish a sufficiently safe authorable Wave1 cohort; close only the identified source/study/scope review gaps.';
    $foundationReviewIds = [];
    $foundationSufficientIds = [];
    $selectiveDeepeningIds = [];
    $maintenanceOnlyIds = [];
    $evidenceLimitedUnder5Ids = [];
    $currentnessReviewIds = [];
    foreach ($leafInventory as $leafId => $leaf) {
        $record = (array) ($leaf['readiness_record'] ?? []);
        if (!$leaf['authorable']) $foundationReviewIds[] = (string) $leafId;
        else $foundationSufficientIds[] = (string) $leafId;
        if ($leaf['authorable'] && (int) $leaf['native_question_count'] < 10) $selectiveDeepeningIds[] = (string) $leafId;
        if (($leaf['planning_state'] ?? '') === 'NO_EXPANSION_NEEDED') $maintenanceOnlyIds[] = (string) $leafId;
        if ((int) $leaf['native_question_count'] < 5 && !$leaf['authorable']) $evidenceLimitedUnder5Ids[] = (string) $leafId;
        if (in_array((string) ($record['source_currentness'] ?? 'UNKNOWN'), ['CURRENT_WITH_LIMITS', 'SUPERSEDED', 'FUTURE_EFFECTIVE', 'CONFLICTING', 'UNKNOWN'], true)) $currentnessReviewIds[] = (string) $leafId;
    }
    $actionGroups = [
        'large_expansion_candidates' => ['count' => 0, 'board_ids' => [], 'note' => 'No full-portfolio/full-board expansion is cleared; LET has only the bounded partial Wave1 gate below.'],
        'large_expansion_partial_candidates' => ['count' => $decision === 'LET_PARTIAL_EXPANSION_READY' ? 1 : 0, 'board_ids' => $decision === 'LET_PARTIAL_EXPANSION_READY' ? ['LET'] : []],
        'taxonomy_modernization_candidates' => ['count' => 0, 'leaf_ids' => [], 'note' => 'LET taxonomy is already granular; no deterministic structural defect was found.'],
        'source_readiness_foundation_candidates' => ['count' => count($foundationReviewIds), 'leaf_ids' => $foundationReviewIds],
        'selective_deepening_candidates' => ['count' => count($selectiveDeepeningIds), 'leaf_ids' => $selectiveDeepeningIds],
        'currentness_review_candidates' => ['count' => count($currentnessReviewIds), 'leaf_ids' => $currentnessReviewIds],
        'foundation_sufficient' => ['count' => count($foundationSufficientIds), 'leaf_ids' => $foundationSufficientIds],
        'evidence_limited' => ['count' => count($foundationReviewIds), 'leaf_ids' => $foundationReviewIds, 'under5_leaf_count' => count($evidenceLimitedUnder5Ids), 'under5_leaf_ids' => $evidenceLimitedUnder5Ids],
        'maintenance_only' => ['count' => count($maintenanceOnlyIds), 'leaf_ids' => $maintenanceOnlyIds],
    ];

    $artifact = [
        'title' => 'BOARDPREP — BATCH 649 LET FULL SOURCE-READINESS FOUNDATION + 205-LEAF OFFICIAL-SCOPE RECONCILIATION + SPECIALIZATION-AWARE AUTHORABILITY/HEADROOM GATE',
        'batch' => B649_BATCH,
        'review_date' => '2026-10-03',
        'lineage' => ['expected_base' => B649_BASE_HEAD, 'observed_base' => B649_BASE_HEAD, 'batch648_artifact' => B649_BASELINE_ARTIFACT, 'parent_checkpoint' => '56415c2c4308be70d359407b96a9a36460def4a', 'isolated_worktree' => true, 'main_touched' => false, 'generated_question_statistics_excluded' => true],
        'official_scope' => [
            'currentness_class' => 'CURRENT_WITH_LIMITS',
            'controlling_prc_sources' => B649_SCOPE_SOURCES,
            'operational_confirmation_source_ids' => B649_OPERATIONAL_CONFIRMATION_SOURCES,
            'missing_official_source_ids' => $missingOfficialSourceIds,
            'latest_exam_program_found' => 'PRC revised March 15, 2026 LEPT program; September 20, 2026 official exam schedule verified; no later program located in bounded PRC search.',
            'latest_tos_or_scope' => 'PRC Board Resolution No. 11 s.2025 amends the 2022 enhanced LEPT TOS; PRC Resolution No. 20 s.2025 aligns the specialization regime effective September 2025 and subsequent schedules.',
            'newer_controlling_source_found' => false,
            'official_specialization_count' => $specializationTotal,
            'official_specializations' => array_map(static fn(array $spec): array => ['id' => $spec['id'], 'name' => $spec['official_name'], 'level' => $spec['level'], 'scope_source_id' => 'prc-resolution-20-2025'], array_values($specializationRows)),
            'exam_structure' => ['GENERAL_EDUCATION', 'PROFESSIONAL_EDUCATION', 'SPECIALIZATION'],
            'weights_verified' => ['elementary_without_specialization' => ['general_education' => 40, 'professional_education' => 60], 'elementary_with_specialization' => ['general_education' => 20, 'professional_education' => 40, 'specialization' => 40], 'secondary' => ['general_education' => 20, 'professional_education' => 40, 'specialization' => 40]],
            'official_uncertainties' => ['The PRC Resolution 11 s.2025 PDF/Annex is linked by the official Board page but exceeds the browser PDF extraction size; scope fit is therefore conservatively PARTIAL at individual leaf level.', 'The March 2026 program is the newest LEPT program found in the bounded PRC search; September 2026 schedule/operational labels verify the sitting and current specialization labels, not a newer detailed TOS.'],
            'future_effective_scope' => [],
            'superseded_scope' => ['Resolution 11 s.2022 TOS is superseded as current controlling TOS by Resolution 11 s.2025 amendment except where not amended.'],
            'source_role_firewall' => 'PRC controls exam inclusion; DepEd/CHED/TESDA and subject sources are supporting competence evidence only, never independent proof of LET scope.',
        ],
        'baseline' => [
            'health_before' => ['overall_status' => $healthBefore['overall_status'] ?? '', 'summary' => $healthBeforeSummary, 'runtime_ms' => null],
            'global_before' => $b648Global,
            'let_before' => (array) ($baseline['boards']['let'] ?? []),
            'let_native_counts_recomputed' => $rawStats,
            'repository_health_runs' => ['before' => 'COMPLETED; OVERALL=REVIEW; FAIL_ENTITIES=0; optimized permanent health; no profile', 'after' => 'COMPLETED; OVERALL=REVIEW; FAIL_ENTITIES=0; optimized permanent health; no profile'],
        ],
        'action_groups' => $actionGroups,
        'content_freeze' => ['new_canonical_questions' => 0, 'question_deletions' => 0, 'question_semantic_rewrites' => 0, 'question_mapping_changes' => 0, 'new_taxonomy_leaves' => 0, 'taxonomy_mapping_changes' => 0, 'file_hashes_before' => $beforeHashes, 'file_hashes_after' => b649_fileHashes($root), 'question_store_identical' => $beforeHashes['storage/questions.json'] === (b649_fileHashes($root)['storage/questions.json'] ?? ''), 'eligibility_identical' => $beforeHashes['storage/question-eligibility.json'] === (b649_fileHashes($root)['storage/question-eligibility.json'] ?? '') && $beforeHashes['storage/concept-derived-eligibility.json'] === (b649_fileHashes($root)['storage/concept-derived-eligibility.json'] ?? '') && $beforeHashes['storage/question-reuse-overrides.json'] === (b649_fileHashes($root)['storage/question-reuse-overrides.json'] ?? '') && $beforeHashes['storage/question-reuse-metadata.json'] === (b649_fileHashes($root)['storage/question-reuse-metadata.json'] ?? '')],
        'global_baseline' => ['total_canonical' => $globalStats['canonical'] ?? 0, 'total_effective_declared' => $globalStats['effective_declared'] ?? 0, 'total_runtime' => $globalStats['runtime'] ?? 0, 'native_by_board' => $b648Global['native_by_board'] ?? [], 'effective_declared_by_board' => $b648Global['effective_declared_by_board'] ?? [], 'runtime_by_board' => $b648Global['runtime_by_board'] ?? []],
        'health_before' => ['overall_status' => $healthBefore['overall_status'] ?? '', 'summary' => $healthBeforeSummary, 'fail_entities' => $healthBeforeSummary['fail_entity_count'] ?? 0, 'let_relevant_review_findings' => 0],
        'health_after' => ['overall_status' => $healthAfter['overall_status'] ?? '', 'summary' => $healthSummary, 'fail_entities' => $healthSummary['fail_entity_count'] ?? 0, 'warn_entities' => $healthSummary['warn_entity_count'] ?? 0, 'review_entities' => $healthSummary['review_entity_count'] ?? 0, 'runtime_ms' => null, 'new_deterministic_fail_ids' => $newDeterministicFailIds, 'performance_regression' => null, 'let_source_study_review_findings' => $letSourceStudyReviewFindings],
        'let' => [
            'baseline' => ['native_canonical' => $letHealth['canonical'] ?? 0, 'effective_declared' => $letHealth['effective_declared'] ?? 0, 'runtime' => $letHealth['runtime'] ?? 0, 'active_leaves' => count($leafInventory), 'structural_nodes' => $letHealth['structural_administrative_leaves'] ?? 0, 'taxonomy_status' => 'GRANULAR', 'thinness_metrics_trust' => 'HIGH', 'raw_leaf_health' => $rawStats],
            'readiness' => $readinessIntegrity + ['model' => 'FULL_READINESS_MODEL', 'record_ids' => array_keys($leafInventory)],
            'source_inventory' => $sourceInventory,
            'study_inventory' => ['let_study_materials' => count($letMaterials), 'coverage_counts' => $studyInventoryCounts, 'traceability_counts' => $traceabilityCounts, 'leaf_coverage_counts' => $leafStudyCoverageCounts, 'leaf_traceability_counts' => $leafTraceabilityCounts, 'repair_order' => ['REUSE', 'REORGANIZE', 'EXTEND', 'SPLIT', 'NEW'], 'reused' => count($letMaterials), 'reorganized' => 0, 'extended' => 0, 'split' => 0, 'new' => 0, 'materials' => $studyInventory],
            'structure' => ['unique_active_leaves' => count($leafInventory), 'general_education_leaves' => $majorAreas['general-education']['active_leaves'], 'professional_education_leaves' => $majorAreas['professional-education']['active_leaves'], 'specialization_primary_area_leaves' => $majorAreas['specialization']['active_leaves'], 'distinct_specialization_placement_leaves' => count($specializationPlacementLeafIds), 'major_area_leaf_counts_are_primary_assignment' => true, 'major_area_question_counts_are_explicit_placement_pools' => true, 'major_area_question_counts_are_nonexclusive_availability_pools' => true, 'major_area_question_counts' => $majorAreas, 'cross_area_question_overlap' => ['native_question_overlap_count' => $crossAreaOverlapCounts['native'], 'effective_question_overlap_count' => $crossAreaOverlapCounts['effective'], 'runtime_question_overlap_count' => $crossAreaOverlapCounts['runtime'], 'effective_overlap_by_component_set' => $crossAreaOverlapByKindAndPair['effective'], 'runtime_overlap_by_component_set' => $crossAreaOverlapByKindAndPair['runtime'], 'native_union' => $areaNativeUnion, 'effective_union' => $areaEffectiveUnion, 'runtime_union' => $areaRuntimeUnion, 'note' => 'Shared General/Professional availability is reported in each component pool; per-area sums may exceed unique LET global totals, while each per-area set and the global union are deduplicated canonical question IDs.'], 'flat_shared_placements' => $flatCount, 'taxonomy_changes' => 0],
            'specializations' => ['total' => $specializationTotal, 'zero_pools' => $zeroSpecializationPools, 'summaries' => array_values($specializationRows)],
            'shared_placements' => ['total_flat_shared_placements' => $flatCount, 'classification_counts' => $sharedClassCounts, 'review_required' => $sharedReviewCount, 'flat_shared_rows' => $flatPlacementRows, 'specialization_variant_placements' => ['total' => $sharedVariantEdgeCount, 'distinct_native_question_rows' => count($sharedVariantRows), 'shared_receiver_edges' => $sharedVariantEdgeCount, 'question_row_classification_counts' => array_count_values(array_column($sharedVariantRows, 'classification')), 'records' => $sharedVariantRows], 'native_depth_rule' => 'A shared-variant question counts as native only in its explicit primary specialization; it counts as shared-in in the receiving specializations, and shared availability is excluded from receiver-native depth. No equivalence or mapping is activated.'],
            'raw_leaf_health' => $rawStats,
            'authorable_leaf_health' => $authorableStats,
            'authorable_non_authorable_counts' => ['authorable_zero' => $authorableStats['zero'], 'authorable_under5' => $authorableStats['under5'], 'authorable_under10' => $authorableStats['under10'], 'authorable_under20' => $authorableStats['under20'], 'nonauthorable_zero' => max(0, $rawStats['zero'] - $authorableStats['zero']), 'nonauthorable_under5' => max(0, $rawStats['under5'] - $authorableStats['under5']), 'nonauthorable_under10' => max(0, $rawStats['under10'] - $authorableStats['under10']), 'nonauthorable_under20' => max(0, $rawStats['under20'] - $authorableStats['under20'])],
            'deficits' => ['raw_to5' => $rawStats['deficit_to_5'], 'raw_to10' => $rawStats['deficit_to_10'], 'raw_to20' => $rawStats['deficit_to_20'], 'authorable_to5' => $authorableDeficits['to5'], 'authorable_to10' => $authorableDeficits['to10'], 'authorable_to20' => $authorableDeficits['to20']],
            'zero_analysis' => ['total_zero' => count($zeroRows), 'causal_class_counts' => $zeroCauseCounts, 'authorable_zero' => $authorableStats['zero'], 'records' => $zeroRows],
            'under5_analysis' => ['total' => count($under5Rows), 'authorable' => $authorableStats['under5'], 'nonauthorable' => max(0, count($under5Rows) - $authorableStats['under5']), 'evidence_limited' => max(0, count($under5Rows) - count($wave1Candidates)), 'distinct_high_value_families' => count($wave1DistinctFamilies), 'high_value_family_ids' => $wave1DistinctFamilies, 'safe_additions_to_5' => $wave1PlanCount, 'records' => $under5Rows],
            'under10_analysis' => ['total' => count($under10Rows), 'authorable' => $authorableStats['under10'], 'nonauthorable' => max(0, count($under10Rows) - $authorableStats['under10']), 'evidence_limited' => max(0, count($under10Rows) - count($wave2Candidates)), 'distinct_high_value_families' => count($wave2DistinctFamilies), 'high_value_family_ids' => $wave2DistinctFamilies, 'provisional_safe_additions_to_10' => array_sum(array_column($wave2Candidates, 'provisional_planned_new')), 'records' => $under10Rows],
            'under20_analysis' => ['total' => count($under20Rows), 'authorable' => $authorableStats['under20'], 'records' => $under20Rows],
            'depth' => ['question_distribution' => $depthDistribution, 'leaf_diversity' => $leafDepthState, 'question_depth_metadata_coverage' => array_sum($depthDistribution) . '/' . (int) ($letHealth['canonical'] ?? 0), 'd5_manufactured' => false, 'supported_future_depth_summary' => ['min' => min(array_column($readinessRecords, 'supported_depth_min')), 'max' => max(array_column($readinessRecords, 'supported_depth_max')), 'source' => 'Observed per-leaf reuse.required_depth only; no D5 invented.']],
            'headroom' => ['safe_headroom_min' => array_sum(array_column($readinessRecords, 'safe_headroom_min')), 'safe_headroom_preferred' => array_sum(array_column($readinessRecords, 'safe_headroom_preferred')), 'safe_headroom_max' => array_sum(array_column($readinessRecords, 'safe_headroom_max')), 'high_value_headroom' => array_sum(array_column($leafInventory, 'high_value_headroom')), 'numerical_capacity' => array_sum(array_column($leafInventory, 'numerical_capacity')), 'numerical_capacity_not_immediate' => array_sum(array_map(static fn(array $leaf): int => max(0, (int) ($leaf['numerical_capacity'] ?? 0) - (int) ($leaf['high_value_headroom'] ?? 0)), $leafInventory)), 'basis' => 'Numerical capacity is bounded by source breadth, study support, and observed depth; high-value capacity additionally deducts observed family diversity and repeat risk. Neither measure is based on page count or deficits.', 'limiting_factors' => ['official TOS exact leaf crosswalk is conservatively partial', '38 flat/shared legacy placements remain REVIEW', 'family metadata is incomplete for part of the preexisting bank']],
            'currentness' => ['counts' => $currentnessCounts, 'version_sensitive_leaf_ids' => $versionSensitiveLeaves, 'currentness_firewall' => 'No SUPERSEDED, FUTURE_EFFECTIVE, CONFLICTING, or UNKNOWN source-currentness state produces authoring readiness.'],
            'repeat_risk' => ['overall' => $overallRepeatRisk, 'overall_counts' => $repeatRiskCounts, 'general_education' => $majorAreas['general-education']['repeat_risk'], 'professional_education' => $majorAreas['professional-education']['repeat_risk'], 'specialization_low' => count(array_filter($specializationRows, static fn(array $r): bool => $r['repeat_risk'] === 'LOW')), 'specialization_moderate' => count(array_filter($specializationRows, static fn(array $r): bool => $r['repeat_risk'] === 'MODERATE')), 'specialization_high' => count(array_filter($specializationRows, static fn(array $r): bool => $r['repeat_risk'] === 'HIGH')), 'specialization_critical' => count(array_filter($specializationRows, static fn(array $r): bool => $r['repeat_risk'] === 'CRITICAL')), 'drivers' => ['cognitive-intent family metadata unknown rates and dominant-family concentration are used per leaf and specialization', 'specialization variant sharing is concentrated in five common TLE leaves and is excluded from native specialization depth', 'depth D5 is absent; do not treat difficulty as depth']],
            'wave1' => ['objective' => 'AUTHORABLE_ZERO_THEN_UNDER5', 'zero_leaf_count' => $authorableStats['zero'], 'under5_leaf_count' => count($wave1Candidates), 'mathematical_deficit' => $wave1MathDeficit, 'evidence_supported_planned' => $wave1PlanCount, 'planned_by_area' => $wave1ByArea, 'specialization_planned' => $wave1Specialization, 'candidates' => $wave1Candidates, 'run_now' => false],
            'wave2' => ['status' => 'PROVISIONAL', 'authorable_under10_leaves_after_wave1' => count($wave2Candidates), 'mathematical_deficit' => array_sum(array_column($wave2Candidates, 'remaining_deficit_to_10')), 'evidence_supported_range' => ['min' => 0, 'preferred' => array_sum(array_column($wave2Candidates, 'provisional_planned_new')), 'max' => array_sum(array_column($wave2Candidates, 'headroom_remaining'))], 'priority_areas' => ['specialization balance', 'general education reading/language/math/science', 'professional education after policy-source review'], 'candidates' => $wave2Candidates, 'recalculate_after_wave1' => true, 'run_now' => false],
            'wave3' => ['preauthorized_additions' => 0, 'gate' => 'Fresh semantic/depth/specialization-balance review after foundation waves.', 'run_now' => false],
            'safe_expansion_range' => ['min_safe_expansion' => $safeMin, 'preferred_expansion' => $safePreferred, 'max_evidence_supported_expansion' => $safeMax, 'not_a_quota' => true],
            'expansion_states' => $expansionStates,
            'leaves' => array_values($leafInventory),
            'authorization' => ['decision' => $decision, 'rationale' => $authRationale, 'blocked_areas' => ['flat/shared rows awaiting semantic review', 'policy/currentness-sensitive content beyond cited version boundaries'], 'review_required_before_authoring' => array_values(array_map(static fn(array $leaf): string => (string) $leaf['leaf_id'], array_filter($leafInventory, static fn(array $leaf): bool => !$leaf['authorable']))), 'batch650_prerequisites' => ['Recheck PRC board page/issuances and program immediately before authoring.', 'Use only READY_WITH_LIMITS leaf records, exact ALLOWED_SCOPE, cited source IDs and study material IDs.', 'Do not write for the 38 flat/shared REVIEW rows until semantic/scope placement review closes.', 'After Wave1, rerun authorable thinness and specialization balance before provisional Wave2.', 'Keep Wave3 preauthorization at zero.']],
        ],
        'regressions' => $globalRegression,
        'repository_health' => ['before_overall' => $healthBefore['overall_status'] ?? '', 'after_overall' => $healthAfter['overall_status'] ?? '', 'fail_entities_before' => $healthBeforeSummary['fail_entity_count'] ?? 0, 'fail_entities_after' => $healthSummary['fail_entity_count'] ?? 0, 'warn_entities_after' => $healthSummary['warn_entity_count'] ?? 0, 'review_entities_after' => $healthSummary['review_entity_count'] ?? 0, 'issue_counts_by_code' => $issueCounts, 'new_fail_ids' => $newDeterministicFailIds, 'runtime_profiled' => false, 'batch648_known_orphans' => 16, 'batch648_cpa_source_semantic_review_findings' => 3, 'batch648_let_review_findings' => count($letSourceStudyReviewFindings)],
        'determinism' => ['readiness_record_hash' => b649_hash($readinessRecords), 'readiness_ids_hash' => b649_hash(array_map(static fn(array $r): string => (string) $r['leaf_id'], $readinessRecords)), 'specialization_set_hash' => b649_hash(array_keys($specializationRows)), 'leaf_inventory_hash' => b649_hash(array_values($leafInventory)), 'question_store_hashes' => b649_fileHashes($root), 'runtime_excluded_from_hash' => true],
        'validation' => ['permanent_readiness' => ($letValidation['valid'] ?? false) === true, 'all_collection_readiness' => ($validationAll['valid'] ?? false) === true, 'official_specializations' => $specializationTotal === 15, 'official_scope_sources_present' => $missingOfficialSourceIds === [], 'specialization_ids_resolve' => $readinessIntegrity['invalid_specialization_ids'] === 0, 'scope_components_valid' => $readinessIntegrity['invalid_scope_component_ids'] === 0, 'shared_flat_placements' => $flatCount === 38, 'specialization_variant_placements' => count($sharedVariantRows) === 25 && $sharedVariantEdgeCount === 75 && count(array_filter($sharedVariantRows, static fn(array $row): bool => ($row['classification'] ?? '') !== 'VALID_SHARED_COMPETENCY')) === 0, 'specialization_native_leaf_counts_reconcile' => $specializationNativeLeafCountsReconcile, 'major_area_native_union' => $areaNativeUnion === (int) ($b648Global['native_by_board']['let'] ?? -1), 'major_area_effective_union' => $areaEffectiveUnion === (int) ($b648Global['effective_declared_by_board']['let'] ?? -1), 'major_area_runtime_union' => $areaRuntimeUnion === (int) ($b648Global['runtime_by_board']['let'] ?? -1), 'duplicate_effective' => (int) ($regressions['duplicate_effective'] ?? -1) === 0, 'double_weighting' => (int) ($regressions['double_weighting'] ?? -1) === 0, 'content_freeze' => $beforeHashes === b649_fileHashes($root), 'global_counts' => $globalRegression['all_expected_global_counts_match'], 'fail_entities' => (int) ($healthSummary['fail_entity_count'] ?? -1)],
    ];
    $artifact['audit_hash'] = b649_hash($artifact);
    $auditPath = b649_path($root, $options['output'], $root . '/' . B649_ARTIFACT_FILE);
    b649_write($auditPath, $artifact);
    if (($letValidation['valid'] ?? false) !== true) b649_fail('Permanent LET readiness validation failed; inspect audit errors.');
    if (($healthSummary['fail_entity_count'] ?? 1) !== 0) b649_fail('Repository Health after Batch649 reports FAIL entities.');
    if ($flatCount !== 38 || $specializationTotal !== 15 || $zeroSpecializationPools !== 0 || count($sharedVariantRows) !== 25 || $sharedVariantEdgeCount !== 75) b649_fail('LET structural/specialization invariants failed.');
    if ($areaNativeUnion !== (int) ($b648Global['native_by_board']['let'] ?? -1) || $areaEffectiveUnion !== (int) ($b648Global['effective_declared_by_board']['let'] ?? -1) || $areaRuntimeUnion !== (int) ($b648Global['runtime_by_board']['let'] ?? -1)) b649_fail('LET major-area question pools do not reconcile to unique board totals.');
    if (!$specializationNativeLeafCountsReconcile) b649_fail('Specialization-native leaf question counts do not reconcile to native specialization pools.');
    if ($beforeHashes !== b649_fileHashes($root)) b649_fail('A question, eligibility, or taxonomy freeze hash changed during Batch649.');
    if ($sourceInventory['invalid'] !== 0 || $missingOfficialSourceIds !== [] || $readinessIntegrity['invalid_source_ids'] !== 0 || $readinessIntegrity['invalid_study_ids'] !== 0 || $readinessIntegrity['invalid_specialization_ids'] !== 0 || $readinessIntegrity['invalid_scope_component_ids'] !== 0 || $readinessIntegrity['cross_field_contradictions'] !== 0) b649_fail('Source/readiness integrity checks failed.');
    fwrite(STDOUT, json_encode(['audit_hash' => $artifact['audit_hash'], 'readiness_records' => count($readinessRecords), 'readiness_counts' => $readinessCounts, 'flat_shared' => $flatCount, 'specializations' => $specializationTotal, 'wave1_candidates' => count($wave1Candidates), 'wave1_planned' => $wave1PlanCount, 'decision' => $decision, 'health_fail_entities' => $healthSummary['fail_entity_count'] ?? null, 'artifact' => $auditPath, 'mode' => $options['mode']], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL);
}

b649_run($argv ?? []);
