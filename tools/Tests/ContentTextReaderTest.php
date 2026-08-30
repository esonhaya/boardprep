<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/Core/Autoloader.php';
App\Core\Autoloader::register();

use App\Services\Quality\Validators\Content\ContentTextReader;

assert(ContentTextReader::read(['question' => '  hello  '], 'question') === 'hello');
assert(ContentTextReader::read([], 'question') === '');
assert(ContentTextReader::read(['question' => null], 'question') === '');
echo "[PASS] Content text reader assertions verified.\n";
