#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();
putenv('APP_ENV=testing');
putenv('APP_DEBUG=false');
\App\Core\App::boot();
$storage = \App\Core\App::storage();
$questions = $storage->all('questions');
$lessons = $storage->all('study-lessons');
$links = $storage->all('study-question-links');
$eligibility = new \App\Services\Question\QuestionEligibilityIndex(
    $storage->all('question-eligibility'),
    $storage->all('blueprints'),
    \App\Services\Question\QuestionReuseEligibilityResolver::productionDerivedRelationships(),
    $storage->all('question-reuse-overrides')
);
$qIndex = [];
foreach ($questions as $index => $question) if (is_array($question)) $qIndex[(string) ($question['id'] ?? '')] = $index;
$lessonByMaterial = [];
$sectionByQuestion = [];
$scopeLessonIds = [
 'let|early-childhood-education'=>'let-early-childhood-learning-and-inclusion',
 'let|special-needs-education'=>'let-special-needs-inclusive-education',
 'let|technical-vocational-teacher-education'=>'let-tvted-competency-based-teaching',
 'let|culture-and-arts-education'=>'let-culture-arts-learning-foundation',
 'let|physical-education'=>'let-physical-education-movement-and-safety',
 'architecture|architecture-utilities-and-construction'=>'architecture-building-systems-and-construction',
 'psychometrician|developmental-psychology'=>'psychometrician-developmental-psychology-review',
 'psychometrician|abnormal-psychology'=>'psychometrician-abnormal-psychology-classification-and-assessment',
 'psychometrician|industrial-organizational'=>'psychometrician-work-psychology-job-analysis-and-evaluation',
];
$sectionFor = static function (array $q, string $board, string $subject): string {
    $tax = is_array($q['taxonomy'] ?? null) ? $q['taxonomy'] : [];
    $topic = strtolower((string) ($tax['topic_id'] ?? ''));
    $concept = strtolower((string) ($tax['concept_id'] ?? ''));
    if ($subject === 'developmental-psychology') {
        return match ($concept) {
            'psych-development-theories' => '2-theories-and-developmental-mechanisms',
            'psych-development-nature-nurture' => '3-nature-experience-and-variation',
            'psych-development-research-ethics' => '4-research-design-and-ethics',
            default => '1-development-across-domains-and-time',
        };
    }
    if ($board === 'let' && $subject === 'early-childhood-education') {
        if (str_contains($topic, 'observation') || str_contains($topic, 'assessment')) return '3-observation-and-assessment';
        if (str_contains($topic, 'family') || str_contains($topic, 'inclusion')) return '4-family-partnership-and-inclusion';
        return '1-development-in-context';
    }
    if ($board === 'let' && $subject === 'special-needs-education') {
        if (str_contains($topic, 'collaboration')) return '4-assessment-behavior-and-partnership';
        if (str_contains($topic, 'instruction') || str_contains($topic, 'assessment')) return '2-accommodation-modification-and-validity';
        return '1-start-with-the-participation-barrier';
    }
    if ($board === 'let' && $subject === 'technical-vocational-teacher-education') {
        if (str_contains($topic, 'safety')) return '3-safety-is-part-of-competence';
        if (str_contains($topic, 'assessment') || str_contains($topic, 'workplace-learning') || str_contains($topic, 'industry') || str_contains($topic, 'employability')) return '4-assessment-remediation-and-workplace-links';
        if (str_contains($topic, 'resources') || str_contains($topic, 'tools')) return '2-demonstrate-practice-coach';
        return '1-competency-and-performance-evidence';
    }
    if ($board === 'let' && $subject === 'culture-and-arts-education') {
        if (str_contains($topic, 'heritage') || str_contains($topic, 'cultural')) return '3-cultural-context-and-attribution';
        if (str_contains($topic, 'creative') || str_contains($topic, 'media') || str_contains($topic, 'performance')) return '2-making-and-revising';
        if (str_contains($topic, 'pedagogy') || str_contains($topic, 'assessment') || str_contains($topic, 'ethics') || str_contains($topic, 'inclusion')) return '4-critique-and-access';
        return '1-describe-before-interpreting';
    }
    if ($board === 'let' && $subject === 'physical-education') {
        if (str_contains($topic, 'fitness') || str_contains($topic, 'wellness')) return '2-fitness-concepts-and-assessment';
        if (str_contains($topic, 'inclusion')) return '4-inclusion-rules-and-reflection';
        if (str_contains($topic, 'safety')) return '3-safety-and-progression';
        return '1-movement-skill-and-feedback';
    }
    if ($board === 'architecture') return '2-structure-and-service-coordination';
    if ($subject === 'abnormal-psychology') {
        if (str_contains($concept, 'assessment') || str_contains($concept, 'screen') || str_contains($concept, 'interventions')) return '3-measurement-and-screening';
        if (str_contains($concept, 'ethic') || str_contains($concept, 'professional') || str_contains($concept, 'referral')) return '4-ethical-communication-and-referral';
        if (str_contains($concept, 'symptom') || str_contains($concept, 'disorder') || str_contains($concept, 'classif')) return '1-classification-is-a-shared-language';
        return '2-pattern-duration-and-functioning';
    }
    if ($subject === 'industrial-organizational') {
        if (str_contains($concept, 'selection') || str_contains($concept, 'measure')) return '2-selection-and-measurement';
        if (str_contains($concept, 'training')) return '3-training-and-transfer';
        if (str_contains($concept, 'performance') || str_contains($concept, 'organization') || str_contains($concept, 'team') || str_contains($concept, 'change')) return '4-performance-and-organizational-evidence';
        return '1-start-with-the-work';
    }
    return '1-start-with-the-work';
};
$lessonAt = [];
foreach ($lessons as $i => $lesson) if (is_array($lesson)) $lessonAt[(string) ($lesson['id'] ?? '')] = $i;
foreach ($scopeLessonIds as $scope => $lessonId) {
    if (!isset($lessonAt[$lessonId])) throw new RuntimeException('Expected lesson missing: ' . $lessonId);
    [$board, $subject] = explode('|', $scope, 2);
    $lessonIndex = $lessonAt[$lessonId];
    foreach ($lessons[$lessonIndex]['sections'] as $sectionIndex => &$section) {
        $headingSlug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', (string) ($section['heading'] ?? '')) ?? '');
        $headingSlug = trim($headingSlug, '-');
        $section['id'] ??= ($headingSlug !== '' ? $headingSlug : sprintf('section-%02d', $sectionIndex + 1));
        $section['related_topic_ids'] ??= [];
        $section['related_concept_ids'] ??= [];
    }
    unset($section);
    $sectionIndices = [];
    foreach ($lessons[$lessonIndex]['sections'] as $sectionIndex => $section) $sectionIndices[(string) ($section['id'] ?? '')] = $sectionIndex;
    $practiceIds = array_map('strval', (array) ($lessons[$lessonIndex]['practice_question_ids'] ?? []));
    foreach ($questions as $candidate) {
        if (!is_array($candidate) || (string) ($candidate['study_material_id'] ?? '') !== (string) ($lessons[$lessonIndex]['material_id'] ?? '')) continue;
        $placement = \App\Services\Question\QuestionEligibilityService::forExam($candidate, $board, $eligibility);
        if (!is_array($placement) || (string) ($placement['subject_id'] ?? '') !== $subject) continue;
        $practiceIds[] = (string) ($candidate['id'] ?? '');
    }
    $practiceIds = array_values(array_unique(array_filter($practiceIds, static fn(string $id): bool => $id !== '')));
    $lessons[$lessonIndex]['practice_question_ids'] = $practiceIds;
    foreach ($practiceIds as $qid) {
        $qid = (string) $qid;
        if (!isset($qIndex[$qid])) continue;
        $questionIndex = $qIndex[$qid];
        $question = $questions[$questionIndex];
        $sectionId = $sectionFor($question, $board, $subject);
        if (!isset($sectionIndices[$sectionId])) {
            throw new RuntimeException('No instructional section ' . $sectionId . ' for ' . $lessonId . ' question ' . $qid);
        }
        $sectionIndex = $sectionIndices[$sectionId];
        $questions[$questionIndex]['study_section_id'] = $sectionId;
        $tax = (array) ($question['taxonomy'] ?? []);
        $topicId = (string) ($tax['topic_id'] ?? '');
        $conceptId = (string) ($tax['concept_id'] ?? '');
        if ($topicId !== '') $lessons[$lessonIndex]['sections'][$sectionIndex]['related_topic_ids'][] = $topicId;
        if ($conceptId !== '') $lessons[$lessonIndex]['sections'][$sectionIndex]['related_concept_ids'][] = $conceptId;
        $sectionByQuestion[$qid] = ['material'=>(string) ($lessons[$lessonIndex]['material_id'] ?? ''),'section'=>$sectionId];
    }
    foreach ($lessons[$lessonIndex]['sections'] as &$section) {
        $section['related_topic_ids'] = array_values(array_unique($section['related_topic_ids']));
        $section['related_concept_ids'] = array_values(array_unique($section['related_concept_ids']));
    }
    unset($section);
}

