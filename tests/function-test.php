<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/Core/Autoloader.php';

\App\Core\Autoloader::register();

$passed = 0;
$failed = 0;
$startTime = microtime(true);

function test(string $name, callable $callback): void
{
    global $passed, $failed;

    echo "\n[TEST] {$name}\n";

    $start = microtime(true);

    try {

        $result = $callback();

        $elapsed = number_format(
            (microtime(true) - $start) * 1000,
            2
        );

        echo "[PASS] ";

        if (is_string($result)) {
            echo $result;
        } elseif ($result === null) {
            echo "OK";
        } else {
            echo "Returned " . get_debug_type($result);
        }

        echo " ({$elapsed} ms)\n";

        $passed++;

    } catch (\Throwable $e) {

        $elapsed = number_format(
            (microtime(true) - $start) * 1000,
            2
        );

        echo "[FAIL] "
            . get_class($e)
            . ": "
            . $e->getMessage()
            . " ({$elapsed} ms)\n";

        echo "       "
            . $e->getFile()
            . ":"
            . $e->getLine()
            . "\n";

        $failed++;
    }
}

function assertTrue(
    bool $condition,
    string $message = "Expected condition to be true."
): void {
    if (!$condition) {
        throw new \RuntimeException($message);
    }
}

function assertFalse(
    bool $condition,
    string $message = "Expected condition to be false."
): void {
    if ($condition) {
        throw new \RuntimeException($message);
    }
}

function assertSame(
    mixed $expected,
    mixed $actual,
    string $message = ""
): void {
    if ($expected !== $actual) {

        throw new \RuntimeException(
            $message !== ""
                ? $message
                : sprintf(
                    "Expected %s, got %s.",
                    var_export($expected, true),
                    var_export($actual, true)
                )
        );
    }
}

function assertNotNull(
    mixed $value,
    string $message = "Expected value to be non-null."
): void {
    if ($value === null) {
        throw new \RuntimeException($message);
    }
}

function testId(string $value): string
{
    $id = strtolower(trim($value));

    $id = preg_replace(
        "/[^a-z0-9]+/",
        "-",
        $id
    );

    return trim($id, "-");
}

echo "======================================\n";
echo " BoardPrep Function Test\n";
echo " CLI Runtime Simulation\n";
echo "======================================\n";

\App\Core\App::boot();

$boardRepository =
    \App\Core\App::container()
        ->get(\App\Repositories\BoardRepository::class);

$subjectRepository =
    \App\Core\App::container()
        ->get(\App\Repositories\SubjectRepository::class);

$blueprintRepository =
    \App\Core\App::container()
        ->get(\App\Repositories\BlueprintRepository::class);

$testSuffix =
    date("YmdHis")
    . "-"
    . substr(
        str_replace(
            ".",
            "",
            (string) microtime(true)
        ),
        -6
    );

$testBoardName =
    "CLI Test Board {$testSuffix}";

$testSubjectName =
    "CLI Test Subject {$testSuffix}";

$testBlueprintName =
    "CLI Test Blueprint {$testSuffix}";

$testBoardId = null;
$testSubjectId = null;
$testBlueprintId = null;

/*
|--------------------------------------------------------------------------
| Bootstrap
|--------------------------------------------------------------------------
*/

test(
    "Autoloader",
    function (): string {

        assertTrue(
            class_exists(\App\Core\Autoloader::class),
            "Autoloader class unavailable."
        );

        return "Autoloader loaded";
    }
);

test(
    "App Container",
    function (): string {

        assertTrue(
            class_exists(\App\Core\App::class),
            "App class unavailable."
        );

        assertTrue(
            class_exists(\App\Core\Container::class),
            "Container class unavailable."
        );

        return "Core container classes loaded";
    }
);

test(
    "Storage Contract",
    function (): string {

        assertTrue(
            interface_exists(
                \App\Contracts\StorageInterface::class
            ),
            "StorageInterface unavailable."
        );

        return "StorageInterface loaded";
    }
);

/*
|--------------------------------------------------------------------------
| Repository Baseline
|--------------------------------------------------------------------------
*/

test(
    "BoardRepository",
    function () use ($boardRepository): string {

        $result = $boardRepository->all();

        assertTrue(
            is_array($result),
            "BoardRepository::all() must return an array."
        );

        return "Returned " . count($result) . " board(s)";
    }
);

