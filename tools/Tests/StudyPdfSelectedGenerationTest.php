<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

use App\Services\Study\StudyPdfGenerator;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};
$root = sys_get_temp_dir() . '/boardprep-study-pdf-selected-' . bin2hex(random_bytes(5));
mkdir($root . '/storage', 0700, true);
mkdir($root . '/public/study-resources', 0700, true);
$lesson = static function (string $id, string $file, string $body): array {
    return [
        'id' => $id,
        'title' => 'Fixture ' . $id,
        'status' => 'published',
        'version' => 1,
        'board_id' => 'fixture',
        'subject_id' => 'fixture-subject',
        'material_id' => 'material-' . $id,
        'official_scope_source_ids' => ['fixture-source'],
        'instructional_source_ids' => [],
        'scope_note' => 'Synthetic test scope.',
        'learning_objectives' => ['Explain the fixture concept.'],
        'sections' => [['id' => 'section-' . $id, 'heading' => 'Concept', 'body' => $body]],
        'worked_examples' => [],
        'common_misconceptions' => [],
        'question_patterns' => [],
        'practice_question_ids' => [],
        'pdf_file' => $file,
    ];
};
$lessons = [
    $lesson('fixture-a', 'fixture-a.pdf', 'Original A content.'),
    $lesson('fixture-b', 'fixture-b.pdf', 'Unchanged B content.'),
];
$source = [[
    'id' => 'fixture-source',
    'title' => 'Synthetic test source',
    'authority' => 'Test fixture',
    'url' => 'https://example.invalid/source',
    'version' => 'fixture',
]];
$write = static function (string $path, array $value): void {
    file_put_contents($path, json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n");
};
$lessonPath = $root . '/storage/study-lessons.json';
$sourcePath = $root . '/storage/sources.json';
$write($lessonPath, $lessons);
$write($sourcePath, $source);

try {
    StudyPdfGenerator::build($root);
    $pdfA = $root . '/public/study-resources/fixture-a.pdf';
    $pdfB = $root . '/public/study-resources/fixture-b.pdf';
    $beforeA = hash_file('sha256', $pdfA);
    $beforeB = hash_file('sha256', $pdfB);
    $beforeManifest = json_decode((string) file_get_contents($root . '/public/study-resources/manifest.json'), true, 512, JSON_THROW_ON_ERROR);

    $lessons[0]['sections'][0]['body'] = 'Updated A content for the selected lesson.';
    $write($lessonPath, $lessons);
    $generated = StudyPdfGenerator::build($root, false, ['fixture-a']);
    $afterManifest = json_decode((string) file_get_contents($root . '/public/study-resources/manifest.json'), true, 512, JSON_THROW_ON_ERROR);
    $afterEntries = [];
    foreach ($afterManifest['files'] as $entry) $afterEntries[$entry['lesson_id']] = $entry;
    $assert(($generated['status'] ?? '') === 'GENERATED', 'A selected lesson PDF was not generated.');
    $assert(hash_file('sha256', $pdfA) !== $beforeA, 'The selected lesson PDF did not change.');
    $assert(hash_file('sha256', $pdfB) === $beforeB, 'An unselected lesson PDF was rewritten.');
    $assert(count($afterManifest['files']) === 2, 'The merged manifest did not retain all published lesson entries.');
    $assert(($afterEntries['fixture-b']['sha256'] ?? '') === $beforeB
        && ($afterEntries['fixture-b']['lesson_sha256'] ?? '') === ($beforeManifest['files'][1]['lesson_sha256'] ?? ''),
        'The unchanged lesson manifest evidence was not reused.');
    $assert((StudyPdfGenerator::build($root, true, ['fixture-a'])['status'] ?? '') === 'PASS', 'The selected PDF check did not pass.');

    $lessons[1]['sections'][0]['body'] = 'Changed unselected B content.';
    $write($lessonPath, $lessons);
    $refused = false;
    try {
        StudyPdfGenerator::build($root, false, ['fixture-a']);
    } catch (RuntimeException $exception) {
        $refused = str_contains($exception->getMessage(), 'unverified PDF evidence');
    }
    $assert($refused, 'A selected build did not refuse to reuse stale evidence for an omitted changed lesson.');
} finally {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $entry) $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
    rmdir($root);
}

echo "Selected study PDF generation and evidence reuse tests passed\n";
