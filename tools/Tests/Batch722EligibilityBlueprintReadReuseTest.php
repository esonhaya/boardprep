<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();
require_once dirname(__DIR__, 2) . '/bootstrap/app.php';
require_once dirname(__DIR__) . '/Benchmarks/Support/CountingReadOnlyStorage.php';

use App\Core\App;
use App\Services\Question\QuestionEligibilityService;

$database = App::database();
$storageProperty = new ReflectionProperty($database, 'storage');
$storage = new CountingReadOnlyStorage($storageProperty->getValue($database));
$storageProperty->setValue($database, $storage);
$questions = App::storage()->all('questions');
if (count($questions) !== 9123) {
    throw new RuntimeException('The eligibility index reuse fixture requires all 9,123 canonical questions.');
}

$storage->allReads = [];
$index = QuestionEligibilityService::index($questions);
if (($storage->allReads['blueprints'] ?? 0) !== 1) {
    throw new RuntimeException('One eligibility index build must reuse a single blueprint snapshot.');
}

echo "[PASS] Cold eligibility index construction reuses one blueprint collection read across derived relationship validation.\n";
echo "[PASS] Benchmark storage wrapper blocked every attempted mutation.\n";
