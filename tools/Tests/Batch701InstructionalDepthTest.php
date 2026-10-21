<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

putenv('APP_ENV=testing');
putenv('APP_DEBUG=false');
\App\Core\App::boot();

$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};
$expected = [
    'architecture-4-1-foundation' => [
        'lesson' => 'architecture-site-analysis-and-design-response',
        'questions' => ['1779' => 'architecture-environmental-comparisons', '3723' => 'architecture-site-evidence-map', '3724' => 'architecture-circulation-and-tradeoffs', '3725' => 'architecture-circulation-and-tradeoffs'],
    ],
    'cse-numerical-foundation' => [
        'lesson' => 'cse-percent-and-ratio-reasoning',
        'questions' => ['616' => 'cse-percent-of-base', '617' => 'cse-percent-of-base', '618' => 'cse-ratios-unit-rates', '627' => 'cse-percent-change', '628' => 'cse-ratios-unit-rates'],
    ],
    'let-assessment-review' => [
        'lesson' => 'let-assessment-evidence-and-feedback',
        'questions' => ['62' => 'let-validity-and-reliability', '63' => 'let-assessment-purpose-and-use', '65' => 'let-triangulating-broad-competencies', '152' => 'let-validity-and-reliability'],
    ],
    'cle-investigation-foundation' => [
        'lesson' => 'cle-scene-documentation-and-evidence-custody',
        'questions' => ['757' => 'cle-secure-observe-document', '762' => 'cle-review-gaps-without-overclaiming', '770' => 'cle-preserve-context-before-collection', '780' => 'cle-review-gaps-without-overclaiming'],
    ],
    'nle-np-iii-foundation' => [
        'lesson' => 'nursing-standard-and-transmission-precautions',
        'questions' => ['4004' => 'nle-transmission-based-precautions', '4005' => 'nle-safe-injection-practices', '4006' => 'nle-transmission-based-precautions'],
    ],
    'batch622-cpa-auditing-evidence' => [
        'lesson' => 'cpa-audit-evidence-and-assertion-testing',
        'questions' => ['1338' => 'cpa-investigate-contradictory-evidence', '4238' => 'cpa-assertion-determines-direction', '4248' => 'cpa-investigate-contradictory-evidence'],
    ],
];
$storage = \App\Core\App::storage();
$lessonsById = [];
foreach ($storage->all('study-lessons') as $lesson) {
    if (is_array($lesson)) $lessonsById[(string) ($lesson['id'] ?? '')] = $lesson;
}
$conceptIds = [];
foreach ($storage->all('taxonomy/concepts') as $concept) {
    if (is_array($concept)) $conceptIds[(string) ($concept['id'] ?? '')] = true;
}
$linkByPair = [];
foreach ($storage->all('study-question-links') as $link) {
    if (is_array($link)) $linkByPair[(string) ($link['material_id'] ?? '') . "\0" . (string) ($link['question_id'] ?? '')] = $link;
}

$letLesson = $lessonsById['let-assessment-evidence-and-feedback'] ?? [];
$letInstructionalSources = array_map('strval', (array) ($letLesson['instructional_source_ids'] ?? []));
$assert(!in_array('deped-classroom-assessment-do-8-2015', $letInstructionalSources, true), 'The updated LET lesson still presents the superseded 2015 DepEd order as a current instructional source.');
$assert(in_array('cmu-assessment-formative-summative', $letInstructionalSources, true)
    && in_array('berkeley-design-effective-assessments', $letInstructionalSources, true), 'The updated LET lesson is missing its checked university assessment references.');
$sourceById = [];
foreach ($storage->all('sources') as $source) {
    if (is_array($source)) $sourceById[(string) ($source['id'] ?? '')] = $source;
}
foreach (['cmu-assessment-formative-summative', 'berkeley-design-effective-assessments'] as $sourceId) {
    $assert(($sourceById[$sourceId]['status'] ?? '') === 'verified_reference', 'A new LET instructional source is not explicitly verified: ' . $sourceId);
}
$letMaterial = null;
foreach ($storage->all('study-materials') as $material) {
    if (is_array($material) && ($material['id'] ?? '') === 'let-assessment-review') $letMaterial = $material;
}
$assert(is_array($letMaterial)
    && !in_array('deped-classroom-assessment-do-8-2015', array_map('strval', (array) ($letMaterial['source_ids'] ?? [])), true), 'The learner-facing LET study material still presents the superseded order as a current source.');

