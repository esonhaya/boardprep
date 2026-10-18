#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

if (!in_array(($argv[1] ?? ''), ['--apply', '--dry-run'], true)) {
    fwrite(STDERR, "Usage: php tools/apply-batch694-content.php --dry-run|--apply\n");
    exit(2);
}

putenv('APP_ENV=testing');
putenv('APP_DEBUG=false');
\App\Core\App::boot();
$storage = \App\Core\App::storage();
$data = require __DIR__ . '/batch694-content-data.php';
$questions = $storage->all('questions');
foreach ($questions as $existingQuestion) {
    if (is_array($existingQuestion) && ($existingQuestion['batch_marker'] ?? '') === 'batch694') {
        throw new RuntimeException('Batch 694 content is already present; refusing a non-idempotent reapplication.');
    }
}
$eligibility = $storage->all('question-eligibility');
$domains = $storage->all('taxonomy/domains');
$topics = $storage->all('taxonomy/topics');
$concepts = $storage->all('taxonomy/concepts');
$subjects = $storage->all('subjects');
$blueprints = $storage->all('blueprints');
$sources = $storage->all('sources');
$materials = $storage->all('study-materials');
$lessons = $storage->all('study-lessons');
$links = $storage->all('study-question-links');
$readiness = $storage->all('source-readiness-psychometrician');

$indexById = static function (array $rows): array {
    $result = [];
    foreach ($rows as $row) if (is_array($row) && isset($row['id'])) $result[(string) $row['id']] = $row;
    return $result;
};
$subjectById = $indexById($subjects);
$domainById = $indexById($domains);
$topicById = $indexById($topics);
$conceptById = $indexById($concepts);
$questionAt = [];
foreach ($questions as $i => $q) if (is_array($q) && isset($q['id'])) $questionAt[(string) $q['id']] = $i;
$questionById = static function (string $id) use (&$questions, &$questionAt): ?array {
    return isset($questionAt[$id]) ? $questions[$questionAt[$id]] : null;
};

