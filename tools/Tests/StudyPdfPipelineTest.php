<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

use App\Services\Study\StudyPdfGenerator;

$root = dirname(__DIR__, 2);
$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};
$check = StudyPdfGenerator::build($root, true);
$assert(($check['status'] ?? '') === 'PASS' && count($check['files'] ?? []) === 3, 'The source-first generated PDF set did not pass its deterministic check.');
$manifest = json_decode((string) file_get_contents($root . '/public/study-resources/manifest.json'), true, 512, JSON_THROW_ON_ERROR);
$assert(($manifest['content_policy'] ?? '') === 'Original BoardPrep instructional text with source attribution; external source documents are not reproduced.', 'The PDF manifest does not state its original-content policy.');
foreach ($check['files'] as $entry) {
    $path = $root . '/' . $entry['path'];
    $pdf = (string) file_get_contents($path);
    $assert(str_starts_with($pdf, '%PDF-1.4') && hash('sha256', $pdf) === $entry['sha256'], 'PDF header or recorded content hash failed for ' . $entry['lesson_id']);
    preg_match('/startxref\n(\d+)\n%%EOF/', $pdf, $match);
    $assert(isset($match[1]) && substr($pdf, (int) $match[1], 5) === "xref\n", 'PDF cross-reference table offset is invalid for ' . $entry['lesson_id']);
    $assert(preg_match_all('/\/Type \/Page\b/', $pdf) === (int) $entry['pages'], 'PDF page count does not match its manifest.');
    $assert(str_contains($pdf, 'BoardPrep') && str_contains($pdf, 'source') && str_contains($pdf, 'https://'), 'Generated PDF is missing searchable lesson text or source URLs.');
}
$assert(str_contains((string) file_get_contents($root . '/public/study-resources/cse-percent-and-ratio-reasoning.pdf'), '18% of 250'), 'The CSE PDF does not preserve the worked example as searchable text.');
$assert(str_contains((string) file_get_contents($root . '/public/study-resources/ree-dc-circuit-analysis.pdf'), 'OpenStax'), 'The REE PDF does not preserve instructional source attribution.');

echo "Study PDF generation and integrity tests passed\n";
