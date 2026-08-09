<?php

declare(strict_types=1);

namespace Tools\Tests;

use App\Core\Autoloader;

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';

Autoloader::register();

final class QuizTest
{
    private int $passed = 0;
    private int $failed = 0;
    private int $assertions = 0;

    public function run(): int
    {
        $this->header();

        $this->testAutoloader();
        $this->testServicesAvailable();
        $this->testCoreQuizServices();
        $this->testSelectionServices();
        $this->testBlueprintServices();

        $this->summary();

        return $this->failed > 0 ? 1 : 0;
    }

    private function header(): void
    {
        echo "======================================\n";
        echo " BoardPrep Quiz Test\n";
        echo "======================================\n";
        echo "Mode: In-process simulation\n";
        echo "Database: NOT USED\n";
        echo "HTTP server: NOT USED\n\n";
    }

    private function testAutoloader(): void
    {
        echo "[TEST] Application autoloader\n";

        $this->assertTrue(
            class_exists(Autoloader::class),
            'Autoloader available'
        );

        echo "[PASS] OK\n";
    }

    private function testServicesAvailable(): void
    {
        echo "[TEST] Quiz services\n";

        $services = [
            'QuizScoringService',
            'QuizStartService',
            'QuizSubmissionService',
            'QuizNavigationService',
            'QuizResultService',
            'QuizGenerationService',
            'QuizHistoryService',
            'QuizBlueprintService',
            'QuestionSelectionService',
            'QuestionBalancingService',
            'AdaptiveQuizService',
            'ExamAssemblyService',
        ];

        foreach ($services as $class) {
            $this->assertTrue(
                class_exists($class),
                "{$class} available"
            );
        }

        if ($this->failed === 0) {
            echo "[PASS] OK\n";
        }
    }

    private function testCoreQuizServices(): void
    {
        echo "[TEST] Core quiz service methods\n";

        $methods = [
            'QuizScoringService' => [
                'calculate',
                'checkAnswer',
            ],
            'QuizStartService' => [
                'start',
            ],
            'QuizSubmissionService' => [
                'submit',
            ],
            'QuizNavigationService' => [
                'next',
                'current',
                'reset',
            ],
            'QuizResultService' => [
                'build',
            ],
            'QuizGenerationService' => [
                'generate',
            ],
            'QuizHistoryService' => [
                'all',
            ],
        ];

        $before = $this->failed;

        foreach ($methods as $class => $classMethods) {
            foreach ($classMethods as $method) {
                $this->assertTrue(
                    method_exists($class, $method),
                    "{$class}::{$method} exists"
                );
            }
        }

        if ($this->failed === $before) {
            echo "[PASS] OK\n";
        }
    }

    private function testSelectionServices(): void
    {
        echo "[TEST] Quiz selection services\n";

        $services = [
            'QuestionSelectionService',
            'QuestionBalancingService',
            'AdaptiveQuizService',
            'ExamAssemblyService',
        ];

        $before = $this->failed;

        foreach ($services as $class) {
            $this->assertTrue(
                class_exists($class),
                "{$class} available"
            );
        }

        if ($this->failed === $before) {
            echo "[PASS] OK\n";
        }
    }

    private function testBlueprintServices(): void
    {
        echo "[TEST] Quiz blueprint services\n";

        $services = [
            'BlueprintExecutor',
            'BlueprintResolverService',
            'BlueprintCoverageAnalyzer',
            'BlueprintCoverageValidator',
            'BlueprintDifficultyAllocator',
            'BlueprintDistributionService',
            'BlueprintIntegrityValidator',
        ];

        $before = $this->failed;

        foreach ($services as $class) {
            $this->assertTrue(
                class_exists($class),
                "{$class} available"
            );
        }

        if ($this->failed === $before) {
            echo "[PASS] OK\n";
        }
    }

    private function assertTrue(
        bool $condition,
        string $message
    ): void {
        $this->assertions++;

        if ($condition) {
            $this->passed++;
            return;
        }

        $this->failed++;
        echo "[FAIL] {$message}\n";
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
            echo "[PASS] Quiz service simulation passed.\n";
        } else {
            echo "[FAIL] Quiz service simulation failed.\n";
        }
    }
}

$test = new QuizTest();

exit($test->run());
