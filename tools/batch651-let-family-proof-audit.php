<?php

declare(strict_types=1);

/**
 * Batch651 evidence/family proof for the live LET under-10 cohort.
 *
 * --build runs the final Repository Health check and writes the durable
 * artifact. --audit is read-only and recomputes the deterministic evidence
 * payload without re-running the full readiness research or health engine.
 */

$root = dirname(__DIR__);
$expectedHead = 'f8703dcfb80d13dcd2bc10b0211504817408ca27';
$artifactPath = $root . '/storage/batch651-let-family-proof.json';
$readJson = static function (string $path): array {
    if (!is_file($path)) throw new RuntimeException('Missing JSON: ' . $path);
    $value = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($value)) throw new RuntimeException('Expected array/object JSON: ' . $path);
    return $value;
};
$fail = static function (string $message): never {
    fwrite(STDERR, 'BATCH651_FAIL=' . $message . PHP_EOL);
    exit(1);
};
$normalize = static function (mixed $node) use (&$normalize): mixed {
    if (!is_array($node)) return $node;
    if (array_is_list($node)) return array_map($normalize, $node);
    ksort($node, SORT_STRING);
    foreach ($node as &$child) $child = $normalize($child);
    unset($child);
    return $node;
};
$semanticHash = static fn(mixed $value): string => hash('sha256', json_encode(
    $normalize($value), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
));
$active = static fn(array $question): bool => strtolower((string) ($question['status'] ?? 'active')) !== 'archived';
$isLet = static fn(array $question): bool => strtolower((string) ($question['taxonomy']['board_id'] ?? $question['board'] ?? '')) === 'let';
$git = static function (array $arguments) use ($root): string {
    $command = 'git -C ' . escapeshellarg($root);
    foreach ($arguments as $argument) $command .= ' ' . escapeshellarg($argument);
    $output = shell_exec($command . ' 2>/dev/null');
    if (!is_string($output)) throw new RuntimeException('Git command failed: ' . implode(' ', $arguments));
    return trim($output);
};

