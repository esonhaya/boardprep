<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/Core/Autoloader.php';
App\Core\Autoloader::register();

use App\Services\Quality\Validators\Content\ContentLength;

assert(ContentLength::lessThan('12345678901234', 15) === true);
assert(ContentLength::lessThan('123456789012345', 15) === false);
assert(ContentLength::lessThan('1234567890123456789', 20) === true);
assert(ContentLength::lessThan('12345678901234567890', 20) === false);
echo "[PASS] Content length boundary assertions verified.\n";
