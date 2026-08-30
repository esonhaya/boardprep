<?php

declare(strict_types=1);

namespace Tools\Doctor\Project\BoardPrep\Simulation\Output;

final class SimulationReport
{
    /**
     * @param array<int, array{scenario:string,result:\Tools\Doctor\Project\BoardPrep\Simulation\SimulationResult}> $results
     * @param array{scenarios:int,passed:int,failed:int,steps:int,failedSteps:int,success:bool} $summary
     */
    public static function render(
        array $results,
        array $summary
    ): string {
        $lines = [
            '',
            '============================================================',
            'BOARDPREP DEVELOPER SIMULATION',
            '============================================================',
            '',
            'Scenario Matrix',
            '',
        ];

        foreach ($results as $item) {
            $result = $item['result'];
            $status = $result->passed() ? 'PASS' : 'FAIL';

            $lines[] = sprintf('%-27s %s', $item['scenario'], $status);
        }

        $personaLines = [];
        foreach ($results as $item) {
            foreach ($item['result']->steps() as $step) {
                if (preg_match('/^[A-Z]+(?:_[A-Z]+)*_LEARNER$/', $step['description']) === 1) {
                    $personaLines[] = sprintf(
                        '%-27s %s',
                        $step['description'],
                        $step['passed'] ? 'PASS' : 'FAIL'
                    );
                }
            }
        }

        if ($personaLines !== []) {
            $lines[] = '';
            $lines[] = 'Learner Personas';
            $lines[] = '';
            array_push($lines, ...$personaLines);
        }

        foreach ($results as $item) {
            $result = $item['result'];
            if ($result->passed()) {
                continue;
            }
            $lines[] = '';
            $lines[] = "FAILURE: {$item['scenario']}";
            foreach ($result->failures() as $failure) {
                $lines[] = "  REASON: {$failure}";
            }
            if ($result->failures() === []) {
                foreach ($result->steps() as $step) {
                    if (!$step['passed']) {
                        $lines[] = "  REASON: {$step['description']} failed";
                    }
                }
            }
        }

        $lines[] = '';
        $lines[] = 'Summary';
        $lines[] = "SCENARIOS={$summary['scenarios']}";
        $lines[] = "PASS={$summary['passed']}";
        $lines[] = "FAIL={$summary['failed']}";
        $lines[] = '';
        $lines[] = 'SIMULATION_STATUS=' . ($summary['success'] ? 'PASS' : 'FAIL');

        return implode(PHP_EOL, $lines);
    }
}
