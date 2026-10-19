#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();
putenv('APP_ENV=testing');
putenv('APP_DEBUG=false');
\App\Core\App::boot();

$args = array_slice($argv, 1);
$options = [];
foreach ($args as $arg) {
    if (!preg_match('/^--(inventory|json|markdown)=(.+)$/', $arg, $match)) {
        fwrite(STDERR, "Usage: php tools/audit-academic-quality.php --inventory=PATH --json=PATH --markdown=PATH\n");
        exit(2);
    }
    $options[$match[1]] = $match[2];
}
foreach (['inventory','json','markdown'] as $required) if (!isset($options[$required])) throw new RuntimeException('Missing --' . $required . '.');
$inventory = json_decode((string) file_get_contents($options['inventory']), true, 512, JSON_THROW_ON_ERROR);
if (!is_array($inventory['subjects'] ?? null)) throw new RuntimeException('Inventory schema is missing subjects.');
$storage = \App\Core\App::storage();
$questions = \App\Core\App::container()->get(\App\Repositories\QuestionRepository::class)->all();
$questionById = [];
foreach ($questions as $question) if (is_array($question)) $questionById[(string) ($question['id'] ?? '')] = $question;
$lessons = $storage->all('study-lessons');
$materials = $storage->all('study-materials');
$links = $storage->all('study-question-links');
$linksByQuestion = [];
foreach ($links as $link) {
    if (is_array($link) && is_scalar($link['question_id'] ?? null)) {
        $linksByQuestion[(string) $link['question_id']][] = $link;
    }
}
$contentFitByQuestion = [];
foreach (array_values(array_filter($storage->all('content-fit-audits'), 'is_array')) as $fitAudit) {
    $contentFitByQuestion[(string) ($fitAudit['question_id'] ?? '')][] = $fitAudit;
}
$index = \App\Services\Question\QuestionEligibilityService::index();
$lessonByScope = [];
$lessonById = [];
$lessonByMaterial = [];
foreach ($lessons as $lesson) {
    if (!is_array($lesson) || ($lesson['status'] ?? '') !== 'published') continue;
    $scope = strtolower((string) ($lesson['board_id'] ?? '')) . '|' . strtolower((string) ($lesson['subject_id'] ?? ''));
    $lessonByScope[$scope][] = $lesson;
    $lessonById[(string) ($lesson['id'] ?? '')] = $lesson;
    $materialId = (string) ($lesson['material_id'] ?? '');
    if ($materialId !== '') $lessonByMaterial[$materialId] ??= $lesson;
}
$materialById = [];
foreach ($materials as $material) if (is_array($material)) $materialById[(string) ($material['id'] ?? '')] = $material;
$findings = [];
$add = static function (string $code, string $kind, string $severity, string $board, string $subject, string $topic, string|int $questionId, string $evidence, string $action, array $details = []) use (&$findings): void {
    $findings[] = ['code'=>$code,'class'=>$kind,'severity'=>$severity,'board_id'=>$board,'subject_id'=>$subject,'topic_id'=>$topic,'question_id'=>(string) $questionId,'evidence'=>$evidence,'suggested_action'=>$action,'details'=>$details];
};
$scopeRows = [];
foreach ($inventory['subjects'] as $row) {
    if (!is_array($row)) continue;
    $board = strtolower((string) ($row['board_id'] ?? ''));
    $subject = strtolower((string) ($row['subject_id'] ?? ''));
    $scope = $board . '|' . $subject;
    $scopeRows[$scope] = $row;
    $count = (int) ($row['effective_eligible_question_count'] ?? 0);
    $topicDistribution = (array) ($row['topic_distribution'] ?? []);
    $conceptCount = (int) ($row['unique_concept_count'] ?? 0);
    if ($count < 100) $add('SUBJECT_DEPTH_BELOW_100','DETERMINISTIC','WARNING',$board,$subject,'','',"{$count} unique active eligible question IDs; deficit " . (100-$count) . '.', 'Expand from source-supported blueprint concepts; document an evidence-backed scope exception if 100 is not warranted.', ['effective_question_count'=>$count,'deficit'=>100-$count]);
    if ($count >= 20 && count($topicDistribution) < 3) $add('SUBJECT_TOPIC_COVERAGE_GAP','HEURISTIC_REVIEW','REVIEW',$board,$subject,'','',count($topicDistribution) . ' observed question topics; this is a coverage cue, not a blueprint conformance conclusion.', 'Compare observed topics against the official blueprint and add supported topic-level coverage where needed.', ['observed_topics'=>array_keys($topicDistribution),'unique_concepts'=>$conceptCount]);
    if ($count >= 20 && $conceptCount > 0) {
        $maxConcept = 0;
        foreach ((array) ($row['concept_distribution'] ?? []) as $n) $maxConcept = max($maxConcept, (int) $n);
        if ($maxConcept > $count * .4) $add('SUBJECT_CONCEPT_CONCENTRATION','HEURISTIC_REVIEW','REVIEW',$board,$subject,'','',"Largest concept count {$maxConcept} of {$count} (" . round(100*$maxConcept/$count,1) . '%).', 'Check whether the official scope is genuinely narrow or the bank overuses a small concept set.', ['largest_concept_count'=>$maxConcept,'effective_question_count'=>$count]);
    }
    if (count($lessonByScope[$scope] ?? []) === 0) $add('STUDY_MATERIAL_MISSING','DETERMINISTIC','WARNING',$board,$subject,'','', 'No published lesson exists for this learner-facing selector.', 'Create an original instructional lesson and a validated downloadable resource.');
    foreach ($lessonByScope[$scope] ?? [] as $lesson) {
        $materialId = (string) ($lesson['material_id'] ?? '');
        $pdf = (string) ($lesson['pdf_file'] ?? '');
        $manifest = json_decode((string) @file_get_contents(dirname(__DIR__) . '/public/study-resources/manifest.json'), true) ?: [];
        $manifestEntry = null;
        foreach ((array) ($manifest['files'] ?? []) as $entry) if (is_array($entry) && ($entry['lesson_id'] ?? '') === ($lesson['id'] ?? '')) { $manifestEntry = $entry; break; }
        $path = dirname(__DIR__) . '/public/study-resources/' . $pdf;
        if (!isset($materialById[$materialId]) || $pdf === '' || !is_file($path) || !is_array($manifestEntry) || !hash_equals((string) ($manifestEntry['sha256'] ?? ''), (string) (is_file($path) ? hash_file('sha256',$path) : ''))) {
            $add('STUDY_MATERIAL_BROKEN','DETERMINISTIC','ERROR',$board,$subject,'','', 'Published lesson lacks its registered material or the PDF/manifest path or hash is invalid.', 'Repair the material reference and regenerate the PDF manifest after validating source links.', ['lesson_id'=>$lesson['id']??'','material_id'=>$materialId,'pdf_file'=>$pdf]);
        }
    }
}

