<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/Core/Autoloader.php';

\App\Core\Autoloader::register();

if (in_array('--health', array_slice($argv ?? [], 1), true)) {
    $healthArguments = array_slice($argv ?? [], 1);
    $allowedHealthArguments = ['--health', '--json', '--persist'];
    $invalidHealthArguments = array_values(array_diff($healthArguments, $allowedHealthArguments));
    if ($invalidHealthArguments !== []) {
        fwrite(STDERR, 'Unknown Repository Health option: ' . $invalidHealthArguments[0] . PHP_EOL);
        exit(2);
    }

    putenv('APP_ENV=testing');
    putenv('APP_DEBUG=false');
    \App\Core\App::boot();
    $healthReport = \App\Services\RepositoryHealth\Engine\RepositoryHealthEngine::analyze();
    $healthArray = $healthReport->toArray();
    if (in_array('--persist', $healthArguments, true)) {
        $healthPath = dirname(__DIR__) . '/storage/repository-health.json';
        $healthJson = json_encode($healthArray, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $temporary = $healthPath . '.tmp';
        file_put_contents($temporary, $healthJson . PHP_EOL, LOCK_EX);
        rename($temporary, $healthPath);
    }
    if (in_array('--json', $healthArguments, true)) {
        echo json_encode($healthArray, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), PHP_EOL;
    } else {
        printf(
            "REPOSITORY_HEALTH_STATUS=%s PASS=%d WARN=%d FAIL=%d REVIEW=%d ISSUES=%d\n",
            $healthReport->overallStatus,
            $healthReport->summary['pass_count'] ?? 0,
            $healthReport->summary['warn_count'] ?? 0,
            $healthReport->summary['fail_count'] ?? 0,
            $healthReport->summary['review_count'] ?? 0,
            count($healthReport->issues)
        );
        foreach ($healthReport->checks as $check) {
            printf("CHECK=%s STATUS=%s ISSUES=%d\n", $check['check_code'], $check['status'], $check['issue_count']);
        }
        foreach (array_slice($healthReport->actionQueue, 0, 20) as $issue) {
            printf("ACTION=%s STATUS=%s ENTITY=%s MESSAGE=%s\n", $issue['code'], $issue['status'], (string) ($issue['entity_id'] ?? ''), $issue['message']);
        }
    }
    exit($healthReport->overallStatus === 'FAIL' ? 1 : 0);
}

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
