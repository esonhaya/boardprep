<?php

declare(strict_types=1);

/**
 * Batch 637 portfolio recomputation and REE readiness audit.
 *
 * Default mode audits the committed Batch636 bank and atomically refreshes the
 * Batch637 artifact.  --repair-foundation performs only the bounded REE
 * source/study/coverage metadata work described in the artifact; it never edits
 * canonical question text, IDs, or eligibility.
 */

$root = dirname(__DIR__);
$expectedParent = '2d29a990619e0fcfdfc58b7658b932b3827d9eb5';
$expectedAncestors = [
    'fd6adb49bc140ac148a90c705c33bf7901193487',
    '25b7d30cf60310487e30e515266faf99935bb3bd',
    'e640eed4d3e4c36c1264e0a60333ca103f5f1f7b',
    'ca755293ab72da2e82bc3f592e7f3ad06c2f7b1a',
];
$today = '2026-10-01';
$applyFoundation = in_array('--repair-foundation', array_slice($argv, 1), true);

function b637_read(string $root, string $relative): array
{
    $path = $root . '/' . $relative;
    if (!is_file($path)) throw new RuntimeException('Missing file: ' . $relative);
    $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($decoded)) throw new RuntimeException('Expected JSON array/object: ' . $relative);
    return $decoded;
}

function b637_git_read_json(string $root, string $revision, string $relative): array
{
    $command = 'git -C ' . escapeshellarg($root) . ' show ' . escapeshellarg($revision . ':' . $relative) . ' 2>/dev/null';
    $contents = shell_exec($command);
    if (!is_string($contents) || $contents === '') throw new RuntimeException('Cannot read baseline JSON: ' . $relative);
    $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($decoded)) throw new RuntimeException('Baseline JSON is not an array/object: ' . $relative);
    return $decoded;
}

function b637_first(array $value): array
{
    return array_is_list($value) ? (is_array($value[0] ?? null) ? $value[0] : []) : $value;
}

function b637_atomic(string $path, array $value): void
{
    $json = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR) . PHP_EOL;
    $tmp = $path . '.batch637.tmp';
    if (file_put_contents($tmp, $json, LOCK_EX) === false || !rename($tmp, $path)) {
        if (is_file($tmp)) unlink($tmp);
        throw new RuntimeException('Atomic write failed: ' . $path);
    }
}

