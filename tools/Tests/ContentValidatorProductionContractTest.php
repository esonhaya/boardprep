<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/Core/Autoloader.php';
App\Core\Autoloader::register();

use App\Services\Quality\Validators\ContentValidator;

$issues = ContentValidator::validate([
    'question' => '123456789012345',
    'explanation' => '12345678901234567890',
]);
assert($issues === []);

$issues = ContentValidator::validate([
    'question' => '',
    'explanation' => '',
]);
assert($issues[0]['severity'] === 'error');
assert($issues[0]['type'] === 'empty-question');
assert($issues[1]['severity'] === 'warning');
assert($issues[1]['type'] === 'missing-explanation');
echo "[PASS] Content validator production contract verified.\n";
