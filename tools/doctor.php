<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/Core/Autoloader.php';

\App\Core\Autoloader::register();

use Tools\Doctor\Commands\BaselineCommand;
use Tools\Doctor\Engine\DoctorRunner;
use Tools\Doctor\Output\V2ConsoleWriter;
use Tools\Doctor\Project\BoardPrep\Simulation\SimulationCommand;

$arguments = array_slice($argv ?? [], 1);
$validArguments = ['--baseline', '--simulate'];
$invalidArguments = array_values(array_diff($arguments, $validArguments));

if ($invalidArguments !== []) {
    fwrite(
        STDERR,
        'Unknown Doctor option: ' . $invalidArguments[0] . PHP_EOL
        . 'Usage: php tools/doctor.php [--baseline|--simulate]' . PHP_EOL
    );
    exit(2);
}

if (in_array('--baseline', $arguments, true)) {
    if (count($arguments) !== 1) {
        fwrite(STDERR, "--baseline cannot be combined with other options.\n");
        exit(2);
    }

    (new BaselineCommand())->run();
    exit(0);
}

$simulate = in_array(
    '--simulate',
    $argv ?? [],
    true
);

$result = (new DoctorRunner())->run();
(new V2ConsoleWriter())->write($result);

if ($simulate) {
    $simulationExit = SimulationCommand::run();

    exit($result->failCount() > 0 ? 1 : $simulationExit);
}

exit($result->failCount() > 0 ? 1 : 0);
