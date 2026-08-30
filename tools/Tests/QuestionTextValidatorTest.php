<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/Core/Autoloader.php';
App\Core\Autoloader::register();

use App\Services\Quality\Validators\Content\QuestionTextValidator;

assert(QuestionTextValidator::validate('') [0]['type'] === 'empty-question');
assert(QuestionTextValidator::validate('12345678901234')[0]['type'] === 'short-question');
assert(QuestionTextValidator::validate('123456789012345') === []);
echo "[PASS] Question text validator assertions verified.\n";
