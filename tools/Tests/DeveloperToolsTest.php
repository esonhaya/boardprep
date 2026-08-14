<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';

use App\Core\Autoloader;
use App\Builders\Developer\DeveloperPageBuilder;
use App\Builders\Developer\EntityCardBuilder;
use App\Services\Developer\DeveloperViewService;
use App\ViewModels\Developer\ActionBarViewModel;
use App\ViewModels\Developer\EntityCardViewModel;
use App\ViewModels\Developer\PageHeaderViewModel;
use App\ViewModels\Developer\SummaryViewModel;

Autoloader::register();

echo "======================================\n";
echo " BoardPrep Developer Tools Test\n";
echo "======================================\n";
echo "Mode: In-process simulation\n";
echo "Database: NOT USED\n";
echo "HTTP server: NOT USED\n\n";

$pass = 0;
$fail = 0;

function check(bool $condition, string $message): void
{
    global $pass, $fail;

    if ($condition) {
        $pass++;
        echo "[PASS] {$message}\n";
        return;
    }

    $fail++;
    echo "[FAIL] {$message}\n";
}

echo "[TEST] Developer controller surface\n";

$controllers = [
    'BaseDeveloperController',
    'DashboardController',
    'DeveloperToolsController',
    'DoctorDashboardController',
    'DoctorRunController',
    'DoctorApiController',
    'QuestionInspectorController',
    'QuestionQualityController',
    'MetadataRepairController',
    'QuestionImportController',
    'QuestionExportController',
    'BlueprintController',
    'BlueprintHealthController',
    'CoverageController',
    'TaxonomyController',
];

foreach ($controllers as $class) {
    $fqcn = "App\\Controllers\\{$class}";
    $exists = class_exists($fqcn);

    check($exists, "{$fqcn} class exists");

    if ($exists && $class !== 'BaseDeveloperController') {
        $reflection = new ReflectionClass($fqcn);
        $publicMethods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);

        check(
            count($publicMethods) > 0,
            "{$fqcn} exposes public runtime methods"
        );
    }
}

$baseDeveloperController = new ReflectionClass(
    App\Controllers\BaseDeveloperController::class
);

$baseMethods = [
    'renderDeveloper',
    'developerRedirect',
    'renderDeveloperErrors',
];

foreach ($baseMethods as $method) {
    check(
        $baseDeveloperController->hasMethod($method),
        "BaseDeveloperController::{$method} exists"
    );

    if ($baseDeveloperController->hasMethod($method)) {
        $reflectionMethod = $baseDeveloperController->getMethod($method);

        check(
            $reflectionMethod->isProtected()
                && $reflectionMethod->isStatic(),
            "BaseDeveloperController::{$method} is protected static"
        );
    }
}


echo "[TEST] Route-derived controller method contracts\n";

$routesSource = file_get_contents(
    __DIR__ . "/../../routes/web.php"
);

check(
    $routesSource !== false,
    "routes/web.php is readable"
);

if ($routesSource !== false) {
    preg_match_all(
        '/\[\s*([A-Za-z_][A-Za-z0-9_]*)::class\s*,\s*"([A-Za-z_][A-Za-z0-9_]*)"\s*\]/s',
        $routesSource,
        $routeMatches,
        PREG_SET_ORDER
    );

    check(
        !empty($routeMatches),
        "controller route targets discovered"
    );

    $routeTargets = [];

    foreach ($routeMatches as $match) {
        $class = $match[1];
        $method = $match[2];
        $routeTargets["{$class}::{$method}"] = [$class, $method];
    }

    check(
        count($routeTargets) > 0,
        "controller route targets discovered"
    );

    foreach ($routeTargets as [$class, $method]) {
        $fqcn = "App\\Controllers\\{$class}";

        check(
            class_exists($fqcn),
            "{$fqcn} exists for routed method {$method}"
        );

        if (!class_exists($fqcn)) {
            continue;
        }

        $reflection = new ReflectionClass($fqcn);

        check(
            $reflection->hasMethod($method),
            "{$fqcn}::{$method} exists"
        );

        if (!$reflection->hasMethod($method)) {
            continue;
        }

        $methodReflection = $reflection->getMethod($method);

        check(
            $methodReflection->isPublic(),
            "{$fqcn}::{$method} is public"
        );

        check(
            $methodReflection->isStatic(),
            "{$fqcn}::{$method} is static"
        );
    }
}

echo "[TEST] Developer view service factories\n";

$header = DeveloperViewService::pageHeader('Test Page', 'Test description');
$summary = DeveloperViewService::summary(['Questions' => 10]);
$actions = DeveloperViewService::actionBar([
    ['label' => 'Test', 'href' => '/developer'],
]);
$entity = DeveloperViewService::entity(
    ['id' => 'test'],
    ['Status' => 'OK'],
    [['label' => 'Open', 'href' => '/developer']]
);

check($header instanceof PageHeaderViewModel, 'pageHeader returns PageHeaderViewModel');
check($header->title === 'Test Page', 'pageHeader preserves title');
check($header->description === 'Test description', 'pageHeader preserves description');

check($summary instanceof SummaryViewModel, 'summary returns SummaryViewModel');
check($actions instanceof ActionBarViewModel, 'actionBar returns ActionBarViewModel');
check($entity instanceof EntityCardViewModel, 'entity returns EntityCardViewModel');

echo "[TEST] Developer page builder\n";

$page = DeveloperPageBuilder::make()
    ->title('Developer Test', 'Smoke test')
    ->summary(['Questions' => 10])
    ->actions([['label' => 'Open', 'href' => '/developer']])
    ->entities([['id' => 'q1']])
    ->build();

check(is_array($page), 'DeveloperPageBuilder::build returns array');
check(isset($page['pageHeader']), 'page builder contains pageHeader');
check(isset($page['summary']), 'page builder contains summary');
check(isset($page['actionBar']), 'page builder contains actionBar');
check(isset($page['entities']), 'page builder contains entities');

echo "[TEST] Entity card builder\n";

$card = EntityCardBuilder::make()
    ->entity(['id' => 'q1'])
    ->details(['Status' => 'approved'])
    ->actions([['label' => 'Open', 'href' => '/question-editor']])
    ->build();

check($card instanceof EntityCardViewModel, 'EntityCardBuilder returns EntityCardViewModel');

echo "[TEST] Developer view files\n";

$views = [
    'app/Views/developer/index.php',
    'app/Views/developer/dashboard.php',
    'app/Views/developer/workspace/index.php',
    'app/Views/developer/question/workspace.php',
    'app/Views/developer/question/editor.php',
    'app/Views/developer/question-inspector.php',
    'app/Views/developer/question-quality.php',
    'app/Views/developer/metadata-repair.php',
    'app/Views/developer/taxonomy.php',
    'app/Views/developer/coverage.php',
    'app/Views/developer/blueprints.php',
    'app/Views/developer/blueprint-health.php',
    'app/Views/developer/doctor/index.php',
];

foreach ($views as $view) {
    check(is_file(__DIR__ . "/../../{$view}"), "{$view} exists");
}

echo "\n======================================\n";
echo " SUMMARY\n";
echo "======================================\n";
echo "PASS       : {$pass}\n";
echo "FAIL       : {$fail}\n";
echo "ASSERTIONS : " . ($pass + $fail) . "\n";

if ($fail > 0) {
    echo "\n[FAIL] Developer tools simulation failed.\n";
    exit(1);
}

echo "\n[PASS] Developer tools simulation passed.\n";
