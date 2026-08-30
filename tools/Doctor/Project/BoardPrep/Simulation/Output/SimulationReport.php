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
            'BOARDPREP DEVELOPER SIMULATION',
            '',
            'Personas: 6',
            'Journeys: 6',
            'Quiz attempts: 16',
            'Exam attempts: 1',
            '',
        ];

        foreach ($results as $item) {
            $result = $item['result'];
            $status = $result->passed() ? 'PASS' : 'FAIL';

            $lines[] = sprintf('%-27s %s', $item['scenario'], $status);

            foreach ($result->steps() as $step) {
                if (in_array($step['description'], [
                    'NEW_LEARNER', 'STRUGGLING_LEARNER', 'IMPROVING_LEARNER',
                    'STRONG_LEARNER', 'MIXED_LEARNER', 'EXAM_READY_LEARNER',
                    'Persistence', 'Weakness analytics', 'Progress analytics',
                    'Recommendations', 'Quiz generation', 'Exam simulation',
                    'Failure recovery',
                ], true)) {
                    $lines[] = sprintf(
                        '  %-25s %s',
                        $step['description'],
                        $step['passed'] ? 'PASS' : 'FAIL'
                    );
                    continue;
                }
                if (!$step['passed']) {
                    $lines[] = "  FAIL: {$step['description']}";
                }
            }

            foreach ($result->failures() as $failure) {
                $lines[] = "  ERROR: {$failure}";
            }
        }

        $lines[] = '';
        $lines[] = "SIMULATION_PASS={$summary['passed']}";
        $lines[] = "SIMULATION_FAIL={$summary['failed']}";

        return implode(PHP_EOL, $lines);
    }
}
