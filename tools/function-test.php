#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/Core/Autoloader.php';

\App\Core\Autoloader::register();

exit(
    (new \Tools\Tests\FunctionTest())->run()
);