test(
    "SubjectRepository",
    function () use ($subjectRepository): string {

        $result = $subjectRepository->all();

        assertTrue(
            is_array($result),
            "SubjectRepository::all() must return an array."
        );

        return "Returned " . count($result) . " subject(s)";
    }
);

test(
    "BlueprintRepository",
    function () use ($blueprintRepository): string {

        $result = $blueprintRepository->all();

        assertTrue(
            is_array($result),
            "BlueprintRepository::all() must return an array."
        );

        return "Returned " . count($result) . " blueprint(s)";
    }
);

/*
|--------------------------------------------------------------------------
| View Services
|--------------------------------------------------------------------------
*/

test(
    "BoardViewService",
    function (): string {

        $result =
            \App\Services\Board\BoardViewService::all();

        assertTrue(
            is_array($result),
            "BoardViewService::all() must return an array."
        );

        return "Returned " . count($result) . " board(s)";
    }
);

test(
    "SubjectViewService",
    function (): string {

        $result =
            \App\Services\Subject\SubjectViewService::all();

        assertTrue(
            is_array($result),
            "SubjectViewService::all() must return an array."
        );

        return "Returned " . count($result) . " subject(s)";
    }
);

/*
|--------------------------------------------------------------------------
| Blueprint Service
|--------------------------------------------------------------------------
*/

test(
    "BlueprintService",
    function (): string {

        $result =
            \App\Services\BlueprintService::all();

        assertTrue(
            is_array($result),
            "BlueprintService::all() must return an array."
        );

        return "Returned " . count($result) . " blueprint(s)";
    }
);

/*
|--------------------------------------------------------------------------
| Board CRUD Simulation
|--------------------------------------------------------------------------
*/

test(
    "Board CRUD - Create",
    function () use (
        &$testBoardId,
        $testBoardName,
        $boardRepository
    ): string {

        $id = testId($testBoardName);

        $boardRepository->create(
            [
                "id" => $id,
                "name" => $testBoardName,
                "description" => "CLI runtime simulation board.",
                "status" => \App\Constants\Status::ACTIVE,
                "subjects" => [],
            ]
        );

        $testBoardId = $id;

        $board =
            $boardRepository->find($id);

        assertNotNull(
            $board,
            "Created board could not be found."
        );

        assertSame(
            $testBoardName,
            $board["name"] ?? null,
            "Created board name does not match."
        );

        return "Created {$id}";
    }
);

test(
    "Board CRUD - Find",
    function () use (
        &$testBoardId,
        $boardRepository
    ): string {

        assertNotNull(
            $testBoardId,
            "Test board ID was not created."
        );

        $board =
            $boardRepository->find(
                $testBoardId
            );

        assertNotNull(
            $board,
            "Board could not be found."
        );

        return "Found {$testBoardId}";
    }
);

test(
    "Board CRUD - Archive",
    function () use (
        &$testBoardId,
        $boardRepository
    ): string {

        assertNotNull(
            $testBoardId,
            "Test board ID was not created."
        );

        $result =
            $boardRepository->archive(
                $testBoardId
            );

        assertNotNull(
            $result,
            "Board archive failed."
        );

        assertSame(
            \App\Constants\Status::ARCHIVED,
            $result["status"] ?? null,
            "Board was not archived."
        );

        return "Archived {$testBoardId}";
    }
);

test(
    "Board CRUD - Activate",
    function () use (
        &$testBoardId,
        $boardRepository
    ): string {

        assertNotNull(
            $testBoardId,
            "Test board ID was not created."
        );

        $result =
            $boardRepository->activate(
                $testBoardId
            );

        assertNotNull(
            $result,
            "Board activation failed."
        );

        assertSame(
            \App\Constants\Status::ACTIVE,
            $result["status"] ?? null,
            "Board was not activated."
        );

        return "Activated {$testBoardId}";
    }
);

/*
|--------------------------------------------------------------------------
| Subject CRUD Simulation
|--------------------------------------------------------------------------
*/

test(
    "Subject CRUD - Create",
    function () use (
        &$testSubjectId,
        $testSubjectName,
        $subjectRepository
    ): string {

        $id = testId($testSubjectName);

        $subjectRepository->create(
            [
                "id" => $id,
                "name" => $testSubjectName,
                "description" => "CLI runtime simulation subject.",
                "status" => \App\Constants\Status::ACTIVE,
            ]
        );

        $testSubjectId = $id;

        $subject =
            $subjectRepository->find($id);

        assertNotNull(
            $subject,
            "Created subject could not be found."
        );

        return "Created {$id}";
    }
);

