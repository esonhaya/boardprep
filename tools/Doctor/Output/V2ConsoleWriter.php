<?php

declare(strict_types=1);

namespace Tools\Doctor\Output;

use Tools\Doctor\DTO\DoctorResult;

final class V2ConsoleWriter
{
    public function write(
        DoctorResult $result
    ): void {
        $diagnostics = $result->diagnostics();

        echo PHP_EOL;
        echo "=== BOARDPREP DOCTOR V2 ===" . PHP_EOL;
        echo "Checks: " . count($result->checks) . PHP_EOL;
        echo "Findings: " . $diagnostics->count() . PHP_EOL;
        echo "Warnings: " . $result->warningCount() . PHP_EOL;
        echo "Errors: " . $diagnostics->errorCount() . PHP_EOL;
        echo "Critical: " . $diagnostics->criticalCount() . PHP_EOL;
        echo "Health: " . $result->health() . PHP_EOL;

        $top = $diagnostics->topFinding();

        if ($top === null) {
            echo "Primary: none" . PHP_EOL;
            echo "Remediation: none" . PHP_EOL;

            return;
        }

        echo "Primary: " . $top->id . PHP_EOL;
        echo "Priority: " . $top->priorityLabel() . PHP_EOL;
        echo "Impact: " . $top->impact() . PHP_EOL;
        echo "Effort: " . $top->effort() . PHP_EOL;

        $remediation =
            $this->remediation($result);

        echo "Remediation actions: "
            . $remediation['count']
            . PHP_EOL;

        foreach (
            $remediation['actions']
            as $index => $action
        ) {
            echo sprintf(
                "  %d. %s",
                $index + 1,
                $action
            ) . PHP_EOL;
        }
    }

    /**
     * @return array{count:int,actions:string[]}
     */
    private function remediation(
        DoctorResult $result
    ): array {
        $actions = [];

        foreach (
            $result->findings()->all()
            as $finding
        ) {
            if ($finding->recommendation !== null) {
                $actions[] =
                    $finding->recommendation;
            }
        }

        return [
            'count' => count($actions),
            'actions' => array_values(
                array_unique($actions)
            ),
        ];
    }
}
