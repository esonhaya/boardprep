#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$beforePath = $root . '/docs/reports/batch-696-coverage-before.json';
$afterPath = $root . '/docs/reports/batch-696-coverage-after.json';
$outputPath = $root . '/docs/reports/batch-696-instructional-readiness.md';
$before = json_decode((string) file_get_contents($beforePath), true, 512, JSON_THROW_ON_ERROR);
$after = json_decode((string) file_get_contents($afterPath), true, 512, JSON_THROW_ON_ERROR);
$concepts = json_decode((string) file_get_contents($root . '/storage/taxonomy/concepts.json'), true, 512, JSON_THROW_ON_ERROR);
$lessons = json_decode((string) file_get_contents($root . '/storage/study-lessons.json'), true, 512, JSON_THROW_ON_ERROR);

$conceptById = [];
foreach ($concepts as $concept) {
    if (is_array($concept) && is_scalar($concept['id'] ?? null)) {
        $conceptById[(string) $concept['id']] = $concept;
    }
}
$lessonsByScope = [];
foreach ($lessons as $lesson) {
    if (!is_array($lesson) || ($lesson['status'] ?? '') !== 'published') continue;
    $key = strtolower((string) ($lesson['board_id'] ?? '')) . '|' . strtolower((string) ($lesson['subject_id'] ?? ''));
    $lessonsByScope[$key][] = $lesson;
}
$beforeByScope = [];
foreach ((array) ($before['subjects'] ?? []) as $row) {
    if (is_array($row)) $beforeByScope[strtolower((string) $row['board_id']) . '|' . strtolower((string) $row['subject_id'])] = $row;
}

