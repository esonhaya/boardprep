<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

$root = dirname(__DIR__, 2);
$read = static fn(string $file): array => json_decode((string) file_get_contents($root . '/' . $file), true, 512, JSON_THROW_ON_ERROR);
$assert = static function (bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
};
$questions = $read('storage/questions.json');
$sources = $read('storage/sources.json');
$materials = $read('storage/study-materials.json');
$links = $read('storage/study-question-links.json');
$reviews = $read('storage/academic-source-reviews.json');
$summary = \App\Services\Shared\AcademicSourceReviewService::summarize($questions, $sources, $reviews, $links, $materials);

$assert(($summary['reviewed_questions'] ?? 0) === 100, 'The balanced source review sample changed size.');
$assert(($summary['classifications'] ?? []) === ['DIRECT_SUPPORT' => 39, 'PARTIAL' => 33, 'CONTRADICTED' => 0, 'UNKNOWN' => 28], 'Source classifications no longer match the Batch 687 evidence ledger.');
$assert(($summary['human_confirmed_rows'] ?? -1) === 0 && ($summary['whole_corpus_verified_count'] ?? -1) === 0, 'AI-assisted evidence was promoted to human or corpus-wide verification.');
$assert(($summary['invalid_review_rows'] ?? []) === [], 'A review row has stale hashes, invalid provenance, or broken question/material/source linkage.');

$recheckedIds = ['1338','1336','4238','4239','1322','1326','1334','1332','1330','1320','1779','3723','3724','1786','1787','1788','1789','1790','1791','1792','4617','4618','4619','4620','4621','4622','4623','4624','4625','955','3853','4005','4007','4011','5013','5014','5017','3958'];
$byId = [];
foreach ($reviews as $row) $byId[(string) ($row['question_id'] ?? '')] = $row;
foreach ($recheckedIds as $id) {
    $row = $byId[$id] ?? null;
    $assert(is_array($row) && ($row['review_state'] ?? '') === 'AI_REVIEWED_PENDING_HUMAN_CONFIRMATION', 'Rechecked question ' . $id . ' lacks pending human provenance.');
    $assert(($row['reviewer_type'] ?? '') === 'AI_ASSISTED_EDITORIAL_REVIEW', 'Rechecked question ' . $id . ' claims a non-AI reviewer.');
}
foreach (['4617','4622','4623','4625','4011','5013'] as $id) {
    $assert(($byId[$id]['classification'] ?? '') === 'DIRECT_SUPPORT', 'Direct support lacks an exact cited passage for question ' . $id . '.');
}
foreach (['955','5017','3958','1338'] as $id) {
    $assert(($byId[$id]['classification'] ?? '') === 'PARTIAL', 'Partial evidence was overstated for question ' . $id . '.');
}
$aasc = array_values(array_filter($sources, static fn(array $source): bool => ($source['id'] ?? '') === 'aasc-psa-500-redrafted'))[0] ?? [];
$assert(str_ends_with((string) ($aasc['url'] ?? ''), '/psa-500-redrafted') && str_contains((string) ($aasc['version'] ?? ''), 'HTTP 404'), 'The broken AASC PDF pointer was not replaced by an explicitly limited publisher overview.');

echo "[PASS] Batch 687 source review coverage, semantic classifications, snapshots, and pending human states are consistent.\n";
