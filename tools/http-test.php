<?php

declare(strict_types=1);

require_once __DIR__
    . '/Doctor/Simulation/HttpSimulator.php';

require_once __DIR__
    . '/Tests/HttpTest.php';

(new \Tools\Tests\HttpTest())->run();