test(
    "Subject CRUD - Update",
    function () use (
        &$testSubjectId,
        $subjectRepository
    ): string {

        assertNotNull(
            $testSubjectId,
            "Test subject ID was not created."
        );

        $updated =
            $subjectRepository->update(
                $testSubjectId,
                [
                    "description" =>
                        "Updated CLI simulation subject.",
                ]
            );

        assertNotNull(
            $updated,
            "Subject update failed."
        );

        assertSame(
            "Updated CLI simulation subject.",
            $updated["description"] ?? null,
            "Subject description was not updated."
        );

        return "Updated {$testSubjectId}";
    }
);

test(
    "Subject CRUD - Archive",
    function () use (
        &$testSubjectId,
        $subjectRepository
    ): string {

        assertNotNull(
            $testSubjectId,
            "Test subject ID was not created."
        );

        $result =
            $subjectRepository->archive(
                $testSubjectId
            );

        assertNotNull(
            $result,
            "Subject archive failed."
        );

        assertSame(
            \App\Constants\Status::ARCHIVED,
            $result["status"] ?? null,
            "Subject was not archived."
        );

        return "Archived {$testSubjectId}";
    }
);

test(
    "Subject CRUD - Activate",
    function () use (
        &$testSubjectId,
        $subjectRepository
    ): string {

        assertNotNull(
            $testSubjectId,
            "Test subject ID was not created."
        );

        $result =
            $subjectRepository->activate(
                $testSubjectId
            );

        assertNotNull(
            $result,
            "Subject activation failed."
        );

        assertSame(
            \App\Constants\Status::ACTIVE,
            $result["status"] ?? null,
            "Subject was not activated."
        );

        return "Activated {$testSubjectId}";
    }
);

/*
|--------------------------------------------------------------------------
| Blueprint CRUD Simulation
|--------------------------------------------------------------------------
*/

test(
    "Blueprint CRUD - Create",
    function () use (
        &$testBlueprintId,
        $testBoardId,
        $testSubjectId,
        $testBlueprintName,
        $blueprintRepository
    ): string {

        assertNotNull(
            $testBoardId,
            "Test board ID was not created."
        );

        assertNotNull(
            $testSubjectId,
            "Test subject ID was not created."
        );

        $result =
            \App\Services\BlueprintService::create(
                [
                    "board" => $testBoardId,
                    "subject" => $testSubjectId,
                    "name" => $testBlueprintName,
                    "questionCount" => 20,
                    "easy" => 30,
                    "medium" => 50,
                    "hard" => 20,
                ]
            );

        assertTrue(
            ($result["success"] ?? false) === true,
            "Blueprint creation failed."
        );

        $blueprint =
            $result["blueprint"] ?? null;

        assertNotNull(
            $blueprint,
            "Blueprint result is missing."
        );

        $testBlueprintId =
            $blueprint["id"] ?? null;

        assertNotNull(
            $testBlueprintId,
            "Blueprint ID was not generated."
        );

        $stored =
            $blueprintRepository->find(
                $testBlueprintId
            );

        assertNotNull(
            $stored,
            "Created blueprint could not be found."
        );

        return "Created {$testBlueprintId}";
    }
);

/*
|--------------------------------------------------------------------------
| Validation Simulation
|--------------------------------------------------------------------------
*/

test(
    "Validation - Empty Subject Name",
    function (): string {

        $thrown = false;

        try {

            \App\Services\Subject\SubjectValidator::validateCreate(
                [
                    "name" => "",
                ]
            );

        } catch (\InvalidArgumentException $e) {

            $thrown = true;
        }

        assertTrue(
            $thrown,
            "Empty subject name should be rejected."
        );

        return "Invalid subject rejected";
    }
);

test(
    "Validation - Duplicate Subject",
    function () use (
        $testSubjectName,
        $subjectRepository
    ): string {

        assertTrue(
            $subjectRepository->existsByName(
                $testSubjectName
            ),
            "Test subject should already exist."
        );

        $thrown = false;

        try {

            \App\Services\Subject\SubjectValidator::validateCreate(
                [
                    "name" => $testSubjectName,
                ]
            );

        } catch (\InvalidArgumentException $e) {

            $thrown = true;
        }

        assertTrue(
            $thrown,
            "Duplicate subject should be rejected."
        );

        return "Duplicate subject rejected";
    }
);

