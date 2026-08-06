<?php

declare(strict_types=1);

namespace Tools\Doctor\Commands;

use Tools\Doctor\Baseline\BaselineManager;
use Tools\Doctor\Context\DoctorContext;
use Tools\Doctor\Engine\DoctorRunner;

final class BaselineCommand
{
    public function run(): void
    {
        $runner = new DoctorRunner();

        $result = $runner->run();

        $manager = new BaselineManager();

        $manager->capture(
            $result,
            DoctorContext::snapshot()
        );

        echo PHP_EOL;
        echo "✓ Baseline saved." . PHP_EOL;
    }
}
