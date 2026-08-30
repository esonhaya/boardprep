<?php

declare(strict_types=1);

require_once __DIR__
    . '/Doctor/Project/BoardPrep/Simulation/HttpSimulator.php';

require_once __DIR__
    . '/Tests/HttpTest.php';

exit((new \Tools\Tests\HttpTest())->run());
