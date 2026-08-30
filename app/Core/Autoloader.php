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
        'BaseSpecificationFactory' => __DIR__ . '/../Services/Quiz/Specification/BaseSpecificationFactory.php',
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
        'QuestionValidationService' => __DIR__ . '/../Services/Shared/QuestionValidationService.php',
        'QuizScoringService' => __DIR__ . '/../Services/Quiz/QuizScoringService.php',
        'AnswerNormalizer' => __DIR__ . '/../Services/Quiz/Scoring/AnswerNormalizer.php',
        'QuestionScoreEvaluator' => __DIR__ . '/../Services/Quiz/Scoring/QuestionScoreEvaluator.php',
        'ScoreAccumulator' => __DIR__ . '/../Services/Quiz/Scoring/ScoreAccumulator.php',
        'ResultRecordFactory' => __DIR__ . '/../Services/Quiz/Scoring/ResultRecordFactory.php',
        'QuizStartService' => __DIR__ . '/../Services/Quiz/QuizStartService.php',
        'QuizSubmissionService' => __DIR__ . '/../Services/Quiz/QuizSubmissionService.php',
        'QuizNavigationService' => __DIR__ . '/../Services/Quiz/QuizNavigationService.php',
        'QuizResultService' => __DIR__ . '/../Services/Quiz/QuizResultService.php',
        'QuizResultSessionReader' => __DIR__ . '/../Services/Quiz/Result/QuizResultSessionReader.php',
        'QuizResultAttemptFactory' => __DIR__ . '/../Services/Quiz/Result/QuizResultAttemptFactory.php',
        'QuizResultPersistenceGuard' => __DIR__ . '/../Services/Quiz/Result/QuizResultPersistenceGuard.php',
        'QuizResultPersistenceService' => __DIR__ . '/../Services/Quiz/Result/QuizResultPersistenceService.php',
        'QuizResultResponseFactory' => __DIR__ . '/../Services/Quiz/Result/QuizResultResponseFactory.php',
        'QuizGenerationService' => __DIR__ . '/../Services/Quiz/QuizGenerationService.php',
        'QuizHistoryService' => __DIR__ . '/../Services/Quiz/QuizHistoryService.php',
        'QuizBlueprintService' => __DIR__ . '/../Services/Quiz/QuizBlueprintService.php',
        'QuestionSelectionService' => __DIR__ . '/../Services/Quiz/QuestionSelectionService.php',
        'QuestionBalancingService' => __DIR__ . '/../Services/Quiz/QuestionBalancingService.php',
        'QuestionBalanceDifficultyResolver' => __DIR__ . '/../Services/Quiz/Balance/QuestionBalanceDifficultyResolver.php',
        'QuestionBalanceDifficultyFilter' => __DIR__ . '/../Services/Quiz/Balance/QuestionBalanceDifficultyFilter.php',
        'QuestionBalanceTopicResolver' => __DIR__ . '/../Services/Quiz/Balance/QuestionBalanceTopicResolver.php',
        'QuestionBalanceGrouper' => __DIR__ . '/../Services/Quiz/Balance/QuestionBalanceGrouper.php',
        'QuestionBalanceShuffler' => __DIR__ . '/../Services/Quiz/Balance/QuestionBalanceShuffler.php',
        'QuestionBalanceRoundRobin' => __DIR__ . '/../Services/Quiz/Balance/QuestionBalanceRoundRobin.php',
        'AdaptiveQuizService' => __DIR__ . '/../Services/Quiz/AdaptiveQuizService.php',
        'ExamAssemblyService' => __DIR__ . '/../Services/Quiz/ExamAssemblyService.php',
        'BlueprintExecutor' => __DIR__ . '/../Services/Quiz/Blueprint/BlueprintExecutor.php',
        'BlueprintRequestPlanBuilder' => __DIR__ . '/../Services/Quiz/Blueprint/BlueprintRequestPlanBuilder.php',
        'BlueprintRequestExecutor' => __DIR__ . '/../Services/Quiz/Blueprint/BlueprintRequestExecutor.php',
        'BlueprintCoverageFinalizer' => __DIR__ . '/../Services/Quiz/Blueprint/BlueprintCoverageFinalizer.php',
        'BlueprintExecutionResultFactory' => __DIR__ . '/../Services/Quiz/Blueprint/BlueprintExecutionResultFactory.php',
        'BlueprintResolverService' => __DIR__ . '/../Services/Quiz/Blueprint/BlueprintResolverService.php',
        'RequestExecutionPlan' => __DIR__ . '/../Services/Quiz/Blueprint/RequestExecutionPlan.php',
        'RequestExecutionPlanService' => __DIR__ . '/../Services/Quiz/Blueprint/RequestExecutionPlanService.php',
        'RequestPriorityService' => __DIR__ . '/../Services/Quiz/Blueprint/RequestPriorityService.php',
        'BlueprintAllocationReconciler' => __DIR__ . '/../Services/Quiz/Blueprint/BlueprintAllocationReconciler.php',
        'BlueprintCoverageAnalyzer' => __DIR__ . '/../Services/Quiz/Blueprint/BlueprintCoverageAnalyzer.php',
        'BlueprintCoverageValidator' => __DIR__ . '/../Services/Quiz/Blueprint/BlueprintCoverageValidator.php',
        'BlueprintDifficultyAllocator' => __DIR__ . '/../Services/Quiz/Blueprint/BlueprintDifficultyAllocator.php',
        'BlueprintDistributionService' => __DIR__ . '/../Services/Quiz/Blueprint/BlueprintDistributionService.php',
        'BlueprintAllocationReconciler' => __DIR__ . '/../Services/Quiz/Blueprint/BlueprintAllocationReconciler.php',
        'BlueprintAllocationTargetGuard' => __DIR__ . '/../Services/Quiz/Blueprint/BlueprintAllocationTargetGuard.php',
        'BlueprintAllocationTotalCalculator' => __DIR__ . '/../Services/Quiz/Blueprint/BlueprintAllocationTotalCalculator.php',
        'BlueprintAllocationDeltaCalculator' => __DIR__ . '/../Services/Quiz/Blueprint/BlueprintAllocationDeltaCalculator.php',
        'BlueprintAllocationRequestFactory' => __DIR__ . '/../Services/Quiz/Blueprint/BlueprintAllocationRequestFactory.php',
        'BlueprintAllocationIncrementer' => __DIR__ . '/../Services/Quiz/Blueprint/BlueprintAllocationIncrementer.php',
        'BlueprintAllocationDecrementer' => __DIR__ . '/../Services/Quiz/Blueprint/BlueprintAllocationDecrementer.php',
        'BlueprintDistributionRequestNormalizer' => __DIR__ . '/../Services/Quiz/Blueprint/BlueprintDistributionRequestNormalizer.php',
        'BlueprintDistributionAllocator' => __DIR__ . '/../Services/Quiz/Blueprint/BlueprintDistributionAllocator.php',
        'BlueprintDistributionResultFactory' => __DIR__ . '/../Services/Quiz/Blueprint/BlueprintDistributionResultFactory.php',
        'BlueprintDistributionDiagnostics' => __DIR__ . '/../Services/Quiz/Blueprint/BlueprintDistributionDiagnostics.php',
        'BlueprintDistributionGuard' => __DIR__ . '/../Services/Quiz/Blueprint/BlueprintDistributionGuard.php',

        'BlueprintIntegrityValidator' => __DIR__ . '/../Services/Quiz/Blueprint/BlueprintIntegrityValidator.php',
        'BlueprintExecutionResult' => __DIR__ . '/../Services/Quiz/Blueprint/BlueprintExecutionResult.php',
        'SessionService' => __DIR__ . '/../Services/Shared/SessionService.php',
        'RuntimeAllocationService' => __DIR__ . '/../Services/Quiz/Allocation/RuntimeAllocationService.php',
        'SelectionRequest' => __DIR__ . '/../Services/Quiz/Selection/SelectionRequest.php',
        'RecoveryScope' => __DIR__ . '/../Services/Quiz/Recovery/RecoveryScope.php',
        'SelectionSession' => __DIR__ . '/../Services/Quiz/Selection/SelectionSession.php',
        'SelectionDeduplicator' => __DIR__ . '/../Services/Quiz/Selection/SelectionDeduplicator.php',
        'SelectionResult' => __DIR__ . '/../Services/Quiz/Selection/SelectionResult.php',
        'SelectionPool' => __DIR__ . '/../Services/Quiz/Selection/SelectionPool.php',
        'QuestionPoolFilter' => __DIR__ . '/../Services/Quiz/Selection/QuestionPoolFilter.php',
        'SelectionPipeline' => __DIR__ . '/../Services/Quiz/Selection/SelectionPipeline.php',
        'SelectionDiagnostics' => __DIR__ . '/../Services/Quiz/Selection/SelectionDiagnostics.php',
        'SelectionFulfillmentFactory' => __DIR__ . '/../Services/Quiz/Selection/SelectionFulfillmentFactory.php',
        'WeightedShuffleService' => __DIR__ . '/../Services/Quiz/Selection/WeightedShuffleService.php',
        'DifficultyDistributionNormalizer' => __DIR__ . '/../Services/Quiz/Selection/DifficultyDistributionNormalizer.php',
        'DifficultyQuotaAllocator' => __DIR__ . '/../Services/Quiz/Selection/DifficultyQuotaAllocator.php',
        'DifficultyBucketSelector' => __DIR__ . '/../Services/Quiz/Selection/DifficultyBucketSelector.php',
        'SelectionFallbackService' => __DIR__ . '/../Services/Quiz/Selection/SelectionFallbackService.php',
        'DifficultySelectionService' => __DIR__ . '/../Services/Quiz/Selection/DifficultySelectionService.php',
        'ShortageRecoveryService' => __DIR__ . '/../Services/Quiz/ShortageRecoveryService.php',
        'BlueprintQuotaValidator' => __DIR__ . '/../Services/Quiz/Validation/BlueprintQuotaValidator.php',
        'RecoveryCandidateService' => __DIR__ . '/../Services/Quiz/Recovery/RecoveryCandidateService.php',
        'RecoveryQuestionContext' => __DIR__ . '/../Services/Quiz/Recovery/RecoveryQuestionContext.php',
        'RecoveryQuestionContextFactory' => __DIR__ . '/../Services/Quiz/Recovery/RecoveryQuestionContextFactory.php',
        'RecoveryStatusPolicy' => __DIR__ . '/../Services/Quiz/Recovery/RecoveryStatusPolicy.php',
        'RecoveryScopeMatcher' => __DIR__ . '/../Services/Quiz/Recovery/RecoveryScopeMatcher.php',
        'RecoveryScopePlan' => __DIR__ . '/../Services/Quiz/Recovery/RecoveryScopePlan.php',
        'RecoveryCandidateFilter' => __DIR__ . '/../Services/Quiz/Recovery/RecoveryCandidateFilter.php',
        'SubjectAllocationService' => __DIR__ . '/../Services/Quiz/Board/SubjectAllocationService.php',
        'CoverageTracker' => __DIR__ . '/../Services/Quiz/Coverage/CoverageTracker.php',
        'SubjectAssemblyService' => __DIR__ . '/../Services/Quiz/Subject/SubjectAssemblyService.php',

        'QuizSpecification' => __DIR__ . '/../DTO/QuizSpecification.php',
        'AttemptRepository' => __DIR__ . '/../Repositories/AttemptRepository.php',
    ];

    public static function register(): void
    {
        /*
         * Register the host autoloader first so BoardPrep-specific
         * Doctor classes override the generic Haya implementations.
         * Haya is registered afterwards as a fallback for classes
         * the host does not provide.
         */
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

        $hayaDoctorRoot =
            dirname(__DIR__, 2)
            . '/tools/HayaDoctor/tools/Doctor/';

        spl_autoload_register(
            static function (string $class) use ($hayaDoctorRoot): void {
                $prefix = 'Tools\\Doctor\\';

                if (!str_starts_with($class, $prefix)) {
                    return;
                }

                $relative =
                    substr($class, strlen($prefix));

                $file =
                    $hayaDoctorRoot
                    . str_replace('\\', '/', $relative)
                    . '.php';

                if (is_file($file)) {
                    require_once $file;
                }
            }
        );
    }
}
