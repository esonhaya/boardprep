<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/Core/Autoloader.php';
App\Core\Autoloader::register();

use App\Services\Quality\Validators\ContentValidator;

$question = ['question' => 'short', 'explanation' => ''];
$issues = ContentValidator::validate($question);
assert(count($issues) === 2);
assert($issues[0]['type'] === 'short-question');
assert($issues[1]['type'] === 'missing-explanation');
echo "[PASS] Content validator extraction integration verified.\n";
