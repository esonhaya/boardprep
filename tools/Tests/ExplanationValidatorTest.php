<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/Core/Autoloader.php';
App\Core\Autoloader::register();

use App\Services\Quality\Validators\Content\ExplanationValidator;

assert(ExplanationValidator::validate('')[0]['type'] === 'missing-explanation');
assert(ExplanationValidator::validate('1234567890123456789')[0]['type'] === 'short-explanation');
assert(ExplanationValidator::validate('12345678901234567890') === []);
echo "[PASS] Explanation validator assertions verified.\n";
