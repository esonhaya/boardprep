<?php

declare(strict_types=1);

namespace Tools\Doctor\Output;

use Tools\Doctor\DTO\DoctorResult;

final class V2ConsoleWriter
{
    public function write(
        DoctorResult $result
    ): void {
        $projectDiagnostics =
            $result->diagnostics();

        $doctorDiagnostics =
            new \Tools\Doctor\Diagnostics\DiagnosticSummary(
                $result->findings('DOCTOR')->all()
            );

        echo PHP_EOL;
        echo "=== BOARDPREP DOCTOR V2 ===" . PHP_EOL;
        echo "Checks: " . count($result->checks) . PHP_EOL;
        echo "Project Checks: "
            . count(
                array_filter(
                    $result->checks,
                    static fn($check) =>
                        $check->scope === 'PROJECT'
                )
            )
            . PHP_EOL;
        echo "Doctor Checks: "
            . count(
                array_filter(
                    $result->checks,
                    static fn($check) =>
                        $check->scope === 'DOCTOR'
                )
            )
            . PHP_EOL;
        echo "Project Findings: "
            . $projectDiagnostics->count()
            . PHP_EOL;
        echo "Doctor Findings: "
            . $doctorDiagnostics->count()
            . PHP_EOL;
        echo "Project Warnings: "
            . $result->warningCount('PROJECT')
            . PHP_EOL;
        echo "Doctor Warnings: "
            . $result->warningCount('DOCTOR')
            . PHP_EOL;
        echo "Project Health: "
            . $result->health()
            . PHP_EOL;
        echo "Doctor Health: "
            . $result->doctorHealth()
            . PHP_EOL;

        foreach ($result->checks as $check) {
            if ($check->title !== 'UI Contract Engine') {
                continue;
            }
            echo "UI_HEALTH: " . $check->score . PHP_EOL;
            echo "UI_FINDINGS: " . $check->findingCount() . PHP_EOL;
            foreach ($check->findings->all() as $finding) {
                echo "  [{$finding->id}] "
                    . ($finding->file ?? 'unknown') . ': '
                    . $finding->message . PHP_EOL;
            }
            break;
        }

        $top = $projectDiagnostics->topFinding();

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