$activeSubjects = [];
foreach ($scopeRows as $scope => $row) $activeSubjects[$scope] = true;
$scopedQuestionIds = [];
foreach ($questionById as $id => $question) {
    $id = (string) $id;
    foreach (array_keys($activeSubjects) as $scope) {
        [$board,$subject] = explode('|',$scope,2);
        $placement = $index->forExam($question, $board);
        if (!is_array($placement) || strtolower((string) ($placement['subject_id'] ?? '')) !== $subject) continue;
        if (($question['status'] ?? 'active') !== 'active') continue;
        $scopedQuestionIds[$scope][$id] = true;
        $isBatch694 = ($question['batch_marker'] ?? '') === 'batch694';
        $isDeficient = (int) ($scopeRows[$scope]['effective_eligible_question_count'] ?? 0) < 100;
        $hasBatch696SectionLink = false;
        foreach ($linksByQuestion[$id] ?? [] as $link) {
            if (!is_array($link)) continue;
            $targetLesson = $lessonByMaterial[(string) ($link['material_id'] ?? '')] ?? null;
            if (is_array($targetLesson)
                && strtolower((string) ($targetLesson['board_id'] ?? '')) === $board
                && strtolower((string) ($targetLesson['subject_id'] ?? '')) === $subject
                && str_starts_with((string) ($link['id'] ?? ''), 'batch696-')) {
                $hasBatch696SectionLink = true;
                break;
            }
        }
        if (!$isBatch694 && !$isDeficient && !$hasBatch696SectionLink) continue;
        $hasLesson = false;
        foreach ($lessonByScope[$scope] ?? [] as $lesson) {
            if (!in_array($id, array_map('strval', (array) ($lesson['practice_question_ids'] ?? [])), true)) continue;
            $hasLesson = true;
            $sectionId = (string) ($question['study_section_id'] ?? '');
            if ($sectionId !== '') {
                $foundSection = null;
                foreach ((array) ($lesson['sections'] ?? []) as $section) if (is_array($section) && (string) ($section['id'] ?? '') === $sectionId) { $foundSection = $section; break; }
                if (!is_array($foundSection)) {
                    $add('QUESTION_LESSON_MISSING','DETERMINISTIC','ERROR',$board,$subject,(string) ($question['taxonomy']['topic_id'] ?? ''),$id,'Question names a study section that does not resolve in its linked lesson.','Repair the question-to-section identifier.',['lesson_id'=>$lesson['id']??'','section_id'=>$sectionId]);
                } else {
                    $tax = (array) ($question['taxonomy'] ?? []);
                    $relatedTopics = array_map('strval',(array) ($foundSection['related_topic_ids'] ?? []));
                    $relatedConcepts = array_map('strval',(array) ($foundSection['related_concept_ids'] ?? []));
                    $topic = (string) ($tax['topic_id'] ?? '');
                    $concept = (string) ($tax['concept_id'] ?? '');
                    if (($topic !== '' && !in_array($topic,$relatedTopics,true)) || ($concept !== '' && !in_array($concept,$relatedConcepts,true))) $add('QUESTION_LESSON_CONCEPT_MISMATCH','DETERMINISTIC','ERROR',$board,$subject,$topic,$id,'The selected lesson section does not list this question topic/concept in its instructional crosswalk.','Review the section content and its explicit taxonomy crosswalk.',['lesson_id'=>$lesson['id']??'','section_id'=>$sectionId,'concept_id'=>$concept]);
                }
            }
            break;
        }
        foreach ($linksByQuestion[$id] ?? [] as $link) {
            if (!is_array($link)) continue;
            $targetLesson = $lessonByMaterial[(string) ($link['material_id'] ?? '')] ?? null;
            if (!is_array($targetLesson)
                || strtolower((string) ($targetLesson['board_id'] ?? '')) !== $board
                || strtolower((string) ($targetLesson['subject_id'] ?? '')) !== $subject) continue;
            $hasLesson = true;
            $sectionId = (string) ($link['section_id'] ?? $link['lesson_section_id'] ?? '');
            $foundSection = null;
            foreach ((array) ($targetLesson['sections'] ?? []) as $section) {
                if (is_array($section) && (string) ($section['id'] ?? '') === $sectionId) {
                    $foundSection = $section;
                    break;
                }
            }
            if ($sectionId === '' || !is_array($foundSection)) {
                $add('QUESTION_LESSON_SECTION_MISSING','DETERMINISTIC','ERROR',$board,$subject,(string) ($question['taxonomy']['topic_id'] ?? ''),$id,'The question-study link does not resolve an exact section in its same-scope lesson.','Repair the material, lesson, and section relationship.',['lesson_id'=>$targetLesson['id']??'','material_id'=>$link['material_id']??'','section_id'=>$sectionId]);
                continue;
            }
            $tax = (array) ($question['taxonomy'] ?? []);
            $relatedTopics = array_map('strval',(array) ($foundSection['related_topic_ids'] ?? []));
            $relatedConcepts = array_map('strval',(array) ($foundSection['related_concept_ids'] ?? []));
            $topic = (string) ($tax['topic_id'] ?? '');
            $concept = (string) ($tax['concept_id'] ?? '');
            if (($topic !== '' && !in_array($topic,$relatedTopics,true)) || ($concept !== '' && !in_array($concept,$relatedConcepts,true))) {
                $add('QUESTION_LESSON_CONCEPT_MISMATCH','DETERMINISTIC','ERROR',$board,$subject,$topic,$id,'The exact linked section omits the question taxonomy topic or concept from its explicit crosswalk.','Review the section content and its taxonomy crosswalk.',['lesson_id'=>$targetLesson['id']??'','section_id'=>$sectionId,'concept_id'=>$concept]);
            }
            if (str_contains(strtoupper((string) ($link['semantic_review_status'] ?? '')), 'PENDING')) {
                $add('QUESTION_LESSON_SEMANTIC_REVIEW','HEURISTIC_REVIEW','REVIEW',$board,$subject,$topic,$id,'Section and source pointers exist, but the link explicitly awaits independent semantic academic review.','Compare question wording, answer, explanation, lesson text, and source evidence; retain pending status until reviewed.',['lesson_id'=>$targetLesson['id']??'','section_id'=>$sectionId,'link_id'=>$link['id']??'']);
            }
        }
        if (!$hasLesson) {
            $hasSourceLink = false;
            $hasSourceLink = ($linksByQuestion[$id] ?? []) !== [];
            $add($hasSourceLink ? 'STUDY_MATERIAL_SOURCE_ONLY' : 'QUESTION_LESSON_MISSING','DETERMINISTIC','ERROR',$board,$subject,(string) ($question['taxonomy']['topic_id'] ?? ''),$id,$hasSourceLink ? 'A source/citation link exists but no matching instructional lesson practice link resolves.' : 'Question is in a newly authored or still-deficient active subject but has no matching published lesson practice link.',$hasSourceLink ? 'Create or link an instructional section; a citation is not a lesson.' : 'Link the question to the lesson section that teaches its tested concept.');
        }
        foreach ($contentFitByQuestion[$id] ?? [] as $fitAudit) {
            $fit = strtoupper((string) ($fitAudit['fit_classification'] ?? ''));
            if (in_array($fit, ['MISMATCH','WEAK','UNSUPPORTED','CONFLICT'], true)) {
                $add('QUESTION_SOURCE_SEMANTIC_MISMATCH','HEURISTIC_REVIEW','REVIEW',$board,$subject,(string) ($question['taxonomy']['topic_id'] ?? ''),$id,'Stored source-fit audit is ' . $fit . '; this is a review cue, not an automated semantic verdict.','Compare the stem, answer, taxonomy, lesson, and cited source; change content only when evidence supports it.',['fit_classification'=>$fitAudit['fit_classification'],'audit_id'=>$fitAudit['id']??'']);
            }
        }
    }
}

