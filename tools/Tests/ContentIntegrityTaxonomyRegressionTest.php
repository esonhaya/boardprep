<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

use App\Services\Shared\ContentIntegrityService;

$base = [
    'questions' => [],
    'boards' => [['id' => 'let', 'name' => 'LET']],
    'subjects' => [
        ['id' => 'english', 'name' => 'English'],
        ['id' => 'verbal-ability', 'name' => 'Verbal Ability'],
    ],
    'board-subjects' => [
        ['id' => 'let-english', 'board_id' => 'let', 'subject_id' => 'english'],
    ],
    'taxonomy/domains' => [],
    'taxonomy/topics' => [],
    'taxonomy/concepts' => [],
    'question-eligibility' => [],
    'study-materials' => [],
    'study-question-links' => [],
    'sources' => [],
];

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$legitimate = $base;
$legitimate['taxonomy/domains'] = [
    ['id' => 'english-reading', 'subject_id' => 'english'],
    ['id' => 'verbal-reading', 'subject_id' => 'verbal-ability'],
];
$legitimateReport = ContentIntegrityService::analyze($legitimate);
$assert(($legitimateReport['warnings']['duplicate_taxonomy_ids'] ?? []) === [], 'distinct taxonomy IDs were reported as duplicates');

$duplicate = $base;
$duplicate['taxonomy/domains'] = [
    ['id' => 'reading-comprehension', 'subject_id' => 'english'],
    ['id' => 'reading-comprehension', 'subject_id' => 'verbal-ability'],
];
$duplicateReport = ContentIntegrityService::analyze($duplicate);
$assert(($duplicateReport['warnings']['duplicate_taxonomy_ids'] ?? []) === ['taxonomy/domains:reading-comprehension'], 'same-ID taxonomy collision was not reported precisely');

$brokenParent = $base;
$brokenParent['taxonomy/domains'] = [['id' => 'broken-domain', 'subject_id' => 'missing-subject']];
$brokenParentReport = ContentIntegrityService::analyze($brokenParent);
$assert(in_array('domain:broken-domain', $brokenParentReport['failures']['let']['orphan_taxonomy_nodes'] ?? [], true), 'broken taxonomy parent was not detected');

echo "[PASS] Taxonomy namespace, duplicate-ID, and broken-parent integrity regression verified.\n";
