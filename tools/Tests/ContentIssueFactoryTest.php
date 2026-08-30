<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/Core/Autoloader.php';
App\Core\Autoloader::register();

use App\Services\Quality\Validators\Content\ContentIssueFactory;

$issue = ContentIssueFactory::create('warning', 'short-question', 'Question text is unusually short.');
assert($issue['severity'] === 'warning');
assert($issue['type'] === 'short-question');
assert($issue['message'] === 'Question text is unusually short.');
echo "[PASS] Content issue factory assertions verified.\n";