$distractorAudit = \App\Services\Quality\QuestionDistractorAuditService::analyze($questions);
$taxonomyById = $questionById;
$codeMap = [
 'EXACT_OR_NORMALIZED_DUPLICATE'=>'DISTRACTOR_EXACT_DUPLICATE',
 'NEAR_DUPLICATE_OPTIONS'=>'DISTRACTOR_NEAR_DUPLICATE',
 'OPTION_LENGTH_OUTLIER'=>'DISTRACTOR_LENGTH_OUTLIER',
 'OBVIOUS_POLARITY_CUE'=>'DISTRACTOR_OBVIOUS_POLARITY_CUE',
 'ANSWER_FORMATTING_OUTLIER'=>'DISTRACTOR_LENGTH_OUTLIER',
 'REPEATED_DISTRACTOR_TEXT'=>'DISTRACTOR_SEMANTIC_REPETITION_CUE',
];
foreach ($distractorAudit['findings'] as $finding) {
    $id = (string) ($finding['question_id'] ?? '');
    $q = $taxonomyById[$id] ?? null;
    $details = (array) ($finding['details'] ?? []);
    $board = '';
    $subject = '';
    $topic = '';
    if (is_array($q)) {
        $tax = (array) ($q['taxonomy'] ?? []);
        $board = strtolower((string) ($tax['board_id'] ?? $q['board'] ?? ''));
        $subject = strtolower((string) ($tax['subject_id'] ?? $q['subject'] ?? ''));
        $topic = (string) ($tax['topic_id'] ?? '');
    } elseif (isset($details['group'])) {
        [$board,$subject] = array_pad(explode('|',(string) $details['group'],2),2,'');
    }
    $code = $codeMap[(string) ($finding['code'] ?? '')] ?? (string) ($finding['code'] ?? 'ACADEMIC_REVIEW');
    $class = ($finding['severity'] ?? '') === 'ERROR' ? 'DETERMINISTIC' : 'HEURISTIC_REVIEW';
    $add($code,$class,(string) ($finding['severity'] ?? 'REVIEW'),$board,$subject,$topic,$id,(string) ($finding['reason'] ?? ''),'Review the options against the source-backed concept; heuristics do not establish an academic defect.',$details);
}

