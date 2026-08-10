#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../app/Core/Autoloader.php';

\App\Core\Autoloader::register();

$test = new \Tools\Tests\Simulation\SimulationKernelTest();

$test->run();