// Values lesson links use the taxonomy's real three topics and source-unit
// section IDs, making the study section addressable from the saved question.
if (isset($lessonAt['let-values-ethical-reasoning-basics'])) {
    $i = $lessonAt['let-values-ethical-reasoning-basics'];
    $valuesSectionMap = [
        'Unit 1. Foundations of Values Education'=>'values-reasoning-and-character',
        'Unit 2. Personhood Development and Reflective Agency'=>'values-dialogue-and-diversity',
        'Unit 3. Transformative Education and Ethical Decision'=>'values-transformative-education',
        'Unit 4. Work Ethics, Community, and Public Responsibility'=>'values-civic-responsibility',
        'Worked dilemma: correcting a score'=>'values-applied-dilemma',
        'Review: test reasons, not slogans'=>'values-reflective-practice',
    ];
    $valuesSections = [];
    foreach ($lessons[$i]['sections'] as &$section) {
        $heading = (string) ($section['heading'] ?? '');
        $section['id'] = $valuesSectionMap[$heading] ?? ('values-section-' . count($valuesSections));
        $section['related_topic_ids'] = [];
        $section['related_concept_ids'] = [];
        $valuesSections[$section['id']] = true;
    }
    unset($section);
    foreach ($questions as $qi => $question) {
        if (($question['board'] ?? '') !== 'let' || ($question['taxonomy']['subject_id'] ?? '') !== 'values-education') continue;
        $topic = (string) ($question['taxonomy']['topic_id'] ?? '');
        $sectionId = match ($topic) {
            'values-reasoning-and-character' => 'values-reasoning-and-character',
            'values-dialogue-and-diversity' => 'values-dialogue-and-diversity',
            'values-civic-responsibility' => 'values-civic-responsibility',
            default => 'values-transformative-education',
        };
        $questions[$qi]['study_section_id'] = $sectionId;
        $sectionByQuestion[(string) $question['id']] = ['material'=>'let-specialization-values-education','section'=>$sectionId];
        foreach ($lessons[$i]['sections'] as &$section) {
            if (($section['id'] ?? '') !== $sectionId) continue;
            if ($topic !== '') $section['related_topic_ids'][] = $topic;
            $concept = (string) ($question['taxonomy']['concept_id'] ?? '');
            if ($concept !== '') $section['related_concept_ids'][] = $concept;
            break;
        }
        unset($section);
    }
    foreach ($lessons[$i]['sections'] as &$section) {
        $section['related_topic_ids'] = array_values(array_unique($section['related_topic_ids']));
        $section['related_concept_ids'] = array_values(array_unique($section['related_concept_ids']));
    }
    unset($section);
}

foreach ($links as &$link) {
    $qid = (string) ($link['question_id'] ?? '');
    if (!isset($sectionByQuestion[$qid]) || (string) ($link['material_id'] ?? '') !== $sectionByQuestion[$qid]['material']) continue;
    $link['section_id'] = $sectionByQuestion[$qid]['section'];
}
unset($link);
$storage->replace('questions', $questions);
$storage->replace('study-lessons', $lessons);
$storage->replace('study-question-links', $links);
printf("QUESTIONS_WITH_INSTRUCTIONAL_SECTIONS=%d\nLESSONS_WITH_TOPIC_CONCEPT_MAPS=%d\n", count($sectionByQuestion), count($scopeLessonIds) + 1);
