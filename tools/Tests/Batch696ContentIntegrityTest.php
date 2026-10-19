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
$storage = \App\Core\App::storage();
$questions = [];
foreach ($storage->all('questions') as $question) if (is_array($question)) $questions[(string) ($question['id'] ?? '')] = $question;
$sources = [];
foreach ($storage->all('sources') as $source) if (is_array($source)) $sources[(string) ($source['id'] ?? '')] = $source;
$materials = [];
foreach ($storage->all('study-materials') as $material) if (is_array($material)) $materials[(string) ($material['id'] ?? '')] = $material;
$lessonsByMaterial = [];
$lessonCount = 0;
foreach ($storage->all('study-lessons') as $lesson) {
    if (!is_array($lesson) || ($lesson['status'] ?? '') !== 'published') continue;
    $materialId = (string) ($lesson['material_id'] ?? '');
    if ($materialId !== '') $lessonsByMaterial[$materialId] = $lesson;
    if (in_array($lesson['id'] ?? '', [
        'ce-applied-methods-layout-and-delivery','ce-hydraulics-soil-and-foundation-reasoning','ce-structural-equilibrium-and-member-response',
        'cse-analytical-reasoning-evidence-and-patterns','cse-clerical-records-filing-and-proofreading','cse-civic-rights-ethics-and-environment','cse-verbal-reading-grammar-and-cohesion',
        'cpa-advanced-accounting-ownership-and-consolidation','cpa-financial-accounting-recognition-measurement-and-reporting','cpa-management-services-cvp-and-decisions','cpa-regulatory-framework-law-ethics-and-evidence','cpa-taxation-computation-and-current-law-workflow',
        'let-english-reading-language-and-composition','let-filipino-pagbasa-wika-at-pagsulat','let-mathematics-models-and-quantitative-reasoning','let-science-inquiry-systems-and-evidence','let-social-studies-source-civic-and-historical-reasoning','let-tle-safety-competency-and-practice',
        'me-machine-design-stress-materials-and-shop','me-mathematics-engineering-models-and-units','me-power-plant-energy-cycles-and-heat-transfer',
        'psych-assessment-evidence-validity-and-fair-use',
        'ree-professional-circuits-power-and-measurement','ree-allied-mechanics-thermal-electronics-and-practice','ree-mathematics-linear-systems-complex-models-and-data',
    ], true)) {
        $lessonCount++;
        $assert(count((array) ($lesson['learning_objectives'] ?? [])) >= 2 && count((array) ($lesson['sections'] ?? [])) >= 3, 'A Batch 696 lesson is missing substantive instructional structure: ' . (string) ($lesson['id'] ?? ''));
        $assert(!empty($lesson['worked_examples']) && !empty($lesson['common_misconceptions']) && !empty($lesson['scope_note']), 'A Batch 696 lesson is missing an example, misconception, or truthful scope note.');
        $assert(($lesson['source_review']['status'] ?? '') !== 'ACADEMICALLY_APPROVED', 'Batch 696 must not self-approve academic content.');
        foreach (array_merge((array) ($lesson['official_scope_source_ids'] ?? []), (array) ($lesson['instructional_source_ids'] ?? [])) as $sourceId) $assert(isset($sources[(string) $sourceId]), 'A lesson source ID does not resolve: ' . (string) $sourceId);
    }
}
$assert($lessonCount === 25, 'Expected exactly 25 new Batch 696 lessons, found ' . $lessonCount . '.');

$manifest = json_decode((string) file_get_contents(dirname(__DIR__, 2) . '/public/study-resources/manifest.json'), true, 512, JSON_THROW_ON_ERROR);
$manifestByLesson = [];
foreach ((array) ($manifest['files'] ?? []) as $entry) if (is_array($entry)) $manifestByLesson[(string) ($entry['lesson_id'] ?? '')] = $entry;
$newLinks = [];
foreach ($storage->all('study-question-links') as $link) {
    if (!is_array($link) || !str_starts_with((string) ($link['id'] ?? ''), 'batch696-')) continue;
    $newLinks[] = $link;
}
$assert(count($newLinks) === 24, 'Expected 24 section-mapped Batch 696 question links, found ' . count($newLinks) . '.');
$index = new \App\Services\Question\QuestionEligibilityIndex(
    $storage->all('question-eligibility'),
    $storage->all('blueprints'),
    \App\Services\Question\QuestionReuseEligibilityResolver::productionDerivedRelationships(),
    $storage->all('question-reuse-overrides')
);
foreach ($newLinks as $link) {
    $questionId = (string) ($link['question_id'] ?? '');
    $question = $questions[$questionId] ?? null;
    $materialId = (string) ($link['material_id'] ?? '');
    $material = $materials[$materialId] ?? null;
    $lesson = $lessonsByMaterial[$materialId] ?? null;
    $assert(is_array($question) && is_array($material) && is_array($lesson), 'A Batch 696 link has a missing question, material, or lesson.');
    $resolved = \App\Services\Question\QuestionEligibilityService::forExam($question, (string) ($lesson['board_id'] ?? ''), $index);
    $assert(is_array($resolved) && strtolower((string) ($resolved['subject_id'] ?? '')) === strtolower((string) ($lesson['subject_id'] ?? '')), 'A Batch 696 link does not match the production effective subject eligibility.');
    $sectionId = (string) ($link['section_id'] ?? '');
    $section = null;
    foreach ((array) ($lesson['sections'] ?? []) as $candidate) if (is_array($candidate) && (string) ($candidate['id'] ?? '') === $sectionId) { $section = $candidate; break; }
    $assert(is_array($section), 'A Batch 696 question link does not resolve to its exact lesson section.');
    $conceptId = (string) ($question['taxonomy']['concept_id'] ?? '');
    $assert($conceptId !== '' && in_array($conceptId, array_map('strval', (array) ($section['related_concept_ids'] ?? [])), true), 'A Batch 696 section crosswalk omits the linked question concept.');
    $assert(in_array($questionId, array_map('strval', (array) ($lesson['practice_question_ids'] ?? [])), true), 'The lesson does not expose its linked practice question.');
    $assert(($link['semantic_review_status'] ?? '') === 'SECTION_AND_SOURCE_CHECKED_PENDING_INDEPENDENT_ACADEMIC_REVIEW', 'A Batch 696 link has lost its pending independent-review status.');
    foreach ((array) ($link['source_ids'] ?? []) as $sourceId) $assert(isset($sources[(string) $sourceId]) && in_array($sourceId, (array) ($material['source_ids'] ?? []), true), 'A linked source does not resolve from both link and material.');
    $pdf = $manifestByLesson[(string) ($lesson['id'] ?? '')] ?? null;
    $pdfPath = dirname(__DIR__, 2) . '/' . (string) ($pdf['path'] ?? '');
    $assert(is_array($pdf) && is_file($pdfPath) && hash_equals((string) ($pdf['sha256'] ?? ''), (string) hash_file('sha256', $pdfPath)), 'A Batch 696 lesson PDF or manifest hash is invalid.');
}

foreach (range(9124, 9399) as $pendingId) $assert(!isset($questions[(string) $pendingId]), 'A pending Batch 695 question was copied into the B696 branch: ' . $pendingId);
echo "[PASS] {$lessonCount} partial lessons, 24 eligible section links, sources, PDFs, and Batch 695 review isolation verified.\n";