$subjects = (array) ($after['subjects'] ?? []);
$totals = (array) ($after['totals'] ?? []);
$beforeTotals = (array) ($before['totals'] ?? []);
$baselineLessonJson = shell_exec('git show f657b89629a0262d3ebca0ce99baaf551268acf8:storage/study-lessons.json');
$baselineLessons = is_string($baselineLessonJson) ? json_decode($baselineLessonJson, true, 512, JSON_THROW_ON_ERROR) : [];
$publishedBefore = array_values(array_filter($baselineLessons, static fn(mixed $lesson): bool => is_array($lesson) && ($lesson['status'] ?? '') === 'published'));
$publishedAfter = array_values(array_filter($lessons, static fn(mixed $lesson): bool => is_array($lesson) && ($lesson['status'] ?? '') === 'published'));
$lessonCountBefore = count($publishedBefore);
$lessonCountAfter = count($publishedAfter);
$sectionCountBefore = array_sum(array_map(static fn(array $lesson): int => count((array) ($lesson['sections'] ?? [])), $publishedBefore));
$sectionCountAfter = array_sum(array_map(static fn(array $lesson): int => count((array) ($lesson['sections'] ?? [])), $publishedAfter));
$createdSectionCount = max(0, $sectionCountAfter - $sectionCountBefore);
$escape = static fn(mixed $value): string => str_replace(['|', "\n", "\r"], ['\\|', ' ', ' '], (string) $value);
$lines = [
    '# Batch 696 Instructional Readiness',
    '',
    'Generated from the before/after production eligibility inventories and current lesson records. This is a structural readiness view, not an independent academic accreditation or proof of learning effectiveness.',
    '',
    '## Portfolio change',
    '',
    '| Measure | Before | After | Change |',
    '|---|---:|---:|---:|',
];
$measures = [
    ['Active board selectors', (int) ($beforeTotals['active_subject_selectors'] ?? 0), (int) ($totals['active_subject_selectors'] ?? 0)],
    ['Canonical questions', (int) ($beforeTotals['canonical_questions'] ?? 0), (int) ($totals['canonical_questions'] ?? 0)],
    ['Zero-lesson selectors', (int) ($beforeTotals['zero_lesson_subjects'] ?? 0), (int) ($totals['zero_lesson_subjects'] ?? 0)],
    ['Published lessons', $lessonCountBefore, $lessonCountAfter],
    ['Lesson sections', $sectionCountBefore, $sectionCountAfter],
    ['Published study PDFs', (int) ($beforeTotals['published_study_pdfs'] ?? 0), (int) ($totals['published_study_pdfs'] ?? 0)],
    ['Source-only question placements', (int) ($beforeTotals['source_only_subject_question_placements'] ?? 0), (int) ($totals['source_only_subject_question_placements'] ?? 0)],
    ['Concepts structurally associated with substantive text', (int) ($beforeTotals['concepts_with_real_instruction'] ?? 0), (int) ($totals['concepts_with_real_instruction'] ?? 0)],
    ['Concepts without structural instructional association', (int) ($beforeTotals['concepts_without_instruction'] ?? 0), (int) ($totals['concepts_without_instruction'] ?? 0)],
    ['Broken lesson links', (int) ($beforeTotals['broken_lesson_links'] ?? 0), (int) ($totals['broken_lesson_links'] ?? 0)],
    ['Semantic link review candidates', (int) ($beforeTotals['semantic_link_review_candidates'] ?? 0), (int) ($totals['semantic_link_review_candidates'] ?? 0)],
];
foreach ($measures as [$label, $old, $new]) $lines[] = '| ' . $label . ' | ' . $old . ' | ' . $new . ' | ' . ($new - $old >= 0 ? '+' : '') . ($new - $old) . ' |';
$lines = array_merge($lines, [
    '',
    'The 25 new selector lessons are expressly partial introductory modules. One lesson per selector and one linked question per selected subject do not constitute complete subject coverage. The 24 linked questions are section-mapped and remain pending independent academic review.',
    '',
    'Conservative portfolio classification: **0 selectors independently verified as fully instructionally covered**, **37 with some structural concept instruction but incomplete or unverified scope**, and **6 with no concept mapped to a substantive section by the text heuristic**. This classification requires more than the existence of a lesson, PDF, or question bank; no subject is labeled independently approved by this batch.',
    '',
    '## Readiness matrix: all 43 active board-subject selectors',
    '',
    '“Concept coverage” means an explicit taxonomy association on a section meeting the report’s text-length heuristic. It does not establish that every concept is adequately taught. “Verified source” uses source registry statuses and does not prove question-level semantic fit.',
    '',
    '| Board | Subject selector | Question depth (eligible Q) | Lesson availability | Lesson concept coverage (structural) | Question-to-lesson linkage | Registry-verified source coverage | PDFs | Practice explanations | Academic review |',
    '|---|---|---:|---|---|---|---|---:|---:|---|',
]);
foreach ($subjects as $row) {
    $lessonCount = (int) ($row['existing_lesson_count'] ?? 0);
    $conceptCount = (int) ($row['concept_count'] ?? 0);
    $instructionCount = (int) ($row['concepts_with_real_instruction'] ?? 0);
    $qCount = (int) ($row['effective_question_count'] ?? 0);
    $linkedCount = (int) ($row['lesson_linked_questions'] ?? 0);
    $sourceCoverage = (int) ($row['verified_source_coverage'] ?? 0);
    $explanations = (int) ($row['practice_explanations_available'] ?? 0);
    $lessonAvailability = $lessonCount === 0 ? 'NO_LESSON' : 'AVAILABLE; DEPTH UNVERIFIED';
    $linkLabel = $linkedCount . '/' . $qCount . ' placements';
    $lines[] = '| ' . $escape($row['board_name'] ?? $row['board_id']) . ' (`' . $escape($row['board_id']) . '`) | ' . $escape($row['subject_name'] ?? $row['subject_id']) . ' (`' . $escape($row['subject_id']) . '`) | ' . $qCount . ' | ' . $lessonAvailability . ' | ' . $instructionCount . '/' . $conceptCount . ' | ' . $linkLabel . ' (' . (int) ($row['source_only_questions'] ?? 0) . ' source-only) | ' . $sourceCoverage . '/' . $qCount . ' | ' . (int) ($row['downloadable_pdfs'] ?? 0) . '/' . $lessonCount . ' | ' . $explanations . '/' . $qCount . ' | INDEPENDENT REVIEW PENDING |';
}
$lines = array_merge($lines, [
    '',
    'All 43 selectors have a published lesson and PDF in the after snapshot. This means the prior zero-lesson condition is structurally closed; it does not mean that each selector has full instructional coverage. Existing lesson depth was not reauthored in this workstream.',
    '',
    '## Remaining work by subject selector',
    '',
]);
foreach ($subjects as $row) {
    $key = strtolower((string) $row['board_id']) . '|' . strtolower((string) $row['subject_id']);
    $lessonRows = $lessonsByScope[$key] ?? [];
    $conceptIds = array_map('strval', (array) ($row['concept_ids'] ?? []));
    $taught = array_fill_keys(array_map('strval', (array) ($row['concepts_with_instruction_ids'] ?? [])), true);
    $missingConcepts = array_values(array_filter($conceptIds, static fn(string $id): bool => !isset($taught[$id])));
    $missingNames = [];
    foreach ($missingConcepts as $id) $missingNames[] = (string) ($conceptById[$id]['name'] ?? $id) . ' (`' . $id . '`)';
    $headings = [];
    foreach ($lessonRows as $lesson) foreach ((array) ($lesson['sections'] ?? []) as $section) if (is_array($section) && trim((string) ($section['heading'] ?? '')) !== '') $headings[] = trim((string) $section['heading']);
    $headings = array_values(array_unique($headings));
    $qCount = (int) ($row['effective_question_count'] ?? 0);
    $sourceOnly = (int) ($row['source_only_questions'] ?? 0);
    $notVerified = max(0, $qCount - (int) ($row['verified_source_coverage'] ?? 0));
    $lines[] = '### ' . $escape($row['board_name'] ?? $row['board_id']) . ' — ' . $escape($row['subject_name'] ?? $row['subject_id']) . ' (`' . $escape($row['subject_id']) . '`)';
    $lines[] = '';
    $lines[] = '- **Missing competencies:** ' . ($missingNames === [] ? 'No taxonomy concepts are absent from the structural section crosswalk; depth and semantic coverage still require review.' : implode('; ', $missingNames));
    $lines[] = '- **Lesson sections:** currently ' . ($headings === [] ? 'no resolved section headings' : implode('; ', array_map($escape, $headings))) . '. Add or expand sections for each listed missing competency; the headings are a current outline, not a claim of full blueprint coverage.';
    $lines[] = '- **Missing source evidence:** ' . $notVerified . '/' . $qCount . ' placements do not have a question-linked source in one of the inventory’s accepted registry-verified statuses. This status metric is conservative and does not assess semantic source fit; new lesson-source/question relevance has not received independent review.';
    $lines[] = '- **Question linkage gaps:** ' . $sourceOnly . '/' . $qCount . ' placements remain source-only under the lesson-section linkage rule.';
    $lines[] = '- **Recommended follow-up:** validate the subject blueprint against the lesson outline, add source-backed sections for the missing concepts, then map a representative question subset to exact sections after stem, answer, and explanation review. Check time-sensitive standards or laws before publication.';
    $lines[] = '';
}
$lines = array_merge($lines, [
    '## Interpretation limits',
    '',
    '- Every new lesson is marked as checked against its registered references, but this label is not human editorial approval. CPA taxation examples avoid fixed rates and require current-law verification.',
    '- Source registry statuses, taxonomy links, PDFs, and section anchors can be checked deterministically. They cannot establish academic completeness, question-answer correctness, or learning effectiveness.',
    '- Independent academic review remains pending for all new content and all questions linked in this batch. No question answer, identity, or usage counter was changed.',
]);

$content = implode("\n", $lines) . "\n";
$temporary = $outputPath . '.tmp-' . bin2hex(random_bytes(4));
if (file_put_contents($temporary, $content, LOCK_EX) !== strlen($content) || !rename($temporary, $outputPath)) {
    @unlink($temporary);
    throw new RuntimeException('Unable to atomically publish readiness report.');
}
printf("READINESS_REPORT=%s SELECTORS=%d LESSON_RECORDS=%d NEW_SECTIONS=%d\n", $outputPath, count($subjects), $lessonCountAfter, $createdSectionCount);