test(
    "Validation - Invalid Blueprint Difficulty",
    function (): string {

        $result =
            \App\Services\BlueprintService::create(
                [
                    "board" => "cli-test-board",
                    "subject" => "cli-test-subject",
                    "name" => "Invalid Blueprint",
                    "questionCount" => 20,
                    "easy" => 50,
                    "medium" => 50,
                    "hard" => 50,
                ]
            );

        assertFalse(
            ($result["success"] ?? true),
            "Invalid difficulty distribution should fail."
        );

        assertTrue(
            !empty($result["errors"]),
            "Validation errors should be returned."
        );

        return "Invalid difficulty rejected";
    }
);

test(
    "Validation - Invalid Blueprint Question Count",
    function (): string {

        $result =
            \App\Services\BlueprintService::create(
                [
                    "board" => "cli-test-board",
                    "subject" => "cli-test-subject",
                    "name" => "Invalid Question Count",
                    "questionCount" => 0,
                    "easy" => 30,
                    "medium" => 50,
                    "hard" => 20,
                ]
            );

        assertFalse(
            ($result["success"] ?? true),
            "Zero question count should fail."
        );

        assertTrue(
            !empty($result["errors"]),
            "Validation errors should be returned."
        );

        return "Invalid question count rejected";
    }
);

/*
|--------------------------------------------------------------------------
| Container Integrity
|--------------------------------------------------------------------------
*/

test(
    "Container resolves BoardRepository",
    function (): string {

        $repository =
            \App\Core\App::container()
                ->get(\App\Repositories\BoardRepository::class);

        assertTrue(
            $repository instanceof
                \App\Repositories\BoardRepository,
            "Container returned wrong BoardRepository type."
        );

        return "Dependency resolution successful";
    }
);

test(
    "Container resolves SubjectRepository",
    function (): string {

        $repository =
            \App\Core\App::container()
                ->get(\App\Repositories\SubjectRepository::class);

        assertTrue(
            $repository instanceof
                \App\Repositories\SubjectRepository,
            "Container returned wrong SubjectRepository type."
        );

        return "Dependency resolution successful";
    }
);

test(
    "Container resolves BlueprintRepository",
    function (): string {

        $repository =
            \App\Core\App::container()
                ->get(\App\Repositories\BlueprintRepository::class);

        assertTrue(
            $repository instanceof
                \App\Repositories\BlueprintRepository,
            "Container returned wrong BlueprintRepository type."
        );

        return "Dependency resolution successful";
    }
);

test(
    "Database bootstrap",
    function (): string {

        $database =
            \App\Core\App::database();

        if ($database->usingJson()) {
            return "JSON storage active";
        }

        if ($database->usingMysql()) {
            return "MySQL storage active";
        }

        throw new \RuntimeException(
            "Unknown database storage driver."
        );
    }
);