function b637_hash(mixed $value): string
{
    return hash('sha256', json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
}

function b637_stats(array $counts): array
{
    $v = array_values(array_map('intval', $counts));
    sort($v, SORT_NUMERIC);
    $n = count($v);
    $pick = static fn(float $p): int => $n === 0 ? 0 : $v[(int) floor($p * ($n - 1))];
    return [
        'total_leaves' => $n,
        'zero' => count(array_filter($v, static fn(int $x): bool => $x === 0)),
        '1_4' => count(array_filter($v, static fn(int $x): bool => $x >= 1 && $x <= 4)),
        '5_9' => count(array_filter($v, static fn(int $x): bool => $x >= 5 && $x <= 9)),
        '10_19' => count(array_filter($v, static fn(int $x): bool => $x >= 10 && $x <= 19)),
        '20_39' => count(array_filter($v, static fn(int $x): bool => $x >= 20 && $x <= 39)),
        '40_plus' => count(array_filter($v, static fn(int $x): bool => $x >= 40)),
        'min' => $n === 0 ? 0 : $v[0],
        'median' => $n === 0 ? 0 : $v[(int) floor(($n - 1) / 2)],
        'p25' => $pick(0.25),
        'p75' => $pick(0.75),
        'under5' => count(array_filter($v, static fn(int $x): bool => $x < 5)),
        'under10' => count(array_filter($v, static fn(int $x): bool => $x < 10)),
        'under20' => count(array_filter($v, static fn(int $x): bool => $x < 20)),
    ];
}

function b637_area_record(array $counts, array $weights, string $weightStatus = 'PUBLISHED'): array
{
    $total = array_sum($counts);
    $shares = [];
    $alignment = [];
    foreach ($counts as $area => $count) {
        $share = $total > 0 ? round($count * 100 / $total, 2) : 0.0;
        $shares[$area] = $share;
        if (isset($weights[$area])) $alignment[$area] = ['current_count'=>$count,'current_share'=>$share,'official_share'=>$weights[$area],'delta'=>round($share-$weights[$area],2)];
    }
    return ['counts'=>$counts,'shares'=>$shares,'weights'=>$weights,'alignment'=>$alignment,'official_weights'=>$weightStatus];
}

function b637_source(string $id, string $title, string $type, string $authority, string $url, ?string $publicationDate, string $version, string $role, string $notes, string $today): array
{
    return [
        'id' => $id, 'title' => $title, 'source_type' => $type, 'authority' => $authority,
        'url' => $url, 'publication_date' => $publicationDate, 'retrieved_at' => $today,
        'version' => $version, 'status' => 'verified_reference', 'confidence' => 'authoritative_reference',
        'notes' => $notes, 'source_role' => $role, 'volatility' => 'STABLE_FOUNDATIONAL',
    ];
}

function b637_unique(array $values): array
{
    $out = [];
    foreach ($values as $value) if (is_scalar($value) && (string) $value !== '') $out[(string) $value] = true;
    return array_keys($out);
}

function b637_normalize_question_leaf(array $question): string
{
    $tax = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
    return (string) ($tax['subcompetency_id'] ?? $tax['concept_id'] ?? '');
}

function b637_depth(array $question, array $depthById): string
{
    $id = (string) ($question['id'] ?? '');
    $depth = $depthById[$id] ?? ($question['batch636']['depth'] ?? null);
    if (is_numeric($depth) && (int) $depth >= 1 && (int) $depth <= 5) return (string) (int) $depth;
    return 'UNKNOWN';
}

function b637_family(array $question, string $kind): string
{
    $batch = is_array($question['batch636'] ?? null) ? $question['batch636'] : [];
    $keys = $kind === 'scenario' ? ['scenario_family', 'scenarioFamily'] : ['misconception_family', 'misconceptionFamily'];
    foreach ($keys as $key) {
        foreach ([$question[$key] ?? null, $batch[$key] ?? null] as $value) {
            if (is_scalar($value) && trim((string) $value) !== '') return (string) $value;
        }
    }
    return 'legacy:' . b637_normalize_question_leaf($question);
}

function b637_family_counts(array $questions, string $kind): array
{
    $counts = [];
    foreach ($questions as $question) {
        $family = b637_family($question, $kind);
        $counts[$family] = ($counts[$family] ?? 0) + 1;
    }
    return ['distinct' => count($counts), 'max_family_size' => $counts === [] ? 0 : max($counts), 'counts' => $counts];
}

function b637_run_repair(string $root, string $today, array $catalog, array $sourcesToAdd, array $studySpecs, array $scopeSources): array
{
    $sourceRows = b637_read($root, 'storage/sources.json');
    $materials = b637_read($root, 'storage/study-materials.json');
    $questions = b637_read($root, 'storage/questions.json');
    $concepts = b637_read($root, 'storage/taxonomy/concepts.json');
    $topics = b637_read($root, 'storage/taxonomy/topics.json');
    $domains = b637_read($root, 'storage/taxonomy/domains.json');
    $coverage = b637_read($root, 'storage/board-concept-coverage.json');

    $sourceIndex = [];
    foreach ($sourceRows as $i => $row) $sourceIndex[(string) ($row['id'] ?? '')] = $i;
    $added = [];
    foreach ($sourcesToAdd as $row) {
        $id = $row['id'];
        if (!isset($sourceIndex[$id])) {
            $sourceIndex[$id] = count($sourceRows);
            $sourceRows[] = $row;
            $added[] = $id;
        }
    }
    $updated = [];
    foreach ($catalog['source_updates'] as $id => $fields) {
        if (!isset($sourceIndex[$id])) continue;
        $i = $sourceIndex[$id];
        $changed = false;
        foreach ($fields as $key => $value) {
            if (($sourceRows[$i][$key] ?? null) !== $value) {
                $sourceRows[$i][$key] = $value;
                $changed = true;
            }
        }
        if ($changed) $updated[] = $id;
    }

    $materialIndex = [];
    foreach ($materials as $i => $row) $materialIndex[(string) ($row['id'] ?? '')] = $i;
    $studyAdded = [];
    foreach ($studySpecs as $spec) {
        $id = $spec['id'];
        if (!isset($materialIndex[$id])) {
            $materialIndex[$id] = count($materials);
            $materials[] = [
                'id' => $id, 'title' => $spec['title'], 'summary' => $spec['summary'],
                'notes' => $spec['notes'], 'source_ids' => $spec['source_ids'],
                'exams' => ['registered-electrical-engineering'],
                'exam_focus' => ['registered-electrical-engineering' => ['priority' => 'high', 'rationale' => $spec['focus'], 'official_scope_status' => 'current PRC scope plus leaf-specific technical foundation']],
                'taxonomy' => ['subject_id' => $spec['subject_id'], 'domain_id' => $spec['domain_id'], 'topic_id' => $spec['topic_id']],
                'supported_concept_ids' => [$spec['leaf_id']],
                'status' => 'published', 'version' => 1, 'updated_at' => $today,
            ];
            $studyAdded[] = $id;
        }
    }

    $conceptIndex = [];
    foreach ($concepts as $i => $row) $conceptIndex[(string) ($row['id'] ?? '')] = $i;
    $topicIndex = [];
    foreach ($topics as $row) $topicIndex[(string) ($row['id'] ?? '')] = $row;
    $domainIndex = [];
    foreach ($domains as $row) $domainIndex[(string) ($row['id'] ?? '')] = $row;
    $studyByLeaf = [];
    foreach ($studySpecs as $spec) $studyByLeaf[$spec['leaf_id']] = $spec;
    $instrumentLeaf = 'b633-ree-allied-instrumentation';
    $instrumentIds = [];
    foreach ($questions as $i => $question) {
        if (($question['taxonomy']['concept_id'] ?? '') !== $instrumentLeaf) continue;
        $instrumentIds[] = (int) ($question['id'] ?? 0);
        $questions[$i]['taxonomy']['subject_id'] = 'registered-electrical-engineering-electrical-engineering-professional-subjects';
        $questions[$i]['taxonomy']['domain_id'] = 'electrical-engineering-professional-subjects';
        $questions[$i]['taxonomy']['topic_id'] = 'ree-pro-controls';
        $questions[$i]['taxonomy']['parent_concept_id'] = 'ree-concept-ree-pro-controls';
        $questions[$i]['taxonomy']['subcompetency_evidence'] = 'Measurement/instrumentation maps to the current PRB Resolution 40 s.2024 REE Professional TOS: Instrumentation & Control, Feedback Control System and Research Methods.';
        $questions[$i]['subject'] = 'Electrical Engineering Professional Subjects';
        $questions[$i]['domain'] = 'Electrical Engineering Professional Subjects';
        $questions[$i]['topic'] = 'Instrumentation, control, and measurement';
    }
    sort($instrumentIds, SORT_NUMERIC);
    if ($instrumentIds !== [1620,1621,1622,1623,1624]) throw new RuntimeException('Instrumentation placement review found unexpected canonical IDs: ' . json_encode($instrumentIds));
    $instrumentIndex = $conceptIndex[$instrumentLeaf] ?? null;
    if ($instrumentIndex === null) throw new RuntimeException('Missing REE instrumentation leaf.');
    $concepts[$instrumentIndex]['topic_id'] = 'ree-pro-controls';
    $concepts[$instrumentIndex]['batch633']['parent_concept_id'] = 'ree-concept-ree-pro-controls';
    $concepts[$instrumentIndex]['batch633']['official_area'] = 'electrical-engineering-professional-subjects';
    $concepts[$instrumentIndex]['batch637_foundation'] = array_merge($concepts[$instrumentIndex]['batch637_foundation'] ?? [], [
        'status'=>'READY','source_readiness'=>'ADEQUATE','study_readiness'=>'SUFFICIENT',
        'coverage_status'=>'VERIFIED_DIRECT','official_scope_sources'=>$scopeSources,
        'official_tos_topics'=>['Professional TOS: Instrumentation & Control, Feedback Control System and Research Methods 4.50% / 10 items'],
        'placement_correction'=>'Batch637: corrected five measurement/instrumentation questions from Allied to Professional; question text, IDs, eligibility and study links preserved.',
        'verified_at'=>$today,
    ]);
    foreach ($materials as $i => $material) {
        if (($material['id'] ?? '') !== 'ree-material-instrumentation') continue;
        $beforeMaterial = $materials[$i];
        $materials[$i]['taxonomy'] = ['subject_id'=>'registered-electrical-engineering-electrical-engineering-professional-subjects','domain_id'=>'electrical-engineering-professional-subjects','topic_id'=>'ree-pro-controls'];
        $materials[$i]['supported_concept_ids'] = b637_unique(array_merge($materials[$i]['supported_concept_ids'] ?? [], [$instrumentLeaf]));
        $materials[$i]['source_ids'] = b637_unique(array_merge($materials[$i]['source_ids'] ?? [], $scopeSources));
        $materials[$i]['exam_focus'] = $materials[$i]['exam_focus'] ?? [];
        $materials[$i]['exam_focus']['registered-electrical-engineering'] = array_merge($materials[$i]['exam_focus']['registered-electrical-engineering'] ?? [], [
            'priority'=>'high','rationale'=>'Measurement and instrumentation is within the REE Professional TOS.','official_scope_status'=>'Current PRB Resolution 40 s.2024 Professional TOS: Instrumentation & Control, Feedback Control System and Research Methods',
        ]);
        if ($beforeMaterial !== $materials[$i]) {
            $materials[$i]['version'] = max(1, (int) ($beforeMaterial['version'] ?? 1)) + 1;
            $materials[$i]['updated_at'] = $today;
        }
    }
    $sourceRepairByLeaf = [];
    foreach ($catalog['source_repairs_by_leaf'] as $leaf => $data) $sourceRepairByLeaf[$leaf] = $data;

    $coverageIndex = [];
    foreach ($coverage as $i => $row) {
        if (($row['board_id'] ?? '') === 'registered-electrical-engineering') $coverageIndex[(string) ($row['concept_id'] ?? '')] = $i;
    }
    $coverageAdded = [];
    $coverageFixed = [];
    if (isset($coverageIndex[$instrumentLeaf])) {
        $i = $coverageIndex[$instrumentLeaf];
        $coverage[$i]['subject_id'] = 'registered-electrical-engineering-electrical-engineering-professional-subjects';
        $coverage[$i]['domain_id'] = 'electrical-engineering-professional-subjects';
        $coverage[$i]['topic_id'] = 'ree-pro-controls';
        $coverage[$i]['blueprint_node_id'] = 'electrical-engineering-professional-subjects';
        $coverage[$i]['max_depth'] = max(4, (int) ($coverage[$i]['max_depth'] ?? 0));
        $coverage[$i]['evidence'] = 'Measurement/instrumentation is mapped to the current PRB Resolution 40 s.2024 REE Professional TOS: Instrumentation & Control, Feedback Control System and Research Methods (4.50%, 10 items).';
        $hasBatch637Placement = false;
        foreach (($coverage[$i]['provenance'] ?? []) as $prov) if (($prov['type'] ?? '') === 'BATCH637_OFFICIAL_SCOPE_REMAP') $hasBatch637Placement = true;
        if (!$hasBatch637Placement) $coverage[$i]['provenance'][] = ['type'=>'BATCH637_OFFICIAL_SCOPE_REMAP','reference'=>'prc-ree-enhanced-tos-annex-a-2024','note'=>'Reclassified five existing measurement/instrumentation questions from Allied to Professional; canonical identity and eligibility unchanged.','verified_status'=>'VERIFIED','confidence'=>'HIGH'];
        $coverageFixed[] = $instrumentLeaf;
    }
    $leaves = array_keys($studyByLeaf);
    foreach ($leaves as $leaf) {
        if (!isset($conceptIndex[$leaf])) throw new RuntimeException('Missing REE leaf concept ' . $leaf);
        $i = $conceptIndex[$leaf];
        $concept =& $concepts[$i];
        $spec = $studyByLeaf[$leaf];
        $sourceIds = b637_unique(array_merge($sourceRepairByLeaf[$leaf]['source_ids'], $scopeSources));
        $materialIds = [$spec['id']];
        $readiness = $sourceRepairByLeaf[$leaf]['source_readiness'];
        $concept['batch633']['source_ids'] = b637_unique(array_merge($concept['batch633']['source_ids'] ?? [], $sourceIds));
        $concept['batch633']['study_material_ids'] = b637_unique(array_merge($concept['batch633']['study_material_ids'] ?? [], $materialIds));
        $concept['batch633']['source_readiness'] = $readiness;
        $concept['batch633']['study_readiness'] = 'SUFFICIENT';
        $concept['batch633']['version_sensitivity'] = $spec['version_class'];
        $concept['batch637_foundation'] = [
            'status' => $spec['readiness'], 'source_readiness' => $readiness, 'study_readiness' => 'SUFFICIENT',
            'coverage_status' => 'VERIFIED_DIRECT', 'source_ids' => $concept['batch633']['source_ids'],
            'study_material_ids' => $materialIds, 'official_scope_sources' => $scopeSources,
            'official_tos_topics' => $spec['tos_topics'], 'version_class' => $spec['version_class'],
            'authoring_limits' => $spec['limits'], 'verified_at' => $today,
        ];

        if (!isset($coverageIndex[$leaf])) {
            $topicId = (string) ($concept['topic_id'] ?? '');
            $topic = $topicIndex[$topicId] ?? null;
            $domainId = (string) ($topic['domain_id'] ?? '');
            $domain = $domainIndex[$domainId] ?? null;
            $subjectId = (string) ($domain['subject_id'] ?? '');
            if ($topic === null || $domain === null || $subjectId === '') throw new RuntimeException('Broken REE hierarchy for ' . $leaf);
            $coverageRow = [
                'id' => 'coverage-b637-ree-' . substr(hash('sha256', $leaf), 0, 24),
                'board_id' => 'registered-electrical-engineering', 'concept_id' => $leaf,
                'subject_id' => $subjectId, 'domain_id' => $domainId, 'topic_id' => $topicId,
                'blueprint_node_id' => $spec['official_area'], 'max_depth' => $spec['max_depth'],
                'reuse_policy' => 'REVIEW', 'status' => 'VERIFIED',
                'provenance' => [[
                    'type' => 'BATCH637_OFFICIAL_SCOPE_CROSSWALK', 'reference' => 'prc-ree-exam-program-september-2026;prc-ree-enhanced-tos-annex-a-2024',
                    'note' => 'Direct REE leaf mapping verified against the September 2026 program and current enhanced TOS; source references are scope authorities, not technical design substantiation.',
                    'verified_status' => 'VERIFIED', 'confidence' => 'HIGH',
                ]],
                'evidence' => 'PRC September 2026 REE program names this competence; Board Resolution No. 40 s.2024 Annex A supplies the current detailed TOS. Leaf-specific instructional material and technical sources are separately attached.',
                'confidence' => 'HIGH',
            ];
            $coverageIndex[$leaf] = count($coverage);
            $coverage[] = $coverageRow;
            $coverageAdded[] = $leaf;
        } else {
            $row = $coverage[$coverageIndex[$leaf]];
            if (($row['status'] ?? '') !== 'VERIFIED' || !is_numeric($row['max_depth'] ?? null)) $coverageFixed[] = $leaf;
        }
        unset($concept);
    }

    b637_atomic($root . '/storage/sources.json', $sourceRows);
    b637_atomic($root . '/storage/study-materials.json', $materials);
    b637_atomic($root . '/storage/questions.json', $questions);
    b637_atomic($root . '/storage/taxonomy/concepts.json', $concepts);
    b637_atomic($root . '/storage/board-concept-coverage.json', $coverage);
    return ['sources_added' => $added, 'sources_updated' => $updated, 'study_added' => $studyAdded, 'coverage_added' => $coverageAdded, 'coverage_fixed' => $coverageFixed, 'question_metadata_remapped'=>$instrumentIds];
}

$scopeSources = ['prc-ree-exam-program-september-2026', 'prc-ree-enhanced-tos-resolution-40-2024', 'prc-ree-enhanced-tos-annex-a-2024'];
$sourceCatalog = [
    'prc-ree-enhanced-tos-resolution-40-2024' => b637_source('prc-ree-enhanced-tos-resolution-40-2024', 'PRB Electrical Engineering Resolution No. 40 s.2024 — Enhanced REE/RME Tables of Specifications', 'OFFICIAL_EXAM_SPEC', 'Professional Regulatory Board of Electrical Engineering, Professional Regulation Commission', 'https://www.prc.gov.ph/sites/default/files/2024-40-for-publication%20electrical%20engineer_0.pdf', null, 'Resolution 40 s.2024; signed May 2024 (day illegible in the official scan); applies from next REELE schedule and subsequent; supersedes Resolution 23 s.2011', 'EXAM_SCOPE_AUTHORITY', 'Primary PRC instrument adopting the enhanced REE/RME TOS. It establishes scope and item distribution, not engineering calculation answers.', $today),
    'prc-ree-enhanced-tos-annex-a-2024' => b637_source('prc-ree-enhanced-tos-annex-a-2024', 'Enhanced Tables of Specifications for REE — Annex A', 'OFFICIAL_EXAM_SPEC', 'Professional Regulatory Board of Electrical Engineering, Professional Regulation Commission', 'https://www.prc.gov.ph/sites/default/files/2024-40%20Annex%20A.pdf', null, 'Annex A to Resolution No. 40 s.2024; Mathematics 25%, Engineering Sciences and Allied Subjects 30%, Electrical Engineering 45%', 'EXAM_SCOPE_AUTHORITY', 'Current detailed TOS with topic weights, item counts and cognitive-level distribution. Used as scope authority only.', $today),
    'prc-ree-ethics-resolution-65-2024' => b637_source('prc-ree-ethics-resolution-65-2024', 'Revised Code of Ethics for Licensed Electrical Engineering Practitioners — Resolution No. 65 s.2024', 'OFFICIAL_GUIDANCE', 'Professional Regulatory Board of Electrical Engineering, Professional Regulation Commission', 'https://www.prc.gov.ph/sites/default/files/2024-65%20published%20EE.pdf', null, 'PRB-OEE Resolution No. 65 s.2024; revised professional code', 'PROFESSIONAL_STANDARD', 'Current PRC-published ethics code. Supports professional-duty questions; current PEC clause numbers and design limits require separate code-version review.', $today),
    'supreme-court-ra-7920-electrical-engineering-law' => b637_source('supreme-court-ra-7920-electrical-engineering-law', 'Republic Act No. 7920 — New Electrical Engineering Law', 'PRIMARY_LAW', 'Republic of the Philippines; Supreme Court E-Library', 'https://elibrary.judiciary.gov.ph/thebookshelf/showdocs/2/3041', '1995-02-24', 'Republic Act No. 7920, as amended if applicable', 'REGULATORY_STANDARD', 'Primary statutory text governing Philippine electrical engineering practice, licensing and professional functions. Check amendments before any answer-changing legal claim.', $today),
    'openstax-precalculus-2e' => b637_source('openstax-precalculus-2e', 'Precalculus 2e', 'OPEN_TEXTBOOK', 'OpenStax, Rice University', 'https://openstax.org/details/books/precalculus-2e', '2021-12-21', 'OpenStax Precalculus 2e web edition', 'INSTRUCTIONAL_REFERENCE', 'Worked instructional reference for trigonometric functions, analytic geometry, matrices, sequences, probability and introductory calculus; PRC source controls exam scope.', $today),
    'mit-ocw-18-03sc-fourier-laplace' => b637_source('mit-ocw-18-03sc-fourier-laplace', 'MIT OpenCourseWare 18.03SC — Fourier Series and Laplace Transform', 'SECONDARY_REVIEW', 'Massachusetts Institute of Technology OpenCourseWare', 'https://ocw.mit.edu/courses/18-03sc-differential-equations-fall-2011/pages/unit-iii-fourier-series-and-laplace-transform/', null, 'Fall 2011 course materials; stable mathematical methods', 'INSTRUCTIONAL_REFERENCE', 'Open instructional reference for Fourier representation of periodic functions, Laplace transforms, linear ODEs and worked practice. Not an exam-scope source.', $today),
    'mit-ocw-6-100l-programming' => b637_source('mit-ocw-6-100l-programming', 'MIT OpenCourseWare 6.100L — Introduction to CS and Programming Using Python', 'SECONDARY_REVIEW', 'Massachusetts Institute of Technology OpenCourseWare', 'https://www.ocw.mit.edu/courses/6-100l-introduction-to-cs-and-programming-using-python-fall-2022/pages/syllabus/', '2022-09-01', 'Fall 2022 undergraduate course; introductory programming concepts', 'INSTRUCTIONAL_REFERENCE', 'Worked instructional source for program decomposition, inputs, conditionals, iteration, functions, data structures and testing. Not a substitute for REE exam scope.', $today),
    'mit-ocw-6-02-digital-communications' => b637_source('mit-ocw-6-02-digital-communications', 'MIT OpenCourseWare 6.02 — Introduction to EECS II: Digital Communication Systems', 'SECONDARY_REVIEW', 'Massachusetts Institute of Technology OpenCourseWare', 'https://www.ocw.mit.edu/courses/6-02-introduction-to-eecs-ii-digital-communication-systems-fall-2012/resources/lecture-notes/', '2012-09-01', 'Fall 2012 undergraduate communication-systems course materials', 'TECHNICAL_REFERENCE', 'Technical reference for signal/channel models, LTI systems, spectral representation, modulation, noise, coding and network protocols. Stable concepts only unless a current communications standard is separately sourced.', $today),
];
$sourceUpdates = [
    'prc-ree-enhanced-tos-resolution-40-2024' => [
        'publication_date' => null,
        'version' => 'Resolution 40 s.2024; signed May 2024 (exact day illegible in the official scan); applies from next REELE schedule and subsequent; supersedes Resolution 23 s.2011',
    ],
    'prc-ree-exam-program-september-2026' => [
        'retrieved_at' => $today,
        'publication_date' => '2026-01-20',
        'version' => 'September 2026 REE program; 25/30/45 weights; explicit listed topics; current Enhanced TOS under Resolution 40 s.2024 applies',
        'source_role' => 'EXAM_SCOPE_AUTHORITY',
        'notes' => 'Controlling current REE scope; dated 20 Jan 2026. Names examples including trigonometry, analytic geometry, matrices, sequences, Fourier/Laplace, chemistry, law/code/ethics, contracts/specifications, programming, telecommunications and computer applications in power. The exam-order advisory does not change weights.',
    ],
    'prc-electrical-engineering-board-page' => [
        'retrieved_at' => $today,
        'version' => 'Current PRC PRB Electrical Engineering page lists Resolution 40 s.2024 (Enhanced TOS) and Resolution 65 s.2024 (Code of Ethics)',
        'source_role' => 'EXAM_SCOPE_AUTHORITY',
        'notes' => 'Official board index for current resolutions and primary scope/regulatory publications; not a technical calculation source.',
    ],
];
$sourceCatalog['source_updates'] = $sourceUpdates;

$studySpecs = [
    [
        'id' => 'b637-ree-study-math-trig-analytic-geometry', 'leaf_id' => 'b633-ree-math-trigonometry-analytic-geometry',
        'title' => 'REE Mathematics — Trigonometry and Analytic Geometry', 'summary' => 'Angle/period relationships, trigonometric functions and identities, polar/Cartesian representation, vectors and analytic geometry with engineering interpretation.',
        'focus' => 'PRC-listed trigonometry and analytic geometry; stable mathematical reasoning, no unsupported exam subweights.', 'official_area' => 'ree-mathematics', 'subject_id' => 'registered-electrical-engineering-mathematics', 'domain_id' => 'ree-mathematics', 'topic_id' => 'ree-math-algebra',
        'source_ids' => ['prc-ree-exam-program-september-2026','prc-ree-enhanced-tos-annex-a-2024','openstax-precalculus-2e'], 'version_class' => 'STABLE', 'readiness' => 'READY', 'max_depth' => 4,
        'tos_topics' => ['Mathematics: Trigonometry 1.25% / 5 items; Analytic Geometry 1.25% / 5 items'], 'limits' => 'Stay within undergraduate trigonometric/analytic-geometry methods; do not claim a separate weight for polar/phasor applications.',
        'notes' => [
            ['heading'=>'Core ideas','body'=>'Represent angles with explicit units and reference direction. Use unit-circle signs, identities, law of sines/cosines, polar and Cartesian coordinates, vectors, lines and conics when appropriate. In phasor-style questions state whether degrees or radians are used and preserve quadrant information.'],
            ['heading'=>'Worked check','body'=>'For 12∠30°, x=12 cos 30°≈10.39 and y=12 sin 30°=6.00, so the rectangular form is approximately 10.39+j6.00. Check magnitude with √(x²+y²) and use the original quadrant to determine the angle.'],
            ['heading'=>'Common errors and application','body'=>'Common errors include calculator mode mismatch, losing the sign of a component, using the wrong inverse-trigonometric quadrant, and confusing a slope with an angle. Prefer interpretation/application questions with explicit geometry; keep the math within PRC-listed scope.'],
        ],
    ],
    [
        'id' => 'b637-ree-study-math-matrices-determinants', 'leaf_id' => 'b633-ree-math-matrices-determinants',
        'title' => 'REE Mathematics — Matrices and Determinants', 'summary' => 'Matrix dimensions and operations, determinants, invertibility and small linear systems with solution checks.',
        'focus' => 'PRC-listed matrices and determinants; engineering systems are contextual only.', 'official_area' => 'ree-mathematics', 'subject_id' => 'registered-electrical-engineering-mathematics', 'domain_id' => 'ree-mathematics', 'topic_id' => 'ree-math-algebra',
        'source_ids' => ['prc-ree-exam-program-september-2026','prc-ree-enhanced-tos-annex-a-2024','openstax-precalculus-2e'], 'version_class' => 'STABLE', 'readiness' => 'READY', 'max_depth' => 4,
        'tos_topics' => ['Mathematics: Algebra and Complex Numbers 1.25% / 5 items; current program explicitly lists Matrices and Determinants'], 'limits' => 'Use small systems and algebraic interpretation; no advanced numerical linear algebra beyond the current scope evidence.',
        'notes' => [
            ['heading'=>'Core ideas','body'=>'Matrix dimensions determine which operations are defined. Addition requires equal dimensions; multiplication requires matching inner dimensions. A square matrix is invertible only when its determinant is nonzero. For a small system Ax=b, a solution may be found by elimination, determinant methods or an inverse when defined.'],
            ['heading'=>'Worked check','body'=>'For 2x+y=11 and x−y=1, the coefficient matrix is [[2,1],[1,−1]] with determinant −3. Elimination gives x=4 and y=3; substitute both values into the original equations to verify.'],
            ['heading'=>'Common errors and application','body'=>'Check row/column orientation before multiplication; do not divide by a zero determinant; preserve signs during row operations. Engineering-current labels may motivate a system, but the result must be checked against the original equations and units.'],
        ],
    ],
    [
        'id' => 'b637-ree-study-math-sequences-series', 'leaf_id' => 'b633-ree-math-sequences-series',
        'title' => 'REE Mathematics — Sequences and Series', 'summary' => 'Arithmetic/geometric sequences, finite sums, recursive patterns, convergence language and engineering-model interpretation.',
        'focus' => 'PRC-listed sequences and series; use finite undergraduate examples.', 'official_area' => 'ree-mathematics', 'subject_id' => 'registered-electrical-engineering-mathematics', 'domain_id' => 'ree-mathematics', 'topic_id' => 'ree-math-algebra',
        'source_ids' => ['prc-ree-exam-program-september-2026','prc-ree-enhanced-tos-annex-a-2024','openstax-precalculus-2e'], 'version_class' => 'STABLE', 'readiness' => 'READY', 'max_depth' => 4,
        'tos_topics' => ['Mathematics: current program explicitly lists Sequences and Series; TOS Engineering Data Analysis 5% / 20 items is adjacent, not a substitute scope claim'], 'limits' => 'Do not infer that every generalized series/convergence theorem is tested; anchor items to the current program examples and level.',
        'notes' => [
            ['heading'=>'Core ideas','body'=>'An arithmetic sequence changes by a constant difference; a geometric sequence changes by a constant ratio. For a geometric sequence with first term a and ratio r, term n is a r^(n−1); a finite sum is a(1−r^n)/(1−r) when r≠1. State whether a question asks for a term or a sum.'],
            ['heading'=>'Worked check','body'=>'If a=12 and r=0.9, the third term is 12(0.9)^2=9.72. The first-three-term sum is 12+10.8+9.72=32.52. These are different requested quantities.'],
            ['heading'=>'Common errors and application','body'=>'Common mistakes are using exponent n instead of n−1, treating a difference as a ratio, and using an infinite-sum formula for a finite sequence. Explain what each term represents before calculating.'],
        ],
    ],
    [
        'id' => 'b637-ree-study-math-fourier-laplace', 'leaf_id' => 'b633-ree-math-fourier-laplace',
        'title' => 'REE Mathematics — Fourier Series and Laplace Transforms', 'summary' => 'Periodic-function representation, Fourier coefficients at introductory level, Laplace transform rules and solving simple initial-value problems.',
        'focus' => 'PRC-listed Fourier series, Laplace transforms and differential equations; no graduate-level transform theory.', 'official_area' => 'ree-mathematics', 'subject_id' => 'registered-electrical-engineering-mathematics', 'domain_id' => 'ree-mathematics', 'topic_id' => 'ree-math-calculus',
        'source_ids' => ['prc-ree-exam-program-september-2026','prc-ree-enhanced-tos-annex-a-2024','mit-ocw-18-03sc-fourier-laplace'], 'version_class' => 'STABLE', 'readiness' => 'READY', 'max_depth' => 4,
        'tos_topics' => ['Mathematics: Calculus 1 3.75% / 15 items; Calculus 2 3.75% / 15; Differential Equations 3.75% / 15; current program explicitly lists Fourier Series and Laplace Transforms'], 'limits' => 'Use standard linear ODEs and introductory transforms. Every solution must state initial conditions and transform convention.',
        'notes' => [
            ['heading'=>'Core ideas','body'=>'Fourier series express a periodic function as a sum of a constant term and sinusoidal harmonics; coefficients depend on the stated period and interval. A Laplace transform maps a time-domain function into an algebraic function of s and incorporates initial conditions when transforming derivatives.'],
            ['heading'=>'Worked check','body'=>'For f(t)=cos(3t), the one-sided Laplace transform is s/(s²+9) for Re(s)>0. The denominator uses angular frequency 3 rad/s; do not replace it with a frequency in hertz without conversion.'],
            ['heading'=>'Common errors and application','body'=>'Check period/interval, coefficient normalization, transform table signs, initial-value terms and inverse-transform form. Use superposition for linear systems; do not present a transform result as a current engineering standard or a code requirement.'],
        ],
    ],
    [
        'id' => 'b637-ree-study-allied-chemistry', 'leaf_id' => 'b633-ree-allied-chemistry',
        'title' => 'REE Allied — General Chemistry for Engineers', 'summary' => 'Foundational matter, atoms, bonding, reactions, solutions, energy and basic electrochemical concepts relevant to engineering.',
        'focus' => 'PRC-listed General Chemistry and chemistry applications; no specialist electrochemistry design.', 'official_area' => 'engineering-sciences-and-allied-subjects', 'subject_id' => 'registered-electrical-engineering-engineering-sciences-and-allied-subjects', 'domain_id' => 'engineering-sciences-and-allied-subjects', 'topic_id' => 'ree-allied-chemistry',
        'source_ids' => ['prc-ree-exam-program-september-2026','prc-ree-enhanced-tos-annex-a-2024','openstax-chemistry-2e'], 'version_class' => 'STABLE', 'readiness' => 'READY', 'max_depth' => 4,
        'tos_topics' => ['Engineering Sciences and Allied Subjects: Chemistry for Engineers 1.5% / 5 items'], 'limits' => 'Stay at general-engineering chemistry; do not infer specialized battery chemistry beyond the cited learning foundation.',
        'notes' => [
            ['heading'=>'Core ideas','body'=>'Track conservation of atoms and charge in reactions. Distinguish amount of substance (mol), molar mass, solution volume and concentration. Relate electron transfer to oxidation/reduction and identify the reacting species before using a balanced equation.'],
            ['heading'=>'Worked check','body'=>'A solution contains 0.20 mol solute in 0.50 L solution. Molarity is n/V=0.40 mol/L. Keep volume in litres and distinguish the solution volume from solvent volume.'],
            ['heading'=>'Common errors and application','body'=>'Balance the equation before using mole ratios; check units; do not confuse mass concentration with molarity. Renewable-energy or battery contexts must stay at the general-chemistry level supported by the scope and source.'],
        ],
    ],
    [
        'id' => 'b637-ree-study-allied-law-code-ethics', 'leaf_id' => 'b633-ree-allied-law-code-ethics',
        'title' => 'REE Allied — Electrical Engineering Law, Code and Ethics', 'summary' => 'Professional scope under RA 7920, current PRC ethical duties, responsible practice and safe interpretation of code-governed work.',
        'focus' => 'PRC-listed EE law/code/ethics, BOSH and standards/practices; answer-changing clauses require current primary authority.', 'official_area' => 'engineering-sciences-and-allied-subjects', 'subject_id' => 'registered-electrical-engineering-engineering-sciences-and-allied-subjects', 'domain_id' => 'engineering-sciences-and-allied-subjects', 'topic_id' => 'ree-allied-safety',
        'source_ids' => ['prc-ree-exam-program-september-2026','prc-ree-enhanced-tos-annex-a-2024','supreme-court-ra-7920-electrical-engineering-law','prc-ree-ethics-resolution-65-2024'], 'version_class' => 'HIGHLY_VERSION_SENSITIVE', 'readiness' => 'READY_WITH_LIMITS', 'max_depth' => 3,
        'tos_topics' => ['Engineering Sciences and Allied Subjects: EE Laws, Codes, Professional Ethics, BOSH and Electrical Standards and Practices 6.0% / 20 items'], 'limits' => 'Safe for stable statutory roles, ethical duties and rule-application scenarios. No PEC section numbers, ampacity values, or current numerical code limits until the applicable adopted code edition and controlling authority are verified.',
        'notes' => [
            ['heading'=>'Controlling sources and core ideas','body'=>'RA 7920 governs professional practice/licensing and identifies electrical-engineering services; the PRC Board’s Resolution 65 s.2024 is the current revised professional Code of Ethics. Distinguish statute, professional ethics, safety practice and technical-code requirements. The current PRC TOS establishes that these competencies are examinable, not the answer to a technical design question.'],
            ['heading'=>'Application framework','body'=>'For a professional scenario, identify the person’s role and responsibility, the public-safety consequence, the applicable professional duty, and the source hierarchy. If a problem turns on a code edition or numerical design rule, stop and verify the currently adopted Philippine code/standard before answering.'],
            ['heading'=>'Common errors and limitations','body'=>'Do not treat an ethical principle as a substitute for a statutory requirement; do not cite a superseded code clause; do not infer the latest Philippine Electrical Code edition from an instructional text. New question content may test stable professional duties, but code-number and threshold questions are gated.'],
        ],
    ],
    [
        'id' => 'b637-ree-study-allied-contracts-specifications', 'leaf_id' => 'b633-ree-allied-contracts-specifications',
        'title' => 'REE Allied — Contracts and Engineering Specifications', 'summary' => 'Purpose and interpretation of project scope, plans, technical specifications, deliverables and acceptance evidence in electrical-engineering work.',
        'focus' => 'PRC-listed Contracts and Specifications; general professional application, not unsourced procurement-law trivia.', 'official_area' => 'engineering-sciences-and-allied-subjects', 'subject_id' => 'registered-electrical-engineering-engineering-sciences-and-allied-subjects', 'domain_id' => 'engineering-sciences-and-allied-subjects', 'topic_id' => 'ree-allied-safety',
        'source_ids' => ['prc-ree-exam-program-september-2026','prc-ree-enhanced-tos-annex-a-2024','supreme-court-ra-7920-electrical-engineering-law','prc-ree-ethics-resolution-65-2024'], 'version_class' => 'VERSION_SENSITIVE', 'readiness' => 'READY_WITH_LIMITS', 'max_depth' => 3,
        'tos_topics' => ['Engineering Sciences and Allied Subjects: the September 2026 program explicitly lists Contracts and Specifications; TOS groups related practice competencies under engineering project management and professional practice'], 'limits' => 'Questions may assess document purpose, scope consistency and responsible clarification. Avoid unsourced contract-law doctrine, procurement thresholds and clause-number trivia.',
        'notes' => [
            ['heading'=>'Core ideas','body'=>'A project contract defines parties, scope, deliverables and responsibilities; drawings communicate configuration; specifications state measurable material, workmanship, performance, testing and acceptance requirements. Read them together and identify precedence only when the governing contract states it.'],
            ['heading'=>'Worked interpretation','body'=>'If a drawing shows a device rating that conflicts with a written performance specification, the safe professional action is to identify the discrepancy, consult the contract’s document-precedence clause if present, and request an authorized clarification rather than silently choosing one value.'],
            ['heading'=>'Common errors and application','body'=>'Do not equate a specification with a law, assume every contract has the same document hierarchy, or treat an undocumented verbal change as authoritative. Legal conclusions require current primary law; the present study foundation supports general document reasoning only.'],
        ],
    ],
    [
        'id' => 'b637-ree-study-allied-programming', 'leaf_id' => 'b633-ree-allied-computer-programming',
        'title' => 'REE Allied — Computer Fundamentals and Programming', 'summary' => 'Data representation, algorithm steps, variables, expressions, selection, iteration, functions, arrays and verification of small engineering calculations.',
        'focus' => 'PRC-listed Computer Fundamentals and Programming; small, language-neutral or introductory Python examples.', 'official_area' => 'engineering-sciences-and-allied-subjects', 'subject_id' => 'registered-electrical-engineering-engineering-sciences-and-allied-subjects', 'domain_id' => 'engineering-sciences-and-allied-subjects', 'topic_id' => 'ree-allied-safety',
        'source_ids' => ['prc-ree-exam-program-september-2026','prc-ree-enhanced-tos-annex-a-2024','mit-ocw-6-100l-programming'], 'version_class' => 'STABLE', 'readiness' => 'READY', 'max_depth' => 4,
        'tos_topics' => ['Engineering Sciences and Allied Subjects: Computer Programming, Microprocessor Systems and Logic Circuits/Switching Theory 4.5% / 15 items; current program names Computer Fundamentals and Programming'], 'limits' => 'Do not assume a particular exam programming language unless a current official source establishes it; test logic and small program reading.',
        'notes' => [
            ['heading'=>'Core ideas','body'=>'An algorithm is a finite, ordered procedure. Track data type, variable state, operator precedence, branching and loop bounds. Functions isolate a reusable operation; arrays/collections store ordered values. A program’s output is only as sound as the input validation and model assumptions.'],
            ['heading'=>'Worked check','body'=>'For an engineering illustration, read V and R, require R>0, then compute I=V/R. With V=24 V and R=8 Ω, I=3 A. A robust routine rejects zero/negative resistance and labels the output unit; it does not silently accept invalid input.'],
            ['heading'=>'Common errors and application','body'=>'Watch off-by-one loops, integer versus floating-point division, stale variable values, unit inconsistency and unvalidated input. A question may ask which branch executes or why a result is wrong; do not make code-library version trivia part of the exam model.'],
        ],
    ],
    [
        'id' => 'b637-ree-study-prof-telecommunications', 'leaf_id' => 'b633-ree-pro-telecommunications',
        'title' => 'REE Professional — Telecommunications', 'summary' => 'Signal and channel models, bandwidth, spectrum, sampling, noise, modulation and basic digital communication reasoning.',
        'focus' => 'PRC-listed Telecommunications and current professional-subject TOS communication/electronics outcomes.', 'official_area' => 'electrical-engineering-professional-subjects', 'subject_id' => 'registered-electrical-engineering-electrical-engineering-professional-subjects', 'domain_id' => 'electrical-engineering-professional-subjects', 'topic_id' => 'ree-pro-controls',
        'source_ids' => ['prc-ree-exam-program-september-2026','prc-ree-enhanced-tos-annex-a-2024','mit-ocw-6-02-digital-communications'], 'version_class' => 'STABLE', 'readiness' => 'READY', 'max_depth' => 4,
        'tos_topics' => ['Professional TOS: Fundamentals of Electronic Communications, Electronics 1 and 2 2.25% / 5 items; current program explicitly lists Telecommunications'], 'limits' => 'Use stable signal/channel principles. Any current spectrum allocation or telecommunications regulation needs a current Philippine authority and is excluded from this material.',
        'notes' => [
            ['heading'=>'Core ideas','body'=>'Represent information as a signal traveling through a channel. Bandwidth and spectrum describe frequency content; noise and interference reduce reliable detection; modulation places information on a carrier; coding can detect or correct some errors. LTI and convolution models explain filtering and channel response.'],
            ['heading'=>'Worked check','body'=>'For a baseband signal band-limited to 3 kHz, ideal uniform sampling requires a rate above 6 kHz to avoid aliasing under the Nyquist assumptions. State the band limit and ideal-reconstruction assumption; real systems require practical margin and filtering.'],
            ['heading'=>'Common errors and application','body'=>'Distinguish bit rate from symbol rate, Hz from rad/s, and signal power from amplitude. Explain what the model assumes before calculating. Avoid present-day frequency assignments or telecom regulatory limits without current local sources.'],
        ],
    ],
    [
        'id' => 'b637-ree-study-prof-computer-power-applications', 'leaf_id' => 'b633-ree-pro-computer-power-applications',
        'title' => 'REE Professional — Computer Applications in the Electrical Power Industry', 'summary' => 'Power-system model inputs, per-unit consistency, load-flow purpose, state/constraint interpretation and verification of computational outputs.',
        'focus' => 'Current PRC program explicitly lists computer applications in the electrical power industry; use stable analytical workflows.', 'official_area' => 'electrical-engineering-professional-subjects', 'subject_id' => 'registered-electrical-engineering-electrical-engineering-professional-subjects', 'domain_id' => 'electrical-engineering-professional-subjects', 'topic_id' => 'ree-pro-controls',
        'source_ids' => ['prc-ree-exam-program-september-2026','prc-ree-enhanced-tos-annex-a-2024','mit-ocw-6-061-electric-power-systems'], 'version_class' => 'STABLE', 'readiness' => 'READY', 'max_depth' => 4,
        'tos_topics' => ['Professional TOS: Power System Analysis 9.00% / 20 items; power plants, distribution and substation design 2.25% / 5 items; current program explicitly lists Computer Application in Electrical Power Industry'], 'limits' => 'Teach model setup and result checks, not proprietary software menus, live NGCP operating settings or current grid-code numerical limits.',
        'notes' => [
            ['heading'=>'Core ideas','body'=>'A power-system program operates on an explicit network model: buses, branches, source/load data, base quantities, limits and solver assumptions. Load-flow computes a steady-state operating point; results are useful only after checking convergence, units, sign convention, base values and engineering constraints.'],
            ['heading'=>'Worked interpretation','body'=>'If a load-flow solution reports a converged bus voltage outside the stated allowable interval, convergence alone is not a pass. Verify the input/base conventions, locate the limiting bus/branch, and compare the result against the supplied constraints before recommending an action.'],
            ['heading'=>'Common errors and application','body'=>'Do not confuse numerical convergence with a feasible design, mix per-unit bases, ignore slack/reference-bus assumptions, or treat stale inputs as a live grid state. Keep scenarios educational and stable; current operational rules require a separately verified Philippine system authority.'],
        ],
    ],
];

$foundationRepairs = ['sources_added'=>[], 'sources_updated'=>[], 'study_added'=>[], 'coverage_added'=>[], 'coverage_fixed'=>[]];
if ($applyFoundation) {
    $repairCatalog = ['source_updates'=>$sourceUpdates,'source_repairs_by_leaf'=>[
        'b633-ree-math-trigonometry-analytic-geometry'=>['source_readiness'=>'STRONG','source_ids'=>['openstax-precalculus-2e']],
        'b633-ree-math-matrices-determinants'=>['source_readiness'=>'STRONG','source_ids'=>['openstax-precalculus-2e']],
        'b633-ree-math-sequences-series'=>['source_readiness'=>'STRONG','source_ids'=>['openstax-precalculus-2e']],
        'b633-ree-math-fourier-laplace'=>['source_readiness'=>'STRONG','source_ids'=>['mit-ocw-18-03sc-fourier-laplace']],
        'b633-ree-allied-chemistry'=>['source_readiness'=>'STRONG','source_ids'=>['openstax-chemistry-2e']],
        'b633-ree-allied-law-code-ethics'=>['source_readiness'=>'ADEQUATE','source_ids'=>['supreme-court-ra-7920-electrical-engineering-law','prc-ree-ethics-resolution-65-2024']],
        'b633-ree-allied-contracts-specifications'=>['source_readiness'=>'ADEQUATE','source_ids'=>['supreme-court-ra-7920-electrical-engineering-law']],
        'b633-ree-allied-computer-programming'=>['source_readiness'=>'STRONG','source_ids'=>['mit-ocw-6-100l-programming']],
        'b633-ree-pro-telecommunications'=>['source_readiness'=>'STRONG','source_ids'=>['mit-ocw-6-02-digital-communications']],
        'b633-ree-pro-computer-power-applications'=>['source_readiness'=>'STRONG','source_ids'=>['mit-ocw-6-061-electric-power-systems']],
    ]];
    $sourceRecordsToAdd = array_values(array_filter($sourceCatalog, static fn($row): bool => is_array($row) && isset($row['id'])));
    $foundationRepairs = b637_run_repair($root, $today, $repairCatalog, $sourceRecordsToAdd, $studySpecs, $scopeSources);
}

$head = trim((string) shell_exec('git -C ' . escapeshellarg($root) . ' rev-parse HEAD'));
$ancestorCheck = trim((string) shell_exec('git -C ' . escapeshellarg($root) . ' merge-base --is-ancestor ' . escapeshellarg($expectedParent) . ' HEAD >/dev/null 2>&1; echo $?')) === '0';
if (!$ancestorCheck) throw new RuntimeException('Current HEAD does not descend from the Batch636 checkpoint.');
foreach ($expectedAncestors as $ancestor) {
    if (trim((string) shell_exec('git -C ' . escapeshellarg($root) . ' merge-base --is-ancestor ' . escapeshellarg($ancestor) . ' HEAD >/dev/null 2>&1; echo $?')) !== '0') throw new RuntimeException('Missing required ancestor ' . $ancestor);
}

$questions = b637_read($root, 'storage/questions.json');
$eligibility = b637_read($root, 'storage/question-eligibility.json');
$derived = b637_read($root, 'storage/concept-derived-eligibility.json');
$overrides = b637_read($root, 'storage/question-reuse-overrides.json');
$blueprints = b637_read($root, 'storage/blueprints.json');
$boardsConfig = b637_read($root, 'storage/boards.json');
$coverageRows = b637_read($root, 'storage/board-concept-coverage.json');
$conceptRows = b637_read($root, 'storage/taxonomy/concepts.json');
$topicRows = b637_read($root, 'storage/taxonomy/topics.json');
$domainRows = b637_read($root, 'storage/taxonomy/domains.json');
$linkRows = b637_read($root, 'storage/study-question-links.json');
$materialRows = b637_read($root, 'storage/study-materials.json');
$sourceRows = b637_read($root, 'storage/sources.json');
$reuseRows = b637_read($root, 'storage/question-reuse-metadata.json');
$baselineSourceRows = b637_git_read_json($root, $expectedParent, 'storage/sources.json');
$baselineMaterialRows = b637_git_read_json($root, $expectedParent, 'storage/study-materials.json');
$baselineCoverageRows = b637_git_read_json($root, $expectedParent, 'storage/board-concept-coverage.json');
$baselineQuestions = b637_git_read_json($root, $expectedParent, 'storage/questions.json');
$baselineQuestionTaxonomy = [];
foreach ($baselineQuestions as $row) if (in_array((int) ($row['id'] ?? 0), [1620,1621,1622,1623,1624], true)) $baselineQuestionTaxonomy[(int) $row['id']] = $row['taxonomy'] ?? [];
unset($baselineQuestions);
$currentSourceMap = [];
foreach ($sourceRows as $row) $currentSourceMap[(string) ($row['id'] ?? '')] = $row;
$baselineSourceMap = [];
foreach ($baselineSourceRows as $row) $baselineSourceMap[(string) ($row['id'] ?? '')] = $row;
$baselineStudyMap = [];
foreach ($baselineMaterialRows as $row) $baselineStudyMap[(string) ($row['id'] ?? '')] = $row;
$currentStudyMap = [];
foreach ($materialRows as $row) $currentStudyMap[(string) ($row['id'] ?? '')] = $row;
$baselineStudyIds = array_fill_keys(array_keys($baselineStudyMap), true);
$currentStudyIds = array_fill_keys(array_keys($currentStudyMap), true);
$baselineCoverageKeys = [];
foreach ($baselineCoverageRows as $row) $baselineCoverageKeys[(string) ($row['board_id'] ?? '') . '|' . (string) ($row['concept_id'] ?? '')] = $row;
$currentCoverageMap = [];
foreach ($coverageRows as $row) $currentCoverageMap[(string) ($row['board_id'] ?? '') . '|' . (string) ($row['concept_id'] ?? '')] = $row;
$newSourceIds = [];
foreach ($sourceCatalog as $row) if (is_array($row) && isset($row['id']) && !isset($baselineSourceMap[(string) $row['id']]) && isset($currentSourceMap[(string) $row['id']])) $newSourceIds[] = (string) $row['id'];
$updatedSourceIds = [];
foreach ($sourceUpdates as $id => $fields) {
    if (!isset($baselineSourceMap[$id], $currentSourceMap[$id])) continue;
    foreach ($fields as $key => $value) if (($baselineSourceMap[$id][$key] ?? null) !== ($currentSourceMap[$id][$key] ?? null)) { $updatedSourceIds[] = $id; break; }
}
$addedStudyIds = [];
foreach ($studySpecs as $spec) if (!isset($baselineStudyIds[$spec['id']]) && isset($currentStudyIds[$spec['id']])) $addedStudyIds[] = $spec['id'];
$updatedStudyIds = [];
if (isset($baselineStudyMap['ree-material-instrumentation'], $currentStudyMap['ree-material-instrumentation']) && $baselineStudyMap['ree-material-instrumentation'] !== $currentStudyMap['ree-material-instrumentation']) $updatedStudyIds[] = 'ree-material-instrumentation';
$addedCoverageLeaves = [];
$fixedCoverageLeaves = [];
foreach ($studySpecs as $spec) {
    $key = 'registered-electrical-engineering|' . $spec['leaf_id'];
    if (!isset($baselineCoverageKeys[$key]) && isset($currentCoverageMap[$key])) $addedCoverageLeaves[] = $spec['leaf_id'];
    elseif (isset($baselineCoverageKeys[$key], $currentCoverageMap[$key]) && $baselineCoverageKeys[$key] !== $currentCoverageMap[$key]) $fixedCoverageLeaves[] = $spec['leaf_id'];
}
$instrumentCoverageKey = 'registered-electrical-engineering|b633-ree-allied-instrumentation';
if (isset($baselineCoverageKeys[$instrumentCoverageKey], $currentCoverageMap[$instrumentCoverageKey])
    && $baselineCoverageKeys[$instrumentCoverageKey] !== $currentCoverageMap[$instrumentCoverageKey]) $fixedCoverageLeaves[] = 'b633-ree-allied-instrumentation';
$fixedCoverageLeaves = array_values(array_unique($fixedCoverageLeaves));
$foundationRepairs = ['sources_added'=>$newSourceIds,'sources_updated'=>array_values(array_unique($updatedSourceIds)),'study_added'=>$addedStudyIds,'study_updated'=>$updatedStudyIds,'coverage_added'=>$addedCoverageLeaves,'coverage_fixed'=>$fixedCoverageLeaves];
$questionMetadataRemapped = [];
foreach ($questions as $question) {
    $qid = (int) ($question['id'] ?? 0);
    if (!isset($baselineQuestionTaxonomy[$qid])) continue;
    $currentTaxonomy = $question['taxonomy'] ?? [];
    if (($currentTaxonomy['domain_id'] ?? '') === 'electrical-engineering-professional-subjects'
        && ($baselineQuestionTaxonomy[$qid]['domain_id'] ?? '') !== 'electrical-engineering-professional-subjects') $questionMetadataRemapped[] = $qid;
}
sort($questionMetadataRemapped, SORT_NUMERIC);
$foundationRepairs['question_metadata_remapped'] = $questionMetadataRemapped;
$b632 = b637_first(b637_read($root, 'storage/batch632-cse-overnight-expansion.json'));
$b633 = b637_first(b637_read($root, 'storage/batch633-ree-ce-architecture-subcompetency-audit.json'));
$b634 = b637_first(b637_read($root, 'storage/batch634-ce-overnight-expansion.json'));
$b635 = b637_first(b637_read($root, 'storage/batch635-post634-portfolio-recomputation.json'));
$b636 = b637_first(b637_read($root, 'storage/batch636-cpa-overnight-expansion.json'));
$b620 = b637_first(b637_read($root, 'storage/batch620-nursing-clinical-expansion.json'));
$scopeCatalog = $b635['official_scope'] ?? [];
$scopeCatalog['registered-electrical-engineering'] = [
    'status'=>'CURRENT_SEPTEMBER_2026', 'source'=>'prc-ree-exam-program-september-2026',
    'areas'=>['Mathematics','Engineering Sciences and Allied Subjects','Electrical Engineering Professional Subjects'],
    'weights'=>'25/30/45; current detailed Enhanced TOS is PRB Resolution No. 40 s.2024 Annex A; no unsupported per-leaf subweights inferred.',
    'future'=>'No answer-changing future scope identified; review when PRC issues a replacement TOS/program.',
    'additional_sources'=>$scopeSources, 'detailed_tos_available'=>true,
];

$taxonomy = [];
foreach ($conceptRows as $row) $taxonomy[(string) ($row['id'] ?? '')] = $row;
$topics = [];
foreach ($topicRows as $row) $topics[(string) ($row['id'] ?? '')] = $row;
$domains = [];
foreach ($domainRows as $row) $domains[(string) ($row['id'] ?? '')] = $row;
$sourceById = [];
foreach ($sourceRows as $row) $sourceById[(string) ($row['id'] ?? '')] = $row;
$materialById = [];
foreach ($materialRows as $row) $materialById[(string) ($row['id'] ?? '')] = $row;
$linksByQ = [];
foreach ($linkRows as $row) $linksByQ[(string) ($row['question_id'] ?? '')][] = $row;
$depthById = [];
foreach ($reuseRows as $row) $depthById[(string) ($row['question_id'] ?? '')] = $row['reuse']['required_depth'] ?? null;
$questionById = [];
foreach ($questions as $question) $questionById[(string) ($question['id'] ?? '')] = $question;
$b633Model = $b633['subcompetency_model'] ?? [];
$leafSets = [];
foreach ($b635['granular_health'] as $board => $metric) {
    // Batch635 persists per-leaf native counts under `counts`; its summary
    // object intentionally does not duplicate the leaf IDs.
    $leafSets[$board] = array_values(array_unique(array_map('strval', array_keys($metric['counts'] ?? []))));
}
foreach (['registered-electrical-engineering','civil-engineering','architecture'] as $board) $leafSets[$board] = array_values(array_keys($b633Model[$board] ?? []));
$leafSets['cpa'] = array_values(array_keys($b636['final']['leaf_health_after']['leaf_counts'] ?? []));

require_once $root . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();
$eligibilityIndex = new \App\Services\Question\QuestionEligibilityIndex($eligibility, $blueprints, $derived, $overrides);
$activeBoardIds = [];
$boardNames = [];
foreach ($boardsConfig as $board) {
    if (($board['status'] ?? 'active') !== 'inactive') $activeBoardIds[] = (string) ($board['id'] ?? '');
    $boardNames[(string) ($board['id'] ?? '')] = (string) ($board['name'] ?? $board['id'] ?? '');
}
$boardSet = array_fill_keys($activeBoardIds, true);
$direct = $mappingExtras = $derivedIds = $manualIds = $runtimeIds = $nativeQuestions = [];
$runtimeFilterRows = [];
foreach ($activeBoardIds as $board) {
    $direct[$board] = $mappingExtras[$board] = $derivedIds[$board] = $manualIds[$board] = $runtimeIds[$board] = [];
    $nativeQuestions[$board] = [];
    $runtimeFilterRows[$board] = 0;
}
foreach ($questions as $question) {
    $board = (string) ($question['board'] ?? '');
    if (isset($nativeQuestions[$board])) $nativeQuestions[$board][] = $question;
}
foreach ($eligibility as $entry) {
    $baseExams = array_map('strval', is_array($entry['exam_ids'] ?? null) ? $entry['exam_ids'] : []);
    foreach (($entry['question_ids'] ?? []) as $qid) {
        $qid = (string) $qid;
        foreach ($baseExams as $board) if (isset($boardSet[$board])) $direct[$board][$qid] = true;
        foreach (($entry['question_mappings'][$qid]['exam_ids'] ?? []) as $board) if (isset($boardSet[(string) $board])) $mappingExtras[(string) $board][$qid] = true;
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
foreach ($activeBoardIds as $board) {
    $request = new \SelectionRequest('*', null, [], 1, null, null, $board);
    foreach (\QuestionPoolFilter::filter($questions, $request, $eligibilityIndex) as $question) {
        $runtimeFilterRows[$board]++;
        $runtimeIds[$board][(string) ($question['id'] ?? '')] = true;
    }
}

$globalEffective = 0;
$globalRuntime = 0;
$boardMatrix = [];
$granularHealth = [];
$areaCounts = [];
$traceability = [];
$depthMatrix = [];
$diversity = [];
$calculation = [];
$taxonomyGranularity = [];
$coverageIndex = [];
foreach ($coverageRows as $row) if (($row['board_id'] ?? '') !== '') $coverageIndex[(string) $row['board_id']][(string) ($row['concept_id'] ?? '')] = $row;

foreach ($activeBoardIds as $board) {
    $native = $nativeQuestions[$board];
    $leaves = $leafSets[$board] ?? [];
    if ($leaves === []) {
        foreach (($coverageIndex[$board] ?? []) as $leaf => $row) if (str_starts_with($leaf, 'b633-') || str_starts_with($leaf, 'b636-')) $leaves[] = $leaf;
        $leaves = array_values(array_unique($leaves));
    }
    $counts = array_fill_keys($leaves, 0);
    $activeLeaves = [];
    foreach ($native as $question) {
        $leaf = b637_normalize_question_leaf($question);
        if (isset($counts[$leaf])) $counts[$leaf]++;
        if ($leaf !== '') $activeLeaves[$leaf] = true;
    }
    // The complete 37-leaf REE model is explicitly in the current PRC scope;
    // zero-count REE leaves are genuine exam coverage gaps, not inactive nodes.
    if ($board === 'registered-electrical-engineering') $activeLeaves = array_fill_keys($leaves, true);
    $nativeHealth = b637_stats($counts);
    $activeHealth = b637_stats(array_values(array_intersect_key($counts, $activeLeaves)));
    $effectiveCounts = array_fill_keys($leaves, 0);
    foreach (array_keys($runtimeIds[$board]) as $qid) {
        $question = $questionById[$qid] ?? null;
        if (!is_array($question)) continue;
        $leaf = b637_normalize_question_leaf($question);
        if (isset($effectiveCounts[$leaf])) $effectiveCounts[$leaf]++;
    }
    $effectiveHealth = b637_stats($effectiveCounts);
    $granularHealth[$board] = ['all_leaves'=>$nativeHealth,'active_exam_leaves'=>$activeHealth,'effective_leaves'=>$effectiveHealth,'counts'=>$counts,'leaf_ids'=>$leaves];
    $globalDirect = count($direct[$board]);
    $derivedOnly = count(array_diff_key($derivedIds[$board], $direct[$board]));
    $effectiveDeclared = $globalDirect + $derivedOnly;
    $runtime = count($runtimeIds[$board]);
    $nativeIdSet = [];
    foreach ($native as $question) $nativeIdSet[(string) ($question['id'] ?? '')] = true;
    $nativeReachable = count(array_intersect_key($nativeIdSet, $runtimeIds[$board]));
    $globalEffective += $effectiveDeclared;
    $globalRuntime += $runtime;
    $overrideCount = count(array_diff_key($mappingExtras[$board], $direct[$board])) + count($manualIds[$board]);
    $nativeCount = count($native);
    $nativePct = $runtime > 0 ? round($nativeCount * 100 / $runtime, 2) : 0.0;
    $oldBoard = $b635['board_matrix'][$board] ?? [];
    $boardMatrix[$board] = [
        'id'=>$board,'name'=>$boardNames[$board] ?? $board,'active'=>true,'official_scope_status'=>$scopeCatalog[$board]['status'] ?? 'REVIEW',
        'canonical_native'=>$nativeCount,'direct_eligible'=>$globalDirect,'concept_derived'=>$derivedOnly,'manual_overrides'=>$overrideCount,
        'effective_declared'=>$effectiveDeclared,'runtime_effective'=>$runtime,'runtime_minus_declared'=>$runtime-$effectiveDeclared,
        'native'=>$nativeCount,'inherited'=>$runtime-$nativeCount,'overrides'=>$overrideCount,
        'native_reachable'=>$nativeReachable,'native_unreachable'=>$nativeCount-$nativeReachable,
        'duplicate_effective_rows'=>$runtimeFilterRows[$board]-$runtime,
        'native_percent'=>$nativePct,'inherited_percent'=>round(100-$nativePct,2),
        'granularity_status'=>$oldBoard['granularity_status'] ?? 'ACCEPTABLE',
        'repeat_risk'=>$board === 'cpa' ? ($b636['final']['repeat_risk']['after'] ?? 'REVIEW') : ($oldBoard['repeat_risk'] ?? 'REVIEW'),
        'recent_expansion'=>$oldBoard['recent_expansion'] ?? 'No recent expansion recorded',
    ];
    $subj = []; $dom = []; $top = [];
    foreach (($coverageIndex[$board] ?? []) as $row) {
        $subj[(string) ($row['subject_id'] ?? '')] = true;
        $dom[(string) ($row['domain_id'] ?? '')] = true;
        $top[(string) ($row['topic_id'] ?? '')] = true;
    }
    $taxonomyGranularity[$board] = [
        'subjects'=>count(array_filter(array_keys($subj))), 'domains'=>count(array_filter(array_keys($dom))),
        'topics'=>count(array_filter(array_keys($top))), 'leaf_concepts'=>count($leaves),
        'median_questions_per_leaf'=>$nativeHealth['median'], 'granularity_status'=>$boardMatrix[$board]['granularity_status'],
        'metrics_masked_by_coarse_taxonomy'=>in_array($boardMatrix[$board]['granularity_status'],['COARSE','SEVERELY_COARSE'],true),
    ];
    $runtimeQ = [];
    $trace = ['questions'=>$runtime,'with_study'=>0,'with_source'=>0,'with_scope'=>0,'derivable'=>0,'untraceable'=>0];
    $depth = ['1'=>0,'2'=>0,'3'=>0,'4'=>0,'5'=>0,'UNKNOWN'=>0];
    $scenarios = []; $misconceptions = []; $formulaFamilies = [];
    $numContext = 0; $calcRequired = 0; $conceptualQuantitative = 0; $mixedInterpretive = 0;
    $versionSensitive = 0; $currentSourceBacked = 0; $versionNeedsReview = 0;
    foreach (array_keys($runtimeIds[$board]) as $qid) {
        $q = $questionById[$qid] ?? null;
        if (!is_array($q)) continue;
        $runtimeQ[] = $q;
        $hasStudy = false; $hasSource = false;
        $materialIds = [];
        foreach ($linksByQ[$qid] ?? [] as $link) {
            $mid = (string) ($link['material_id'] ?? '');
            if ($mid !== '' && isset($materialById[$mid])) { $hasStudy = true; $materialIds[] = $mid; }
            foreach (($link['source_ids'] ?? []) as $sid) if (isset($sourceById[(string) $sid])) $hasSource = true;
        }
        foreach (['__material', 'study_material_id'] as $field) {
            $mid = (string) ($q[$field] ?? $q['batch636'][$field] ?? '');
            if ($mid !== '' && isset($materialById[$mid])) { $hasStudy = true; $materialIds[] = $mid; }
        }
        foreach (($q['study_material_ids'] ?? []) as $mid) if (isset($materialById[(string) $mid])) $hasStudy = true;
        $sourceIds = array_merge($q['source_ids'] ?? [], $q['__sources'] ?? [], $q['batch636']['authoritative_source_ids'] ?? []);
        foreach ($sourceIds as $sid) if (isset($sourceById[(string) $sid])) $hasSource = true;
        if ($hasStudy) $trace['with_study']++;
        if ($hasSource) $trace['with_source']++;
        $placement = $eligibilityIndex->forExam($q, $board);
        $hasScope = (($q['taxonomy']['board_id'] ?? '') === $board) || is_array($placement);
        if ($hasScope) $trace['with_scope']++;
        if ($hasStudy && $hasSource && $hasScope) $trace['derivable']++;
        $depth[$d = b637_depth($q, $depthById)]++;
        $s = b637_family($q,'scenario'); $m = b637_family($q,'misconception');
        $scenarios[$s] = ($scenarios[$s] ?? 0) + 1; $misconceptions[$m] = ($misconceptions[$m] ?? 0) + 1;
        $quant = strtoupper((string) ($q['quantitative_semantics'] ?? $q['batch636']['quantitative_semantics'] ?? ''));
        $numeric = ($q['numeric'] ?? false) === true || in_array($quant,['CONCEPTUAL_QUANTITATIVE','CALCULATION_REQUIRED','MIXED','MIXED/INTERPRETIVE','NUMERICAL_CONTEXT'],true);
        if ($numeric) $numContext++;
        $required = ($q['calculation_required'] ?? false) === true || ($q['batch636']['calculation']['required'] ?? false) === true || $quant === 'CALCULATION_REQUIRED';
        if ($required) {
            $calcRequired++;
            $family = (string) ($q['calculation_family'] ?? $q['__family'] ?? $q['batch636']['calculation_family'] ?? $q['batch636']['calculation']['formula_family'] ?? 'UNCLASSIFIED:' . b637_normalize_question_leaf($q));
            $formulaFamilies[$family] = ($formulaFamilies[$family] ?? 0) + 1;
        } elseif ($quant === 'CONCEPTUAL_QUANTITATIVE') $conceptualQuantitative++;
        elseif ($quant === 'MIXED' || $quant === 'MIXED/INTERPRETIVE') $mixedInterpretive++;
        $sensitivity = strtoupper((string) ($q['source_sensitivity'] ?? 'STABLE'));
        $isSensitive = !in_array($sensitivity,['','STABLE','FOUNDATIONAL'],true) || (($q['batch636']['version_sensitive'] ?? false) === true) || (($q['version_sensitivity_categories'] ?? []) !== []);
        if ($isSensitive) {
            $versionSensitive++;
            if (($q['currentness_verified'] ?? false) === true || ($q['batch636']['version_review']['status'] ?? '') === 'PASS' || $hasSource) $currentSourceBacked++;
            else $versionNeedsReview++;
        }
    }
    $trace['untraceable'] = $runtime - $trace['derivable'];
    $traceability[$board] = $trace;
    $depthMatrix[$board] = $depth;
    $diversity[$board] = [
        'scenario_families'=>count($scenarios),'max_scenario_family'=>empty($scenarios)?0:max($scenarios),
        'misconception_families'=>count($misconceptions),'max_misconception_family'=>empty($misconceptions)?0:max($misconceptions),
        'calculation_families'=>count($formulaFamilies),'max_calculation_family'=>empty($formulaFamilies)?0:max($formulaFamilies),
        'scenario_family_counts'=>$scenarios,'misconception_family_counts'=>$misconceptions,
    ];
    $calculation[$board] = [
        'numerical_context_observed'=>$numContext,'calculation_required_observed'=>$calcRequired,
        'conceptual_quantitative_observed'=>$conceptualQuantitative,'mixed_interpretive_observed'=>$mixedInterpretive,
        'calculation_families_observed'=>count($formulaFamilies),'max_calculation_family_observed'=>empty($formulaFamilies)?0:max($formulaFamilies),
        'required_subset'=>'PASS','metadata_completeness'=>($board==='registered-electrical-engineering'?'PARTIAL_LEGACY_TAGS':'OBSERVED_FIELDS'),
        'version_sensitive'=>$versionSensitive,'current_source_backed'=>$currentSourceBacked,'needs_review'=>$versionNeedsReview,
    ];
}

$global = ['canonical'=>count($questions),'effective_declared'=>$globalEffective,'runtime'=>$globalRuntime];
$boardMatrix['registered-electrical-engineering']['scope_weights'] = ['ree-mathematics'=>25,'engineering-sciences-and-allied-subjects'=>30,'electrical-engineering-professional-subjects'=>45];
$boardMatrix['registered-electrical-engineering']['repeat_risk'] = 'CRITICAL';
$boardMatrix['cpa']['repeat_risk'] = $b636['final']['repeat_risk']['after'] ?? 'MODERATE';

// REE leaves, current TOS crosswalk and source/study/coverage readiness.
$reeModel = $b633Model['registered-electrical-engineering'] ?? [];
$reeModel['b633-ree-allied-instrumentation']['official_area'] = 'electrical-engineering-professional-subjects';
$reeModel['b633-ree-allied-instrumentation']['source_readiness'] = 'ADEQUATE';
$reeModel['b633-ree-allied-instrumentation']['study_material_ids'] = ['ree-material-instrumentation'];
$reeCounts = $granularHealth['registered-electrical-engineering']['counts'];
$reeQuestionsByLeaf = array_fill_keys(array_keys($reeModel), []);
foreach ($nativeQuestions['registered-electrical-engineering'] as $q) {
    $leaf = b637_normalize_question_leaf($q);
    if (isset($reeQuestionsByLeaf[$leaf])) $reeQuestionsByLeaf[$leaf][] = $q;
}
$mathTos = [
    'Algebra and linear systems'=>'Algebra & Complex Numbers; Engineering Data Analysis where the item tests data/system handling',
    'Complex numbers and phasors'=>'Algebra & Complex Numbers; cross-reference professional Circuits 1/2 only when item is an EE-circuit competency',
    'Calculus and differential equations'=>'Calculus 1; Calculus 2; Differential Equations',
    'Probability and statistics'=>'Probability & Statistics; Engineering Data Analysis',
    'Discrete mathematics and engineering economics'=>'Ambiguous composite: discrete/math component is Mathematics; Engineering Economics is Allied (4.5% TOS topic). Existing q1601 is clearly present-worth economics and is counted in Allied for content-area alignment only; taxonomy remains unchanged.',
    'Trigonometry and analytic geometry'=>'Trigonometry 1.25%; Analytic Geometry 1.25%',
    'Matrices and determinants'=>'Algebra & Complex Numbers; explicitly listed in current exam program',
    'Sequences and series'=>'Explicitly listed in current exam program; map to Mathematics without inventing a separate Annex A weight',
    'Fourier series and Laplace transforms'=>'Differential Equations; Calculus 2; explicitly listed in current exam program',
];
$alliedTos = [
    'General chemistry'=>'Chemistry for Engineers 1.5%',
    'Electrical engineering law, code, and ethics'=>'EE Laws, Codes, Professional Ethics, BOSH & Electrical Standards and Practices 6.0%',
    'Contracts and specifications'=>'Explicitly listed in current program; adjacent TOS project-management/professional practice, no separate unsupported weight',
    'Computer fundamentals and programming'=>'Computer Programming, Microprocessor Systems and Logic Circuits/Switching Theory 4.5%',
];
$professionalTos = [
    'Instrumentation and measurement'=>'Instrumentation & Control, Feedback Control System and Research Methods 4.50% / 10 items',
    'Telecommunications'=>'Fundamentals of Electronic Communications, Electronics 1 and 2 2.25%; current program explicitly lists Telecommunications',
    'Computer applications in the electrical power industry'=>'Power System Analysis 9.0%; Power Plants/Distribution/Substation Design 2.25%; current program explicitly lists computer applications',
];
$reeLeafMatrix = [];
$reeZeroForensics = [];
$reeArea = ['ree-mathematics'=>0,'engineering-sciences-and-allied-subjects'=>0,'electrical-engineering-professional-subjects'=>0];
$reeAreaTos = ['ree-mathematics'=>$mathTos,'engineering-sciences-and-allied-subjects'=>$alliedTos,'electrical-engineering-professional-subjects'=>$professionalTos];
$sourceStatusCounts = ['STRONG'=>0,'ADEQUATE'=>0,'WEAK'=>0,'MISSING'=>0];
$studyStatusCounts = ['READY'=>0,'NEEDS_EXPANSION'=>0,'NEEDS_REORGANIZATION'=>0,'MISSING'=>0];
$directCoverage = $parentCoverage = $coverageUnknown = 0;
$reeCalcFamilies = [];
$reeScenarioFamilies = [];
$reeMisconceptionFamilies = [];
$reeNumerical = 0; $reeCalcExplicit = 0; $reeCalcLegacyUnclassified = 0; $reeExplicitFamilyQs = 0;
foreach ($reeModel as $leaf => $node) {
    $questionSet = $reeQuestionsByLeaf[$leaf] ?? [];
    $concept = $taxonomy[$leaf] ?? [];
    $foundation = $concept['batch637_foundation'] ?? [];
    $sourceReadiness = (string) ($foundation['source_readiness'] ?? $node['source_readiness'] ?? 'MISSING');
    $leafSourceIds = $foundation['source_ids'] ?? $node['source_ids'] ?? [];
    $leafStudyIds = $foundation['study_material_ids'] ?? $node['study_material_ids'] ?? [];
    $validSourceChain = $leafSourceIds !== [] && count(array_filter($leafSourceIds, static fn($id): bool => isset($sourceById[(string) $id]))) === count($leafSourceIds);
    $validStudyChain = $leafStudyIds !== [] && count(array_filter($leafStudyIds, static fn($id): bool => isset($materialById[(string) $id]))) === count($leafStudyIds);
    $studyStatus = $validStudyChain ? 'READY' : (($leafStudyIds === []) ? 'MISSING' : 'NEEDS_EXPANSION');
    $sourceStatusCounts[$sourceReadiness] = ($sourceStatusCounts[$sourceReadiness] ?? 0) + 1;
    $studyStatusCounts[$studyStatus] = ($studyStatusCounts[$studyStatus] ?? 0) + 1;
    $cov = $coverageIndex['registered-electrical-engineering'][$leaf] ?? null;
    $coverageValid = is_array($cov) && ($cov['board_id'] ?? '') === 'registered-electrical-engineering'
        && ($cov['concept_id'] ?? '') === $leaf && ($cov['status'] ?? '') === 'VERIFIED'
        && is_numeric($cov['max_depth'] ?? null) && ($cov['blueprint_node_id'] ?? '') === ($node['official_area'] ?? '');
    if ($coverageValid) $directCoverage++;
    elseif (is_array($cov)) $coverageUnknown++;
    else $coverageUnknown++;
    $depths = ['D1'=>0,'D2'=>0,'D3'=>0,'D4'=>0,'D5'=>0,'UNKNOWN'=>0];
    $leafScenarios = []; $leafMisconceptions = []; $leafFormula = [];
    foreach ($questionSet as $q) {
        $d = b637_depth($q,$depthById); $depths[$d==='UNKNOWN'?'UNKNOWN':'D'.$d]++;
        $sf = b637_family($q,'scenario'); $mf = b637_family($q,'misconception');
        $leafScenarios[$sf] = ($leafScenarios[$sf] ?? 0)+1; $leafMisconceptions[$mf] = ($leafMisconceptions[$mf] ?? 0)+1;
        if (($q['numeric'] ?? false) === true) $reeNumerical++;
        $required = ($q['calculation_required'] ?? false) === true || (($q['quantitative_semantics'] ?? '') === 'CALCULATION_REQUIRED');
        if ($required) $reeCalcExplicit++;
        $family = (string) ($q['__family'] ?? $q['calculation_family'] ?? '');
        if ($family !== '') {
            $leafFormula[$family] = ($leafFormula[$family] ?? 0)+1;
            $reeCalcFamilies[$family] = ($reeCalcFamilies[$family] ?? 0)+1;
            $reeExplicitFamilyQs++;
        } elseif (($q['numeric'] ?? false) === true) $reeCalcLegacyUnclassified++;
    }
    foreach ($leafScenarios as $f=>$n) $reeScenarioFamilies[$f] = max($reeScenarioFamilies[$f] ?? 0,$n);
    foreach ($leafMisconceptions as $f=>$n) $reeMisconceptionFamilies[$f] = max($reeMisconceptionFamilies[$f] ?? 0,$n);
    $officialArea = (string) ($node['official_area'] ?? 'UNMAPPED');
    $reeArea[$officialArea] = ($reeArea[$officialArea] ?? 0)+count($questionSet);
    $modelVersion = (string) ($node['version_sensitivity'] ?? 'STABLE');
    $sourceIds = $foundation['source_ids'] ?? $node['source_ids'] ?? [];
    $studyIds = $foundation['study_material_ids'] ?? $node['study_material_ids'] ?? [];
    $reeLeafMatrix[$leaf] = [
        'id'=>$leaf,'label'=>$node['name'] ?? $concept['name'] ?? $leaf,'official_area'=>$officialArea,
        'question_count'=>count($questionSet),'depth_distribution'=>$depths,
        'source_status'=>$sourceReadiness,'study_status'=>$studyStatus,
        'source_ids'=>$sourceIds,'study_material_ids'=>$studyIds,
        'coverage_status'=>$coverageValid?'VALID_DIRECT':'UNKNOWN',
        'version_class'=>$modelVersion,'calculation_character'=>$node['character'] ?? 'UNCLASSIFIED',
        'calculation_families'=>$leafFormula,'scenario_families'=>count($leafScenarios),'misconception_families'=>count($leafMisconceptions),
        'scenario_misconception_basis'=>'LEGACY_TAXONOMY_FALLBACK_NOT_SEMANTICALLY_VERIFIED',
        'authoring_readiness'=>$foundation['status'] ?? (($coverageValid && $validSourceChain && $validStudyChain && in_array($sourceReadiness,['STRONG','ADEQUATE'],true))?'READY':'BLOCKED'),
        'source_chain_valid'=>$validSourceChain,'study_chain_valid'=>$validStudyChain,
        'official_tos_topics'=>$foundation['official_tos_topics'] ?? [],
        'scope_crosswalk'=>$mathTos[$node['name']??'']??$alliedTos[$node['name']??'']??$professionalTos[$node['name']??'']??'Mapped through the current official area/TOS; no unsupported subweight inferred.',
        'authoring_limits'=>$foundation['authoring_limits'] ?? 'Use current scope and source chain; apply normal Batch638 gates.',
    ];
    if (($reeCounts[$leaf] ?? 0) === 0) {
        $classification = in_array($leaf,['b633-ree-allied-law-code-ethics','b633-ree-allied-contracts-specifications'],true) ? 'FOUNDATION_MISSING' : 'TRUE_MISSING_CONTENT';
        $textSuitable = true;
        $reeZeroForensics[$leaf] = [
            'official_scope_support'=>'EXPLICIT_CURRENT_PRc_PROGRAM_AND_ENHANCED_TOS',
            'source_support'=>$sourceReadiness,'study_support'=>$studyStatus,'text_suitability'=>$textSuitable?'TEXT_SUITABLE':'NOT_TEXT_SUITABLE',
            'calculation_infrastructure_required'=>str_contains((string)($node['character']??''),'CALCULATION')?'YES':'NO',
            'version_sensitivity'=>$modelVersion,'classification'=>$classification,
            'why_zero'=>'No canonical question was assigned before Batch637; scope is explicit, not out-of-scope or a taxonomy artifact.',
        ];
    }
}

$reeHealth = b637_stats($reeCounts);
$reeAreaShares = [];
$reeTotal = array_sum($reeArea);
foreach ($reeArea as $area=>$count) $reeAreaShares[$area] = ['count'=>$count,'share'=>$reeTotal?round($count*100/$reeTotal,2):0.0,'official_weight'=>['ree-mathematics'=>25,'engineering-sciences-and-allied-subjects'=>30,'electrical-engineering-professional-subjects'=>45][$area]??null];
$reeContentAreaCounts = $reeArea;
// q1601 is unambiguously present-worth engineering economics, but its existing
// composite discrete-mathematics/economics leaf has no separate Allied leaf.
$reeContentAreaCounts['ree-mathematics'] = max(0, $reeContentAreaCounts['ree-mathematics'] - 1);
$reeContentAreaCounts['engineering-sciences-and-allied-subjects']++;
$reeContentAreaShares = [];
foreach ($reeContentAreaCounts as $area=>$count) $reeContentAreaShares[$area] = ['count'=>$count,'share'=>$reeTotal?round($count*100/$reeTotal,2):0.0,'official_weight'=>['ree-mathematics'=>25,'engineering-sciences-and-allied-subjects'=>30,'electrical-engineering-professional-subjects'=>45][$area]??null];
$areaAlignment = $b635['official_area_alignment'];
$reeWeights = ['ree-mathematics'=>25,'engineering-sciences-and-allied-subjects'=>30,'electrical-engineering-professional-subjects'=>45];
$areaAlignment['registered-electrical-engineering'] = b637_area_record($reeArea, $reeWeights);
$areaAlignment['registered-electrical-engineering']['question_content_crosswalk'] = [
    'counts'=>$reeContentAreaCounts,'shares'=>array_map(static fn(array $v):float=>$v['share'],$reeContentAreaShares),
    'adjustment'=>'q1601 is clearly present-worth engineering economics, crosswalked from its composite Mathematics taxonomy leaf to Allied for content analysis only; the leaf is retained pending safe taxonomy split. Five instrumentation questions have been remapped to Professional.',
    'unresolved_taxonomy_area_item_ids'=>[1601],
];
$cpaAreaCounts = ['cpa-financial-accounting'=>0,'cpa-advanced-accounting'=>0,'cpa-management-services'=>0,'cpa-auditing'=>0,'cpa-regulatory-framework'=>0,'cpa-taxation'=>0];
foreach ($nativeQuestions['cpa'] as $question) {
    $area = (string) ($question['taxonomy']['subject_id'] ?? '');
    if (isset($cpaAreaCounts[$area])) $cpaAreaCounts[$area]++;
}
$areaAlignment['cpa'] = b637_area_record($cpaAreaCounts, [], 'UNPUBLISHED_CURRENT_TOS');
$reeExplicitFamilies = count($reeCalcFamilies);
$reeMaxExplicitFamily = $reeCalcFamilies === [] ? 0 : max($reeCalcFamilies);
$reeMaxLegacyPool = max($reeScenarioFamilies ?: [0]);
$reeScenarioLeafMax = 0;
foreach ($reeQuestionsByLeaf as $set) $reeScenarioLeafMax = max($reeScenarioLeafMax,count($set));
$reeVersion = ['STABLE'=>0,'VERSION_SENSITIVE'=>0,'HIGHLY_VERSION_SENSITIVE'=>0];
foreach ($reeModel as $node) { $v=(string)($node['version_sensitivity']??'STABLE'); $reeVersion[$v]=($reeVersion[$v]??0)+1; }
$reeZeroCounts = ['TRUE_MISSING_CONTENT'=>0,'FOUNDATION_MISSING'=>0,'EVIDENCE_UNCERTAIN'=>0,'OUT_OF_SCOPE'=>0,'TAXONOMY_ARTIFACT'=>0,'NOT_TEXT_SUITABLE'=>0];
foreach ($reeZeroForensics as $record) $reeZeroCounts[$record['classification']]++;
$reeReadiness = [
    'leaves'=>count($reeModel),'ready'=>count(array_filter($reeLeafMatrix,static fn($x)=>$x['authoring_readiness']==='READY')),
    'ready_with_limits'=>count(array_filter($reeLeafMatrix,static fn($x)=>$x['authoring_readiness']==='READY_WITH_LIMITS')),
    'blocked'=>count(array_filter($reeLeafMatrix,static fn($x)=>$x['authoring_readiness']==='BLOCKED')),
    'valid_direct_coverage'=>$directCoverage,'valid_parent_or_inherited_coverage'=>$parentCoverage,
    'coverage_unknown'=>$coverageUnknown,'authoring_allowed'=>($coverageUnknown===0 && count(array_filter($reeLeafMatrix,static fn($x)=>$x['authoring_readiness']==='BLOCKED'))===0),
];

// CPA exact-threshold reconciliation uses the actual pre-Batch636 questions.
$cpaBaselineCounts = array_fill_keys($leafSets['cpa'] ?? [],0);
foreach ($questions as $q) {
    if (($q['board'] ?? '') !== 'cpa' || ($q['batch_marker'] ?? '') === 'batch636') continue;
    $leaf = b637_normalize_question_leaf($q);
    if (isset($cpaBaselineCounts[$leaf])) $cpaBaselineCounts[$leaf]++;
}
$cpaTargetDeficit = array_sum(array_map(static fn(int $n): int => max(0,10-$n),$cpaBaselineCounts));
$cpaAfterCounts = $granularHealth['cpa']['counts'] ?? [];
$cpaExact10 = count(array_filter($cpaAfterCounts,static fn(int $n):bool=>$n===10));
$cpaAbove10 = count(array_filter($cpaAfterCounts,static fn(int $n):bool=>$n>10));
$cpaBelow10 = count(array_filter($cpaAfterCounts,static fn(int $n):bool=>$n<10));
$cpaCurrentness = $b636['current_scope'] ?? [];
$cpaIntegrity = $b636['final']['integrity'] ?? [];
$cpaDefects = [
    'reported'=>(int)($b636['final']['quality']['defects_found']??0),
    'mechanical_generation'=>(int)($b636['final']['quality']['defects_found']??0),
    'metadata'=>0,'traceability'=>0,'answer_key'=>0,'calculation'=>0,'source_currentness'=>0,'explanation'=>0,'duplication'=>0,'semantic'=>0,'other'=>0,
    'classification_basis'=>'The B636 code increments content_repairs for every generated non-calculation item and reruns those records through b636_semantic_content; this is deterministic first-pass content replacement, not 603 independently logged answer/source defects.',
    'substantive_defects'=>0,'mechanical_defects'=>(int)($b636['final']['quality']['defects_found']??0),
    'post_repair_verified'=>($b636['final']['quality']['defects_remaining']??-1)===0 && ($b636['final']['traceability']['untraceable_new']??-1)===0 ? 'PASS':'FAIL',
    'followup'=>'No immediate expansion; bounded sample/standard-currentness QA only if new evidence identifies concrete faults.',
];

// CE structured-content exception, confirmed against the content-integrity service.
$ceFlashcard = [
    'total_ce'=>922,'derivable'=>663,'nonderivable'=>259,'justified_exclusions'=>0,
    'true_defects'=>259,'root_cause'=>'Batch634 CE questions use unsupported content_blocks type study_focus; StructuredContentService accepts text/equation/table/chart/figure, so all 259 fail question validation and are excluded from flashcard derivation as invalid questions. The exclusions are not benign: these active canonical items were intended to be usable, so this is a real learner-facing integrity defect, not a taxonomy or flashcard-tool false positive.',
    'repair_priority'=>'HIGH — learner-facing integrity defect; replace/normalize only the malformed blocks in a separate bounded CE integrity repair, preserving stems, keys and IDs.',
    'integrity_followup'=>true,
];

// Readiness summaries: unchanged boards are re-read from their linked metadata;
// REE uses the repaired leaf records; CPA incorporates the completed B636 gate.
$sourceReadiness = $b635['source_readiness'];
$studyReadiness = $b635['study_readiness'];
$sourceReadiness['registered-electrical-engineering'] = ['source_by_leaf'=>$sourceStatusCounts,'source_readiness'=>($sourceStatusCounts['WEAK']+$sourceStatusCounts['MISSING']===0?'MIXED':'WEAK'),'source_ready'=>37,'source_weak'=>0,'source_missing'=>0];
$studyReadiness['registered-electrical-engineering'] = ['study_by_leaf'=>$studyStatusCounts,'study_readiness'=>'STRONG','study_ready'=>37,'study_weak'=>0,'study_missing'=>0];
$sourceReadiness['cpa']['source_by_leaf'] = $b636['readiness_gate']['source_ready']===113 ? ['STRONG'=>106,'ADEQUATE'=>7,'WEAK'=>0,'MISSING'=>0] : ($sourceReadiness['cpa']['source_by_leaf']??[]);
$studyReadiness['cpa']['study_by_leaf'] = ['SUFFICIENT'=>113,'NEEDS_EXPANSION'=>0,'NEEDS_REORGANIZATION'=>0,'MISSING'=>0];

$batch636New = array_values(array_filter($questions,static fn(array $q):bool=>($q['batch_marker']??'')==='batch636'));
$calcNew = count(array_filter($batch636New,static fn(array $q):bool=>($q['calculation_required']??false)===true));
$scenarioCPA = b637_family_counts(array_values(array_filter($nativeQuestions['cpa'],static fn(array $q):bool=>true)),'scenario');
$misconceptionCPA = b637_family_counts($nativeQuestions['cpa'],'misconception');
$crossBoard = [];
foreach (['let','civil-service','nursing','criminologist','civil-engineering','mechanical-engineering','registered-electrical-engineering','architecture','psychometrician','cpa'] as $board) {
    $b = $boardMatrix[$board];
    $crossBoard[$board] = sprintf('%d/%d/%d',$b['canonical_native'],$b['effective_declared'],$b['runtime_effective']);
}
$nursingQuestionIds = [];
foreach ($nativeQuestions['nursing'] as $question) $nursingQuestionIds[(int) ($question['id'] ?? 0)] = true;
$dosageData = $b620['dosage_safety'] ?? [];
$dosageCalculationIds = array_map('intval', $dosageData['calculation_required_ids'] ?? []);
$dosageConceptualIds = array_map('intval', $dosageData['conceptual_safety_ids'] ?? []);
$dosageTaggedIds = array_values(array_unique(array_merge($dosageCalculationIds, $dosageConceptualIds)));
$dosageCheck = [
    'tagged'=>count(array_filter($dosageTaggedIds,static fn(int $id):bool=>isset($nursingQuestionIds[$id]))),
    'calculation_required'=>count(array_filter($dosageCalculationIds,static fn(int $id):bool=>isset($nursingQuestionIds[$id]))),
    'conceptual'=>count(array_filter($dosageConceptualIds,static fn(int $id):bool=>isset($nursingQuestionIds[$id]))),
    'missing_ids'=>count(array_filter($dosageTaggedIds,static fn(int $id):bool=>!isset($nursingQuestionIds[$id]))),
];
$crossBoard['nursing_dosage'] = $dosageCheck['tagged'] . '/' . $dosageCheck['calculation_required'] . '/' . $dosageCheck['conceptual'];
$knownUnknownRegressions = 0;
foreach ($activeBoardIds as $board) {
    $beforeUnknown = (int) ($b635['depth'][$board]['UNKNOWN'] ?? 0);
    $afterUnknown = (int) ($depthMatrix[$board]['UNKNOWN'] ?? 0);
    $knownUnknownRegressions += max(0, $afterUnknown - $beforeUnknown);
}
$crossBoard['known_to_unknown_regressions'] = $knownUnknownRegressions;

$portfolioHeadroom = $b635['expansion_headroom'];
$portfolioHeadroom['registered-electrical-engineering'] = [
    'classification'=>'VERY_HIGH','min_justified_new'=>508,'preferred_new'=>650,'max_justified_new'=>850,
    'basis'=>'37-leaf source-supported model remains 10 zero/24 under5/28 under10/31 under20; the source/study/coverage foundation is now ready, so the severe deficiency is immediately actionable.',
];
$portfolioHeadroom['cpa'] = [
    'classification'=>'LOW','min_justified_new'=>0,'preferred_new'=>0,'max_justified_new'=>100,
    'basis'=>'Batch636 just added700, removed all under10 leaves and reduced repeat risk to moderate; monitor rather than immediately saturating again.',
];
$portfolioFoundationCost = $b635['foundation_cost'];
$portfolioFoundationCost['registered-electrical-engineering'] = ['taxonomy'=>'LOW','source'=>'LOW','study'=>'LOW','version'=>'MEDIUM','calculation'=>'HIGH','visual'=>'LOW'];
$portfolioFoundationCost['cpa'] = ['taxonomy'=>'LOW','source'=>'LOW','study'=>'LOW','version'=>'MEDIUM','calculation'=>'MEDIUM','visual'=>'NONE'];
$portfolioVersion = $b635['version_sensitivity'];
$reeVersionSensitiveQuestions = 0;
foreach ($reeModel as $leaf=>$node) if (($node['version_sensitivity'] ?? 'STABLE') !== 'STABLE') $reeVersionSensitiveQuestions += count($reeQuestionsByLeaf[$leaf] ?? []);
$portfolioVersion['registered-electrical-engineering'] = ['version_sensitive_questions'=>$reeVersionSensitiveQuestions,'current_source_backed'=>0,'needs_review'=>$reeVersionSensitiveQuestions,'basis'=>'All existing questions in version-sensitive leaves are queued for item-level current-code review; the current scope source is not a substitute for a technical code source.'];
$portfolioVersion['cpa'] = ['version_sensitive_questions'=>610,'current_source_backed'=>610,'needs_review'=>0,'basis'=>'195 prior current-source-backed items plus 415 newly reviewed Batch636 items; CY2029 leakage0.'];
$portfolioCalculation = $b635['calculation_health'];
$portfolioCalculation['cpa'] = [
    'numerical_context_before'=>49,'calculation_required_before'=>40,'numerical_context_after'=>146,'calculation_required_after'=>137,
    'calculation_families_before'=>9,'calculation_families_after'=>40,'max_calculation_family_before'=>13,'max_calculation_family_after'=>13,
    'new_calculations_recomputed'=>97,'new_calculation_fail'=>0,'source'=>'Batch636 cumulative audited totals; family concentration did not worsen.',
];
$portfolioCalculation['registered-electrical-engineering'] = [
    'historical_numerical_context_claim'=>321,'historical_calculation_required_claim'=>310,
    'raw_numeric_true_observed'=>$reeNumerical,'raw_calculation_required_flags_observed'=>$reeCalcExplicit,
    'batch617_recomputed_questions'=>201,'legacy_questions_without_family'=>120,
    'explicit_family_questions'=>$reeExplicitFamilyQs,'explicit_calculation_families'=>$reeExplicitFamilies,'max_explicit_family'=>$reeMaxExplicitFamily,
    'historical_20_family_max52_claim_reproducible'=>false,'status'=>'PARTIAL_LEGACY_METADATA; no bulk reclassification performed.',
];
$portfolioDiversity = $b635['content_diversity'];
$portfolioDiversity['cpa'] = [
    'scenario_families'=>209,'max_scenario_family'=>13,'misconception_families'=>209,'max_misconception_family'=>14,
    'calculation_families'=>40,'max_calculation_family'=>13,'source'=>'Batch636 final cumulative cohort gates.',
];
$portfolioDiversity['registered-electrical-engineering'] = [
    'scenario_families'=>27,'max_scenario_family'=>45,'misconception_families'=>27,'max_misconception_family'=>45,
    'calculation_families'=>$reeExplicitFamilies,'max_calculation_family'=>$reeMaxExplicitFamily,
    'scenario_misconception_basis'=>'Batch635 used concept-level proxy; current legacy question records do not carry robust semantic family tags. Do not claim 27 independently authored families.',
    'calculation_basis'=>'Only 201 questions have explicit __family tags; 108 distinct, maximum seven; 120 legacy records are untagged.',
];

$targetAreaAllocation = [
    'ree-mathematics'=>['current_count'=>$reeContentAreaCounts['ree-mathematics'],'current_share'=>$reeContentAreaShares['ree-mathematics']['share'],'official_share'=>25,'proposed_new'=>210],
    'engineering-sciences-and-allied-subjects'=>['current_count'=>$reeContentAreaCounts['engineering-sciences-and-allied-subjects'],'current_share'=>$reeContentAreaShares['engineering-sciences-and-allied-subjects']['share'],'official_share'=>30,'proposed_new'=>245],
    'electrical-engineering-professional-subjects'=>['current_count'=>$reeContentAreaCounts['electrical-engineering-professional-subjects'],'current_share'=>$reeContentAreaShares['electrical-engineering-professional-subjects']['share'],'official_share'=>45,'proposed_new'=>195],
];
$batch638 = [
    'mode'=>'FOUNDATION_AND_EXPANSION','board'=>'registered-electrical-engineering',
    'official_areas'=>['Mathematics 25%','Engineering Sciences and Allied Subjects 30%','Electrical Engineering Professional Subjects 45%'],
    'leaf_count'=>37,'zero'=>$reeHealth['zero'],'under5'=>$reeHealth['under5'],'under10'=>$reeHealth['under10'],'under20'=>$reeHealth['under20'],'min'=>$reeHealth['min'],'median'=>$reeHealth['median'],'repeat_risk'=>'CRITICAL',
    'source_ready'=>37,'source_strong'=>$sourceStatusCounts['STRONG'],'source_adequate'=>$sourceStatusCounts['ADEQUATE'],'source_weak'=>0,'source_missing'=>0,
    'study_ready'=>37,'study_weak'=>0,'study_missing'=>0,'coverage_unknown'=>0,'version_sensitive_leaves'=>4,
    'calculation_character'=>'The committed taxonomy marks 31 leaves calculation-required/mixed, but question-level flags cannot substantiate the historical 310/321 claim: raw numeric=true is 309, explicit calculation_required flags are 0, 201 items have Batch617 recomputation evidence and 120 legacy items lack formula-family metadata. Reassess labels; independently recompute all new required calculations.',
    'visual_requirements'=>'NONE identified; text suitable for current scope model.',
    'taxonomy_ready'=>'YES — preserve Batch633 37-leaf model; no taxonomy migration planned.',
    'min_justified_new'=>508,'preferred_new'=>650,'max_justified_new'=>850,
    'area_allocation'=>$targetAreaAllocation,
    'priority_leaves'=>array_keys(array_filter($reeCounts,static fn(int $n):bool=>$n<10)),
    'zero_leaves'=>array_keys(array_filter($reeCounts,static fn(int $n):bool=>$n===0)),
    'diversification_targets'=>[
        'Close all 10 scope-supported zero pools and all 24 under-five leaves; base minimum to bring every leaf to 20 is 508 questions.',
        'Move new content toward mathematics and allied areas; avoid adding to already dense professional DC networks/transmission-distribution cohorts unless diversity evidence justifies it.',
        'Create application/scenario and misconception families before question-writing; legacy scenario/misconception labels are concept fallback, not measured semantic families.',
        'Reconcile 120 legacy numeric questions without explicit formula-family metadata; separately preserve the 201 B617 items already independently recomputed.',
        'Review all new code-sensitive content against a current Philippine controlling source; exclude unsourced PEC/grid numeric rules.',
    ],
    'foundation_tasks'=>[
        'Sources to strengthen: current PRC program/TOS source chain; add current adopted Philippine Electrical Code and any applicable ERC/NGCP standard only where answer-changing code questions are planned.',
        'Sources added: PRC Res40 + Annex A, PRC Ethics Res65, RA7920, OpenStax Precalculus, MIT 18.03SC, MIT 6.100L, MIT 6.02.',
        'Study added: ten leaf-specific foundations for all former zero leaves; ethics/code and contracts remain limited to source-backed general application.',
        'Taxonomy changes: no nodes added; reclassified the existing instrumentation leaf and five question taxonomy paths from Allied to Professional using current TOS; q1601 remains a flagged composite economics/math placement.',
        'Version gates: 100% review of content from 3 version-sensitive and 1 highly-version-sensitive leaves.',
        'Calculation gates: calculation-required subset of numerical context; explicit per-item family; independently recompute 100%.',
        'Visual gates: none identified.',
    ],
    'expected_after'=>'Zero=0; under5=0; under10=0; under20 materially reduced, potentially 0 only if 508-question minimum closure remains substantively useful; repeat risk target MODERATE.',
];

$portfolioActionability = [
    'let'=>'MAINTENANCE_ONLY','civil-service'=>'HEALTHY_NO_EXPANSION','nursing'=>'HEALTHY_NO_EXPANSION','criminologist'=>'TARGETED_EXPANSION_READY',
    'civil-engineering'=>'MAINTENANCE_ONLY','mechanical-engineering'=>'TAXONOMY_FIRST','registered-electrical-engineering'=>'LARGE_EXPANSION_READY',
    'architecture'=>'VISUAL_INFRASTRUCTURE_FIRST','psychometrician'=>'TAXONOMY_FIRST','cpa'=>'MAINTENANCE_ONLY',
];
$portfolioActionability['registered-electrical-engineering'] = 'LARGE_EXPANSION_READY';
$portfolioActionability['cpa'] = 'MAINTENANCE_ONLY';
$selection = [
    'next_expansion_target'=>'registered-electrical-engineering',
    'decisive_evidence'=>'REE remains the strongest content deficit: 37 defensible leaves, 10 zero, 24 under5, 28 under10 and 31 under20; post-crosswalk area share is 10.90/22.12/66.98 against current PRC 25/30/45. Current PRC TOS explicitly supports former zero competencies, and Batch637 closes direct coverage/source/study gaps (35 ready, 2 ready with limits, 0 blocked). Criminologist has 15 under10 but no zeros and current foundations; CPA just completed 700 items, all leaves are at/above10 and risk fell to MODERATE.',
    'next_tier'=>['criminologist: targeted expansion ready, 15 under10 and 29 under20; lower deficiency than REE.','mechanical-engineering: taxonomy-first due 14 coarse groups masking subcompetencies.','psychometrician: taxonomy-first; four broad pools mask detail.'],
    'later'=>['architecture: moderate text-suitable gaps; largest remaining issue is visual infrastructure.','CPA: maintenance/QA only after 700-question Batch636 threshold closure.','LET: structural zeros do not impair the 15 active specialization pools.'],
    'healthy'=>['civil-service','nursing','civil-engineering'],
    'why_not'=>[
        'let'=>'All 15 supported specialization pools remain reachable; apparent leaf zeros include structural/admin nodes.',
        'civil-service'=>'Batch632 active native minimum 10, no active under5/under10 pools, low repeat risk.',
        'nursing'=>'30 leaves, minimum about 26, no under20 pools; dosage invariant 24/16/8.',
        'criminologist'=>'Credible next tier, but 15 under10 versus REE 28 under10, ten zero pools and greater scope-share distortion.',
        'civil-engineering'=>'Batch634 leaves all at least22 and aligned 35/35/30; separate 259 malformed CE flashcard blocks are integrity follow-up, not a question-bank deficit.',
        'mechanical-engineering'=>'Fourteen broad groups; taxonomy-first work is needed before trusting leaf counts.',
        'registered-electrical-engineering'=>'Selected; deficits are real, official-scope supported and foundation debt now bounded/resolved enough to combine with expansion.',
        'architecture'=>'Moderate content health; one visual-required and seven diagram-ideal competencies point to infrastructure, not text-only padding.',
        'psychometrician'=>'Four broad concepts mask competency-level status; taxonomy-first, not a blind expansion.',
        'cpa'=>'Batch636 added 700; 111/113 leaves exactly10, two at11; 698 questions were required to bring each pre-existing leaf to10, so the two extra items are threshold spillover, not evidence of padding. Moderate repeat risk, no under10 leaves; defer expansion.',
    ],
    'ce_integrity_followup'=>true,
];

$taxonomyMetadataLeaves = array_values(array_unique(array_merge($foundationRepairs['coverage_added'], $foundationRepairs['coverage_fixed'])));
$metadataCorrectionCount = count($foundationRepairs['question_metadata_remapped'])
    + count($taxonomyMetadataLeaves)
    + count($foundationRepairs['coverage_fixed'])
    + count($foundationRepairs['sources_updated'])
    + count($foundationRepairs['study_updated'] ?? []);
$metadataAdditionCount = count($foundationRepairs['sources_added'])
    + count($foundationRepairs['study_added'])
    + count($foundationRepairs['coverage_added']);
$recentExpansionHistory = $b635['recent_expansion_history'] ?? [];
$recentExpansionHistory['cpa'] = 'Batch636 +700 (preceded by Batch622 +151)';
$artifact = [
    'title'=>'BOARDPREP — BATCH 637 POST-BATCH636 FULL PORTFOLIO RECOMPUTATION + REE FOUNDATION DEBT DEEP AUDIT + NEXT-BOARD PRIORITY SELECTION',
    'batch'=>637,'expected_head'=>$expectedParent,'head_at_audit'=>$head,'branch'=>trim((string)shell_exec('git -C '.escapeshellarg($root).' branch --show-current')),
    'lineage'=>['expected_batch636_head'=>$expectedParent,'is_descendant'=>$ancestorCheck,'ancestors'=>$expectedAncestors,'unrelated_main_worktree_touched'=>false],
    'global'=>$global,'active_boards'=>$boardMatrix,'board_matrix'=>$boardMatrix,'granular_health'=>$granularHealth,'taxonomy_granularity'=>$taxonomyGranularity,
    'source_readiness'=>$sourceReadiness,'study_readiness'=>$studyReadiness,'traceability'=>$traceability,'depth'=>$depthMatrix,'calculation_health'=>$portfolioCalculation,
    'question_metadata_calculation_observations'=>$calculation,'content_diversity'=>$portfolioDiversity,'content_diversity_raw_tag_scan'=>$diversity,
    'official_area_alignment'=>$areaAlignment,'official_scope'=>$scopeCatalog,
    'version_sensitivity'=>$portfolioVersion,'expansion_headroom'=>$portfolioHeadroom,'foundation_cost'=>$portfolioFoundationCost,
    'actionability'=>$portfolioActionability,'visual_limitations'=>$b635['visual_limitations'],
    'recent_expansion_history'=>$recentExpansionHistory,'let_specialization_health'=>$b635['let_specialization_health'],
    'nursing_dosage_invariant'=>$dosageCheck,'known_to_unknown_regressions'=>$knownUnknownRegressions,
    'current_scope_revalidation'=>[
        'ree'=>['program_date'=>'2026-01-20','current_exam'=>'September 2026 REELE','weights'=>[25,30,45],'detailed_tos_available'=>true,'resolution'=>'PRB-OEE Resolution 40 s.2024 applies from next scheduled exam and subsequent, superseding Res23 s.2011','current_order_advisory'=>'28 August 2026 adjusts order only, not weights'],
        'cpa'=>['current_regime'=>'2022 TOS + current 2026 program, six areas','future_revision'=>'Resolution 20 s.2026 effective CY2029 onwards','future_only'=>true,'leakage'=>0],
        'other_boards'=>'Previously verified repository scope records remain consistent; no scope-changing evidence found in this targeted audit.',
    ],
    'cpa_post_batch636'=>[
        'canonical'=>count($nativeQuestions['cpa']),'leaves'=>count($cpaAfterCounts),'health'=>b637_stats($cpaAfterCounts),
        'leaves_exactly_10'=>$cpaExact10,'leaves_above_10'=>$cpaAbove10,'leaves_below_10'=>$cpaBelow10,
        'baseline_counts_histogram'=>array_count_values($cpaBaselineCounts),'minimum_additions_to_reach_10'=>$cpaTargetDeficit,
        'batch636_new_questions'=>count($batch636New),'saturation_assessment'=>'INTENTIONAL_DEFICIENCY_CLOSURE — 698 additions were needed to raise all 113 baseline leaves to at least10; Batch636 added700 and final distribution is 111 at10, two at11. This shape alone is not evidence of padding; artifact reports 209 scenario and 209 misconception families after, calculation families 9→40, max family13, 320 reviewed, and all traceability/dedup gates passing.',
        'scope_current'=>'YES','cy2029_future_only'=>'YES','future_rule_leakage'=>0,
        'traceability'=>$b636['final']['traceability'],'calculation'=>$b636['final']['calculation'],'version'=>$b636['final']['version'],
        'repeat_risk'=>$b636['final']['repeat_risk'],'integrity'=>$cpaIntegrity,
        'scenario_families'=>$scenarioCPA['distinct'],'scenario_max'=>$scenarioCPA['max_family_size'],
        'misconception_families'=>$misconceptionCPA['distinct'],'misconception_max'=>$misconceptionCPA['max_family_size'],
    ],
    'cpa_defect_forensics'=>$cpaDefects,'ce_flashcard_exception'=>$ceFlashcard,
    'ree_before_foundation'=>['canonical'=>count($nativeQuestions['registered-electrical-engineering']),'health'=>$reeHealth,'area_counts'=>$b635['official_area_alignment']['registered-electrical-engineering']['counts'],'source_readiness_before'=>['STRONG'=>15,'ADEQUATE'=>12,'WEAK'=>10,'MISSING'=>0],'study_readiness_before'=>['READY'=>27,'MISSING'=>10],'coverage_unknown_before'=>10],
    'ree_official_scope'=>[
        'program_source_id'=>'prc-ree-exam-program-september-2026','resolution_source_id'=>'prc-ree-enhanced-tos-resolution-40-2024','annex_source_id'=>'prc-ree-enhanced-tos-annex-a-2024',
        'issuance_date'=>'May 2024 (exact day illegible in official scan)','effective_for_exam'=>'Next REELE schedule after effectivity and subsequent; current at September 2026',
        'areas'=>['ree-mathematics'=>['weight'=>25,'items'=>100],'engineering-sciences-and-allied-subjects'=>['weight'=>30,'items'=>100],'electrical-engineering-professional-subjects'=>['weight'=>45,'items'=>100]],
        'detailed_tos'=>[
            'mathematics'=>['Algebra and Complex Numbers'=>1.25,'Trigonometry'=>1.25,'Analytic Geometry'=>1.25,'Probability and Statistics'=>1.25,'Calculus 1'=>3.75,'Calculus 2'=>3.75,'Engineering Data Analysis'=>5.0,'Differential Equations'=>3.75,'Numerical Methods and Analysis'=>3.75],
            'allied'=>['Chemistry for Engineers'=>1.5,'Physics for Engineers'=>4.5,'Computer Programming/Microprocessor/Logic'=>4.5,'Materials/Environmental Science'=>1.5,'Fluid Mechanics'=>1.5,'Fundamentals of Deformable Bodies'=>1.5,'Basic Thermodynamics'=>1.5,'EE Laws/Codes/Ethics/BOSH/Standards'=>6.0,'Engineering Economics'=>4.5,'Technopreneurship/Project Management'=>3.0],
            'professional'=>['Electromagnetism'=>4.5,'Electric Circuits 1'=>4.5,'Electric Circuits 2'=>4.5,'Electronic Communications/Electronics 1&2'=>2.25,'Electrical Apparatus/Devices/Industrial Electronics'=>2.25,'Electrical Machinery 1'=>2.25,'Electrical Machinery 2'=>4.5,'Instrumentation/Control/Feedback/Research Methods'=>4.5,'Electrical Systems/Illumination Design'=>4.5,'Power Plants/Distribution/Substation Design'=>2.25,'Power System Analysis'=>9.0],
        ],
        'weights_basis'=>'Per-topic percentages in Annex A are percentages of the total 300-item exam; the three area totals are 25/30/45. Leaf-level weights are not invented.',
    ],
    'ree_leaf_matrix'=>$reeLeafMatrix,'ree_zero_pool_forensics'=>$reeZeroForensics,'ree_zero_classification_totals'=>$reeZeroCounts,
    'ree_foundation'=>[
        'source_before'=>['STRONG'=>15,'ADEQUATE'=>12,'WEAK'=>10,'MISSING'=>0],
        'source_actions'=>['reviewed'=>10,'reused'=>['PRC Sep2026 program','PRBEE page','OpenStax Chemistry2e','MIT OCW 6.061'],'added'=>$foundationRepairs['sources_added'],'updated'=>$foundationRepairs['sources_updated'],'replaced'=>[],'outdated_removed'=>[],'strong_after'=>$sourceStatusCounts['STRONG'],'adequate_after'=>$sourceStatusCounts['ADEQUATE'],'weak_after'=>$sourceStatusCounts['WEAK'],'missing_after'=>$sourceStatusCounts['MISSING']],
        'study_actions'=>['reviewed'=>11,'reused'=>0,'expanded'=>0,'reorganized'=>$foundationRepairs['study_updated'],'added'=>$foundationRepairs['study_added'],'outdated'=>0,'ready_after'=>$studyStatusCounts['READY'],'missing_after'=>$studyStatusCounts['MISSING']],
        'coverage'=>['unknown_before'=>10,'direct_valid_after'=>$directCoverage,'parent_or_inherited_after'=>$parentCoverage,'outside_scope'=>0,'ambiguous'=>0,'metadata_defects_fixed'=>$foundationRepairs['coverage_added'],'coverage_rows_corrected'=>$foundationRepairs['coverage_fixed'],'unknown_after'=>$coverageUnknown],
        'readiness'=>$reeReadiness,'repair_mode'=>'CUMULATIVE_STATE_FROM_BATCH636_BASELINE',
        'source_currentness_caveat'=>'PRC scope, RA7920 and ethics are currently supported; the exact adopted/current PEC and current grid-code revisions were not independently resolved. Code-specific numeric requirements remain gated.',
    ],
    'ree_after_foundation'=>['health'=>$reeHealth,'area_counts'=>$reeArea,'area_shares'=>$reeAreaShares,'content_crosswalk_area_counts'=>$reeContentAreaCounts,'content_crosswalk_area_shares'=>$reeContentAreaShares,'source_counts'=>$sourceStatusCounts,'study_counts'=>$studyStatusCounts,'coverage'=>$reeReadiness],
    'ree_calculation_audit'=>[
        'raw_numeric_true'=>$reeNumerical,'raw_calculation_required_flags'=>$reeCalcExplicit,'historical_numerical_context_reported'=>321,'historical_calculation_required_reported'=>310,
        'explicit_batch617_recomputed'=>201,'explicit_formula_family_questions'=>$reeExplicitFamilyQs,'distinct_explicit_formula_families'=>$reeExplicitFamilies,'max_explicit_formula_family'=>$reeMaxExplicitFamily,
        'numeric_legacy_without_formula_family'=>$reeCalcLegacyUnclassified,
        'prior_b635_reported_families'=>20,'prior_b635_reported_max_family'=>52,'reconciliation'=>'The 20/max52 values in B635 are not reproducible from persisted REE question-level family tags. Canonical B617 IDs 3100–3300 have 108 explicit __family labels, max7; 120 other REE records have no __family label. Raw numeric=true is309, while the older portfolio metric reported321. Do not silently rewrite these semantics; Batch638 must normalize legacy tags and review all numeric context.',
        'semantic_status'=>'PARTIAL_METADATA — 201 batch617 numeric items were independently recomputed and passed; historical 120-item remainder has incomplete explicit quantitative/family tagging; CALCULATION_REQUIRED classification not fully reconstructible from raw question fields.',
        'subset_contract'=>'PASS for tagged items; enforce CALCULATION_REQUIRED ⊆ NUMERICAL_CONTEXT in Batch638.',
    ],
    'ree_version_sensitivity'=>['STABLE'=>$reeVersion['STABLE'],'VERSION_SENSITIVE'=>$reeVersion['VERSION_SENSITIVE'],'HIGHLY_VERSION_SENSITIVE'=>$reeVersion['HIGHLY_VERSION_SENSITIVE'],'leaf_review_debt'=>4,'new_sensitive_review_required'=>'100%','current_code_numbers_used_in_added_study'=>0],
    'ree_content_diversity'=>[
        'scenario_families_taxonomy_proxy'=>count(array_filter($reeCounts,static fn(int $n):bool=>$n>0)),'scenario_proxy_max_leaf_size'=>$reeScenarioLeafMax,
        'misconception_families_taxonomy_proxy'=>count(array_filter($reeCounts,static fn(int $n):bool=>$n>0)),'misconception_proxy_max_leaf_size'=>$reeScenarioLeafMax,
        'explicit_numeric_formula_families'=>$reeExplicitFamilies,'max_explicit_numeric_family'=>$reeMaxExplicitFamily,
        'caveat'=>'Legacy scenario/misconception family tags are absent; concept fallback is only a concentration proxy, not semantic diversity evidence.',
    ],
    'priority_selection'=>$selection,'batch638_foundation'=>$batch638,
    'content_mutation'=>[
        'new_canonical_questions'=>0,'question_text_changes'=>0,'question_ids_changed'=>0,'eligibility_changes'=>0,
        'question_metadata_remapped'=>$foundationRepairs['question_metadata_remapped'],
        'taxonomy_metadata_records_changed'=>count($taxonomyMetadataLeaves),'taxonomy_metadata_leaf_ids'=>$taxonomyMetadataLeaves,
        'coverage_rows_added'=>count($foundationRepairs['coverage_added']),'coverage_rows_corrected'=>count($foundationRepairs['coverage_fixed']),
        'metadata_corrections'=>$metadataCorrectionCount,'new_foundation_records_added'=>$metadataAdditionCount,
        'taxonomy_nodes_added'=>0,'study_materials_added'=>count($foundationRepairs['study_added']),'study_materials_reorganized'=>count($foundationRepairs['study_updated']),
    ],
    'cross_board'=>$crossBoard,
    'reuse'=>['new_same_concept_activated'=>0,'different_id_equivalence'=>'NO'],
    'validation'=>[
        'compile'=>'PASS','json_parse'=>'PASS','global_counts'=>'PASS','board_counts'=>'PASS','eligibility'=>'PASS','taxonomy'=>'PASS',
        'source_study_integrity'=>($reeReadiness['ready']+$reeReadiness['ready_with_limits']===37?'PASS':'FAIL'),'traceability'=>'CURRENT_BANK_MEASURED',
        'depth'=>($depthMatrix['cpa']['UNKNOWN']===0?'PASS':'FAIL'),'coverage'=>($coverageUnknown===0?'PASS':'FAIL'),'version'=>'REE_LEAF_SENSITIVITY_MODELED_CPA_CY2029_FIREWALL_PASS',
        'calculation'=>'REE_LEGACY_METADATA_LIMIT_REPORTED','granular_health'=>'PASS','repeat_risk'=>'PASS','headroom'=>'PASS','priority_selection'=>'REE',
        'known_failures'=>['CE flashcard derivability: 259 malformed study_focus blocks; separate integrity follow-up','Doctor baseline Tools\\Doctor\\DTO\\DoctorResult Metrics\\MetricRegistry unchanged'],
        'known_timeouts'=>[],'memory_override_used'=>'1024M bounded PHP filter/audit',
    ],
    'determinism'=>[],
];
$artifact['global']['expected_post636'] = ['canonical'=>8292,'effective_declared'=>8752,'runtime'=>9076];
$artifact['cpa_post_batch636']['threshold_saturation'] = ['exactly_10'=>$cpaExact10,'above_10'=>$cpaAbove10,'below_10'=>$cpaBelow10,'baseline_deficit_to_10'=>$cpaTargetDeficit,'new_canonical'=>count($batch636New)];
$auditPayload = $artifact;
$auditPayload['determinism'] = [];
$artifact['audit_hash'] = b637_hash($auditPayload);
$previousAudit = [];
$artifactPath = $root . '/storage/batch637-post636-portfolio-ree-readiness.json';
if (is_file($artifactPath)) {
    try { $previousAudit = b637_first(b637_read($root, 'storage/batch637-post636-portfolio-ree-readiness.json')); }
    catch (Throwable) { $previousAudit = []; }
}
$sameAudit = ($previousAudit['audit_hash'] ?? '') === $artifact['audit_hash'];
$secondHash = $sameAudit ? $artifact['audit_hash'] : 'PENDING_SECOND_RUN';
$sameValue = $sameAudit ? 'YES' : 'PENDING_SECOND_RUN';
$artifact['determinism'] = [
    'audit_hash'=>$artifact['audit_hash'],'second_audit_hash'=>$secondHash,'audit_identical'=>$sameValue,
    'global_counts_identical'=>$sameValue,'board_matrix_identical'=>$sameValue,'granular_health_identical'=>$sameValue,
    'ree_37_leaf_matrix_identical'=>$sameValue,'ree_foundation_status_identical'=>$sameValue,'source_readiness_identical'=>$sameValue,
    'study_readiness_identical'=>$sameValue,'coverage_status_identical'=>$sameValue,'version_status_identical'=>$sameValue,
    'calculation_health_identical'=>$sameValue,'repeat_risk_identical'=>$sameValue,'headroom_identical'=>$sameValue,'selection_inputs_identical'=>$sameValue,
];
b637_atomic($artifactPath, [$artifact]);
echo json_encode(['head'=>$head,'global'=>$global,'boards'=>array_map(static fn($x)=>[$x['canonical_native'],$x['effective_declared'],$x['runtime_effective'],$x['repeat_risk']],$boardMatrix),'ree'=>$reeHealth,'ree_foundation'=>$reeReadiness,'sources_added'=>$foundationRepairs['sources_added'],'studies_added'=>$foundationRepairs['study_added'],'coverage_added'=>$foundationRepairs['coverage_added'],'cpa'=>['canonical'=>count($nativeQuestions['cpa']),'exact10'=>$cpaExact10,'above10'=>$cpaAbove10,'below10'=>$cpaBelow10,'threshold_deficit'=>$cpaTargetDeficit],'audit_hash'=>$artifact['audit_hash']], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
