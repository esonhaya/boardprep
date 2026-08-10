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
            '======================================',
            ' BoardPrep Application Simulation',
            '======================================',
        ];

        foreach ($results as $item) {
            $result = $item['result'];
            $status = $result->passed() ? 'PASS' : 'FAIL';

            $lines[] = "[{$status}] {$item['scenario']}";

            foreach ($result->steps() as $step) {
                $stepStatus = $step['passed'] ? 'PASS' : 'FAIL';

                $lines[] =
                    "  [{$stepStatus}] {$step['description']}";
            }

            foreach ($result->failures() as $failure) {
                $lines[] = "  ERROR: {$failure}";
            }
        }

        $lines[] = '';
        $lines[] = '======================================';
        $lines[] = "SCENARIOS: {$summary['scenarios']}";
        $lines[] = "PASS: {$summary['passed']}";
        $lines[] = "FAIL: {$summary['failed']}";
        $lines[] = "STEPS: {$summary['steps']}";
        $lines[] = "FAILED STEPS: {$summary['failedSteps']}";
        $lines[] = '======================================';

        return implode(PHP_EOL, $lines);
    }
}