// Direct taxonomy-parent reconciliation. The canonical domain subject is
// treated as evidence only when the destination exists in the board blueprint
// and the production editor validator accepts the exact mapping.
$blueprintNode = static function (string $board, string $subject) use ($blueprints): ?string {
    $explicitNodes = [
        'registered-electrical-engineering' => ['registered-electrical-engineering-mathematics'=>'mathematics'],
        'architecture' => [
            'architecture-history-and-theory'=>'history-and-theory',
            'architecture-planning-and-practice'=>'planning-and-practice',
            'architecture-utilities-and-construction'=>'utilities-and-construction',
            'architecture-architectural-design-and-site-planning'=>'architectural-design-and-site-planning',
        ],
    ];
    if (isset($explicitNodes[$board][$subject])) return $explicitNodes[$board][$subject];
    foreach ($blueprints as $bp) {
        if (!is_array($bp) || ($bp['board_id'] ?? '') !== $board) continue;
        foreach ((array) ($bp['sections'] ?? []) as $section) {
            if (!is_array($section)) continue;
            $allowed = array_map('strval', (array) ($section['subjects'] ?? []));
            if (in_array($subject, $allowed, true) || (string) ($section['id'] ?? '') === $subject) return (string) $section['id'];
        }
    }
    return null;
};
$boardAliases = ['ree'=>'registered-electrical-engineering','arch'=>'architecture','ple'=>'psychometrician','nle'=>'nursing','cle'=>'criminologist','cse'=>'civil-service'];
$effectiveSubjectByQuestion = [];
foreach ($eligibility as $row) {
    if (!is_array($row)) continue;
    foreach (array_map('strval', (array) ($row['question_ids'] ?? [])) as $id) {
        $override = is_array($row['question_mappings'][$id] ?? null) ? $row['question_mappings'][$id] : [];
        $examIds = (array) ($override['exam_ids'] ?? $row['exam_ids'] ?? []);
        $subjectIds = (array) ($override['subject_ids'] ?? $row['subject_ids'] ?? []);
        $placements = (array) ($override['placements'] ?? []);
        foreach (array_map('strval', $examIds) as $exam) {
            $normalizedExam = $boardAliases[strtolower($exam)] ?? strtolower($exam);
            $effectiveSubjectByQuestion[$id][$normalizedExam] = (string) ($placements[$exam]['subject_id'] ?? $placements[$normalizedExam]['subject_id'] ?? $subjectIds[$exam] ?? $subjectIds[$normalizedExam] ?? '');
        }
    }
}
$eligibleSubjectFor = static fn(array $q, string $board): ?string => $effectiveSubjectByQuestion[(string) ($q['id'] ?? '')][$board] ?? null;
$validatedMappingCache = [];
$reclassified = [];
foreach ($questions as &$q) {
    if (!is_array($q)) continue;
    $tax = is_array($q['taxonomy'] ?? null) ? $q['taxonomy'] : [];
    $rawBoard = strtolower((string) ($tax['board_id'] ?? $q['board'] ?? ''));
    $board = $boardAliases[$rawBoard] ?? $rawBoard;
    if (!in_array($board, ['let', 'registered-electrical-engineering', 'architecture'], true)) continue;
    $domain = $domainById[(string) ($tax['domain_id'] ?? '')] ?? null;
    if (!is_array($domain)) continue;
    $target = (string) ($domain['subject_id'] ?? '');
    $current = $eligibleSubjectFor($q, $board);
    if (($current === null || $current === '') && $board === ($boardAliases[$rawBoard] ?? $rawBoard)) {
        $current = (string) ($tax['subject_id'] ?? $q['subject_id'] ?? '');
    }
    if ($target === '' || $current === null || $current === '' || $current === $target) continue;
    $node = $blueprintNode($board, $target);
    if ($node === null || !isset($subjectById[$target])) continue;
    $input = [
        'target_board' => $board,
        'target_subject' => $target,
        'target_domain' => (string) ($tax['domain_id'] ?? ''),
        'target_topic' => (string) ($tax['topic_id'] ?? ''),
        'target_concept' => (string) ($tax['concept_id'] ?? ''),
        'blueprint_node' => $node,
    ];
    if ($board === 'let') {
        $placement = is_array($q['let_placement'] ?? null) ? $q['let_placement'] : [];
        foreach (['level' => 'let_level', 'component' => 'let_component', 'specialization_id' => 'let_specialization_id'] as $from => $to) {
            if (isset($placement[$from])) $input[$to] = $placement[$from];
        }
    }
    $mappingCacheKey = hash('sha256', json_encode($input, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    $check = $validatedMappingCache[$mappingCacheKey] ??= \App\Services\Question\QuestionEligibilityService::validateMapping($q, $input, true);
    if (($check['valid'] ?? false) !== true) {
        throw new RuntimeException('Mapping validation failed for question ' . (string) $q['id'] . ': ' . json_encode($check['errors'] ?? []));
    }
    $id = (string) $q['id'];
    $found = false;
    foreach ($eligibility as &$entry) {
        if (!is_array($entry) || !in_array($id, array_map('strval', (array) ($entry['question_ids'] ?? [])), true)) continue;
        $override = is_array($entry['question_mappings'][$id] ?? null) ? $entry['question_mappings'][$id] : [];
        $examIds = array_values(array_unique(array_map('strval', (array) ($override['exam_ids'] ?? $entry['exam_ids'] ?? []))));
        $subjectIds = (array) ($override['subject_ids'] ?? $entry['subject_ids'] ?? []);
        $placements = (array) ($override['placements'] ?? []);
        $subjectIds[$board] = $target;
        $placements[$board] = $check['mapping'];
        $entry['question_mappings'][$id] = array_merge($override, ['exam_ids' => $examIds, 'subject_ids' => $subjectIds, 'placements' => $placements]);
        $found = true;
        break;
    }
    unset($entry);
    if (!$found) throw new RuntimeException('Eligibility record missing for question ' . $id);
    if ($board === 'architecture' && (string) ($tax['subject_id'] ?? '') !== $target) {
        $tax['subject_id'] = $target;
        $q['taxonomy'] = $tax;
    }
    $q['subject'] = (string) ($subjectById[$target]['name'] ?? $target);
    if (array_key_exists('subject_id', $q)) $q['subject_id'] = $target;
    if ($board === 'let' && is_array($q['let_placement'] ?? null)) {
        $q['let_placement'] = array_merge($q['let_placement'], $check['mapping']);
    }
    $reclassified[] = ['question_id' => $id, 'board_id' => $board, 'from' => $current, 'to' => $target, 'node' => $node];
}
unset($q);

// Existing Values items retain IDs, history counters, timestamps, and all
// learner-facing/non-content fields. Their old unsupported template family is
// removed and the new answer key is set by answer text after option rotation.
$valuesConceptMap = [
    'values-personhood-and-dignity' => 'values-diversity-and-dignity',
    'values-hierarchy-and-conflicts' => 'values-ethical-reasoning',
    'values-filipino-cultural-analysis' => 'values-clarification',
    'values-psychological-principles' => 'values-reflective-practice',
    'values-intrapersonal-relations' => 'values-character-and-habits',
    'values-interpersonal-relations' => 'values-respectful-dialogue',
    'values-family-moral-reasoning' => 'values-ethical-reasoning',
    'values-self-understanding' => 'values-clarification',
    'values-filipino-value-system' => 'values-clarification',
    'values-rights-and-citizenship' => 'values-civic-responsibility',
    'values-peace-gender-environment' => 'values-civic-responsibility',
    'values-career-development' => 'values-reflective-practice',
    'values-community-service-in-teaching' => 'values-civic-responsibility',
    'values-digital-work-ethics' => 'values-ethical-reasoning',
    'values-workplace-professional-conduct' => 'values-character-and-habits',
];
$lessonBySubject = [
    'values-education' => ['material' => 'let-specialization-values-education', 'lesson' => 'let-values-ethical-reasoning', 'section' => 'values-four-units'],
    'developmental-psychology' => ['material' => 'psychometrician-developmental-psychology-review', 'lesson' => 'psychometrician-developmental-psychology-review', 'section' => 'lifespan-development'],
];
$sourceValues = ['prc-values-tos-2025', 'prc-let-enhanced-tos-2025', 'deped-esep-curriculum-guide', 'deped-ppst-do-42-2017'];
$timestamp = gmdate('Y-m-d\TH:i:s+00:00');
$newIds = ['values' => [], 'psych' => []];
$nextId = 1;
foreach ($questions as $q) if (is_array($q) && is_numeric($q['id'] ?? null)) $nextId = max($nextId, (int) $q['id'] + 1);
$answerPosition = static fn(int $id): int => ($id * 11) % 4;
$prepareQuestion = static function (array $template, int|string $id, string $board, string $subject, array $tax, string $difficulty, string $stem, string $answer, array $distractors, string $explanation, array $sourceIds, string $materialId, string $sectionId, int $position, ?array $letPlacement = null) use ($timestamp): array {
    if (count($distractors) !== 3) throw new RuntimeException('A four-choice item needs exactly three authored distractors.');
    $choices = $distractors;
    array_splice($choices, $position, 0, [$answer]);
    $template['id'] = $id;
    $template['taxonomy'] = $tax;
    $template['board'] = $board;
    $template['status'] = 'active';
    $template['source'] = 'batch694-source-first';
    $template['batch_marker'] = 'batch694';
    $template['difficulty'] = $difficulty;
    $template['question'] = $stem;
    $template['choices'] = $choices;
    $template['answer'] = $answer;
    $template['explanation'] = $explanation;
    $template['subject'] = $subject;
    $template['domain'] = (string) ($tax['domain_id'] ?? '');
    $template['topic'] = (string) ($tax['topic_id'] ?? '');
    $template['concept'] = (string) ($tax['concept_id'] ?? '');
    $template['source_ids'] = $sourceIds;
    $template['study_material_id'] = $materialId;
    $template['study_section_id'] = $sectionId;
    $template['timesUsed'] = 0;
    $template['timesCorrect'] = 0;
    $template['timesIncorrect'] = 0;
    $template['bookmarks'] = 0;
    $template['reports'] = 0;
    $template['helpfulExplanation'] = 0;
    $template['notHelpfulExplanation'] = 0;
    $template['createdAt'] = $timestamp;
    $template['updatedAt'] = $timestamp;
    $template['scenario_family'] = 'batch694-' . $board . '-' . $sectionId . '-' . (string) $id;
    $template['misconception_family'] = 'batch694-reviewed-distractor-set-' . (string) $id;
    $template['professional_judgment'] = true;
    $template['case_interpretation'] = true;
    unset($template['batch629']);
    if ($letPlacement !== null) $template['let_placement'] = $letPlacement;
    return $template;
};

foreach ($data['values_rewrites'] as $row) {
    [$id, $originalConcept, $difficulty, $stem, $answer, $distractors, $explanation] = $row;
    $id = (string) $id;
    if (!isset($questionAt[$id])) throw new RuntimeException('Missing existing Values question ' . $id);
    $q = $questions[$questionAt[$id]];
    $conceptId = $valuesConceptMap[(string) $originalConcept] ?? null;
    if ($conceptId === null || !isset($conceptById[$conceptId])) throw new RuntimeException('Unmapped Values concept ' . (string) $originalConcept);
    $topicId = (string) $conceptById[$conceptId]['topic_id'];
    $position = $answerPosition((int) $id);
    $choices = array_values($distractors);
    array_splice($choices, $position, 0, [(string) $answer]);
    $q['question'] = (string) $stem;
    $q['choices'] = $choices;
    $q['answer'] = (string) $answer;
    $q['explanation'] = (string) $explanation;
    $q['difficulty'] = (string) $difficulty;
    $q['taxonomy']['concept_id'] = $conceptId;
    $q['taxonomy']['topic_id'] = $topicId;
    $q['topic'] = $topicId;
    $q['concept'] = $conceptId;
    $q['source_ids'] = $sourceValues;
    $q['study_material_id'] = 'let-specialization-values-education';
    $q['study_section_id'] = 'values-four-units';
    $q['source'] = 'batch694-source-first';
    $q['batch_marker'] = 'batch694';
    $q['scenario_family'] = 'batch694-values-rewrite-' . $id;
    $q['misconception_family'] = 'batch694-values-distractor-set-' . $id;
    $q['professional_judgment'] = true;
    $q['case_interpretation'] = true;
    if (is_array($q['let_placement'] ?? null)) {
        $q['let_placement'] = array_merge($q['let_placement'], ['subject_id'=>'values-education','domain_id'=>'values-and-ethical-education','topic_id'=>$topicId,'concept_id'=>$conceptId,'blueprint_node'=>'secondary-values-education']);
    }
    foreach ($eligibility as &$entry) {
        if (!is_array($entry) || !in_array($id, array_map('strval', (array) ($entry['question_ids'] ?? [])), true)) continue;
        $override = is_array($entry['question_mappings'][$id] ?? null) ? $entry['question_mappings'][$id] : [];
        $placements = (array) ($override['placements'] ?? []);
        if (is_array($placements['let'] ?? null)) {
            $placements['let'] = array_merge($placements['let'], ['subject_id'=>'values-education','domain_id'=>'values-and-ethical-education','topic_id'=>$topicId,'concept_id'=>$conceptId,'blueprint_node'=>'secondary-values-education']);
            $entry['question_mappings'][$id] = array_merge($override, ['placements'=>$placements]);
        }
        break;
    }
    unset($entry);
    unset($q['batch629']);
    $questions[$questionAt[$id]] = $q;
}

$valuesTemplate = $questionById('5818');
$valuesTaxonomy = $valuesTemplate['taxonomy'];
foreach ($data['values_new'] as $row) {
    // The authoring data carries a trailing legacy difficulty field. Use its
    // first difficulty field and validate it against the supported set.
    [$originalConcept, $difficulty, $stem, $answer, $distractors, $explanation] = array_slice($row, 0, 6);
    if (!in_array($difficulty, ['easy', 'medium', 'hard'], true)) throw new RuntimeException('Invalid Values difficulty.');
    $conceptId = $valuesConceptMap[(string) $originalConcept] ?? null;
    if ($conceptId === null || !isset($conceptById[$conceptId])) throw new RuntimeException('Unmapped Values concept ' . (string) $originalConcept);
    $id = $nextId++;
    $concept = $conceptById[$conceptId];
    $topicId = (string) $concept['topic_id'];
    $taxonomy = array_merge($valuesTaxonomy, ['concept_id' => $conceptId, 'topic_id' => $topicId]);
    $placement = [
        'level' => 'SECONDARY', 'component' => 'SPECIALIZATION', 'specialization_id' => 'secondary-values-education',
        'subject_id' => 'values-education', 'domain_id' => 'values-and-ethical-education', 'topic_id' => $topicId,
        'concept_id' => $conceptId, 'blueprint_node' => 'secondary-values-education',
    ];
    $valuesNewOrdinal = $valuesNewOrdinal ?? 0;
    $position = $valuesNewOrdinal++ === 0 ? 3 : $answerPosition($id);
    $q = $prepareQuestion($valuesTemplate, $id, 'let', 'values-education', $taxonomy, (string) $difficulty, (string) $stem, (string) $answer, (array) $distractors, (string) $explanation, $sourceValues, 'let-specialization-values-education', 'values-four-units', $position, $placement);
    $questions[] = $q;
    $questionAt[(string) $id] = array_key_last($questions);
    $newIds['values'][] = $id;
}

$psychTemplates = [];
foreach ($questions as $q) {
    $tax = (array) ($q['taxonomy'] ?? []);
    if (($tax['board_id'] ?? '') === 'psychometrician' && in_array((string) ($tax['concept_id'] ?? ''), ['psych-development-theories', 'psych-development-nature-nurture', 'psych-development-research-ethics'], true)) {
        $psychTemplates[(string) $tax['concept_id']] ??= $q;
    }
}
$psychMaterial = 'psychometrician-developmental-psychology-review';
foreach ($data['psych_development_new'] as $row) {
    [$conceptId, $difficulty, $stem, $answer, $distractors, $explanation] = array_slice($row, 0, 6);
    $template = $psychTemplates[(string) $conceptId] ?? null;
    if (!is_array($template)) throw new RuntimeException('Missing developmental taxonomy template for ' . (string) $conceptId);
    $id = $nextId++;
    $taxonomy = $template['taxonomy'];
    $q = $prepareQuestion($template, $id, 'psychometrician', 'psychology-foundations', $taxonomy, (string) $difficulty, (string) $stem, (string) $answer, (array) $distractors, (string) $explanation, ['prc-psychometrician-enhanced-tos-2023', 'openstax-psychology-2e'], $psychMaterial, 'lifespan-development', $answerPosition($id));
    $questions[] = $q;
    $questionAt[(string) $id] = array_key_last($questions);
    $newIds['psych'][] = $id;
}

// Record independent destination eligibility for newly authored items.
$addEligibility = static function (array &$entries, array $q, string $board, string $subject, array $placement, string $id): void {
    $entryId = 'batch694-' . $board . '-' . $subject;
    foreach ($entries as &$entry) {
        if (!is_array($entry) || (string) ($entry['id'] ?? '') !== $entryId) continue;
        $entry['question_ids'][] = $id;
        $entry['question_mappings'][$id] = ['exam_ids' => [$board], 'subject_ids' => [$board => $subject], 'placements' => [$board => $placement]];
        unset($entry);
        return;
    }
    unset($entry);
    $entries[] = ['id' => $entryId, 'question_ids' => [$id], 'exam_ids' => [$board], 'subject_ids' => [$board => $subject], 'question_mappings' => [$id => ['exam_ids' => [$board], 'subject_ids' => [$board => $subject], 'placements' => [$board => $placement]]]];
};
foreach ($newIds['values'] as $id) {
    $q = $questions[$questionAt[(string) $id]];
    $placement = $q['let_placement'];
    $addEligibility($eligibility, $q, 'let', 'values-education', $placement, (string) $id);
}
foreach ($newIds['psych'] as $id) {
    $q = $questions[$questionAt[(string) $id]];
    $tax = $q['taxonomy'];
    $placement = ['subject_id' => 'developmental-psychology', 'domain_id' => $tax['domain_id'], 'topic_id' => $tax['topic_id'], 'concept_id' => $tax['concept_id'], 'blueprint_node' => 'developmental-psychology'];
    $addEligibility($eligibility, $q, 'psychometrician', 'developmental-psychology', $placement, (string) $id);
}

// Update the three psych developmental source-readiness rows to reflect these
// 24 bounded items; remaining declared headroom is not silently erased.
$psychAddsByConcept = array_count_values(array_map(static fn(int|string $id): string => (string) $questions[$questionAt[(string) $id]]['taxonomy']['concept_id'], $newIds['psych']));
foreach ($readiness as &$record) {
    $conceptId = (string) ($record['leaf_id'] ?? '');
    if (!isset($psychAddsByConcept[$conceptId])) continue;
    $added = (int) $psychAddsByConcept[$conceptId];
    $record['current_question_count'] = (int) ($record['current_question_count'] ?? 0) + $added;
    $record['max_new_questions'] = max(0, (int) ($record['max_new_questions'] ?? 0) - $added);
    $record['planned_new_questions'] = 0;
    $record['limitations'][] = 'Batch 694 added bounded source-grounded items; independent academic review remains pending.';
    $record['limitations'] = array_values(array_unique($record['limitations']));
}
unset($record);

// A focused record-level lesson/material builder. Every resource is original
// instructional prose with official scope and instructional references.
$scopeLessons = [
 'let|early-childhood-education' => ['title'=>'Early Childhood Learning, Assessment, and Inclusive Practice','material'=>'let-early-childhood-learning-and-inclusion','lesson'=>'let-early-childhood-learning-and-inclusion','pdf'=>'let-early-childhood-learning-and-inclusion.pdf','sources'=>['prc-let-enhanced-tos-2025'],'instruction'=>['deped-esep-curriculum-guide','deped-ra11650-irr'],'summary'=>'Original review of child development, play-based pedagogy, observation, family partnership, inclusion, and developmentally appropriate assessment.','objectives'=>['Connect developmental knowledge to responsive early-years teaching.','Select observation and assessment evidence appropriate to a stated learning purpose.','Plan inclusive play and family partnership without treating one child’s behavior as a diagnosis.'],'sections'=>[['heading'=>'1. Development in context','body'=>'Early learning reflects interacting physical, language, cognitive, social, and emotional processes. A milestone is a guide for noticing patterns, not a single-child diagnostic cutoff. Interpret a behavior with age, opportunity, language, culture, health, and setting in view. A teaching response should build from what the child can do while offering a reachable next step.','items'=>['Describe observed behavior before explaining it.','Ask what prior experience and support the child has had.','Plan one next step and observe the response.']],['heading'=>'2. Play and guided learning','body'=>'Play can support language, symbolic thought, movement, cooperation, and self-regulation when materials and adult prompts fit the learning aim. Guided play preserves meaningful child choice while a teacher notices, models language, asks an open question, or changes a material to extend thinking. A worksheet or adult-directed task may be appropriate for some purposes, but it is not a universal substitute for active exploration.','items'=>['State the learning aim before selecting materials.','Use prompts that extend the child’s idea instead of taking over.','Observe whether the activity invites multiple ways to participate.']],['heading'=>'3. Observation and assessment','body'=>'A useful record separates what was seen or heard from the interpretation. A running note, work sample, conversation, and repeated observation can answer different questions. Avoid turning one event into a stable label. Assessment should inform planning and communication, use suitable tools, and protect confidential information. If evidence raises a concern, follow the authorized referral and family communication process.','items'=>['Record context, task, and observable response.','Compare evidence across times or settings when useful.','Use an assessment result only for its stated purpose.']],['heading'=>'4. Family partnership and inclusion','body'=>'Families contribute knowledge about a child’s interests, routines, language, strengths, and support needs. Partnership is a two-way exchange, not a request that a family simply endorse a teacher’s conclusion. Identify access barriers in the environment and adjust materials, communication, timing, or response options. Coordinate specialist support through the applicable school process while maintaining the child’s participation and dignity.','items'=>['Ask families what has worked and what the child enjoys.','Offer more than one accessible way to respond.','Check whether an adjustment changed participation.']]],'worked'=>[['heading'=>'From observation to next step','prompt'=>'During block play, a child sorts pieces by color, then leaves when a peer changes the structure. What should the teacher record and try?','steps'=>['Record the sequence and setting without labeling the child as inflexible.','Ask whether the peer change, language demand, material access, or transition affected participation.','Offer a shared planning prompt or duplicate pieces, then observe whether play continues.'],'answer'=>'Use the observation to test a support, then review further evidence.','check'=>'The response changes an accessible condition and avoids inferring a trait from one episode.']], 'misconceptions'=>['A milestone range is a diagnosis for an individual child.','Play is unstructured time with no instructional purpose.','A single observation is enough to infer a stable ability.','Family partnership means asking parents to accept the school’s interpretation.'],'patterns'=>['Interpret a classroom observation in context.','Choose a play prompt that preserves child agency.','Select evidence and next steps for assessment or inclusion.']],
 'let|special-needs-education' => ['title'=>'Inclusive Education: Access, Participation, and Support','material'=>'let-special-needs-inclusive-education','lesson'=>'let-special-needs-inclusive-education','pdf'=>'let-special-needs-inclusive-education.pdf','sources'=>['prc-let-enhanced-tos-2025'],'instruction'=>['deped-ra11650-irr','deped-ppst-do-42-2017'],'summary'=>'Original instructional review of inclusive planning, access supports, assessment adjustments, family partnership, and authorized referral.','objectives'=>['Distinguish an access support from a change to the learning standard.','Analyze barriers in task, environment, communication, and assessment.','Choose a respectful support and review its effect with the learner and appropriate team.'],'sections'=>[['heading'=>'1. Start with the participation barrier','body'=>'Inclusive planning begins with the task and setting: what must the learner understand or do, and which feature creates an avoidable barrier? The barrier may concern access to print, language, movement, time, sensory conditions, directions, or response format. Asking the learner and family what helps is more informative than guessing from a label. A support should be connected to the identified barrier and reviewed for whether it enables meaningful participation.','items'=>['Separate the learning goal from the way a task is presented.','Ask about strengths and useful supports.','Check access in the actual activity, not only on paper.']],['heading'=>'2. Accommodation, modification, and validity','body'=>'An accommodation changes access or response conditions while keeping the intended competency intact. A modification changes the expected learning outcome or level. The distinction matters because support should not accidentally remove the skill being assessed. Extra time, accessible text, or a response device can preserve an objective when appropriate; changing the construct or standard requires a deliberate, authorized decision rather than an informal convenience.','items'=>['Name the competency before changing a task.','Ask whether the adjustment changes access or the target itself.','Document an authorized change and communicate its basis.']],['heading'=>'3. Universal and individualized planning','body'=>'Universal design anticipates varied learners through clear instructions, multiple ways to access information, and varied opportunities to show learning. Universal design does not replace a learner-specific support plan. When a broad design is insufficient, use individualized supports, consult the authorized team, and make the adjustment practical for the learner and teacher. Review outcomes rather than assuming a support works because it is available.','items'=>['Provide clear directions and more than one response mode where suitable.','Use a specific plan when broad access is insufficient.','Review participation evidence with the learner.']],['heading'=>'4. Assessment, behavior, and partnership','body'=>'Assessment should measure the stated competency without allowing an unrelated access barrier to dominate. A behavior that interrupts learning is information about context and support needs, not by itself a diagnosis or moral judgment. Record antecedents and consequences carefully, protect privacy, and involve qualified personnel through the proper route. Families and learners should receive understandable communication and a chance to contribute to planning.','items'=>['Use multiple evidence sources when one format blocks access.','Describe behavior objectively and inspect context.','Share only information needed for the educational purpose.']]],'worked'=>[['heading'=>'Preserve the learning objective','prompt'=>'A learner understands a science explanation but cannot read the small print on the task sheet. The objective is to compare two explanations. What adjustment preserves the target?','steps'=>['Keep the comparison criterion unchanged.','Provide accessible text or an authorized read-aloud format.','Check the learner’s comparison using the same conceptual criteria.'],'answer'=>'Change access to the directions while retaining the comparison standard.','check'=>'The response addresses the print barrier without simplifying the science competency.']], 'misconceptions'=>['A diagnosis alone determines a useful classroom adjustment.','Giving every learner the same format is always equitable.','Any adjustment lowers the academic standard.','One challenging behavior establishes a disorder or intent.'],'patterns'=>['Separate access barrier from competency.','Choose accommodation or modification based on the stated objective.','Select a privacy-preserving referral and review step.']],
 'let|technical-vocational-teacher-education' => ['title'=>'Competency-Based Technical and Vocational Teaching','material'=>'let-tvted-competency-based-teaching','lesson'=>'let-tvted-competency-based-teaching','pdf'=>'let-tvted-competency-based-teaching.pdf','sources'=>['prc-let-enhanced-tos-2025'],'instruction'=>['deped-epp-tle-curriculum-guide','deped-tle-competency-portal'],'summary'=>'Original review of competency-based outcomes, demonstration assessment, workshop safety, feedback, and industry-informed learning.','objectives'=>['Translate a competency into observable performance criteria.','Plan a safe demonstration and practice sequence.','Give evidence-based feedback and document reassessment fairly.'],'sections'=>[['heading'=>'1. Competency and performance evidence','body'=>'A competency describes the integrated knowledge, skill, and judgment required for a work task. Turn it into observable criteria: the product or service standard, process steps, safety behaviors, and conditions. Criteria should distinguish essential performance from cosmetic preference. A learner may know a procedure verbally but still need a practical demonstration; use evidence that matches the stated competency and equipment context.','items'=>['State the task, conditions, and standard.','Choose evidence aligned to the performance claim.','Separate safety-critical criteria from refinements.']],['heading'=>'2. Demonstrate, practice, coach','body'=>'A strong sequence makes expert thinking visible, then gives learners supported practice before independent performance. Demonstration should name decision points, setup checks, and common faults. Practice needs a defined task, sufficient equipment, observation, and corrective feedback. Feedback is most useful when it points to a criterion and an actionable next attempt rather than giving a global judgment about ability.','items'=>['Model setup and decision checks aloud.','Use a short practice task before the full performance.','Give feedback tied to one or two criteria.']],['heading'=>'3. Safety is part of competence','body'=>'Safety is not an optional add-on to technical quality. Identify hazards, required controls, protective equipment, emergency steps, and stop-work conditions before practice. A paper assessment cannot substitute for a safe workshop routine. When equipment, materials, or supervision are insufficient, defer the hazardous task or use an approved alternative rather than asking learners to improvise.','items'=>['Inspect the work area and equipment before use.','State the control and stop condition for each significant hazard.','Record and correct near misses through the proper process.']],['heading'=>'4. Assessment, remediation, and workplace links','body'=>'Use criterion-based assessment with clear evidence, consistent conditions, and a documented result. If a learner is not yet competent, identify the unmet criterion, plan targeted practice, and reassess under suitable conditions. Workplace examples should reflect current tools and actual standards, but a local employer preference should not be presented as a universal competency without evidence.','items'=>['Report which criterion is and is not demonstrated.','Plan practice against the gap and allow fair reassessment.','Keep industry examples tied to recognized learning outcomes.']]],'worked'=>[['heading'=>'Diagnose the performance gap','prompt'=>'A learner’s assembly works, but a guard is missing and the final measurement exceeds the stated tolerance. How should feedback and reassessment be planned?','steps'=>['Treat the missing guard as a safety-critical failure and stop use of the assembly.','Record the measurement against the published tolerance.','Provide supervised practice on guarding and measurement, then reassess the criteria.'],'answer'=>'Document both gaps, correct safety first, and reassess against the same standard.','check'=>'A working result does not cancel a safety criterion or make the tolerance optional.']], 'misconceptions'=>['A completed product proves that the process was safe.','Competency assessment is a single pass/fail observation with no feedback.','Employer preference alone defines a national competency.','Remediation means lowering the criterion.'],'patterns'=>['Map a task to observable criteria.','Choose safe practice and demonstration conditions.','Interpret performance evidence and plan a fair reassessment.']],
 'let|culture-and-arts-education' => ['title'=>'Arts Learning: Elements, Making, Context, and Reflection','material'=>'let-culture-arts-learning-foundation','lesson'=>'let-culture-arts-learning-foundation','pdf'=>'let-culture-arts-learning-foundation.pdf','sources'=>['prc-let-enhanced-tos-2025'],'instruction'=>['deped-arts-curriculum-guide-2020'],'summary'=>'Original study lesson on arts vocabulary, making and responding, cultural context, critique, and accessible arts instruction.','objectives'=>['Use relevant elements and principles to describe an artwork or performance.','Distinguish observation, interpretation, and evaluation in critique.','Plan culturally respectful and accessible arts learning.'],'sections'=>[['heading'=>'1. Describe before interpreting','body'=>'A disciplined arts response begins with observable features: line, color, shape, texture, rhythm, movement, space, material, or arrangement as relevant to the art form. Interpretation then proposes a meaning supported by those features and the context. Evaluation explains how well the work achieves an intended effect using stated criteria. Keeping these steps distinct makes critique more precise and less dependent on whether a learner simply likes the work.','items'=>['Name visible or audible evidence.','Offer an interpretation tied to that evidence.','Evaluate against an explicit purpose or criterion.']],['heading'=>'2. Making and revising','body'=>'Artistic practice includes experimenting, selecting, composing, reviewing, and revising. A technique is a means, not a guaranteed artistic result. Learners can compare how materials or choices change an effect, explain a revision, and use feedback selectively. Instruction should make room for multiple solutions where the prompt allows them while retaining any specified technical or safety constraints.','items'=>['Set a focused intention before choosing a technique.','Compare two choices and describe their effects.','Revise using feedback connected to the intention.']],['heading'=>'3. Cultural context and attribution','body'=>'Works arise in social, historical, and cultural settings. Identify whose perspective is represented, what evidence supports an interpretation, and whether a practice or image has limits on use. Avoid treating one artist or artifact as the voice of an entire community. When using local knowledge, seek appropriate permission and attribution and follow the curriculum and community guidance.','items'=>['Situate the work without stereotyping a group.','Distinguish source evidence from a personal response.','Respect permissions and attribution.']],['heading'=>'4. Critique and access','body'=>'Constructive critique identifies evidence, effect, and a possible next step without demeaning the maker. The teacher can offer varied ways to encounter and create art, such as spoken, written, tactile, movement-based, or digital responses when appropriate. Access choices should preserve the learning aim and allow learners to make substantive artistic decisions.','items'=>['Comment on the work and its criteria, not a person’s worth.','Offer accessible ways to participate and respond.','Ask what a revision changes in the work.']]],'worked'=>[['heading'=>'Ground an interpretation','prompt'=>'A poster uses repeated high-contrast shapes and a narrow path leading to an empty center. How can a learner support an interpretation that the composition creates isolation?','steps'=>['Identify repetition, contrast, and the direction of the path as observable features.','Explain how those features guide attention toward the empty center.','State isolation as an interpretation and acknowledge other readings if evidence supports them.'],'answer'=>'Connect a proposed meaning to compositional evidence instead of presenting it as the only possible meaning.','check'=>'Observation and interpretation are related but not identical.']], 'misconceptions'=>['An interpretation is valid only when it matches the artist’s stated intent.','A strong critique ranks personal taste as objective quality.','One artifact represents an entire culture.','Accessibility removes the need for meaningful artistic choice.'],'patterns'=>['Distinguish evidence, interpretation, and evaluation.','Analyze how a formal choice shapes an effect.','Select culturally respectful, accessible teaching actions.']],
 'let|physical-education' => ['title'=>'Physical Education: Movement, Fitness, Safety, and Assessment','material'=>'let-physical-education-movement-and-safety','lesson'=>'let-physical-education-movement-and-safety','pdf'=>'let-physical-education-movement-and-safety.pdf','sources'=>['prc-let-enhanced-tos-2025'],'instruction'=>['deped-pe-curriculum-guide'],'summary'=>'Original review of movement learning, fitness concepts, safe participation, inclusion, and assessment matched to physical-education outcomes.','objectives'=>['Analyze a movement task using an appropriate skill cue.','Distinguish fitness components and use assessment evidence appropriately.','Plan safe, inclusive activity with suitable progression and feedback.'],'sections'=>[['heading'=>'1. Movement skill and feedback','body'=>'Movement instruction identifies the task, a small number of useful cues, and the conditions under which the learner practices. A cue should direct attention to a controllable feature, such as balance, force, direction, or follow-through. Feedback should be timely and specific enough to guide another attempt. One successful or unsuccessful performance is limited evidence; observe patterns under the intended conditions.','items'=>['State the movement goal and relevant cue.','Change one task condition at a time when diagnosing difficulty.','Invite the learner to notice what changed between attempts.']],['heading'=>'2. Fitness concepts and assessment','body'=>'Health-related fitness is commonly discussed through cardiorespiratory endurance, muscular strength and endurance, flexibility, and body composition; skill-related fitness includes capacities such as balance, coordination, agility, reaction time, speed, and power. Use a measure only for the construct it actually assesses and consider age, context, access, and safety. A test result is not a complete judgment of health or personal worth.','items'=>['Name the fitness component before selecting a task.','Use consistent instructions and safe conditions.','Interpret change with measurement limits and context.']],['heading'=>'3. Safety and progression','body'=>'Safe activity requires attention to space, equipment, environmental conditions, readiness, hydration, and the level of exertion. Progression should increase demand in manageable steps and provide recovery. A learner reporting pain, dizziness, or breathing difficulty needs an appropriate pause and school response, not pressure to complete a score. Use relevant school and health guidance for the situation.','items'=>['Inspect space and equipment.','Scale duration, intensity, or complexity deliberately.','Stop and follow the applicable procedure when warning signs appear.']],['heading'=>'4. Inclusion, rules, and reflection','body'=>'Inclusive physical education preserves meaningful participation and the learning purpose while adjusting space, equipment, rules, grouping, or response method as appropriate. Ask learners about barriers and assess whether the adjustment helps. Rules support safe, fair play; they should be explained and applied consistently while allowing documented, appropriate supports. Reflection can help a learner connect practice choices with future goals.','items'=>['Identify the participation barrier before choosing an adjustment.','Explain a rule and its safety or fairness purpose.','Review the effect of an adjustment with the learner.']]],'worked'=>[['heading'=>'Adapt without losing the learning goal','prompt'=>'The objective is to practice passing accuracy, but a learner cannot grip the standard ball comfortably. What should the teacher do?','steps'=>['Keep the accuracy objective explicit.','Try an accessible ball or target distance consistent with safe participation.','Observe whether the learner can demonstrate direction and control, then refine the adjustment.'],'answer'=>'Adapt equipment or conditions to address grip access while still assessing the intended passing skill.','check'=>'An adjustment is judged by access to the objective, not by whether the equipment is identical.']], 'misconceptions'=>['A fitness score is a complete measure of a learner’s health.','Equal equipment and rules always create fair participation.','More exertion always means better instruction.','One attempt is enough to infer skill mastery.'],'patterns'=>['Select a cue that matches the movement problem.','Interpret a fitness or skill result within its limits.','Choose a safe, inclusive task adjustment.']],
 'architecture|architecture-utilities-and-construction' => ['title'=>'Building Systems, Construction Coordination, and Life Safety','material'=>'architecture-building-systems-and-construction','lesson'=>'architecture-building-systems-and-construction','pdf'=>'architecture-building-systems-and-construction.pdf','sources'=>['prc-architecture-syllabi'],'instruction'=>['pd1096-national-building-code','bp344-2024-irr','ra9514-fire-code-irr'],'summary'=>'Original architecture study guide on envelope and structural coordination, building services, accessibility, construction sequence, and life-safety review.','objectives'=>['Coordinate spatial, structural, and building-service requirements at concept level.','Recognize the purpose and scope limits of building-code and accessibility checks.','Identify coordination evidence needed before a construction decision.'],'sections'=>[['heading'=>'1. Building envelope and climate response','body'=>'The envelope mediates weather, daylight, ventilation, acoustics, privacy, and thermal conditions. Design decisions should connect orientation, openings, shading, material transitions, drainage, and maintenance. A detail that performs one function may create a conflict elsewhere: a shaded opening can still obstruct a required route, and a water-shedding joint can fail if its construction sequence is unclear. Evaluate the system and its interfaces rather than treating components as isolated objects.','items'=>['Trace water, air, and movement paths.','Check interfaces between materials and assemblies.','Include maintenance access in the design conversation.']],['heading'=>'2. Structure and service coordination','body'=>'Architectural planning coordinates structural supports with circulation, room use, and mechanical, electrical, plumbing, and fire-protection routes. Early coordination reserves plausible space and access for equipment, shafts, and distribution. Do not infer that a system fits merely because a symbol appears on a plan. Check clearances, access for installation and maintenance, and conflicts in section as well as plan, then consult the responsible engineers for detailed design.','items'=>['Compare plan and section at congested zones.','Check equipment access and service clearances.','Record unresolved engineering decisions.']],['heading'=>'3. Accessibility and life safety','body'=>'Accessibility and life-safety review need the applicable, current rule and the project conditions. The reviewer should identify the route, use, occupancy, and technical provision at issue, then verify dimensions and exceptions in the governing text. A general design principle is not a compliance determination. Coordinate accessible circulation with doors, level changes, signage, fire egress, and user needs; keep source citations separate from original explanatory notes.','items'=>['Locate the applicable provision before stating a requirement.','Check route continuity and conflicts at transitions.','Escalate unresolved code interpretation to the responsible professional.']],['heading'=>'4. Construction sequence and quality','body'=>'A buildable detail anticipates sequence, tolerances, temporary conditions, inspection, and repair. A drawing communicates intended geometry and performance, while specifications and submittals add material and execution requirements. When a site condition differs from the documents, record the discrepancy and obtain authorized resolution rather than silently improvising. Quality review tests observable work against approved documents and applicable standards.','items'=>['Ask how the assembly is installed and inspected.','Compare field conditions with approved documents.','Document changes through the authorized process.']]],'worked'=>[['heading'=>'Resolve a service-zone conflict','prompt'=>'A duct route crosses a beam zone and would reduce the clear width of an accessible corridor if dropped below the beam. What is the appropriate design action?','steps'=>['Record the conflict in plan and section and verify actual clearance.','Coordinate alternatives with the structural and building-services designers.','Check the revised route against access, maintenance, fire, and construction needs before documenting it.'],'answer'=>'Resolve the coordinated design and verify the applicable clearances before issuing a detail.','check'=>'Moving the duct without coordination can create structural, access, or service problems.']], 'misconceptions'=>['A plan symbol proves a service system is coordinated.','A source citation alone demonstrates code compliance.','Code dimensions may be recalled without checking the applicable version and conditions.','A field adjustment can be treated as approved because it seems buildable.'],'patterns'=>['Identify a coordination conflict and the right evidence to resolve it.','Distinguish concept guidance from code-compliance judgment.','Select a safe documentation and escalation step.']],
 'psychometrician|abnormal-psychology' => ['title'=>'Psychological Disorder Concepts: Classification, Assessment, and Limits','material'=>'psychometrician-abnormal-psychology-classification-and-assessment','lesson'=>'psychometrician-abnormal-psychology-classification-and-assessment','pdf'=>'psychometrician-abnormal-psychology-classification-and-assessment.pdf','sources'=>['prc-psychometrician-enhanced-tos-2023'],'instruction'=>['openstax-psychology-2e','who-icd11-cddr-2024','apa-psychological-assessment-guidelines-2020'],'summary'=>'Original psychometrician review of classification purpose, symptom patterns, functional impact, assessment limits, and ethical referral boundaries.','objectives'=>['Explain what classification systems organize and what they do not establish by themselves.','Interpret symptom and functioning evidence cautiously.','Separate screening, assessment, and diagnosis and protect professional boundaries.'],'sections'=>[['heading'=>'1. Classification is a shared language','body'=>'A classification system organizes patterns using defined criteria to support communication, research, and appropriate service planning. Its categories have versions and limits; they are not complete descriptions of a person. Use the system and edition specified by a question or source. A label should not be inferred from one symptom, one screening score, or an informal observation, and terminology should be checked against current authoritative materials.','items'=>['Name the system and version when the scenario supplies them.','Distinguish a category description from a full person-level account.','Avoid importing a threshold not established in the evidence.']],['heading'=>'2. Pattern, duration, and functioning','body'=>'Interpretation considers a pattern of symptoms, duration, context, distress or impairment, and relevant exclusions where the classification requires them. Similar behavior can arise for different reasons, and a symptom may be common without establishing a disorder. The appropriate next step may be additional information or qualified assessment. Study questions should test supported reasoning rather than invite diagnosis from an incomplete vignette.','items'=>['Separate observed report from inference.','Ask what duration and functioning evidence is stated.','Identify what additional information is missing.']],['heading'=>'3. Measurement and screening','body'=>'A screening tool identifies whether further assessment may be useful; it does not, by itself, confirm a diagnosis. Reliability, validity, reference group, language, setting, and purpose shape how a score may be used. Assessment choices require appropriate competence, consent and authorization, interpretation limits, and communication suited to the intended audience. A numerical cutoff is not portable across instruments or populations unless its evidence supports that use.','items'=>['Check the measure’s purpose and population.','Distinguish a screening result from a diagnostic conclusion.','State the uncertainty and next step proportionately.']],['heading'=>'4. Ethical communication and referral','body'=>'Communicate findings with precision, respect, confidentiality, and awareness of scope. Describe evidence and limits, avoid stigmatizing shorthand, and share only with authorized recipients for a justified purpose. When a scenario exceeds the professional’s role, consult or refer through the appropriate route. A classification summary should not replace an individualized formulation or the person’s context and strengths.','items'=>['Use person-respecting, evidence-based language.','Protect records and limit disclosure.','Consult or refer when the task exceeds competence.']]],'worked'=>[['heading'=>'Interpret a positive screen','prompt'=>'A client’s screening score exceeds a published threshold, but the vignette provides no duration or functioning information. What conclusion is justified?','steps'=>['State that the result meets the screening threshold on the specified instrument.','Note the missing duration, context, and functional evidence.','Recommend the appropriate qualified assessment rather than assigning a diagnosis from the score alone.'],'answer'=>'The screen indicates a need for follow-up under its stated purpose; it is not a complete diagnosis.','check'=>'The conclusion stays within the instrument’s evidence and purpose.']], 'misconceptions'=>['One symptom establishes a disorder.','A screening threshold is equivalent to diagnosis.','Classification describes every relevant feature of a person.','A score is meaningful without checking instrument, language, norm group, and purpose.'],'patterns'=>['Identify what classification evidence is present and missing.','Distinguish screening from assessment and diagnosis.','Choose a privacy-conscious, within-scope next step.']],
 'psychometrician|industrial-organizational' => ['title'=>'Work Psychology: Job Analysis, Selection, Training, and Evaluation','material'=>'psychometrician-work-psychology-job-analysis-and-evaluation','lesson'=>'psychometrician-work-psychology-job-analysis-and-evaluation','pdf'=>'psychometrician-work-psychology-job-analysis-and-evaluation.pdf','sources'=>['prc-psychometrician-enhanced-tos-2023'],'instruction'=>['openstax-psychology-2e','apa-ethics-code-2017'],'summary'=>'Original psychometrician review of work analysis, job-relevant selection, training transfer, performance evidence, and fair organizational decisions.','objectives'=>['Connect a personnel decision to a documented job requirement.','Interpret selection and performance evidence with validity and fairness limits.','Evaluate training and organizational interventions using suitable outcomes.'],'sections'=>[['heading'=>'1. Start with the work','body'=>'Job analysis describes important tasks and the knowledge, skills, abilities, and other characteristics needed to perform them. Gather evidence from relevant work sources and verify that the resulting requirements match the actual role. A selection method should assess job-relevant requirements, be administered consistently, and have evidence appropriate to its intended use. A prestigious or familiar test is not automatically valid for every job.','items'=>['Identify critical tasks and performance conditions.','Link each assessment measure to a requirement.','Review accessibility and fairness in administration.']],['heading'=>'2. Selection and measurement','body'=>'A selection score is one source of evidence, not a guarantee of future performance. Interpret it with reliability, validity, criterion relevance, group impact, and decision consequences in view. Use established procedures, secure test materials, explain appropriate limits, and avoid changing cut scores after seeing candidates unless the method is authorized and justified. Decisions should be reproducible and documented.','items'=>['Check what the score predicts and for whom.','Use consistent conditions and scoring rules.','Document the evidence and decision rule.']],['heading'=>'3. Training and transfer','body'=>'Training evaluation begins with a defined need and learning objective. Learning evidence can show whether knowledge or skill changed; transfer evidence asks whether it is used on the job; organizational outcomes require additional causal caution. Practice, feedback, supervisor support, resources, and opportunity to perform can affect transfer. A satisfaction survey alone does not show competence or business impact.','items'=>['Match practice to job tasks.','Measure learning and transfer separately.','Consider work conditions that enable or block application.']],['heading'=>'4. Performance and organizational evidence','body'=>'Performance criteria should reflect role expectations and use evidence gathered in a fair, consistent way. Avoid halo judgments, recency, and unclear standards by collecting relevant observations over time and explaining criteria. Organizational intervention studies need suitable comparisons and attention to concurrent changes. Keep personnel data confidential, limit access, and communicate conclusions at the level the evidence supports.','items'=>['Define criteria before evaluating outcomes.','Use more than one relevant observation when appropriate.','Separate association from causal attribution.']]],'worked'=>[['heading'=>'Interpret a training result','prompt'=>'After a safety course, test scores improve but incident rates are unchanged for one month. What can the evaluation team conclude?','steps'=>['Report evidence of higher test performance under the measure used.','Do not infer that behavior or incidents changed from that measure alone.','Check transfer opportunities, supervision, exposure, and a suitable observation period.'],'answer'=>'Knowledge evidence improved; the available data do not yet demonstrate reduced incidents.','check'=>'The conclusion matches the outcome measured and its time window.']], 'misconceptions'=>['A reliable test is necessarily valid for every hiring decision.','Candidate ranking alone establishes fairness.','Training satisfaction proves skill transfer.','A before-and-after change proves an organizational intervention caused it.'],'patterns'=>['Link job requirements to assessment methods.','Interpret test and performance evidence within its limits.','Plan a training or intervention evaluation with relevant outcomes.']],
];
$scopeLessons['psychometrician|developmental-psychology'] = [
 'title'=>'Lifespan Development: Theory, Context, and Research Interpretation',
 'material'=>'psychometrician-developmental-psychology-review','lesson'=>'psychometrician-developmental-psychology-review','pdf'=>'psychometrician-developmental-psychology-review.pdf',
 'sources'=>['prc-psychometrician-enhanced-tos-2023'],'instruction'=>['openstax-psychology-2e'],
 'summary'=>'Original review of lifespan developmental principles, major theoretical perspectives, research design, and evidence limits.',
 'objectives'=>['Compare continuous and stage-oriented accounts without treating either as universal.','Interpret findings in biological, cognitive, social, and cultural context.','Distinguish longitudinal, cross-sectional, and correlational evidence and their limits.'],
 'sections'=>[
  ['heading'=>'1. Development across domains and time','body'=>'Development describes patterned change and continuity across the lifespan. Physical, cognitive, language, emotional, and social processes interact; a change in one domain may affect opportunities in another. A lifespan view considers gains, losses, context, and individual variation rather than assuming that all change ends in childhood. Milestones organize questions but do not establish an individual diagnosis or a fixed timetable.','items'=>['Specify the domain and age period.','Separate general patterns from claims about one person.','Consider setting and opportunity.']],
  ['heading'=>'2. Theories and developmental mechanisms','body'=>'Theories offer different explanations for change. Continuous accounts emphasize gradual accumulation, while stage accounts propose qualitative reorganizations in some capacities. Learning, cognition, attachment, maturation, and sociocultural participation highlight different mechanisms. Apply a theory to the evidence supplied; do not convert a broad framework into a guaranteed age-specific rule.','items'=>['Identify the mechanism a theory emphasizes.','Match the framework to the described observation.','State what the framework does not establish.']],
  ['heading'=>'3. Nature, experience, and variation','body'=>'Development reflects interactions among biological conditions, experience, relationships, and environments. A shared exposure can have different effects because learners and contexts differ. An association between an experience and an outcome does not identify causation, direction, or mechanism by itself. Avoid deterministic claims from a single characteristic or exposure; describe evidence and uncertainty carefully.','items'=>['Separate exposure from measured outcome.','Consider timing, individual variation, and other explanations.','Do not infer causation from one association.']],
  ['heading'=>'4. Research design and ethics','body'=>'Longitudinal research follows the same participants and can observe within-person change, but attrition, repeated testing, and historical change matter. Cross-sectional research compares groups at one time and is efficient, but age can be confounded with cohort and period. Correlational research estimates association rather than causal effect. Research with children and families requires consent, privacy, risk management, and review.','items'=>['Name who is measured and when.','Identify cohort, attrition, and measurement limits.','Apply the relevant participant protections.']]],
 'worked'=>[['heading'=>'Compare age groups cautiously','prompt'=>'Two age groups take a language measure in different years, after one cohort experienced extended school closures. What can be inferred from a score difference?','steps'=>['Report the observed difference between the tested groups.','Recognize that age and historical opportunity vary together.','Avoid attributing the difference to age alone without a design that separates those explanations.'],'answer'=>'The groups differ on this measure in this setting; the data do not isolate an age effect.','check'=>'The inference does not exceed the cross-sectional evidence.']],
 'misconceptions'=>['A milestone is an exact deadline for every child.','A stage theory explains every domain and individual equally.','An association proves a developmental cause.','Longitudinal findings cannot be affected by cohort change or attrition.'],
 'patterns'=>['Choose a design for the change question.','Interpret observations with context and limits.','Compare theoretical explanations without treating them as universal laws.']
];

// Update Values lesson and material in place with four current PRC Values units.
foreach ($lessons as &$lesson) {
    if (($lesson['id'] ?? '') !== 'let-values-ethical-reasoning-basics') continue;
    $lesson['title'] = 'Values Education: Foundations, Personhood, Transformative Education, and Work Ethics';
    $lesson['summary'] = 'Original instructional review of the four Values Education units in the current PRC TOS, with ethical analysis, culturally responsive dialogue, reflective action, and applied dilemmas.';
    $lesson['official_scope_source_ids'] = ['prc-values-tos-2025', 'prc-let-enhanced-tos-2025'];
    $lesson['instructional_source_ids'] = ['deped-matatag-values-curriculum', 'deped-esep-curriculum-guide', 'deped-ppst-do-42-2017'];
    $lesson['learning_objectives'] = ['Clarify a value through reasons, affected persons, context, and consequences.','Use a repeatable ethical decision process when duties or values conflict.','Facilitate respectful dialogue across difference while protecting dignity and participation.','Apply civic and professional responsibilities proportionately and reflect on the result.'];
    $lesson['sections'] = [
        ['heading'=>'Unit 1. Foundations of Values Education','body'=>'Values Education develops reasoned understanding and responsible action; it is not a list of slogans or a test of whether a learner uses approved language. Clarify a value by identifying what is valued, why it matters, who is affected, and how context changes a choice. Distinguish a personal preference from a claim about dignity, fairness, duty, or the common good. A sound response gives reasons that others can examine while recognizing that people may begin from different experiences.','items'=>['Name the value at stake and the evidence in the situation.','Ask who benefits, who bears a cost, and whose voice is missing.','Explain why a proposed action follows from the value and facts.']],
        ['heading'=>'Unit 2. Personhood Development and Reflective Agency','body'=>'Personhood includes agency, relationships, responsibilities, and the possibility of growth. Reflective practice examines an action and its effects without reducing a person to one mistake or assuming that intention cancels harm. A learner can acknowledge what happened, listen to affected people, repair what is reasonably repairable, and plan a concrete future practice. Cultural concepts should be discussed in context and with humility; no one learner should be made to represent an entire community.','items'=>['Separate a behavior from a permanent judgment about character.','Listen to the affected person’s account and preferred repair where appropriate.','Choose a specific practice that can be reviewed later.']],
        ['heading'=>'Unit 3. Transformative Education and Ethical Decision','body'=>'For a difficult case, first establish facts and uncertainty; then identify stakeholders, rights, duties, policies, and foreseeable consequences. Generate feasible actions, compare them against relevant principles, and select a proportionate option. Explain what evidence would change the decision and how the outcome will be reviewed. A decision process does not guarantee one answer for every case, but it makes assumptions visible and reduces impulsive or purely consequential reasoning.','items'=>['Fact: what is known, reported, and still uncertain?','Principle: which duty, right, fairness concern, or consequence applies?','Action: what is proportionate, authorized, and reviewable?']],
        ['heading'=>'Unit 4. Work Ethics, Community, and Public Responsibility','body'=>'Professional conduct includes competence, fairness, confidentiality, accountability, and responsible use of authority. A conflict of interest or request involving private information should be handled through the applicable rule and authorized process; good intentions alone do not remove a conflict. Community participation requires accurate information, accessible ways to contribute, and transparent use of input. Digital conduct also affects dignity and safety: verify before sharing, minimize exposure, and consider whether a response amplifies harm.','items'=>['Identify the professional duty and applicable process.','Disclose or limit a conflict or data use as the governing rule requires.','Record decisions and communicate the basis to affected people.']],
        ['heading'=>'Worked dilemma: fair correction','body'=>'A group discovers that a spreadsheet error raised its project score. Members worry that correcting it will lower a grade. Separate the confirmed calculation from fears about consequences, identify the duty to report accurate work, and determine who can correct the official record. Discuss the error with the group, inform the teacher through the proper route, and ask that the score be recalculated. Waiting for discovery or changing the spreadsheet privately avoids accountability and leaves an unreliable record.','items'=>['Verify the error before acting.','Use the authorized correction route.','Accept that fair assessment may change the result.']],
        ['heading'=>'Review: test reasons, not slogans','body'=>'For any proposed action, ask: Is the factual account accurate? Does the response respect dignity and fair participation? Is the duty relevant to this context? Is the action proportionate and within the decision-maker’s authority? What repair or follow-up would show whether it worked? Consider a plausible alternative carefully rather than dismissing it through a label. Ethical judgment is strengthened by evidence, transparent reasoning, and willingness to revise when facts change.','items'=>['State the controlling principle in plain language.','Give one reason a plausible alternative is less appropriate here.','Name a limitation or fact that could change the decision.']],
    ];
    $lesson['worked_examples'] = [['heading'=>'Choose a privacy-respecting response','prompt'=>'A student reports a peer’s harmful post but asks the teacher to keep it secret; the post identifies a student and may lead to harassment.','steps'=>['Listen and clarify immediate risk without promising absolute secrecy.','Explain what limited disclosure may be needed and through which safeguarding route.','Share only the necessary information and check the affected learner’s support needs.'],'answer'=>'Protect trust while explaining its limits and acting through the authorized safety process.','check'=>'The response considers privacy, foreseeable harm, and professional responsibility together.']];
    $lesson['common_misconceptions'] = ['One value automatically overrides every other value in every setting.','Respectful dialogue requires agreement or silence.','A good intention makes disclosure or unequal treatment harmless.','One action permanently defines a person’s character.','A majority vote can replace the rights and duties that apply to a decision.'];
    $lesson['question_patterns'] = ['Resolve an ethical conflict using facts, stakeholders, duties, and proportionality.','Distinguish values clarification from imposing a preferred answer.','Choose respectful dialogue or repair that preserves agency and dignity.','Apply privacy, civic, or professional responsibilities to a realistic case.'];
    $lesson['practice_question_ids'] = array_values(array_unique(array_merge((array) ($lesson['practice_question_ids'] ?? []), array_map('strval', array_merge(array_column($data['values_rewrites'], 0), [])))));
    $lesson['practice_question_ids'] = array_values(array_unique(array_merge($lesson['practice_question_ids'], array_map('strval', range($nextId, $nextId + count($data['values_new']) - 1)))));
    $lesson['pdf_file'] = 'let-values-ethical-reasoning.pdf';
    $lesson['source_review'] = ['status'=>'SOURCE_CHECKED_HUMAN_REVIEW_PENDING','checked_at'=>gmdate('Y-m-d'),'note'=>'PRC scope and linked official curriculum/source records were checked during authoring. Item-level academic review and owner approval remain pending. The lesson is original instructional prose, not a reproduction of source text.'];
}
unset($lesson);

foreach ($scopeLessons as $scope => $spec) {
    [$board, $subject] = explode('|', $scope, 2);
    $subjectQuestionIds = [];
    $currentIndex = $currentIndex ?? new \App\Services\Question\QuestionEligibilityIndex(
        $eligibility, $blueprints, \App\Services\Question\QuestionReuseEligibilityResolver::productionDerivedRelationships(), $storage->all('question-reuse-overrides')
    );
    foreach ($questions as $q) {
        if (!is_array($q)) continue;
        $placement = $currentIndex->forExam($q, $board);
        if (is_array($placement) && (string) ($placement['subject_id'] ?? '') === $subject) $subjectQuestionIds[] = (string) $q['id'];
    }
    foreach ($newIds['psych'] as $id) {
        if ($board === 'psychometrician') {
            $q = $questions[$questionAt[(string) $id]];
            if (in_array((string) $q['taxonomy']['concept_id'], ['psych-development-theories','psych-development-nature-nurture','psych-development-research-ethics'], true) && $subject === 'developmental-psychology') $subjectQuestionIds[] = (string) $id;
        }
    }
    $subjectQuestionIds = array_values(array_unique($subjectQuestionIds));
    $exampleQ = null;
    foreach ($questions as $q) if (is_array($q) && in_array((string) ($q['id'] ?? ''), $subjectQuestionIds, true)) { $exampleQ = $q; break; }
    if (!is_array($exampleQ)) throw new RuntimeException('No subject questions found for lesson ' . $scope);
    $tax = (array) ($exampleQ['taxonomy'] ?? []);
    $lessonRecord = [
        'id'=>$spec['lesson'],'title'=>$spec['title'],'material_id'=>$spec['material'],'status'=>'published','version'=>1,'board_id'=>$board,'subject_id'=>$subject,
        'official_scope_source_ids'=>$spec['sources'],'instructional_source_ids'=>$spec['instruction'],'scope_note'=>$spec['summary'].' Scope is limited to the linked official board outline and curriculum references; this is not an exam prediction.',
        'learning_objectives'=>$spec['objectives'],'sections'=>$spec['sections'],'worked_examples'=>$spec['worked'],'common_misconceptions'=>$spec['misconceptions'],'question_patterns'=>$spec['patterns'],
        'practice_question_ids'=>$subjectQuestionIds,'pdf_file'=>$spec['pdf'],'taxonomy'=>['subject_id'=>$subject,'domain_id'=>(string)($tax['domain_id']??''),'topic_id'=>(string)($tax['topic_id']??''),'concept_id'=>(string)($tax['concept_id']??'')],
        'source_review'=>['status'=>'SOURCE_CHECKED_HUMAN_REVIEW_PENDING','checked_at'=>gmdate('Y-m-d'),'note'=>'Official scope and listed source records were checked for lesson framing; human academic review and owner approval remain pending. Original BoardPrep instruction, not copied source text.'],
    ];
    $lessonExists = false;
    foreach ($lessons as &$old) if (($old['id'] ?? '') === $spec['lesson']) { $old = $lessonRecord; $lessonExists = true; break; }
    unset($old);
    if (!$lessonExists) $lessons[] = $lessonRecord;

    $materialRecord = [
        'id'=>$spec['material'],'title'=>$spec['title'],'summary'=>$spec['summary'],'notes'=>[['Scope',$lessonRecord['scope_note']],['Method','Study the concept explanation, work the example, then answer a question by stating the evidence and decision rule.'],['Review status','Original lesson with linked source references; human academic review remains pending.']],
        'source_ids'=>array_values(array_unique(array_merge($spec['sources'],$spec['instruction']))),'exams'=>[$board],
        'exam_focus'=>[$board=>['priority'=>'medium','rationale'=>'Board-specific study support linked to the current official scope references; not an exam prediction.','official_scope_status'=>'source-linked instructional support','focus_topics'=>array_column($spec['sections'],'heading')]],
        'taxonomy'=>['subject_id'=>$subject,'domain_id'=>(string)($tax['domain_id']??''),'topic_id'=>(string)($tax['topic_id']??''),'concept_id'=>(string)($tax['concept_id']??'')],
        'status'=>'published','version'=>1,'updated_at'=>gmdate('Y-m-d'),'batch_marker'=>'batch694',
    ];
    $materialExists = false;
    foreach ($materials as &$old) if (($old['id'] ?? '') === $spec['material']) { $old = $materialRecord; $materialExists = true; break; }
    unset($old);
    if (!$materialExists) $materials[] = $materialRecord;

    foreach ($subjectQuestionIds as $qid) {
        foreach ($questions as &$q) {
            if ((string) ($q['id'] ?? '') !== $qid) continue;
            $q['study_material_id'] = $spec['material'];
            $q['study_section_id'] = $board === 'psychometrician' && $subject === 'developmental-psychology' ? 'lifespan-development' : 'subject-foundations';
            break;
        }
        unset($q);
        $foundLink = false;
        foreach ($links as &$link) {
            if ((string) ($link['question_id'] ?? '') === $qid && (string) ($link['material_id'] ?? '') === $spec['material']) {
                $link['source_ids'] = array_values(array_unique(array_merge((array) ($link['source_ids'] ?? []), array_merge($spec['sources'],$spec['instruction']))));
                $foundLink = true;
                break;
            }
        }
        unset($link);
        if (!$foundLink) $links[] = ['id'=>'batch694-'.$board.'-'.$qid,'material_id'=>$spec['material'],'question_id'=>$qid,'source_ids'=>array_values(array_unique(array_merge($spec['sources'],$spec['instruction'])))];
    }
}
unset($q);

// Add new question lesson/source links and update reauthored Values links.
$allValuesIds = array_map('strval', array_merge(array_column($data['values_rewrites'], 0), $newIds['values']));
$allPsychDevelopmentIds = array_map('strval', array_merge(array_column($data['psych_development_new'], 0), []));
$allPsychDevelopmentIds = array_map('strval', array_merge($newIds['psych']));
foreach ($allValuesIds as $qid) {
    $qAt = $questionAt[$qid] ?? null;
    if ($qAt === null) continue;
    $questions[$qAt]['study_material_id'] = 'let-specialization-values-education';
    $questions[$qAt]['study_section_id'] = 'values-four-units';
    $found = false;
    foreach ($links as &$link) if ((string) ($link['question_id'] ?? '') === $qid && ($link['material_id'] ?? '') === 'let-specialization-values-education') { $link['source_ids'] = $sourceValues; $found = true; break; }
    unset($link);
    if (!$found) $links[] = ['id'=>'batch694-let-'.$qid,'material_id'=>'let-specialization-values-education','question_id'=>$qid,'source_ids'=>$sourceValues];
}
unset($lesson);

// Update existing Values material source inventory.
foreach ($materials as &$material) {
    if (($material['id'] ?? '') !== 'let-specialization-values-education') continue;
    $material['source_ids'] = array_values(array_unique(array_merge((array) ($material['source_ids'] ?? []), $sourceValues, ['deped-matatag-values-curriculum'])));
    $material['summary'] = 'Original Values Education study lesson aligned to the four current PRC TOS units: foundations, personhood development, transformative education, and work ethics. It teaches a reasoned decision method through varied dilemmas; it does not predict exam questions.';
    $material['updated_at'] = gmdate('Y-m-d');
    break;
}
unset($material);

// Direct official scope references; the MATATAG landing page is cited as the
// official curriculum index, not as a PDF whose body was inaccessible here.
$sourceAdditions = [
 ['id'=>'prc-values-tos-2025','title'=>'Professional Teachers Table of Specifications, Values Education Secondary Specialization (2025)','source_type'=>'OFFICIAL_TOS','authority'=>'Professional Regulation Commission, Professional Regulatory Board for Professional Teachers','url'=>'https://www.prc.gov.ph/sites/default/files/2025-11%20published%20PROFESSIONAL%20TEACHERS.pdf','publication_date'=>'2025','retrieved_at'=>gmdate('Y-m-d'),'version'=>'2025 published TOS','status'=>'verified_reference','confidence'=>'authoritative_reference','notes'=>'Official PRC TOS used to organize this original lesson into Foundations of Values Education, Personhood Development, Transformative Education, and Work Ethics. It supports scope framing, not independent validation of every item.','source_role'=>'EXAM_SCOPE','volatility'=>'CURRICULUM_VERSIONED'],
 ['id'=>'deped-matatag-values-curriculum','title'=>'MATATAG Curriculum: official curriculum guides index','source_type'=>'OFFICIAL_PROGRAM','authority'=>'Department of Education, Philippines','url'=>'https://www.deped.gov.ph/matatagcurriculumk147/','publication_date'=>'2024','retrieved_at'=>gmdate('Y-m-d'),'version'=>'MATATAG curriculum index','status'=>'verified_reference','confidence'=>'authoritative_reference','notes'=>'Official DepEd index identifying Values Education/GMRC curriculum-guide materials. The guide PDF body was not independently inspected in this batch; use the PRC TOS and accessible curriculum records for the specific lesson scope.','source_role'=>'CURRICULUM_CONTEXT','volatility'=>'CURRICULUM_VERSIONED'],
];
foreach ($sourceAdditions as $source) {
    $exists = false;
    foreach ($sources as &$old) if (($old['id'] ?? '') === $source['id']) { $old = $source; $exists = true; break; }
    unset($old);
    if (!$exists) $sources[] = $source;
}
unset($source);

// Strong evidence hash follows SourceReadinessRecord's canonical payload.
foreach ($readiness as &$record) {
    if (!isset($psychAddsByConcept[(string) ($record['leaf_id'] ?? '')])) continue;
    $record['evidence_hash'] = \App\Services\RepositoryHealth\Readiness\SourceReadinessRecord::evidenceHash($record);
}
unset($record);

// Persist each collection once after all in-memory preconditions have passed.
if (($argv[1] ?? '') === '--apply') {
    $storage->replace('questions', $questions);
    $storage->replace('question-eligibility', $eligibility);
    $storage->replace('study-lessons', $lessons);
    $storage->replace('study-materials', $materials);
    $storage->replace('study-question-links', $links);
    $storage->replace('sources', $sources);
    $readinessPath = dirname(__DIR__) . '/storage/source-readiness-psychometrician.json';
    $readinessTemporary = $readinessPath . '.batch694-tmp';
    file_put_contents($readinessTemporary, json_encode($readiness, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n", LOCK_EX);
    if (!rename($readinessTemporary, $readinessPath)) throw new RuntimeException('Could not atomically update psychometrician source-readiness records.');
}

printf("RECLASSIFIED=%d\nVALUES_REAUTHORED=%d\nVALUES_ADDED=%d\nPSYCH_DEVELOPMENT_ADDED=%d\nLESSONS=%d\n", count($reclassified), count($data['values_rewrites']), count($newIds['values']), count($newIds['psych']), count($scopeLessons) + 1);
$classificationGroups = [];
foreach ($reclassified as $change) {
    $key = $change['board_id'] . '|' . $change['from'] . '|' . $change['to'];
    $classificationGroups[$key] = ($classificationGroups[$key] ?? 0) + 1;
}
printf("CLASSIFICATION_GROUPS=%s\n", json_encode($classificationGroups, JSON_UNESCAPED_SLASHES));
printf("NEW_IDS=%s\n", json_encode(['values'=>$newIds['values'],'psychometrician'=>$newIds['psych']], JSON_UNESCAPED_SLASHES));