$findings = array_values(array_unique($findings, SORT_REGULAR));
usort($findings, static fn(array $a,array $b): int => [$a['class'],$a['severity'],$a['board_id'],$a['subject_id'],$a['topic_id'],$a['question_id'],$a['code']] <=> [$b['class'],$b['severity'],$b['board_id'],$b['subject_id'],$b['topic_id'],$b['question_id'],$b['code']]);
$summary = ['deterministic_errors'=>count(array_filter($findings,static fn(array $x):bool=>$x['class']==='DETERMINISTIC'&&$x['severity']==='ERROR')),'deterministic_warnings'=>count(array_filter($findings,static fn(array $x):bool=>$x['class']==='DETERMINISTIC'&&$x['severity']==='WARNING')),'heuristic_review_candidates'=>count(array_filter($findings,static fn(array $x):bool=>$x['class']==='HEURISTIC_REVIEW')),'unresolved_source_semantic_review'=>'HUMAN_REVIEW_PENDING','ambiguous_answer_review'=>'HUMAN_REVIEW_PENDING'];
$checkCatalog = [
    'SUBJECT_DEPTH_BELOW_100'=>'EFFECTIVE_ELIGIBILITY_CHECKED',
    'SUBJECT_TOPIC_COVERAGE_GAP'=>'HEURISTIC_REVIEW',
    'SUBJECT_CONCEPT_CONCENTRATION'=>'HEURISTIC_REVIEW',
    'DISTRACTOR_EXACT_DUPLICATE'=>'DETERMINISTIC_CHECKED',
    'DISTRACTOR_NEAR_DUPLICATE'=>'HEURISTIC_REVIEW',
    'DISTRACTOR_SEMANTIC_REPETITION_CUE'=>'HEURISTIC_REVIEW',
    'DISTRACTOR_LENGTH_OUTLIER'=>'HEURISTIC_REVIEW',
    'DISTRACTOR_OBVIOUS_POLARITY_CUE'=>'HEURISTIC_REVIEW',
    'QUESTION_AMBIGUOUS_ANSWER_REVIEW'=>'HUMAN_REVIEW_REQUIRED; AUTOMATION_CANNOT_PROVE_UNIQUE_BEST_ANSWER',
    'QUESTION_SOURCE_SEMANTIC_MISMATCH'=>'STORED_FIT_AUDITS_CHECKED; SEMANTIC_REVIEW_REMAINS_HUMAN',
    'QUESTION_LESSON_MISSING'=>'STRUCTURAL_LINK_CHECKED',
    'QUESTION_LESSON_SECTION_MISSING'=>'DETERMINISTIC_EXACT_SECTION_CHECKED',
    'QUESTION_LESSON_CONCEPT_MISMATCH'=>'STRUCTURAL_CROSSWALK_CHECKED',
    'QUESTION_LESSON_SEMANTIC_REVIEW'=>'HEURISTIC_CANDIDATE; HUMAN_REVIEW_REQUIRED',
    'STUDY_MATERIAL_MISSING'=>'STRUCTURAL_SCOPE_CHECKED',
    'STUDY_MATERIAL_BROKEN'=>'PATH_AND_HASH_CHECKED',
    'STUDY_MATERIAL_SOURCE_ONLY'=>'LESSON_VERSUS_CITATION_CHECKED',
    'CROSS_BOARD_SUBJECT_MISMATCH'=>'EFFECTIVE_PLACEMENTS_CHECKED; SEE_CLASSIFICATION_AUDIT',
];
$report = ['report_type'=>'ACADEMIC_QUALITY_AUDIT','schema_version'=>1,'generated_at'=>gmdate(DATE_ATOM),'inventory_path'=>$options['inventory'],'summary'=>$summary,'check_catalog'=>$checkCatalog,'findings'=>$findings,'interpretation'=>'Deterministic findings describe structural integrity only. Heuristic cues are review candidates. Curriculum alignment, semantic source fit, and whether only one answer is defensibly best require human academic judgment.'];
foreach (['json'=>'JSON','markdown'=>'Markdown'] as $key=>$format) {
    $path = $options[$key];
    if ($format === 'JSON') $contents = json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
    else {
        $lines = ['# Academic Quality Audit','',"Generated: `{$report['generated_at']}`",'', '## Summary','', '- Deterministic structural errors: ' . $summary['deterministic_errors'], '- Deterministic release warnings: ' . $summary['deterministic_warnings'], '- Heuristic academic review candidates: ' . $summary['heuristic_review_candidates'], '- Semantic source fit: HUMAN_REVIEW_PENDING', '- Ambiguous-answer review: HUMAN_REVIEW_PENDING', '', 'Heuristic findings are not confirmed academic defects. The tool cannot establish curriculum validity or that one answer is uniquely best.', '', '## Automated check coverage','', '| Check | Status and limit |','|---|---|'];
        foreach ($checkCatalog as $code=>$status) $lines[] = '| '.$code.' | '.$status.' |';
        $lines = array_merge($lines, ['', '## Findings', '']);
        $grouped = [];
        foreach ($findings as $f) {
            $key = json_encode([$f['class'],$f['severity'],$f['code'],$f['board_id'],$f['subject_id'],$f['topic_id'],$f['evidence'],$f['suggested_action']], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
            if (!isset($grouped[$key])) $grouped[$key] = $f + ['question_ids'=>[],'record_count'=>0];
            $ids = $f['question_id'] !== '' ? [$f['question_id']] : (array) ($f['details']['question_ids'] ?? []);
            $grouped[$key]['question_ids'] = array_values(array_unique(array_merge($grouped[$key]['question_ids'],$ids)));
            $grouped[$key]['record_count']++;
        }
        $lines[] = 'The Markdown view groups identical findings by class, severity, code, board, subject, topic, evidence, and action. Question-level records and details remain unaggregated in the JSON report.';
        $lines[] = '';
        $lines[] = '| Class | Severity | Code | Board | Subject | Topic | Records | Question IDs | Evidence | Suggested action |';
        $lines[] = '|---|---|---|---|---|---|---:|---|---|---|';
        foreach ($grouped as $f) {
            sort($f['question_ids'], SORT_NATURAL);
            $values = [$f['class'],$f['severity'],$f['code'],$f['board_id'],$f['subject_id'],$f['topic_id'],$f['record_count'],implode(', ',array_map('strval',$f['question_ids'])),$f['evidence'],$f['suggested_action']];
            $lines[] = '| '.implode(' | ',array_map(static fn(mixed $v):string=>str_replace('|','\\|',(string)$v),$values)).' |';
        }
        $contents = implode("\n",$lines)."\n";
    }
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir,0755,true)) throw new RuntimeException('Cannot create report directory: '.$dir);
    file_put_contents($path.'.tmp',$contents,LOCK_EX);
    if (!rename($path.'.tmp',$path)) throw new RuntimeException('Cannot publish report: '.$path);
}
printf("FINDINGS=%d DETERMINISTIC_ERRORS=%d HEURISTIC_REVIEW=%d\nJSON=%s\nMARKDOWN=%s\n",count($findings),$summary['deterministic_errors'],$summary['heuristic_review_candidates'],$options['json'],$options['markdown']);
