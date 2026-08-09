<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/Core/Autoloader.php';

\App\Core\Autoloader::register();

use Tools\Doctor\Commands\BaselineCommand;
use Tools\Doctor\Engine\DoctorRunner;
use Tools\Doctor\Output\ConsoleRenderer;
use Tools\Doctor\Simulation\SimulationCommand;

if (($argv[1] ?? '') === '--baseline') {
    (new BaselineCommand())->run();
    exit(0);
}

$simulate = in_array(
    '--simulate',
    $argv ?? [],
    true
);

$renderer = new ConsoleRenderer();

$renderer->render(
    (new DoctorRunner())->run()
);

if ($simulate) {
    exit(
        SimulationCommand::run()
    );
}
