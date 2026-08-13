<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../../app/Core/Autoloader.php';

\App\Core\Autoloader::register();

require_once __DIR__ . '/../Doctor/Snapshot/ProjectSnapshot.php';
require_once __DIR__ . '/../Doctor/Rules/Rules.php';
require_once __DIR__ . '/../Doctor/Metrics/MethodMetricsAnalyzer.php';
require_once __DIR__ . '/../Doctor/Contracts/CheckInterface.php';
require_once __DIR__ . '/../Doctor/Context/DoctorContext.php';
require_once __DIR__ . '/../Doctor/DTO/CheckResult.php';
require_once __DIR__ . '/../Doctor/Diagnostics/DiagnosticFinding.php';
require_once __DIR__ . '/../Doctor/Diagnostics/DiagnosticFindingFactory.php';
require_once __DIR__ . '/../Doctor/Project/BoardPrep/Checks/LargestMethodCheck.php';
require_once __DIR__ . '/../Doctor/Project/BoardPrep/Checks/LargestServiceCheck.php';

require_once __DIR__ . '/MethodMetricsAnalyzerTest.php';
require_once __DIR__ . '/LargestMethodCheckTest.php';
require_once __DIR__ . '/LargestServiceCheckTest.php';

$tests = [
    new \Tools\Tests\MethodMetricsAnalyzerTest(),
    new \Tools\Tests\LargestMethodCheckTest(),
    new \Tools\Tests\LargestServiceCheckTest(),
];

$failed = 0;
$assertions = 0;

foreach ($tests as $test) {
    $name = get_class($test);

    try {
        $test->run();
        $count = $test->assertions();
        $assertions += $count;

        printf(
            "[PASS] %s (%d assertions)\n",
            $name,
            $count
        );
    } catch (\Throwable $exception) {
        $failed++;

        printf(
            "[FAIL] %s\n  %s: %s\n",
            $name,
            get_class($exception),
            $exception->getMessage()
        );
    }
}

printf(
    "\nRESULT: %d test classes, %d failed, %d assertions\n",
    count($tests),
    $failed,
    $assertions
);

exit($failed === 0 ? 0 : 1);