test(
    "Learner question issue reports stay private and separate from academic records",
    function (): string {
        $directory = sys_get_temp_dir() . '/boardprep-b732-question-reports-' . bin2hex(random_bytes(8));
        if (!mkdir($directory, 0700, true)) {
            throw new \RuntimeException('Could not create an isolated report storage fixture.');
        }
        try {
            $storage = new \App\Storage\JsonStorage($directory);
            $review = [];
            for ($index = 1; $index <= 7; $index++) {
                $question = [
                    'id' => 'b732-question-' . $index,
                    'question' => 'Fixture question ' . $index,
                    'answer' => 'A',
                    'taxonomy' => ['board_id' => 'let', 'subject_id' => 'english'],
                ];
                $storage->create('questions', $question);
                $review[] = ['question' => $question, 'answered' => false, 'correct' => false];
            }
            $service = new \App\Services\Question\QuestionIssueReportService($storage);
            $learner = 'learner-' . bin2hex(random_bytes(16));
            $attemptId = 'b732-runtime-attempt';
            $storage->create('attempts', [
                'id' => $attemptId,
                'user_id' => $learner,
                'completed' => true,
                'board' => 'let',
                'subject' => 'english',
                'review' => $review,
            ]);
            $first = $service->submit($learner, $attemptId, 'b732-question-1', 'UNCLEAR_WORDING', '<script>alert(1)</script>');
            assertFalse($first['duplicate'], 'The first issue report was treated as a duplicate.');
            assertSame('SUBMITTED', $first['report']['status'], 'A learner report did not start as SUBMITTED.');
            assertFalse(array_key_exists('user_id', $first['report']), 'The learner-facing record exposed its owner identifier.');
            assertSame('<script>alert(1)</script>', $first['report']['description'], 'Plain-text learner detail was altered.');
            $retry = $service->submit($learner, $attemptId, 'b732-question-1', 'UNCLEAR_WORDING', 'retry');
            assertTrue($retry['duplicate'], 'A recent duplicate report was accepted twice.');
            assertSame([], $service->forLearner('learner-' . bin2hex(random_bytes(16))), 'A second learner could read another learner report.');

            $accountId = 'account-' . bin2hex(random_bytes(16));
            $reviewer = new \App\Services\Question\AcademicReviewerAuthorizationService($storage, [[
                'account_id' => $accountId,
                'authorization_status' => 'ACTIVE',
                'qualification_type' => 'QUALIFIED_REVIEWER',
                'qualification_reference' => 'b732-test-reference',
                'authorized_by' => 'owner-provisioned-test-grant',
                'authorized_at' => gmdate(DATE_ATOM),
                'expires_at' => gmdate(DATE_ATOM, time() + 3600),
                'scopes' => [['board_id' => 'let', 'subject_id' => 'english', 'capabilities' => ['REVIEW']]],
            ]]);
            $wrongScopePage = $service->reviewerPage([[
                'board_id' => 'nursing', 'subject_id' => 'nursing-foundations', 'capabilities' => ['REVIEW'],
            ]]);
            assertSame(0, $wrongScopePage['total'], 'A reviewer queue exposed a report outside its scope.');
            assertTrue($service->transition($first['report']['id'], 'UNDER_REVIEW', $accountId, $reviewer), 'Scoped reviewer could not accept a report.');
            assertTrue($service->transition($first['report']['id'], 'RESOLVED', $accountId, $reviewer), 'Scoped reviewer could not resolve an accepted report.');
            assertSame('RESOLVED', $service->forLearner($learner)[0]['status'], 'A real reviewer status transition was not visible to its learner.');
            assertFalse(array_key_exists('status_changed_by', $service->forLearner($learner)[0]), 'Private reviewer identity was exposed to a learner.');

            $limited = false;
            for ($index = 2; $index <= 6; $index++) {
                $type = array_keys(\App\Services\Question\QuestionIssueReportService::types())[$index - 1];
                try {
                    $service->submit($learner, $attemptId, 'b732-question-' . $index, $type, 'bounded fixture');
                } catch (\RuntimeException $exception) {
                    if (str_contains($exception->getMessage(), 'daily question-report limit')) {
                        $limited = true;
                        break;
                    }
                    throw $exception;
                }
            }
            assertTrue($limited, 'The per-learner daily report bound was not enforced.');
            $storedQuestion = $storage->find('questions', 'b732-question-1');
            assertSame('A', $storedQuestion['answer'], 'Submitting a report changed a canonical answer.');
            return 'Report isolation, retry deduplication, scoped reviewer status transitions, daily rate cap, and canonical answer preservation';
        } finally {
            $files = glob($directory . '/*') ?: [];
            foreach ($files as $file) if (is_file($file)) @unlink($file);
            @unlink($directory . '/.storage.lock');
            @rmdir($directory);
        }
    }
);

test(
    "Homepage does not perform a second question repository materialization",
    function (): string {
        $controller = (string) file_get_contents(__DIR__ . '/../app/Controllers/HomeController.php');
        $view = (string) file_get_contents(__DIR__ . '/../app/Views/home/index.php');
        assertFalse(str_contains($controller, 'QuestionRepository'), 'Homepage still depends on the enriched full-bank repository.');
        assertFalse(str_contains($controller, 'questionCount'), 'Homepage still computes the unused question count.');
        assertFalse(str_contains($view, '$questionCount'), 'Homepage unexpectedly depends on the removed value.');
        return 'The homepage retains board availability while avoiding its redundant enriched-bank read';
    }
);

