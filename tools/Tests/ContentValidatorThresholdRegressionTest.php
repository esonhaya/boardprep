<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/Core/Autoloader.php';
App\Core\Autoloader::register();

use App\Services\Quality\Validators\ContentValidator;

// Regression: with mbstring enabled, non-empty text must not become short merely
// because mb_strlen() returns a truthy integer. Threshold comparison is explicit.
assert(ContentValidator::validate([
    'question' => str_repeat('Q', 15),
    'explanation' => str_repeat('E', 20),
]) === []);

echo "[PASS] Content validator threshold regression verified.\n";
