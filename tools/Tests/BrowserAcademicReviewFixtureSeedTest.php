<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/tests/browser/academic-review-fixture-helpers.php';

$currentVersion = str_repeat('a', 64);
$otherVersion = str_repeat('b', 64);
$unrelatedVersion = str_repeat('c', 64);
$matrix = [
    'citation_rows' => [
        ['question_id' => '9348', 'question_version' => $currentVersion],
        ['question_id' => '9348', 'question_version' => $otherVersion],
        ['question_id' => '9228', 'question_version' => $unrelatedVersion],
    ],
];

if (!boardPrepBrowserStaleSupplementalEvidenceVersion($matrix, '9348')) {
    throw new RuntimeException('The synthetic stale-row target was not found.');
}
if (($matrix['citation_rows'][0]['question_version'] ?? '') !== str_repeat('0', 64)) {
    throw new RuntimeException('The first matching supplemental row was not actually changed.');
}
if (($matrix['citation_rows'][1]['question_version'] ?? '') !== $otherVersion
    || ($matrix['citation_rows'][2]['question_version'] ?? '') !== $unrelatedVersion) {
    throw new RuntimeException('The fixture changed more rows than the single requested synthetic row.');
}
if (boardPrepBrowserStaleSupplementalEvidenceVersion($matrix, 'missing')) {
    throw new RuntimeException('A missing fixture row was incorrectly reported as changed.');
}

fwrite(STDOUT, "[PASS] The isolated academic browser fixture mutates exactly one referenced supplemental row.\n");
