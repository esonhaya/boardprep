<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

$storagePath = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
    . '/boardprep-question-eligibility-metadata-'
    . getmypid()
    . '-'
    . bin2hex(random_bytes(6));
if (!mkdir($storagePath, 0700, true)) {
    throw new RuntimeException('Unable to create isolated eligibility metadata fixture.');
}
$passed = false;

try {
    putenv('APP_ENV=testing');
    putenv('APP_DEBUG=false');
    putenv('APP_TIMEZONE=UTC');
    putenv('DB_DRIVER=json');
    putenv('APP_STORAGE_PATH=' . $storagePath);

    $records = [
        [
            'id' => 'grouped-eligibility',
            'question_ids' => ['question-a', 'question-b'],
            'exam_ids' => ['LET', 'CSE'],
            'subject_ids' => ['LET' => 'english', 'CSE' => 'education'],
            'variant_placements' => ['let' => ['secondary' => ['english']]],
            'question_mappings' => [
                'question-a' => [
                    'exam_ids' => ['LET'],
                    'subject_ids' => ['LET' => 'mathematics'],
                    'placements' => ['let' => ['subject_id' => 'mathematics']],
                    'variant_placements' => ['let' => ['secondary' => ['mathematics']]],
                ],
            ],
        ],
        [
            'id' => 'later-duplicate',
            'question_ids' => ['question-a'],
            'exam_ids' => ['NURSING'],
        ],
    ];
    file_put_contents(
        $storagePath . '/question-eligibility.json',
        json_encode($records, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL,
        LOCK_EX
    );

    \App\Core\App::boot();
    $questions = [
        ['id' => 'question-a'],
        ['id' => 'question-b'],
        ['id' => 'question-missing'],
    ];
    $batched = \App\Services\Question\QuestionEligibilityService::metadataForQuestions($questions);
    foreach ($questions as $question) {
        $id = (string) $question['id'];
        $expected = \App\Services\Question\QuestionEligibilityService::metadata($question);
        if (($batched[$id] ?? []) !== $expected) {
            throw new RuntimeException("Batched eligibility metadata changed the single-question result for {$id}.");
        }
    }
    if (($batched['question-a']['exam_ids'] ?? []) !== ['LET']) {
        throw new RuntimeException('Batched eligibility metadata did not preserve first matching record and per-question override behavior.');
    }

    $newQuestion = ['id' => 'question-added'];
    if (\App\Services\Question\QuestionEligibilityService::metadata($newQuestion) !== []) {
        throw new RuntimeException('An unmapped question unexpectedly received eligibility metadata.');
    }
    \App\Services\Question\QuestionEligibilityService::persist($newQuestion, [
        'exam_ids' => ['LET'],
        'subject_ids' => ['LET' => 'english'],
        'placements' => [],
        'variant_placements' => [],
    ]);
    if ((\App\Services\Question\QuestionEligibilityService::metadata($newQuestion)['exam_ids'] ?? []) !== ['LET']) {
        throw new RuntimeException('Eligibility metadata cache was not invalidated after a persisted mapping change.');
    }

    $passed = true;
    echo "[PASS] Batched question export metadata preserves grouped placements, first-match semantics, and post-write invalidation.\n";
} finally {
    if ($passed) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($storagePath, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $entry) {
            if ($entry->isDir() && !$entry->isLink()) {
                rmdir($entry->getPathname());
            } else {
                unlink($entry->getPathname());
            }
        }
        rmdir($storagePath);
    } else {
        fwrite(STDERR, 'TEST_FIXTURE_PRESERVED=' . $storagePath . PHP_EOL);
    }
}
