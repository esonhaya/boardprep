<?php

declare(strict_types=1);

$path = dirname(__DIR__, 2) . '/app/Views/developer/question-quality.php';
$view = (string) file_get_contents($path);

foreach ([
    'id="needs-review-heading"',
    'name="status"',
    'name="category"',
    'name="issue_type"',
    'name="entity_type"',
    'name="board"',
    'name="search"',
    'name="queue"',
    'name="per_page"',
    'aria-label="Question quality issue pages"',
    '$qualityQueue[\'items\']',
    'quality_classification',
    'question_version_sha256',
    'Current status:',
    'Question version SHA-256:',
    'duplicate_scope',
    'duplicate_match_method',
    'duplicate_question_ids',
    'Next action:',
    'Pending B695 proposals',
    'Canonical questions with no detected technical issue',
    'qualityEvidenceState',
    'current Git/worktree fingerprint',
    'A warning or review signal does not establish that a question or answer is incorrect.',
] as $needle) {
    if (!str_contains($view, $needle)) {
        throw new RuntimeException('Question quality queue view is missing ' . $needle . '.');
    }
}

if (str_contains($view, 'foreach ($report->issues as $issue)')) {
    throw new RuntimeException('The quality view still renders every issue in one response.');
}

$editorPath = dirname(__DIR__, 2) . '/app/Views/developer/question/editor.php';
$editor = (string) file_get_contents($editorPath);
if (!str_contains($editor, 'href="/question-quality?queue=needs-review"')) {
    throw new RuntimeException('The canonical Developer Question Repository does not link to Needs Review.');
}
if (!str_contains($editor, 'name="technical_status"')
    || !str_contains($editor, 'No detected technical issue')) {
    throw new RuntimeException('The canonical question inventory does not expose the no-detected-technical-issue filter.');
}

echo "[PASS] Developer Needs Review filters, bounded page rendering, and repository entry point contract verified.\n";
