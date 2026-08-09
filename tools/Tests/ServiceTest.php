<?php

declare(strict_types=1);

namespace Tools\Tests;

use App\Core\Autoloader;
use App\Services\Learning\LearningHistoryService;
use App\Services\Learning\PerformanceAnalyticsService;
use App\Services\Learning\WeaknessService;
use App\Services\Profile\LearningProfileService;
use App\Services\Learning\RecommendationService;
use App\Services\Learning\LearningCoachService;
use App\Services\Question\QuestionViewService;
use App\Services\Question\QuestionQualityService;
use App\Services\Shared\TaxonomyStorageService;
use App\Services\Shared\TaxonomyIntegrityService;
use App\Services\Question\QuestionService;
use App\Services\Question\QuestionQueryService;
use App\Services\Question\QuestionEditorService;
use App\Services\Blueprint\BlueprintService;
use App\Services\Board\BoardService;
use App\Services\Subject\SubjectService;

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';

Autoloader::register();

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/MemoryStorage.php';

final class ServiceTest
{
    private int $passed = 0;

    private int $failed = 0;

    private int $assertions = 0;

    /**
     * @var array<int, array{
     *     name: string,
     *     class: class-string,
     *     methods: array<int, string>
     * }>
     */
    private array $services = [

        [
            'name' => 'LearningHistoryService',
            'class' => LearningHistoryService::class,
            'methods' => [
                'recent',
            ],
        ],

        [
            'name' => 'PerformanceAnalyticsService',
            'class' => PerformanceAnalyticsService::class,
            'methods' => [
                'summary',
            ],
        ],

        [
            'name' => 'WeaknessService',
            'class' => WeaknessService::class,
            'methods' => [
                'all',
            ],
        ],

        [
            'name' => 'LearningProfileService',
            'class' => LearningProfileService::class,
            'methods' => [
                'build',
            ],
        ],

        [
            'name' => 'RecommendationService',
            'class' => RecommendationService::class,
            'methods' => [
                'generate',
            ],
        ],

        [
            'name' => 'LearningCoachService',
            'class' => LearningCoachService::class,
            'methods' => [
                'build',
            ],
        ],

        [
            'name' => 'QuestionViewService',
            'class' => QuestionViewService::class,
            'methods' => [
                'taxonomy',
            ],
        ],

        [
            'name' => 'QuestionQualityService',
            'class' => QuestionQualityService::class,
            'methods' => [
                'analyze',
            ],
        ],

        [
            'name' => 'QuestionImportService',
            'class' => 'App\\Services\\Shared\\QuestionImportService',
            'methods' => [
                'importJson',
            ],
        ],

        [
            'name' => 'TaxonomyStorageService',
            'class' => TaxonomyStorageService::class,
            'methods' => [
                'subjects',
                'domains',
                'topics',
                'concepts',
            ],
        ],

        [
            'name' => 'TaxonomyIntegrityService',
            'class' => TaxonomyIntegrityService::class,
            'methods' => [
                'analyze',
            ],
        ],

        [
            'name' => 'QuestionService',
            'class' => QuestionService::class,
            'methods' => [
                'build',
                'validateForSave',
                'save',
                'update',
            ],
        ],

        [
            'name' => 'QuestionQueryService',
            'class' => QuestionQueryService::class,
            'methods' => [
                'getQuestions',
            ],
        ],

        [
            'name' => 'QuestionEditorService',
            'class' => QuestionEditorService::class,
            'methods' => [
                'find',
            ],
        ],

        [
            'name' => 'BlueprintService',
            'class' => BlueprintService::class,
            'methods' => [
                'all',
                'create',
            ],
        ],

        [
            'name' => 'BoardService',
            'class' => BoardService::class,
            'methods' => [
                'all',
            ],
        ],

        [
            'name' => 'SubjectService',
            'class' => SubjectService::class,
            'methods' => [
                'all',
            ],
        ],

    ];

    public function run(): int
    {
        $this->header();

        $this->testAutoloader();

        foreach ($this->services as $service) {
            $this->testService(
                $service['name'],
                $service['class'],
                $service['methods']
            );
        }

        $this->summary();

        return $this->failed > 0 ? 1 : 0;
    }

    private function header(): void
    {
        echo "======================================\n";
        echo " BoardPrep Service Test\n";
        echo "======================================\n";
        echo "Mode: In-process simulation\n";
        echo "Database: NOT USED\n";
        echo "HTTP server: NOT USED\n\n";
    }

    private function testAutoloader(): void
    {
        echo "[TEST] Application autoloader\n";

        $result = class_exists(Autoloader::class);

        $this->assertTrue(
            $result,
            'Autoloader available'
        );

        echo $result
            ? "[PASS] OK\n"
            : "[FAIL] Autoloader unavailable\n";
    }

    /**
     * @param class-string $class
     * @param array<int, string> $methods
     */
    private function testService(
        string $name,
        string $class,
        array $methods
    ): void {

        echo "[TEST] {$name}\n";

        $classExists = class_exists($class);

        $this->assertTrue(
            $classExists,
            "{$name} class exists"
        );

        if (!$classExists) {
            echo "[FAIL] Class not found: {$class}\n";
            return;
        }

        $allMethodsExist = true;

        foreach ($methods as $method) {

            $exists = method_exists(
                $class,
                $method
            );

            $this->assertTrue(
                $exists,
                "{$name}::{$method} exists"
            );

            if (!$exists) {
                $allMethodsExist = false;
                echo "[FAIL] Missing method: {$method}\n";
            }
        }

        if ($allMethodsExist) {
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
    }

    private function summary(): void
    {
        echo "\n======================================\n";
        echo " SUMMARY\n";
        echo "======================================\n";
        echo "PASS       : {$this->passed}\n";
        echo "FAIL       : {$this->failed}\n";
        echo "ASSERTIONS : {$this->assertions}\n";
        echo "\n";

        if ($this->failed === 0) {
            echo "[PASS] Service simulation passed.\n";
        } else {
            echo "[FAIL] Service simulation failed.\n";
        }
    }
}

exit(
    (new ServiceTest())->run()
);