test(
    "Learner question reports use the SQLite storage route without JSON fallback",
    function (): string {
        if (!extension_loaded('pdo_sqlite')) {
            throw new \RuntimeException('PDO SQLite is required for the repository SQLite storage contract.');
        }
        $directory = sys_get_temp_dir() . '/boardprep-b732-question-reports-sqlite-' . bin2hex(random_bytes(8));
        if (!mkdir($directory, 0700, true)) {
            throw new \RuntimeException('Could not create an isolated SQLite report fixture.');
        }
        $database = null;
        $connection = null;
        try {
            $database = new \App\Core\Database([
                'driver' => 'sqlite',
                'path' => $directory . '/json-storage',
                'sqlite_path' => $directory . '/boardprep.sqlite',
                'migration_path' => dirname(__DIR__) . '/database/migrations',
            ]);
            $storage = $database->storage();
            $storage->create('questions', [
                'id' => 'b732-sqlite-question',
                'question' => 'SQLite fixture question',
                'taxonomy' => ['board_id' => 'let', 'subject_id' => 'english'],
            ]);
            $learner = 'learner-' . bin2hex(random_bytes(16));
            $attemptId = 'b732-sqlite-attempt';
            $storage->create('attempts', [
                'id' => $attemptId,
                'user_id' => $learner,
                'completed' => true,
                'board' => 'let',
                'subject' => 'english',
                'review' => [[
                    'question' => [
                        'id' => 'b732-sqlite-question',
                        'question' => 'SQLite fixture question',
                        'taxonomy' => ['board_id' => 'let', 'subject_id' => 'english'],
                    ],
                    'answered' => false,
                    'correct' => false,
                ]],
            ]);
            $service = new \App\Services\Question\QuestionIssueReportService($storage);
            $saved = $service->submit($learner, $attemptId, 'b732-sqlite-question', 'SOURCE_CONCERN', 'Check source scope.');
            assertFalse($saved['duplicate'], 'SQLite report was unexpectedly considered a duplicate.');
            assertNotNull($storage->find(\App\Services\Question\QuestionIssueReportService::COLLECTION, $saved['report']['id']), 'SQLite router could not read the submitted report.');
            assertFalse(is_file($directory . '/json-storage/learner-question-reports.json'), 'SQLite report collection was written into the JSON fallback path.');
            $connection = new \PDO('sqlite:' . $directory . '/boardprep.sqlite');
            $statement = $connection->prepare('SELECT COUNT(*) FROM storage_records WHERE collection = ?');
            $statement->execute([\App\Services\Question\QuestionIssueReportService::COLLECTION]);
            assertSame(1, (int) $statement->fetchColumn(), 'SQLite storage did not persist exactly one report record.');
            return 'The new report collection participates in the existing SQLite storage_records migration and routing';
        } finally {
            $connection = null;
            $service = null;
            $storage = null;
            $database = null;
            foreach (scandir($directory) ?: [] as $entry) {
                if ($entry === '.' || $entry === '..') continue;
                $path = $directory . DIRECTORY_SEPARATOR . $entry;
                if (is_file($path) || is_link($path)) @unlink($path);
            }
            $jsonDirectory = $directory . '/json-storage';
            if (is_dir($jsonDirectory)) {
                foreach (scandir($jsonDirectory) ?: [] as $entry) {
                    if ($entry === '.' || $entry === '..') continue;
                    $path = $jsonDirectory . DIRECTORY_SEPARATOR . $entry;
                    if (is_file($path) || is_link($path)) @unlink($path);
                }
                @rmdir($jsonDirectory);
            }
            @rmdir($directory);
        }
    }
);

/*
|--------------------------------------------------------------------------
| Cleanup
|--------------------------------------------------------------------------
*/

echo "\n======================================\n";
echo " CLEANUP\n";
echo "======================================\n";

if ($testBlueprintId !== null) {

    $blueprintRepository->delete(
        $testBlueprintId
    );

    echo "[CLEAN] Blueprint {$testBlueprintId}\n";
}

if ($testSubjectId !== null) {

    $subjectRepository->delete(
        $testSubjectId
    );

    echo "[CLEAN] Subject {$testSubjectId}\n";
}

if ($testBoardId !== null) {

    $boardRepository->delete(
        $testBoardId
    );

    echo "[CLEAN] Board {$testBoardId}\n";
}

/*
|--------------------------------------------------------------------------
| Summary
|--------------------------------------------------------------------------
*/

$elapsed =
    number_format(
        microtime(true) - $startTime,
        3
    );

echo "\n======================================\n";
echo " SUMMARY\n";
echo "======================================\n";
echo "PASS : {$passed}\n";
echo "FAIL : {$failed}\n";
echo "TIME : {$elapsed}s\n";

if ($failed === 0) {

    echo "\n[PASS] All runtime simulations passed.\n";

    exit(0);
}

echo "\n[FAIL] Runtime simulation failures detected.\n";

exit(1);