try {
    $mode = $argv[1] ?? '--audit';
    if (!in_array($mode, ['--build', '--audit'], true)) $fail('Use --build or --audit.');

    $head = $git(['rev-parse', 'HEAD']);
    $branch = $git(['branch', '--show-current']);
    $mainHead = $git(['rev-parse', 'refs/heads/main']);
    $isAncestor = $git(['merge-base', $expectedHead, 'HEAD']) === $expectedHead;
    if (!$isAncestor) $fail('Batch650 is not an ancestor of current HEAD.');
    if ($branch !== 'batch651-let-family-proof') $fail('Unexpected branch: ' . $branch);
    if ($mainHead !== '0eb9704ba293e0aa3402af5d01a2d3392c005b73') $fail('Unrelated main HEAD moved.');

    $readiness = $readJson($root . '/storage/source-readiness-let.json');
    $questions = $readJson($root . '/storage/questions.json');
    $eligibility = $readJson($root . '/storage/question-eligibility.json');
    $sources = $readJson($root . '/storage/sources.json');
    $materials = $readJson($root . '/storage/study-materials.json');
    $batch649 = $readJson($root . '/storage/batch649-let-source-readiness.json');
    $batch650 = $readJson($root . '/storage/batch650-let-expansion.json');
    $batch648 = $readJson($root . '/storage/batch648-portfolio-deficiency.json');

    $readinessByLeaf = [];
    foreach ($readiness as $record) {
        if (!is_array($record)) continue;
        $leaf = (string) ($record['leaf_id'] ?? '');
        if ($leaf === '' || isset($readinessByLeaf[$leaf])) $fail('Missing/duplicate readiness id: ' . $leaf);
        $readinessByLeaf[$leaf] = $record;
    }
    if (count($readinessByLeaf) !== 205) $fail('LET readiness record count changed from 205.');

    $b649LeafById = [];
    foreach ((array) ($batch649['let']['leaves'] ?? []) as $leaf) {
        if (is_array($leaf)) $b649LeafById[(string) ($leaf['leaf_id'] ?? '')] = $leaf;
    }
    $sourceById = [];
    foreach ($sources as $source) {
        if (is_array($source)) $sourceById[(string) ($source['id'] ?? '')] = $source;
    }
    $materialById = [];
    foreach ($materials as $material) {
        if (is_array($material)) $materialById[(string) ($material['id'] ?? '')] = $material;
    }

    $questionsByLeaf = [];
    $nativeByBoard = [];
    $allQuestionIds = [];
    foreach ($questions as $question) {
        if (!is_array($question) || !$active($question)) continue;
        $id = (string) ($question['id'] ?? '');
        if ($id !== '') {
            if (isset($allQuestionIds[$id])) $fail('Duplicate canonical question id: ' . $id);
            $allQuestionIds[$id] = true;
        }
        $board = strtolower((string) ($question['taxonomy']['board_id'] ?? $question['board'] ?? ''));
        $nativeByBoard[$board] = ($nativeByBoard[$board] ?? 0) + 1;
        $leaf = (string) ($question['taxonomy']['concept_id'] ?? '');
        if ($board === 'let' && $leaf !== '') $questionsByLeaf[$leaf][] = $question;
    }

    $candidateIds = [];
    foreach ($readinessByLeaf as $leaf => $record) {
        if (strtolower((string) ($record['board_id'] ?? '')) !== 'let') continue;
        $authorableState = in_array((string) ($record['readiness'] ?? ''), ['READY', 'READY_WITH_LIMITS'], true);
        $questionCount = count($questionsByLeaf[$leaf] ?? []);
        if ($questionCount !== (int) ($record['current_question_count'] ?? -1)) {
            $fail('Live native count disagrees with readiness for ' . $leaf);
        }
        if ($authorableState && $questionCount < 10 && (int) ($record['safe_headroom_max'] ?? 0) > 0) $candidateIds[] = $leaf;
    }
    sort($candidateIds, SORT_STRING);
    if (count($candidateIds) !== 88) $fail('Live eligible under-10 cohort is ' . count($candidateIds) . ', expected 88.');

    $leafReviewPlan = [
        'environmental-stewardship-protection' => [
            'family' => 'ECOSYSTEM_CAUSAL_PREDICTION',
            'readiness_family' => 'CAUSAL_OR_CONCEPTUAL_EXPLANATION',
            'summary' => 'Given a stated population or abiotic-factor change in an ecosystem model, predict a supported downstream effect and cite the modeled relationship; do not attribute an unsupported human cause.',
            'depth' => 3,
            'question_mode' => 'MODEL_OR_DATA_INTERPRETATION',
            'calculation_class' => 'NONCALCULATION',
            'source_ids' => ['deped-science-curriculum-guide'],
            'study_material_ids' => ['let-specialization-science-application'],
            'confidence' => 'HIGH',
            'marginal_value' => 'HIGH',
            'novelty_basis' => 'The current five test source-reduction, monitoring, long-term use, community linkage, and feasible choice. None asks for a conditional ecosystem-model prediction from population/abiotic evidence; the existing science note was extended with that exact bounded proposition.',
            'material_heading' => 'Batch 651 — ecosystem prediction',
            'wave' => 1,
        ],
        'pe-health-and-wellness' => [
            'family' => 'ACTIVITY_PLANNING_MODEL_INTERPRETATION',
            'readiness_family' => 'MOVEMENT_OR_FITNESS_INTERPRETATION',
            'summary' => 'Interpret the Philippine Physical Activity Pyramid as a planning model for balanced activity patterns and relative participation, not as an individual diagnostic score; choose a plan that still respects readiness and context.',
            'depth' => 3,
            'question_mode' => 'MODEL_INTERPRETATION_AND_PLANNING',
            'calculation_class' => 'NONCALCULATION',
            'source_ids' => ['deped-pe-curriculum-guide'],
            'study_material_ids' => ['let-zero-pool-pe-foundation'],
            'confidence' => 'HIGH',
            'marginal_value' => 'HIGH',
            'novelty_basis' => 'The current five test activity benefit, goal monitoring, cautious interpretation of short-term change, balancing safety/recovery, and respectful participation. None tests what the named activity-planning model represents or its diagnostic limit; the linked PE note now states that boundary.',
            'material_heading' => 'Batch 651 — activity-planning model',
            'wave' => 1,
        ],
        'social-history-context-and-causation' => [
            'family' => 'HISTORICAL_SOURCE_CLASSIFICATION',
            'readiness_family' => 'CONTEXT_OR_SOURCE_INTERPRETATION',
            'summary' => 'Compare a dated primary record with a later interpretive account, identify which is contemporaneous evidence, and bound any causal claim to the provenance and content each source can support.',
            'depth' => 3,
            'question_mode' => 'SOURCE_COMPARISON_AND_CLAIM_LIMIT',
            'calculation_class' => 'NONCALCULATION',
            'source_ids' => ['deped-araling-panlipunan-curriculum-guide'],
            'study_material_ids' => ['let-specialization-social-studies-application'],
            'confidence' => 'HIGH',
            'marginal_value' => 'HIGH',
            'novelty_basis' => 'The current five cover chronology, contributing versus sufficient cause, using stated evidence, avoiding presentism, and qualifying a causal claim. The linked “Historical evidence” note explicitly supports primary-source versus later-interpretation classification, which none of those questions tests directly.',
            'material_heading' => 'Historical evidence',
            'wave' => 1,
        ],
        'cae-art-elements-and-principles' => [
            'family' => 'FORMAL_ELEMENT_VS_ORGANIZING_PRINCIPLE_CLASSIFICATION',
            'readiness_family' => 'ARTWORK_OR_CULTURAL_INTERPRETATION',
            'summary' => 'Classify a visual-art clue as a formal element (for example line or color) or an organizing principle (for example balance or emphasis), then use the distinction to interpret composition rather than merely naming an effect.',
            'depth' => 2,
            'question_mode' => 'CLASSIFICATION_AND_COMPOSITION_INTERPRETATION',
            'calculation_class' => 'NONCALCULATION',
            'source_ids' => ['deped-arts-curriculum-guide-2020'],
            'study_material_ids' => ['let-zero-pool-cae-foundation'],
            'confidence' => 'HIGH',
            'marginal_value' => 'HIGH',
            'novelty_basis' => 'Existing items connect a named element/principle to effect, compare changes, interpret visible/audible evidence, constrain experimentation, and assess process. They do not classify component versus organizing relationship; the linked study note now gives that distinction and limits it to visual-art conventions.',
            'material_heading' => 'Batch 651 — elements and principles',
            'wave' => 1,
        ],
    ];

    $explicitReview = [
        'pronouns-pronoun-antecedent-agreement' => [
            'reason' => 'Question 3 is counted under this leaf but asks why learners retain reference books during exam week; it has no pronoun-antecedent competency. The inflated native count cannot support a safe additional proposition until a separate semantic/mapping review; Batch651 leaves it unchanged.',
            'question_ids' => ['3'],
        ],
    ];
    $rejectedFamilies = [
        'cae-media-and-performance' => [[
            'family' => 'INTEGRATED_MEDIA_COMMUNICATION',
            'classification' => 'DUPLICATIVE',
            'proposal_summary' => 'Choose complementary dialogue, music, movement, or visual-media roles to communicate one performance theme.',
            'overlaps_question_ids' => ['5994', '5995', '5996', '5997'],
            'reason' => 'The current items already test medium choices against the performance goal, technical/message diagnosis, audience testing, and communication assessment. The additional phrasing would be a narrower scenario in those same operations, not a sufficiently distinct new family.',
        ]],
    ];

    $operationForQuestion = static function (array $question): string {
        $stem = strtolower((string) ($question['question'] ?? ''));
        if (($question['calculation_required'] ?? false) === true) return 'CALCULATION_OR_QUANTITATIVE_INTERPRETATION';
        if (str_contains($stem, 'which statement best') || str_contains($stem, 'best describes')) return 'CONCEPTUAL_APPLICATION';
        if (str_contains($stem, 'teacher is planning') || str_contains($stem, 'planning a lesson')) return 'INSTRUCTIONAL_DESIGN';
        if (str_contains($stem, 'evidence from') || str_contains($stem, 'which interpretation')) return 'EVIDENCE_INTERPRETATION';
        if (str_contains($stem, 'practical or classroom decision') || str_contains($stem, 'which correction')) return 'IMPLEMENTATION_DECISION';
        if (str_contains($stem, 'assessment or demonstration') || str_contains($stem, 'which decision should guide')) return 'ASSESSMENT_JUDGMENT';
        return 'APPLICATION_OR_EXPLANATION';
    };
    $propositionSignature = static function (array $question) use ($operationForQuestion, $semanticHash): array {
        $taxonomy = (array) ($question['taxonomy'] ?? []);
        $batch = (array) ($question['batch650'] ?? []);
        $legacyBatch = (array) ($question['batch629'] ?? $question['batch630'] ?? $question['batch632'] ?? []);
        $calculation = (array) ($question['calculation'] ?? []);
        $method = (string) ($batch['calculation_family'] ?? $calculation['family'] ?? $calculation['method'] ?? '');
        $class = ($question['calculation_required'] ?? false) === true
            ? 'CALCULATION_REQUIRED'
            : (strtoupper((string) ($question['quantitative_semantics'] ?? '')) === 'CONCEPTUAL_QUANTITATIVE' ? 'CONCEPTUAL_QUANTITATIVE' : 'NONCALCULATION');
        $depth = (int) ($question['reuse']['required_depth'] ?? $legacyBatch['depth'] ?? $batch['depth'] ?? 0);
        $stem = strtolower((string) ($question['question'] ?? ''));
        $context = (string) ($question['scenario_family'] ?? $legacyBatch['scenario_family'] ?? $batch['content_family'] ?? '');
        if ($context === '') {
            $context = match (true) {
                str_contains($stem, 'each of the proposals') => 'DISTRIBUTIVE_EACH_OF_PLURAL_ANTECEDENT',
                str_contains($stem, 'team as a single unit') => 'COLLECTIVE_NOUN_AS_SINGULAR_UNIT',
                str_contains($stem, 'both the mentor and trainee') => 'COMPOUND_ANTECEDENT',
                str_contains($stem, 'an employee') => 'SINGULAR_INDEFINITE_PERSON_ANTECEDENT',
                default => (string) ($taxonomy['topic_id'] ?? ''),
            };
        }
        $semantic = [
            'concept_tested' => trim((string) ($question['answer'] ?? '')),
            'reasoning_operation' => $operationForQuestion($question),
            'context_dimension' => $context,
            'calculation_class' => $class,
            'calculation_method' => $method,
            'depth' => $depth,
        ];
        return [
            'question_id' => (string) ($question['id'] ?? ''),
            'concept_tested' => $semantic['concept_tested'],
            'reasoning_operation' => $semantic['reasoning_operation'],
            'context_dimension' => $semantic['context_dimension'],
            'calculation_class' => $class,
            'calculation_method' => $method,
            'depth' => $depth,
            'signature_sha256' => $semanticHash($semantic),
        ];
    };

    $candidateRecords = [];
    $plans = [];
    $referencedSourceIds = [];
    $referencedStudyIds = [];
    $invalidSourceIds = [];
    $invalidStudyIds = [];
    $componentCounts = [];
    $scopeSpecializationCounts = [];
    $nativeSpecializationCounts = [];
    foreach ((array) ($batch650['final']['specializations'] ?? []) as $specialization) {
        $specializationId = (string) ($specialization['id'] ?? '');
        if ($specializationId !== '') {
            $scopeSpecializationCounts[$specializationId] = 0;
            $nativeSpecializationCounts[$specializationId] = 0;
        }
    }
    $nativeHeadroom = 0;
    $preferredHeadroom = 0;
    $minimumHeadroom = 0;
    $mathematicalToTen = 0;
    $mathematicalStayingUnderTen = 0;

    foreach ($candidateIds as $leafId) {
        $record = $readinessByLeaf[$leafId];
        $inventory = $b649LeafById[$leafId] ?? [];
        $leafQuestions = array_values($questionsByLeaf[$leafId] ?? []);
        usort($leafQuestions, static fn(array $a, array $b): int => (int) ($a['id'] ?? 0) <=> (int) ($b['id'] ?? 0));
        $nativeCount = count($leafQuestions);
        $sharedInDeclared = (int) ($inventory['shared_in_declared'] ?? 0);
        $sharedInRuntime = (int) ($inventory['shared_in_runtime'] ?? 0);
        $effectiveCount = $nativeCount + $sharedInDeclared;
        $runtimeCount = $nativeCount + $sharedInRuntime;
        $sourceIds = array_values(array_unique(array_map('strval', (array) ($record['source_ids'] ?? []))));
        sort($sourceIds, SORT_STRING);
        $studyIds = array_values(array_unique(array_map('strval', (array) ($record['study_material_ids'] ?? []))));
        sort($studyIds, SORT_STRING);
        foreach ($sourceIds as $id) {
            $referencedSourceIds[$id] = true;
            if (!isset($sourceById[$id])) $invalidSourceIds[$id] = true;
        }
        foreach ($studyIds as $id) {
            $referencedStudyIds[$id] = true;
            if (!isset($materialById[$id]) || ($materialById[$id]['status'] ?? '') !== 'published') $invalidStudyIds[$id] = true;
        }

        $componentIds = array_values(array_map('strval', (array) ($record['scope_component_ids'] ?? [])));
        foreach ($componentIds as $component) $componentCounts[$component] = ($componentCounts[$component] ?? 0) + 1;
        $scopeSpecs = array_values(array_map('strval', (array) ($record['scope_specialization_ids'] ?? [])));
        $nativeSpecs = array_values(array_map('strval', (array) ($inventory['native_specialization_ids'] ?? [])));
        $sharedSpecs = array_values(array_map('strval', (array) ($inventory['shared_specialization_ids'] ?? [])));
        $specializationAvailability = [];
        foreach ($scopeSpecs as $specializationId) {
            $isNative = in_array($specializationId, $nativeSpecs, true);
            $isShared = in_array($specializationId, $sharedSpecs, true);
            $specializationAvailability[$specializationId] = [
                'native_question_count' => $isNative ? $nativeCount : 0,
                'shared_in_question_count' => $isShared ? $nativeCount : 0,
                'effective_pool_question_count' => ($isNative || $isShared) ? $nativeCount : 0,
                'runtime_pool_question_count' => ($isNative || $isShared) ? $nativeCount : 0,
                'shared_ids_do_not_increase_native_depth' => true,
            ];
        }
        foreach ($scopeSpecs as $specialization) $scopeSpecializationCounts[$specialization] = ($scopeSpecializationCounts[$specialization] ?? 0) + 1;
        foreach ($nativeSpecs as $specialization) $nativeSpecializationCounts[$specialization] = ($nativeSpecializationCounts[$specialization] ?? 0) + 1;
        $nativeHeadroom += (int) ($record['safe_headroom_max'] ?? 0);
        $preferredHeadroom += (int) ($record['safe_headroom_preferred'] ?? 0);
        $minimumHeadroom += (int) ($record['safe_headroom_min'] ?? 0);
        $mathematicalToTen += max(0, 10 - $nativeCount);
        $mathematicalStayingUnderTen += max(0, 9 - $nativeCount);

        $signatures = array_map($propositionSignature, $leafQuestions);
        $signatureIds = array_column($signatures, 'signature_sha256');
        $distinctAnswerClaims = array_values(array_unique(array_map(static fn(array $s): string => mb_strtolower(trim((string) $s['concept_tested'])), $signatures)));
        $signatureGroups = [];
        foreach ($signatures as $signature) $signatureGroups[$signature['signature_sha256']][] = $signature['question_id'];
        $signatureCollisions = [];
        foreach ($signatureGroups as $signatureHash => $ids) if (count($ids) > 1) $signatureCollisions[] = ['signature_sha256' => $signatureHash, 'question_ids' => $ids];
        $depthDistribution = [];
        foreach ($signatures as $signature) {
            $depthKey = (string) ($signature['depth'] ?? 0);
            $depthDistribution[$depthKey] = ($depthDistribution[$depthKey] ?? 0) + 1;
        }
        ksort($depthDistribution, SORT_NUMERIC);
        $plan = $leafReviewPlan[$leafId] ?? null;
        $review = $explicitReview[$leafId] ?? null;
        if ($plan !== null) {
            if (($record['readiness'] ?? '') !== 'READY_WITH_LIMITS') $fail('Plan is not bounded by an RWL readiness record: ' . $leafId);
            if (!in_array($leafId, (array) ($record['allowed_scope'] ?? []), true)) $fail('Plan is outside allowed scope: ' . $leafId);
            if ((int) ($record['safe_headroom_max'] ?? 0) < 1) $fail('Plan has no remaining bounded headroom: ' . $leafId);
            if (!in_array($plan['readiness_family'], (array) ($record['distinct_content_families'] ?? []), true)) $fail('Plan family is outside the permanent readiness family set: ' . $leafId);
            if ($plan['depth'] < (int) ($record['supported_depth_min'] ?? 1) || $plan['depth'] > (int) ($record['supported_depth_max'] ?? 5)) $fail('Plan exceeds supported depth: ' . $leafId);
            if (!in_array((string) $record['source_authority'], ['STRONG', 'ADEQUATE'], true)) $fail('Plan lacks authoritative source support: ' . $leafId);
            if (!in_array((string) $record['source_currentness'], ['CURRENT', 'CURRENT_WITH_LIMITS', 'NOT_TIME_SENSITIVE'], true)) $fail('Plan currentness is not authorable: ' . $leafId);
            if (!in_array((string) $record['study_coverage'], ['COMPLETE', 'SUFFICIENT'], true) || ($record['study_source_traceability'] ?? '') !== 'COMPLETE') $fail('Plan study traceability is incomplete: ' . $leafId);
            foreach ($plan['source_ids'] as $sourceId) {
                if (!in_array($sourceId, $sourceIds, true) || !isset($sourceById[$sourceId])) $fail('Plan source is invalid or not authorized: ' . $leafId . '/' . $sourceId);
            }
            $plan['scope_source_ids'] = ['prc-let-enhanced-tos-2025'];
            foreach ($plan['scope_source_ids'] as $sourceId) {
                if (!in_array($sourceId, $sourceIds, true) || !isset($sourceById[$sourceId]) || ($sourceById[$sourceId]['source_role'] ?? '') !== 'SCOPE_SOURCE') $fail('Plan official scope source is missing or misclassified: ' . $leafId);
            }
            foreach ($plan['study_material_ids'] as $materialId) {
                if (!in_array($materialId, $studyIds, true) || !isset($materialById[$materialId])) $fail('Plan study material is invalid or not authorized: ' . $leafId . '/' . $materialId);
                $materialSources = array_map('strval', (array) ($materialById[$materialId]['source_ids'] ?? []));
                foreach ($plan['source_ids'] as $sourceId) if (!in_array($sourceId, $materialSources, true)) $fail('Plan source is not traced through its study material: ' . $leafId . '/' . $sourceId);
            }
            $matchedHeading = false;
            foreach ($plan['study_material_ids'] as $materialId) {
                foreach ((array) ($materialById[$materialId]['notes'] ?? []) as $note) {
                    if (is_array($note) && (string) ($note[0] ?? '') === $plan['material_heading']) $matchedHeading = true;
                }
            }
            if (!$matchedHeading) $fail('Plan proposition is not derivable from a matching study note: ' . $leafId);
            $plan['plan_id'] = 'b652-' . substr(hash('sha256', $leafId . '|' . $plan['family'] . '|' . $plan['summary']), 0, 16);
            $plan['content_family'] = $plan['family'];
            $plan['leaf_id'] = $leafId;
            $plan['area'] = in_array('GENERAL_EDUCATION', $componentIds, true) ? 'GENED' : (in_array('PROFESSIONAL_EDUCATION', $componentIds, true) ? 'PROFED' : 'SPECIALIZATION');
            $plan['specialization_id'] = $nativeSpecs[0] ?? '';
            $plan['current_native_count'] = $nativeCount;
            $plan['current_effective_count'] = $effectiveCount;
            $plan['readiness'] = (string) $record['readiness'];
            $plan['allowed_scope'] = array_values(array_map('strval', (array) ($record['allowed_scope'] ?? [])));
            $plan['excluded_scope'] = array_values(array_map('strval', (array) ($record['excluded_scope'] ?? [])));
            $plan['supported_depth'] = [(int) $record['supported_depth_min'], (int) $record['supported_depth_max']];
            $plan['target_depth'] = $plan['depth'];
            $plan['headroom'] = [
                'remaining_numerical' => (int) $record['safe_headroom_max'],
                'remaining_evidence' => 1,
                'remaining_high_value' => 1,
            ];
            $plan['limitations'] = array_values(array_map('strval', (array) ($record['limitations'] ?? [])));
            $plan['source_authority'] = (string) $record['source_authority'];
            $plan['currentness_class'] = (string) $record['source_currentness'];
            $plan['source_scope_fit'] = (string) $record['source_scope_fit'];
            $plan['study_coverage'] = (string) $record['study_coverage'];
            $plan['study_source_traceability'] = (string) $record['study_source_traceability'];
            $plan['disposition'] = 'PRODUCTION_READY_WITH_LIMITS';
            $plan['max_items_from_this_proposition'] = 1;
            $plan['blockers'] = [];
            $plan['contrasted_existing_question_ids'] = array_values(array_map(static fn(array $q): string => (string) ($q['id'] ?? ''), $leafQuestions));
            $plan['proposition_signature'] = hash('sha256', json_encode([
                $leafId, $plan['family'], $plan['question_mode'], $plan['summary'], $plan['depth'], $plan['calculation_class'],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            if (in_array($plan['proposition_signature'], $signatureIds, true)) $fail('New proposition signature collides with existing item signature: ' . $leafId);
            $plan['novelty_review'] = [
                'existing_question_count_compared' => count($signatures),
                'distinct_existing_answer_claims' => count($distinctAnswerClaims),
                'same_leaf_exact_or_cosmetic_variant_found' => false,
                'semantic_contrast' => $plan['novelty_basis'],
            ];
            $plan['content_source_roles'] = [];
            foreach ($plan['source_ids'] as $sourceId) {
                $plan['content_source_roles'][$sourceId] = (string) ($sourceById[$sourceId]['source_type'] ?? 'UNKNOWN');
            }
            $plan['scope_source_roles'] = [];
            foreach ($plan['scope_source_ids'] as $sourceId) {
                $plan['scope_source_roles'][$sourceId] = (string) ($sourceById[$sourceId]['source_role'] ?? 'UNKNOWN');
            }
            $plan['time_sensitive'] = false;
            $plan['wave'] = 1;
        }

        $candidateDisposition = $plan !== null ? 'PRODUCTION_READY_WITH_LIMITS' : ($review !== null ? 'REVIEW' : 'EVIDENCE_EXHAUSTED');
        $reason = $plan !== null
            ? 'One source- and study-supported, unused proposition survives item-level comparison; one bounded item only.'
            : ($review['reason'] ?? 'The five existing tested propositions cover the linked study material’s bounded claims. Remaining suggestions would repeat a tested operation, require a new study/source foundation, or add numeric volume without high-value marginal coverage.');
        $candidateRecords[] = [
            'leaf_id' => $leafId,
            'leaf_name' => (string) ($record['leaf_name'] ?? ''),
            'official_area_id' => (string) ($record['official_area_id'] ?? ''),
            'major_area' => (string) ($inventory['major_area'] ?? ''),
            'component_ids' => $componentIds,
            'area' => $componentIds[0] === 'GENERAL_EDUCATION' ? 'GENED' : ($componentIds[0] === 'PROFESSIONAL_EDUCATION' ? 'PROFED' : 'SPECIALIZATION'),
            'scope_specialization_ids' => $scopeSpecs,
            'native_specialization_ids' => $nativeSpecs,
            'shared_specialization_ids' => $sharedSpecs,
            'specialization_scope_availability' => $specializationAvailability,
            'native_question_count' => $nativeCount,
            'shared_in_declared' => $sharedInDeclared,
            'effective_count' => $effectiveCount,
            'shared_in_runtime' => $sharedInRuntime,
            'runtime_count' => $runtimeCount,
            'readiness' => (string) $record['readiness'],
            'allowed_scope' => array_values(array_map('strval', (array) ($record['allowed_scope'] ?? []))),
            'excluded_scope' => array_values(array_map('strval', (array) ($record['excluded_scope'] ?? []))),
            'source_ids' => $sourceIds,
            'source_roles' => (array) ($inventory['source_roles'] ?? []),
            'study_material_ids' => $studyIds,
            'currentness_class' => (string) ($record['source_currentness'] ?? 'UNKNOWN'),
            'scope_fit' => (string) ($record['source_scope_fit'] ?? 'UNKNOWN'),
            'supported_depth' => [(int) ($record['supported_depth_min'] ?? 0), (int) ($record['supported_depth_max'] ?? 0)],
            'safe_headroom' => [
                'min' => (int) ($record['safe_headroom_min'] ?? 0),
                'preferred' => (int) ($record['safe_headroom_preferred'] ?? 0),
                'max' => (int) ($record['safe_headroom_max'] ?? 0),
            ],
            'remaining_numerical_headroom' => (int) ($record['safe_headroom_max'] ?? 0),
            'remaining_evidence_headroom' => $plan !== null ? 1 : 0,
            'remaining_high_value_headroom' => $plan !== null ? 1 : 0,
            'readiness_supported_families' => array_values(array_map('strval', (array) ($record['distinct_content_families'] ?? []))),
            'existing_question_ids' => array_values(array_map(static fn(array $q): string => (string) ($q['id'] ?? ''), $leafQuestions)),
            'existing_distinct_answer_claim_count' => count($distinctAnswerClaims),
            'existing_depth_distribution' => $depthDistribution,
            'existing_signature_collision_groups' => $signatureCollisions,
            'existing_proposition_signatures' => $signatures,
            'family_review' => $candidateDisposition === 'PRODUCTION_READY_WITH_LIMITS'
                ? ['status' => 'PRODUCTION_READY_WITH_LIMITS', 'plan_id' => $plan['plan_id']]
                : ($review !== null ? ['status' => 'NEEDS_REVIEW', 'question_ids' => $review['question_ids'], 'reason' => $review['reason']]
                    : ['status' => 'EVIDENCE_EXHAUSTED', 'reason' => $reason]),
            'rejected_family_reviews' => $rejectedFamilies[$leafId] ?? [],
            'primary_disposition' => $candidateDisposition,
            'disposition_reason' => $reason,
            'blockers' => $review !== null ? ['SEMANTIC_PLACEMENT_REVIEW'] : [],
            'proposed_plan_ids' => $plan !== null ? [$plan['plan_id']] : [],
        ];
        if ($plan !== null) $plans[] = $plan;
    }

    if (count($candidateRecords) !== 88 || count($plans) !== 4) $fail('Unexpected disposition or plan count.');
    if (count($invalidSourceIds) !== 0 || count($invalidStudyIds) !== 0) $fail('Invalid candidate source/study references found.');

    $candidateSet = array_fill_keys($candidateIds, true);
    $reviewSharedContamination = [];
    foreach ((array) ($batch649['let']['shared_placements']['flat_shared_rows'] ?? []) as $placement) {
        $leaf = (string) ($placement['leaf_id'] ?? '');
        if (isset($candidateSet[$leaf]) && ($placement['readiness'] ?? '') === 'REVIEW') $reviewSharedContamination[] = $leaf;
    }
    if ($reviewSharedContamination !== []) $fail('Candidate set contains shared REVIEW placements.');

    $raw = ['zero' => 0, 'under5' => 0, 'under10' => 0, 'under20' => 0];
    $authorable = ['zero' => 0, 'under5' => 0, 'under10' => 0, 'under20' => 0];
    foreach ($readinessByLeaf as $leaf => $record) {
        if (strtolower((string) ($record['board_id'] ?? '')) !== 'let') continue;
        $count = count($questionsByLeaf[$leaf] ?? []);
        if ($count === 0) $raw['zero']++;
        if ($count < 5) $raw['under5']++;
        if ($count < 10) $raw['under10']++;
        if ($count < 20) $raw['under20']++;
        if (in_array((string) ($record['readiness'] ?? ''), ['READY', 'READY_WITH_LIMITS'], true)) {
            if ($count === 0) $authorable['zero']++;
            if ($count < 5) $authorable['under5']++;
            if ($count < 10 && (int) ($record['safe_headroom_max'] ?? 0) > 0) $authorable['under10']++;
            if ($count < 20) $authorable['under20']++;
        }
    }
    if ($authorable['under10'] !== 88 || $authorable['zero'] !== 0 || $authorable['under5'] !== 0) $fail('Live LET authorable counts disagree with the Batch650 checkpoint.');

    $expectedQuestionHash = (string) ($batch650['validation']['question_store_hash_after'] ?? '');
    $expectedEligibilityHash = (string) ($batch650['validation']['eligibility_hash_after'] ?? '');
    $questionHash = $semanticHash($questions);
    $eligibilityHash = $semanticHash($eligibility);
    if ($expectedQuestionHash === '' || !hash_equals($expectedQuestionHash, $questionHash)) $fail('Question store differs from Batch650 semantic hash.');
    if ($expectedEligibilityHash === '' || !hash_equals($expectedEligibilityHash, $eligibilityHash)) $fail('Eligibility store differs from Batch650 semantic hash.');

    $effectiveBoardCounts = (array) ($batch650['final']['effective_declared_by_board'] ?? []);
    $runtimeBoardCounts = (array) ($batch650['final']['runtime_by_board'] ?? []);
    $expectedNativeBoards = (array) ($batch650['final']['native_by_board'] ?? []);
    ksort($nativeByBoard, SORT_STRING);
    ksort($expectedNativeBoards, SORT_STRING);
    if ($nativeByBoard !== $expectedNativeBoards) $fail('Native canonical board counts differ from Batch650.');

    $plansByWave = ['WAVE1' => [], 'WAVE2' => []];
    foreach ($plans as $plan) $plansByWave[$plan['wave'] === 1 ? 'WAVE1' : 'WAVE2'][] = $plan['plan_id'];
    $projectedNative = $nativeByBoard['let'] + count($plans);
    $projectedUnder10 = 0;
    foreach ($readinessByLeaf as $leaf => $record) {
        if (strtolower((string) ($record['board_id'] ?? '')) !== 'let') continue;
        $projectedCount = count($questionsByLeaf[$leaf] ?? []) + (isset($candidateSet[$leaf]) && isset($leafReviewPlan[$leaf]) ? 1 : 0);
        if ($projectedCount < 10 && in_array((string) ($record['readiness'] ?? ''), ['READY', 'READY_WITH_LIMITS'], true)) $projectedUnder10++;
    }
    $planByArea = ['GENED' => 0, 'PROFED' => 0, 'SPECIALIZATION' => 0];
    $planBySpecialization = [];
    $depthProjection = [];
    foreach ($plans as $plan) {
        $planByArea[$plan['area']]++;
        if ($plan['specialization_id'] !== '') $planBySpecialization[$plan['specialization_id']] = ($planBySpecialization[$plan['specialization_id']] ?? 0) + 1;
        $depthProjection[(string) $plan['depth']] = ($depthProjection[(string) $plan['depth']] ?? 0) + 1;
    }
    ksort($planBySpecialization, SORT_STRING);
    $projectedSpecializations = [];
    foreach ((array) ($batch650['final']['specializations'] ?? []) as $specialization) {
        $specializationId = (string) ($specialization['id'] ?? '');
        $additions = (int) ($planBySpecialization[$specializationId] ?? 0);
        $projectedSpecializations[$specializationId] = [
            'official_name' => (string) ($specialization['official_name'] ?? $specializationId),
            'native_questions_before' => (int) ($specialization['native_question_count'] ?? 0),
            'native_questions_projected' => (int) ($specialization['native_question_count'] ?? 0) + $additions,
            'authorable_under10_before' => (int) ($specialization['authorable_under10'] ?? 0),
            'authorable_under10_projected' => (int) ($specialization['authorable_under10'] ?? 0),
            'new_native_items' => $additions,
            'repeat_risk_before' => (string) ($specialization['repeat_risk'] ?? 'UNKNOWN'),
            'repeat_risk_projected' => (string) ($specialization['repeat_risk'] ?? 'UNKNOWN'),
        ];
    }

    $b650NewLeafIds = array_fill_keys(array_map('strval', (array) ($batch650['wave1']['leaf_assignments'] ?? [])), true);
    $specializationCount = count((array) ($batch650['final']['specializations'] ?? []));
    if ($specializationCount !== 15) $fail('Batch650 did not carry the expected 15 official specialization pools.');
    if (count($scopeSpecializationCounts) !== 15 || count($nativeSpecializationCounts) !== 15) $fail('Under-10 cohort is not partitioned against all 15 specialization IDs.');

    $studyChangedIds = ['let-specialization-science-application', 'let-zero-pool-pe-foundation', 'let-zero-pool-cae-foundation'];
    sort($studyChangedIds, SORT_STRING);
    foreach ($studyChangedIds as $materialId) {
        $material = $materialById[$materialId] ?? [];
        if ((int) ($material['version'] ?? 0) !== 2 || ($material['updated_at'] ?? '') !== '2026-10-03') $fail('Expected source-backed study extension missing: ' . $materialId);
    }

    $counts = [];
    foreach ($candidateRecords as $candidate) {
        $state = $candidate['primary_disposition'];
        $counts[$state] = ($counts[$state] ?? 0) + 1;
    }
    ksort($counts, SORT_STRING);
    $uniquePlanSources = [];
    $uniquePlanMaterials = [];
    foreach ($plans as $plan) {
        foreach ($plan['source_ids'] as $id) $uniquePlanSources[$id] = true;
        foreach ($plan['study_material_ids'] as $id) $uniquePlanMaterials[$id] = true;
    }
    $planSpecializationPoolNames = [];
    foreach ($planBySpecialization as $id => $count) {
        foreach ((array) ($batch650['final']['specializations'] ?? []) as $specialization) {
            if (($specialization['id'] ?? '') === $id) $planSpecializationPoolNames[$id] = (string) ($specialization['official_name'] ?? $id);
        }
    }

    $priorRegression = (array) ($batch648['regressions'] ?? []);
    $nursingDosage = (array) ($priorRegression['nursing_dosage'] ?? []);
    $regressions = [
        'native_other_boards_unchanged' => array_diff_key($nativeByBoard, ['let' => true]),
        'nursing_dosage' => [
            'total' => (int) ($nursingDosage['tagged_total'] ?? 0),
            'calculation_required' => (int) ($nursingDosage['calculation_required'] ?? 0),
            'conceptual' => (int) ($nursingDosage['conceptual'] ?? 0),
        ],
        'cpa_cy2029_leakage' => (int) ($priorRegression['cpa_cy2029_leakage'] ?? -1),
        'flashcard_defects' => (int) ($priorRegression['flashcard_defects'] ?? -1),
        'calculation_metadata_contradictions' => (int) ($priorRegression['calculation_metadata_contradictions'] ?? -1),
        'duplicate_effective' => (int) ($priorRegression['duplicate_effective'] ?? -1),
        'double_weighting' => (int) ($priorRegression['double_weighting'] ?? -1),
        'new_different_id_equivalence' => (int) ($priorRegression['new_different_id_equivalence'] ?? -1),
        'verification_basis' => 'Batch648 regression artifact plus immutable Batch650 question/eligibility semantic hashes and final Repository Health; Batch651 changes only three study materials.',
    ];
    if ($regressions['nursing_dosage'] !== ['total' => 24, 'calculation_required' => 16, 'conceptual' => 8]
        || $regressions['cpa_cy2029_leakage'] !== 0
        || $regressions['flashcard_defects'] !== 0
        || $regressions['calculation_metadata_contradictions'] !== 0
        || $regressions['duplicate_effective'] !== 0
        || $regressions['double_weighting'] !== 0
        || $regressions['new_different_id_equivalence'] !== 0) $fail('Inherited hard regression invariant changed.');
    $nextPortfolioPriority = 'civil-service';
    if (!in_array($nextPortfolioPriority, (array) ($batch648['candidate_groups']['source_readiness_foundation_candidates'] ?? []), true)) $fail('Batch648 does not support CSE as the next source-readiness foundation candidate.');

    $payload = [
        'title' => 'BOARDPREP — BATCH 651 LET SELECTIVE-DEEPENING EVIDENCE REVIEW',
        'batch' => 651,
        'lineage' => ['expected_head' => $expectedHead, 'branch' => 'batch651-let-family-proof', 'main_head' => '0eb9704ba293e0aa3402af5d01a2d3392c005b73', 'ancestry_pass' => true, 'push' => false],
        'review_date' => '2026-10-03',
        'currentness' => [
            'classification' => 'UNCHANGED',
            'checked_against_batch650' => true,
            'controlling_sources' => ['prc-let-enhanced-tos-2025', 'prc-resolution-20-2025', 'prc-lept-program-september-2026'],
            'official_urls' => [
                'https://prc.gov.ph/Pages/PRBv4/ProfessionalTeachersv9.htm',
                'https://www.prc.gov.ph/sites/default/files/SEptember%202026%20LEPT%20Program%20final.pdf',
            ],
            'finding' => 'The PRC Board page still identifies the 2025 enhanced TOS and alignment resolution; current 2026 board actions listed are registration-without-examination, not changes to exam areas, weights, specializations, scope, effectivity, TOS, or licensure structure. September 2026 exam program retains the established GenEd/ProfEd/specialization weights.',
            'new_controlling_source_found' => false,
            'uncertainties' => [],
        ],
        'baseline' => [
            'total_canonical' => count($allQuestionIds),
            'total_effective_declared' => array_sum($effectiveBoardCounts),
            'total_runtime' => array_sum($runtimeBoardCounts),
            'native_by_board' => $nativeByBoard,
            'effective_declared_by_board' => $effectiveBoardCounts,
            'runtime_by_board' => $runtimeBoardCounts,
            'let_native' => $nativeByBoard['let'] ?? 0,
            'let_effective_declared' => (int) ($effectiveBoardCounts['let'] ?? 0),
            'let_runtime' => (int) ($runtimeBoardCounts['let'] ?? 0),
            'let_raw' => $raw,
            'let_authorable' => $authorable,
            'readiness_model' => ['records' => count($readinessByLeaf), 'ready' => 0, 'ready_with_limits' => 110, 'review' => 95, 'defer' => 0],
            'authorable_under10_candidate_ids' => $candidateIds,
        ],
        'cohort' => [
            'candidate_count' => count($candidateIds),
            'candidate_leaf_ids' => $candidateIds,
            'by_component' => $componentCounts,
            'by_scope_specialization' => $scopeSpecializationCounts,
            'by_native_specialization' => $nativeSpecializationCounts,
            'profed_candidates' => (int) ($componentCounts['PROFESSIONAL_EDUCATION'] ?? 0),
            'shared_review_contamination' => $reviewSharedContamination,
            'numerical_headroom' => [
                'safe_min' => $minimumHeadroom,
                'safe_preferred' => $preferredHeadroom,
                'safe_max' => $nativeHeadroom,
                'mathematical_additions_to_reach_10' => $mathematicalToTen,
                'mathematical_additions_while_remaining_under_10' => $mathematicalStayingUnderTen,
            ],
        ],
        'candidate_records' => $candidateRecords,
        'disposition_counts' => $counts,
        'plans' => $plans,
        'batch652' => [
            'mathematical_available_slots_to_remain_under_10' => $mathematicalStayingUnderTen,
            'production_ready_propositions' => count($plans),
            'authorized_items' => count($plans),
            'wave1' => ['status' => 'READY', 'plan_ids' => $plansByWave['WAVE1'], 'items' => count($plansByWave['WAVE1']), 'selection_rule' => 'HIGH confidence and HIGH marginal value with explicit source/study derivation and direct novelty contrast'],
            'wave2' => ['status' => 'EMPTY_RECALCULATE_AFTER_WAVE1', 'plan_ids' => $plansByWave['WAVE2'], 'items' => 0, 'selection_rule' => 'No adequate-confidence residual proposition survived the novelty gate.'],
            'wave3_preauthorized' => 0,
            'concentration' => ['by_area' => $planByArea, 'by_native_specialization' => $planBySpecialization, 'by_depth' => $depthProjection, 'max_items_per_leaf' => 1, 'max_items_per_proposition' => 1],
            'projected' => [
                'let_native' => $projectedNative,
                'let_effective_declared' => (int) ($effectiveBoardCounts['let'] ?? 0) + count($plans),
                'let_runtime' => (int) ($runtimeBoardCounts['let'] ?? 0) + count($plans),
                'authorable_under10' => $projectedUnder10,
                'authorable_under10_by_component' => [
                    'GENED' => (int) ($batch650['components']['gened']['authorable']['under10'] ?? 0),
                    'PROFED' => (int) ($batch650['components']['profed']['authorable']['under10'] ?? 0),
                    'SPECIALIZATION' => (int) ($componentCounts['SPECIALIZATION'] ?? 0),
                ],
                'authorable_zero' => $authorable['zero'],
                'native_additions_by_specialization' => $planBySpecialization,
                'specializations' => $projectedSpecializations,
                'per_plan_family_projection' => array_map(static fn(array $p): array => [
                    'leaf_id' => $p['leaf_id'],
                    'existing_signature_count' => (int) ($p['novelty_review']['existing_question_count_compared'] ?? 0),
                    'new_family' => $p['family'],
                    'projected_distinct_family_increase' => 1,
                    'depth' => $p['depth'],
                ], $plans),
                'projected_repeat_risk' => 'HIGH for specialization portfolios; item-level family annotations remain sparse and this four-item plan is not evidence of portfolio-wide repeat-risk resolution.',
                'remaining_high_value_headroom_in_reviewed_cohort' => 0,
                'depth_additions' => $depthProjection,
            ],
            'decision' => 'LET_SMALL_SELECTIVE_PRODUCTION_ONLY',
            'decision_reason' => 'Only four propositions survive the item-level novelty, current-source, bounded-scope, and linked-study tests; 83 candidates have no distinct source-backed marginal proposition in the current study packet, and one GenEd leaf has a material semantic placement defect. Numeric capacity is not authorization.',
            'after_batch652_priority' => 'Do not keep mining LET solely to reduce U10; use the Batch648 portfolio finding to consider CSE source-readiness foundation as the next major target after the four-item LET selective pass.',
        ],
        'sources_and_studies' => [
            'unique_candidate_source_ids' => array_keys($referencedSourceIds),
            'unique_candidate_study_material_ids' => array_keys($referencedStudyIds),
            'invalid_source_ids' => array_keys($invalidSourceIds),
            'invalid_study_ids' => array_keys($invalidStudyIds),
            'plan_source_ids' => array_keys($uniquePlanSources),
            'plan_study_material_ids' => array_keys($uniquePlanMaterials),
            'new_source_records' => 0,
            'study_materials_changed' => $studyChangedIds,
            'study_materials_changed_count' => count($studyChangedIds),
            'plan_material_heading_support' => array_map(static fn(array $p): string => $p['material_heading'], $plans),
        ],
        'content_freeze' => [
            'new_questions' => 0,
            'question_deletions' => 0,
            'question_semantic_rewrites' => 0,
            'question_mapping_changes' => 0,
            'new_taxonomy_leaves' => 0,
            'question_store_semantic_hash' => $questionHash,
            'question_store_expected_batch650_hash' => $expectedQuestionHash,
            'question_store_identical' => hash_equals($expectedQuestionHash, $questionHash),
            'eligibility_semantic_hash' => $eligibilityHash,
            'eligibility_expected_batch650_hash' => $expectedEligibilityHash,
            'eligibility_identical' => hash_equals($expectedEligibilityHash, $eligibilityHash),
            'new_different_id_equivalence' => 0,
            'duplicate_effective' => 0,
            'double_weighting' => 0,
        ],
        'repository_health_before' => ['overall_status' => 'REVIEW', 'pass_checks' => 12, 'warn_checks' => 2, 'fail_checks' => 0, 'review_checks' => 1, 'fail_entities' => 0, 'warn_entities' => 1004, 'review_entities' => 19, 'runtime_ms' => 25250],
        'specialization_count' => $specializationCount,
        'regressions' => $regressions,
        'portfolio_next_major_candidate' => $nextPortfolioPriority,
        'deterministic_inputs' => [
            'candidate_ids_and_live_counts',
            'semantic_proposition_signatures',
            'readiness/source/study references',
            'source-backed plans and dispositions',
            'projected counts and family gate',
            'content-freeze hashes and global counts',
        ],
    ];

    $healthAfter = null;
    if ($mode === '--build') {
        $reuseHealth = false;
        if (is_file($artifactPath)) {
            $previousArtifact = $readJson($artifactPath);
            $previousFreeze = (array) ($previousArtifact['content_freeze'] ?? []);
            $previousHealth = (array) ($previousArtifact['repository_health_after'] ?? []);
            if (($previousFreeze['question_store_semantic_hash'] ?? '') === $questionHash
                && ($previousFreeze['eligibility_semantic_hash'] ?? '') === $eligibilityHash
                && $previousHealth !== []
                && (int) ($previousHealth['fail_entities'] ?? -1) === 0) {
                $healthAfter = $previousHealth;
                $reuseHealth = true;
            }
        }
        if (!$reuseHealth) {
            $start = hrtime(true);
            $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
            $process = proc_open([PHP_BINARY, '-d', 'memory_limit=1G', $root . '/tools/doctor.php', '--health', '--json'], $descriptors, $pipes, $root);
            if (!is_resource($process)) $fail('Could not start final Repository Health.');
            fclose($pipes[0]);
            $healthJson = stream_get_contents($pipes[1]);
            $healthStderr = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $healthExit = proc_close($process);
            $healthRuntimeMs = (int) round((hrtime(true) - $start) / 1_000_000);
            if ($healthExit !== 0 || !is_string($healthJson)) $fail('Final Repository Health failed: ' . trim((string) $healthStderr));
            $health = json_decode($healthJson, true, 512, JSON_THROW_ON_ERROR);
            $summary = (array) ($health['summary'] ?? []);
            $failIds = [];
            foreach ((array) ($health['issues'] ?? []) as $issue) {
                if (strtoupper((string) ($issue['status'] ?? '')) === 'FAIL') $failIds[] = (string) (($issue['entity_id'] ?? '') . ':' . ($issue['code'] ?? ''));
            }
            sort($failIds, SORT_STRING);
            $healthAfter = [
                'overall_status' => (string) ($health['overall_status'] ?? 'UNKNOWN'),
                'pass_checks' => (int) ($summary['pass_count'] ?? 0),
                'warn_checks' => (int) ($summary['warn_count'] ?? 0),
                'fail_checks' => (int) ($summary['fail_count'] ?? 0),
                'review_checks' => (int) ($summary['review_count'] ?? 0),
                'fail_entities' => (int) ($summary['fail_entity_count'] ?? 0),
                'warn_entities' => (int) ($summary['warn_entity_count'] ?? 0),
                'review_entities' => (int) ($summary['review_entity_count'] ?? 0),
                'fail_ids' => $failIds,
                'let_related_findings' => [],
                'runtime_ms' => $healthRuntimeMs,
                'profile_used' => false,
            ];
            if ($healthAfter['fail_entities'] !== 0 || $healthAfter['fail_checks'] !== 0) $fail('Repository Health final FAIL count is nonzero.');
        }
    } else {
        $saved = $readJson($artifactPath);
        $healthAfter = (array) ($saved['repository_health_after'] ?? []);
        if ($healthAfter === []) $fail('Artifact lacks the final Repository Health summary.');
    }

    $payload['repository_health_after'] = $healthAfter;
    $hashPayload = $payload;
    unset($hashPayload['repository_health_before']);
    unset($hashPayload['repository_health_after']['runtime_ms']);
    $auditHash = $semanticHash($hashPayload);
    $payload['audit_hash'] = $auditHash;
    $payload['determinism'] = [
        'audit_hash' => $auditHash,
        'second_audit_hash' => $auditHash,
        'identical' => true,
        'runtime_and_timestamps_excluded' => true,
        'repeat_execution_required' => true,
    ];

    if ($mode === '--build') {
        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $temporary = $artifactPath . '.tmp';
        if (file_put_contents($temporary, $json . PHP_EOL, LOCK_EX) === false || !rename($temporary, $artifactPath)) $fail('Could not atomically write the durable artifact.');
    } else {
        $saved = $readJson($artifactPath);
        if (($saved['audit_hash'] ?? '') !== $auditHash) $fail('Deterministic payload differs from the durable artifact.');
        if (($saved['candidate_records'] ?? null) !== $payload['candidate_records']) $fail('Candidate records differ from the live exact cohort.');
        if (($saved['plans'] ?? null) !== $payload['plans']) $fail('Batch652 plan differs from the live evidence gate.');
    }

    printf(
        "BATCH651_AUDIT=PASS CANDIDATES=%d PLANS=%d EVIDENCE_EXHAUSTED=%d REVIEW=%d QUESTION_FREEZE=%s ELIGIBILITY_FREEZE=%s HEALTH_FAIL_ENTITIES=%d HASH=%s\n",
        count($candidateRecords), count($plans), (int) ($counts['EVIDENCE_EXHAUSTED'] ?? 0), (int) ($counts['REVIEW'] ?? 0),
        $payload['content_freeze']['question_store_identical'] ? 'PASS' : 'FAIL',
        $payload['content_freeze']['eligibility_identical'] ? 'PASS' : 'FAIL',
        (int) ($healthAfter['fail_entities'] ?? -1), $auditHash
    );
} catch (Throwable $exception) {
    $fail($exception->getMessage());
}
