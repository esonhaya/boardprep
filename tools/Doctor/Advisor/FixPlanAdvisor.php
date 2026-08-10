<?php

declare(strict_types=1);

namespace Tools\Doctor\Advisor;

use Tools\Doctor\Diagnostics\DiagnosticFinding;
use Tools\Doctor\Diagnostics\FixPlan;
use Tools\Doctor\Diagnostics\FixPlanCollection;
use Tools\Doctor\DTO\DoctorResult;

final class FixPlanAdvisor
{
    public function primary(
        DoctorResult $report
    ): FixPlan {
        $finding =
            $report
                ->diagnostics()
                ->topFinding();

        if ($finding === null) {
            return new FixPlan([]);
        }

        return $this->forFinding($finding);
    }

    public function all(
        DoctorResult $report
    ): FixPlanCollection {
        $plans = new FixPlanCollection();

        foreach (
            $report->findings()->all()
            as $finding
        ) {
            $plans->add(
                $this->forFinding($finding)
            );
        }

        return $plans;
    }

    public function primaryForFinding(
        DiagnosticFinding $finding
    ): FixPlan {
        return $this->forFinding($finding);
    }

    private function forFinding(
        DiagnosticFinding $finding
    ): FixPlan {
        return new FixPlan(
            finding: $finding->toArray(),
            actions: $this->actions($finding),
        );
    }

    /**
     * @return string[]
     */
    private function actions(
        DiagnosticFinding $finding
    ): array {
        return match ($finding->id) {
            'method.large' => [
                'Extract cohesive private helper methods.',
                'Reduce branching inside the method.',
                'Keep each extracted method focused on one responsibility.',
            ],

            'service.large' => [
                'Extract cohesive responsibilities into collaborators.',
                'Keep orchestration separate from business logic.',
            ],

            'controller.large' => [
                'Move application logic into Services.',
                'Keep the Controller focused on HTTP concerns.',
            ],

            'architecture.layer_violation' => [
                'Replace direct persistence dependencies with Services.',
                'Keep Controllers independent of persistence details.',
            ],

            'dependency.circular' => [
                'Identify the dependency cycle.',
                'Introduce a narrower abstraction to break the cycle.',
            ],

            'dependency.coupled' => [
                'Review the most highly coupled component.',
                'Reduce unnecessary collaborators and consumers.',
            ],

            'class.dead' => [
                'Verify the class is genuinely unused.',
                'Remove it only after confirming no dynamic usage exists.',
            ],

            'import.unused' => [
                'Remove the unused import.',
            ],

            default => array_filter([
                $finding->recommendation,
            ]),
        };
    }
}