$mappedCount = 0;
foreach ($expected as $materialId => $record) {
    $lesson = $lessonsById[$record['lesson']] ?? null;
    $assert(is_array($lesson) && (string) ($lesson['material_id'] ?? '') === $materialId, 'A selected lesson is missing or attached to the wrong material: ' . $record['lesson']);
    $lessonConcepts = [];
    foreach ((array) ($lesson['sections'] ?? []) as $section) {
        if (is_array($section)) {
            foreach ((array) ($section['related_concept_ids'] ?? []) as $conceptId) $lessonConcepts[(string) $conceptId] = true;
        }
    }
    $sectionById = [];
    foreach ((array) ($lesson['sections'] ?? []) as $section) {
        $sectionId = (string) ($section['id'] ?? '');
        $assert($sectionId !== '' && !isset($sectionById[$sectionId]), 'A selected lesson has a missing or duplicate stable section id.');
        $sectionById[$sectionId] = $section;
        foreach ((array) ($section['related_concept_ids'] ?? []) as $conceptId) {
            $assert(isset($conceptIds[(string) $conceptId]) && isset($lessonConcepts[(string) $conceptId]), 'A section uses an unknown or unlisted lesson concept id.');
        }
    }
    foreach ($lessonConcepts as $conceptId => $_) $assert(isset($conceptIds[$conceptId]), 'A selected lesson uses an unknown concept id: ' . $conceptId);

    $material = \App\Services\Study\StudyLibraryService::find($materialId);
    $assert(is_array($material), 'The production study material could not be resolved: ' . $materialId);
    foreach ($record['questions'] as $questionId => $sectionId) {
        $link = $linkByPair[$materialId . "\0" . $questionId] ?? null;
        $legacySourceReview = $materialId === 'let-assessment-review'
            && in_array((string) $questionId, ['62', '63', '65', '152'], true);
        $expectedReviewStatus = $legacySourceReview
            ? 'SECTION_SUPPORTED_SOURCE_ASSOCIATION_REVIEW_REQUIRED_PENDING_INDEPENDENT_ACADEMIC_REVIEW'
            : 'SECTION_AND_SOURCE_CHECKED_PENDING_INDEPENDENT_ACADEMIC_REVIEW';
        $assert(is_array($link)
            && (string) ($link['lesson_id'] ?? '') === $record['lesson']
            && (string) ($link['section_id'] ?? '') === $sectionId
            && ($link['semantic_review_status'] ?? '') === $expectedReviewStatus,
            'A reviewed section mapping is missing, stale, or incorrectly approved: ' . $materialId . '/' . $questionId);
        if ($legacySourceReview) {
            $assert(str_contains((string) ($link['review_guidance'] ?? ''), 'DepEd Order 8 s. 2015 source association'), 'A LET item with inherited legacy provenance lost its source-review warning: ' . $questionId);
        }
        $assert(isset($sectionById[$sectionId]), 'A question link points to a missing lesson section: ' . $sectionId);
        $practice = \App\Services\Study\StudyLibraryService::practiceQuestionIds($material, $sectionId);
        $assert(in_array($questionId, $practice, true), 'The lesson section does not return its linked practice question: ' . $questionId);
        $reference = null;
        foreach (\App\Services\Study\StudyLibraryService::forQuestion((string) $questionId) as $candidate) {
            if (($candidate['material_id'] ?? '') === $materialId && ($candidate['section_id'] ?? '') === $sectionId) $reference = $candidate;
        }
        $expectedAnchor = \App\Services\Study\StudyLibraryService::lessonSectionAnchorId($record['lesson'], $sectionId);
        $assert(is_array($reference) && str_ends_with((string) ($reference['url'] ?? ''), '#' . $expectedAnchor), 'Answer review does not route to the mapped lesson section: ' . $questionId);
        ++$mappedCount;
    }
}
$assert($mappedCount === 23, 'The focused cohort does not contain exactly 23 section-specific practice links.');

foreach ([
    ['nle-np-iii-foundation', 'nursing-standard-and-transmission-precautions', '3853'],
    ['batch622-cpa-auditing-evidence', 'cpa-audit-evidence-and-assertion-testing', '4239'],
] as [$materialId, $lessonId, $questionId]) {
    $lesson = $lessonsById[$lessonId] ?? [];
    $assert(!in_array($questionId, array_map('strval', (array) ($lesson['practice_question_ids'] ?? [])), true), 'An unsupported question remains in a lesson practice pool: ' . $questionId);
    $reference = null;
    foreach (\App\Services\Study\StudyLibraryService::forQuestion($questionId) as $candidate) {
        if (($candidate['material_id'] ?? '') === $materialId) $reference = $candidate;
    }
    $assert(is_array($reference) && ($reference['section_id'] ?? '') === '' && ($reference['lesson_id'] ?? '') === '', 'A source-only question is still presented as a lesson mapping: ' . $questionId);
}

echo "Batch 701: 6 bounded lessons and 23 section-specific answer-review routes passed; 2 unrelated source-only questions remain unmapped.\n";
