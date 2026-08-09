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
