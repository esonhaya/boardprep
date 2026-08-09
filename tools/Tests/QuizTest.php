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
        $this->testScoringBehavior();

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

    private function testScoringBehavior(): void
    {
        echo "[TEST] Quiz scoring behavior\n";

        $questions = [
            [
                'id' => 1,
                'question' => 'Capital of France?',
                'choices' => [
                    'London',
                    'Paris',
                    'Rome',
                    'Madrid',
                ],
                'answer' => 'Paris',
                'explanation' => 'Paris is the capital of France.',
            ],
            [
                'id' => 2,
                'question' => '2 + 2?',
                'choices' => [
                    '3',
                    '4',
                    '5',
                    '6',
                ],
                'answer' => '4',
            ],
            [
                'id' => 3,
                'question' => 'Primary color?',
                'choices' => [
                    'Green',
                    'Orange',
                    'Blue',
                    'Purple',
                ],
                'answer' => 'Blue',
            ],
            [
                'id' => 4,
                'question' => 'Largest ocean?',
                'choices' => [
                    'Atlantic',
                    'Indian',
                    'Pacific',
                    'Arctic',
                ],
                'answer' => 'Pacific',
            ],
        ];

        $answers = [
            1 => 'B',
            2 => '4',
            3 => 'A',
        ];

        $result = \QuizScoringService::calculate(
            $questions,
            $answers
        );

        $this->assertSame(
            2,
            $result['score'] ?? null,
            'Scoring: correct score'
        );

        $this->assertSame(
            2,
            $result['correct'] ?? null,
            'Scoring: correct count'
        );

        $this->assertSame(
            1,
            $result['incorrect'] ?? null,
            'Scoring: incorrect count'
        );

        $this->assertSame(
            1,
            $result['unanswered'] ?? null,
            'Scoring: unanswered count'
        );

        $this->assertSame(
            4,
            $result['total'] ?? null,
            'Scoring: total count'
        );

        $this->assertTrue(
            (float) ($result['percentage'] ?? -1) === 50.0,
            'Scoring: percentage'
        );

        $this->assertTrue(
            \QuizScoringService::checkAnswer(
                $questions[0],
                'B'
            ),
            'Scoring: choice letter B resolves correctly'
        );

        $this->assertTrue(
            \QuizScoringService::checkAnswer(
                $questions[0],
                'paris'
            ),
            'Scoring: direct answer is case-insensitive'
        );

        $this->assertFalse(
            \QuizScoringService::checkAnswer(
                $questions[0],
                'A'
            ),
            'Scoring: wrong choice is rejected'
        );

        $this->assertFalse(
            \QuizScoringService::checkAnswer(
                $questions[0],
                null
            ),
            'Scoring: unanswered answer is rejected'
        );

        $this->assertSame(
            4,
            count($result['results'] ?? []),
            'Scoring: one result per question'
        );

        $this->assertTrue(
            ($result['results'][0]['correct'] ?? false) === true,
            'Scoring: first result marked correct'
        );

        $this->assertTrue(
            ($result['results'][2]['correct'] ?? true) === false,
            'Scoring: third result marked incorrect'
        );

        $this->assertTrue(
            ($result['results'][3]['answered'] ?? true) === false,
            'Scoring: fourth result marked unanswered'
        );

        echo "[PASS] OK\n";
    }

    private function assertFalse(
        bool $condition,
        string $message
    ): void {
        $this->assertions++;

        if ($condition) {
            $this->failed++;
            echo "[FAIL] {$message}\\n";
        } else {
            $this->passed++;
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

    private function assertSame(
        mixed $expected,
        mixed $actual,
        string $message
    ): void {
        $this->assertions++;

        if ($expected === $actual) {
            $this->passed++;
            return;
        }

        $this->failed++;

        echo "[FAIL] {$message}\n";
        echo "       Expected: " . var_export($expected, true) . "\n";
        echo "       Actual:   " . var_export($actual, true) . "\n";
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
