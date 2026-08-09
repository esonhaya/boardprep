<?php

declare(strict_types=1);

namespace App\Core;

final class Autoloader
{
    /**
     * @var array<string,string>
     */
    private static array $prefixes = [
        'App\\'   => __DIR__ . '/../',
        'Tools\\' => __DIR__ . '/../../tools/',
    ];

    private static array $legacyMap = [
        'App\\Services\\Quiz\\QuizScoringService' => __DIR__ . '/../Services/Quiz/QuizScoringService.php',
        'App\\Services\\Quiz\\QuizStartService' => __DIR__ . '/../Services/Quiz/QuizStartService.php',
        'App\\Services\\Quiz\\QuizSubmissionService' => __DIR__ . '/../Services/Quiz/QuizSubmissionService.php',
        'App\\Services\\Quiz\\QuizNavigationService' => __DIR__ . '/../Services/Quiz/QuizNavigationService.php',
        'App\\Services\\Quiz\\QuizResultService' => __DIR__ . '/../Services/Quiz/QuizResultService.php',
        'App\\Services\\Quiz\\QuizGenerationService' => __DIR__ . '/../Services/Quiz/QuizGenerationService.php',
        'App\\Services\\Quiz\\QuizHistoryService' => __DIR__ . '/../Services/Quiz/QuizHistoryService.php',
        'App\\Services\\Quiz\\QuizBlueprintService' => __DIR__ . '/../Services/Quiz/QuizBlueprintService.php',
        'App\\Services\\Quiz\\QuestionSelectionService' => __DIR__ . '/../Services/Quiz/QuestionSelectionService.php',
        'App\\Services\\Quiz\\QuestionBalancingService' => __DIR__ . '/../Services/Quiz/QuestionBalancingService.php',
        'App\\Services\\Quiz\\AdaptiveQuizService' => __DIR__ . '/../Services/Quiz/AdaptiveQuizService.php',
        'App\\Services\\Quiz\\ExamAssemblyService' => __DIR__ . '/../Services/Quiz/ExamAssemblyService.php',
        'App\\Services\\Quiz\\Blueprint\\BlueprintExecutor' => __DIR__ . '/../Services/Quiz/Blueprint/BlueprintExecutor.php',
        'App\\Services\\Quiz\\Blueprint\\BlueprintResolverService' => __DIR__ . '/../Services/Quiz/Blueprint/BlueprintResolverService.php',
        'App\\Services\\Quiz\\Blueprint\\BlueprintCoverageAnalyzer' => __DIR__ . '/../Services/Quiz/Blueprint/BlueprintCoverageAnalyzer.php',
        'App\\Services\\Quiz\\Blueprint\\BlueprintCoverageValidator' => __DIR__ . '/../Services/Quiz/Blueprint/BlueprintCoverageValidator.php',
        'App\\Services\\Quiz\\Blueprint\\BlueprintDifficultyAllocator' => __DIR__ . '/../Services/Quiz/Blueprint/BlueprintDifficultyAllocator.php',
        'App\\Services\\Quiz\\Blueprint\\BlueprintDistributionService' => __DIR__ . '/../Services/Quiz/Blueprint/BlueprintDistributionService.php',
        'App\\Services\\Quiz\\Blueprint\\BlueprintIntegrityValidator' => __DIR__ . '/../Services/Quiz/Blueprint/BlueprintIntegrityValidator.php',
        'LearningHistoryService' => __DIR__ . '/../Services/Learning/LearningHistoryService.php',
        'PerformanceAnalyticsService' => __DIR__ . '/../Services/Learning/PerformanceAnalyticsService.php',
        'WeaknessService' => __DIR__ . '/../Services/Learning/WeaknessService.php',
        'LearningProfileService' => __DIR__ . '/../Services/Profile/LearningProfileService.php',
        'RecommendationService' => __DIR__ . '/../Services/Learning/RecommendationService.php',
        'LearningCoachService' => __DIR__ . '/../Services/Learning/LearningCoachService.php',
        'LearningTimelineService' => __DIR__ . '/../Services/Learning/LearningTimelineService.php',
        'MasteryService' => __DIR__ . '/../Services/Learning/MasteryService.php',
        'QuestionViewService' => __DIR__ . '/../Services/Question/QuestionViewService.php',
        'QuestionQualityService' => __DIR__ . '/../Services/Question/QuestionQualityService.php',
        'QuestionImportService' => __DIR__ . '/../Services/Shared/QuestionImportService.php',
        'QuizScoringService' => __DIR__ . '/../Services/Quiz/QuizScoringService.php',
        'QuizStartService' => __DIR__ . '/../Services/Quiz/QuizStartService.php',
        'QuizSubmissionService' => __DIR__ . '/../Services/Quiz/QuizSubmissionService.php',
        'QuizNavigationService' => __DIR__ . '/../Services/Quiz/QuizNavigationService.php',
        'QuizResultService' => __DIR__ . '/../Services/Quiz/QuizResultService.php',
        'QuizGenerationService' => __DIR__ . '/../Services/Quiz/QuizGenerationService.php',
        'QuizHistoryService' => __DIR__ . '/../Services/Quiz/QuizHistoryService.php',
        'QuizBlueprintService' => __DIR__ . '/../Services/Quiz/QuizBlueprintService.php',
        'QuestionSelectionService' => __DIR__ . '/../Services/Quiz/QuestionSelectionService.php',
        'QuestionBalancingService' => __DIR__ . '/../Services/Quiz/QuestionBalancingService.php',
        'AdaptiveQuizService' => __DIR__ . '/../Services/Quiz/AdaptiveQuizService.php',
        'ExamAssemblyService' => __DIR__ . '/../Services/Quiz/ExamAssemblyService.php',
        'BlueprintExecutor' => __DIR__ . '/../Services/Quiz/Blueprint/BlueprintExecutor.php',
        'BlueprintResolverService' => __DIR__ . '/../Services/Quiz/Blueprint/BlueprintResolverService.php',
        'BlueprintCoverageAnalyzer' => __DIR__ . '/../Services/Quiz/Blueprint/BlueprintCoverageAnalyzer.php',
        'BlueprintCoverageValidator' => __DIR__ . '/../Services/Quiz/Blueprint/BlueprintCoverageValidator.php',
        'BlueprintDifficultyAllocator' => __DIR__ . '/../Services/Quiz/Blueprint/BlueprintDifficultyAllocator.php',
        'BlueprintDistributionService' => __DIR__ . '/../Services/Quiz/Blueprint/BlueprintDistributionService.php',
        'BlueprintIntegrityValidator' => __DIR__ . '/../Services/Quiz/Blueprint/BlueprintIntegrityValidator.php',
        'SessionService' => __DIR__ . '/../Services/Shared/SessionService.php',
        'AttemptRepository' => __DIR__ . '/../Repositories/AttemptRepository.php',
    ];

    public static function register(): void
    {
        spl_autoload_register(
            function (string $class): void {

                foreach (self::$prefixes as $prefix => $baseDirectory) {

                    if (
                        strncmp(
                            $prefix,
                            $class,
                            strlen($prefix)
                        ) !== 0
                    ) {
                        continue;
                    }

                    $relativeClass = substr(
                        $class,
                        strlen($prefix)
                    );

                    $file =
                        rtrim($baseDirectory, '/')
                        . '/'
                        . str_replace('\\', '/', $relativeClass)
                        . '.php';

                    if (is_file($file)) {
                        require_once $file;
                    }

                    return;
                }

                // A small legacy layer is required while the remaining
                // pre-namespace services are migrated. It keeps old
                // controllers/services loadable without weakening PSR-4.
                if (isset(self::$legacyMap[$class])) {
                    $legacyFile = self::$legacyMap[$class];

                    if (is_file($legacyFile)) {
                        require_once $legacyFile;
                    }
                }
            }
        );
    }
}
