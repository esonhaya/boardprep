#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$sources = json_decode((string) file_get_contents($root . '/storage/sources.json'), true, 512, JSON_THROW_ON_ERROR);
$lessons = json_decode((string) file_get_contents($root . '/storage/study-lessons.json'), true, 512, JSON_THROW_ON_ERROR);
$sourceById = [];
foreach ($sources as $source) if (is_array($source) && is_scalar($source['id'] ?? null)) $sourceById[(string) $source['id']] ??= $source;
$newLessons = array_values(array_filter(array_slice($lessons, -25), static fn(mixed $lesson): bool => is_array($lesson) && ($lesson['status'] ?? '') === 'published'));
$directlyRead = array_fill_keys([
    'prc-cele-program-march-2026',
    'mit-structural-engineering-design-notes',
    'mit-structural-analysis-member-systems',
    'prc-let-enhanced-tos-2025',
    'prc-lept-alignment-guidance-2025',
    'deped-science-curriculum-guide',
    'tesda-competency-standards',
    'tesda-training-regulations-index',
    'prc-mechanical-engineering-2026-program',
    'openstax-principles-managerial-accounting',
    'aera-apa-ncme-testing-standards-2014',
], true);
$references = [];
foreach ($newLessons as $lesson) {
    $lessonId = (string) ($lesson['id'] ?? '');
    $lessonTitle = (string) ($lesson['title'] ?? $lessonId);
    $sections = [];
    foreach ((array) ($lesson['sections'] ?? []) as $section) if (is_array($section) && trim((string) ($section['heading'] ?? '')) !== '') $sections[] = trim((string) $section['heading']);
    foreach ((array) ($lesson['official_scope_source_ids'] ?? []) as $sourceId) {
        if (is_scalar($sourceId)) $references[(string) $sourceId][$lessonId] = ['title'=>$lessonTitle,'role'=>'official scope','sections'=>$sections];
    }
    foreach ((array) ($lesson['instructional_source_ids'] ?? []) as $sourceId) {
        if (is_scalar($sourceId)) $references[(string) $sourceId][$lessonId] = ['title'=>$lessonTitle,'role'=>'instructional reference','sections'=>$sections];
    }
}
ksort($references);
$lines = [
    '# Batch 696 Source Evidence Register',
    '',
    'This register covers the 25 new partial lessons. Source metadata comes from the repository source registry. `verified_reference` and `official_reference` are registry labels; they do not prove the source supports every sentence or the linked question. `CONTENT READ` means the source page/document was opened and relevant content was inspected during Batch 696 research. All question-to-source and new lesson claims still await independent academic review.',
    '',
    '## Scope and instructional source mapping',
    '',
    '| Source title | Publisher / authority | Publication date or version | Source type | Relevant section / scope basis | Associated lesson competencies and sections (author mapping) | Verification status | Known limits | URL |',
    '|---|---|---|---|---|---|---|---|---|',
];
$escape = static fn(mixed $value): string => str_replace(['|', "\n", "\r"], ['\\|', ' ', ' '], (string) $value);
$missing = [];
foreach ($references as $sourceId => $lessonRefs) {
    $source = $sourceById[$sourceId] ?? null;
    if (!is_array($source)) {
        $missing[] = $sourceId;
        continue;
    }
    $roleParts = [];
    $competencyParts = [];
    foreach ($lessonRefs as $ref) {
        $roleParts[] = $ref['role'];
        $competencyParts[] = $ref['title'] . ': ' . implode('; ', $ref['sections']);
    }
    $status = strtoupper((string) ($source['status'] ?? 'UNVERIFIED')) . (isset($directlyRead[$sourceId]) ? ' · CONTENT READ' : ' · REGISTRY METADATA; CLAIM-LEVEL READ NOT RECORDED');
    $knownLimit = trim((string) ($source['notes'] ?? ''));
    if ($knownLimit === '') $knownLimit = 'No proposition-level excerpt or page/section locator is stored; consult the linked document before relying on a narrow claim.';
    if (isset($directlyRead[$sourceId])) $knownLimit .= ' Independent expert review of lesson/source fit remains pending.';
    else $knownLimit .= ' This batch did not record direct reading of the specific claim-bearing section; validate before expanding coverage.';
    $date = (string) ($source['publication_date'] ?? $source['version'] ?? 'not recorded');
    $relevant = implode(', ', array_values(array_unique($roleParts)));
    $lines[] = '| ' . $escape($source['title'] ?? $sourceId) . ' (`' . $escape($sourceId) . '`) | ' . $escape($source['authority'] ?? $source['publisher'] ?? 'Not recorded') . ' | ' . $escape($date) . ' | ' . $escape($source['source_type'] ?? 'Not recorded') . ' | ' . $escape($relevant . '; see registry notes/version and lesson scope note') . ' | ' . $escape(implode(' · ', $competencyParts)) . ' | ' . $escape($status) . ' | ' . $escape($knownLimit) . ' | [' . $escape($source['url'] ?? '') . '](' . $escape($source['url'] ?? '') . ') |';
}
$lines = array_merge($lines, [
    '',
    '## Source-reading notes and time-sensitive limitations',
    '',
    '- The PRC CELE March 2026 program was read for the three examination areas and stated weight distribution; it provides exam scope, not technical design authority.',
    '- The official CSC scope was read from the available exam material; the current 2026 scope document is present in the registry but its claim-bearing pages were not independently re-read in this pass. The current LEPT TOS/alignment materials, DepEd science guide, TESDA competency/training-regulation explanations, and current ME program were checked for their listed subject areas. The PRC REE board page lists TOS Resolution No. 40 s. 2024; the linked Annex A was not directly read. Exam documents identify tested scope; they do not validate every instructional example.',
    '- MIT OpenCourseWare structural materials and OpenStax managerial accounting break-even material were used as instructional references. They are educational references, not current Philippine engineering codes or accounting regulation.',
    '- The PRC Board of Accountancy page identifies the current Revised LECPA TOS under Resolution No. 20 s. 2026; the linked oversized Annex A PDF was not directly read during this pass. The CPA lessons therefore avoid asserting TOS-level completeness and avoid fixed tax rates or deadlines.',
    '- The APA testing-standards page identifies the 2014 AERA/APA/NCME Standards and notes revision activity; this is a professional reference, not Philippine law. The psychological assessment lesson does not offer individual clinical advice.',
    '- Historical textbooks, course notes, old engineering manuals, and curriculum guides support stable foundational explanations only. Current legal requirements, exam programs, safety rules, tax treatment, and engineering standards must be rechecked before a future publication claims current applicability.',
    '- URLs are supplied by the source registry. A URL’s presence alone is not evidence that a lesson or question is supported.',
]);
if ($missing !== []) throw new RuntimeException('Unregistered lesson sources: ' . implode(', ', $missing));
$output = $root . '/docs/reports/batch-696-source-evidence.md';
$content = implode("\n", $lines) . "\n";
$temporary = $output . '.tmp-' . bin2hex(random_bytes(4));
if (file_put_contents($temporary, $content, LOCK_EX) !== strlen($content) || !rename($temporary, $output)) {
    @unlink($temporary);
    throw new RuntimeException('Unable to atomically publish source evidence report.');
}
printf("SOURCE_EVIDENCE=%s SOURCES=%d NEW_LESSONS=%d\n", $output, count($references), count($newLessons));
