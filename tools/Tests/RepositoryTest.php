<?php

declare(strict_types=1);

namespace Tools\Tests;

use App\Core\Autoloader;

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';

Autoloader::register();

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/MemoryStorage.php';

final class RepositoryTest
{
    private int $passed = 0;

    private int $failed = 0;

    private int $assertions = 0;

    public function run(): int
    {
        $this->header();

        $this->testAutoloader();
        $this->testBaseRepositoryContract();
        $this->testAttemptRepository();
        $this->testProgressRepository();
        $this->testQuestionRepository();
        $this->testBlueprintRepository();
        $this->testBoardRepository();
        $this->testSubjectRepository();
        $this->testBoardSubjectRepository();

        $this->summary();

        return $this->failed > 0 ? 1 : 0;
    }

    private function testAutoloader(): void
    {
        echo "[TEST] Application autoloader\n";

        $this->assertTrue(
            class_exists(Autoloader::class),
            'Autoloader available'
        );
    }

    private function testBaseRepositoryContract(): void
    {
        echo "[TEST] BaseRepository\n";

        $class = 'App\\Repositories\\BaseRepository';

        $this->assertTrue(
            class_exists($class),
            'BaseRepository class exists'
        );

        foreach ([
            'all',
            'find',
            'where',
            'create',
            'update',
            'delete',
            'exists',
        ] as $method) {
            $this->assertTrue(
                method_exists($class, $method),
                "BaseRepository::{$method} exists"
            );
        }
    }

    private function testAttemptRepository(): void
    {
        echo "[TEST] AttemptRepository\n";

        $class = 'App\\Repositories\\AttemptRepository';

        $this->assertClassMethods($class, [
            'byUser',
            'byMode',
            'completed',
        ]);
    }

    private function testProgressRepository(): void
    {
        echo "[TEST] ProgressRepository\n";

        $class = 'App\\Repositories\\ProgressRepository';

        $this->assertClassMethods($class, [
            'all',
            'find',
            'save',
        ]);
    }

    private function testQuestionRepository(): void
    {
        echo "[TEST] QuestionRepository\n";

        $class = 'App\\Repositories\\QuestionRepository';

        $this->assertClassMethods($class, [
            'byBoard',
            'bySubject',
            'byDomain',
            'byTopic',
            'byConcept',
            'byDifficulty',
            'approved',
        ]);
    }

    private function testBlueprintRepository(): void
    {
        echo "[TEST] BlueprintRepository\n";

        $class = 'App\\Repositories\\BlueprintRepository';

        $this->assertClassMethods($class, [
            'board',
            'subject',
            'versions',
            'activate',
            'archive',
        ]);
    }

    private function testBoardRepository(): void
    {
        echo "[TEST] BoardRepository\n";

        $class = 'App\\Repositories\\BoardRepository';

        $this->assertClassMethods($class, [
            'all',
            'find',
            'where',
            'create',
            'update',
            'delete',
            'exists',
            'active',
            'archived',
            'setStatus',
            'activate',
            'archive',
        ]);
    }

    private function testSubjectRepository(): void
    {
        echo "[TEST] SubjectRepository\n";

        $class = 'App\\Repositories\\SubjectRepository';

        $this->assertClassMethods($class, [
            'all',
            'find',
            'where',
            'create',
            'update',
            'delete',
            'exists',
            'active',
            'archived',
            'setStatus',
            'activate',
            'archive',
            'existsByName',
        ]);
    }

    private function testBoardSubjectRepository(): void
    {
        echo "[TEST] BoardSubjectRepository\n";

        $class = 'App\\Repositories\\BoardSubjectRepository';

        $this->assertClassMethods($class, [
            'all',
            'find',
            'where',
            'create',
            'update',
            'delete',
            'exists',
        ]);
    }

    /**
     * @param array<int, string> $methods
     */
    private function assertClassMethods(
        string $class,
        array $methods
    ): void {
        $exists = class_exists($class);

        $this->assertTrue(
            $exists,
            "{$class} class exists"
        );

        if (!$exists) {
            return;
        }

        foreach ($methods as $method) {
            $this->assertTrue(
                method_exists($class, $method),
                "{$class}::{$method} exists"
            );
        }
    }

    private function assertTrue(
        bool $condition,
        string $message
    ): void {
        $this->assertions++;

        if ($condition) {
            $this->passed++;
            echo "[PASS] {$message}\n";
            return;
        }

        $this->failed++;
        echo "[FAIL] {$message}\n";
    }

    private function header(): void
    {
        echo "======================================\n";
        echo " BoardPrep Repository Test\n";
        echo "======================================\n";
        echo "Mode: In-process simulation\n";
        echo "Database: NOT USED\n";
        echo "HTTP server: NOT USED\n\n";
    }

    private function summary(): void
    {
        echo "\n======================================\n";
        echo " SUMMARY\n";
        echo "======================================\n";
        echo "PASS       : {$this->passed}\n";
        echo "FAIL       : {$this->failed}\n";
        echo "ASSERTIONS : {$this->assertions}\n\n";

        if ($this->failed === 0) {
            echo "[PASS] Repository simulation passed.\n";
        } else {
            echo "[FAIL] Repository simulation failed.\n";
        }
    }
}

exit((new RepositoryTest())->run());
