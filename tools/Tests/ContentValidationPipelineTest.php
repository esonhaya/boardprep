<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/Core/Autoloader.php';
App\Core\Autoloader::register();

use App\Services\Quality\Validators\Content\ContentValidationPipeline;

$issues = ContentValidationPipeline::validate([
    'question' => 'short',
    'explanation' => '',
]);

assert(count($issues) === 2);
assert($issues[0]['type'] === 'short-question');
assert($issues[1]['type'] === 'missing-explanation');
echo "[PASS] Content validation pipeline assertions verified.\n";
