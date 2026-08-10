<?php

declare(strict_types=1);

namespace Tools\Doctor\Advisor;

use Tools\Doctor\Diagnostics\DiagnosticFinding;
use Tools\Doctor\Diagnostics\PriorityAction;
use Tools\Doctor\DTO\DoctorResult;

final class ActionPriorityAdvisor
{
    /**
     * @return PriorityAction[]
     */
    public function prioritize(
        DoctorResult $report
    ): array {
        $actions = [];

        foreach (
            $report->findings()->all()
            as $finding
        ) {
            $actions[] =
                $this->fromFinding($finding);
        }

        usort(
            $actions,
            static function (
                PriorityAction $a,
                PriorityAction $b
            ): int {
                return $b->score() <=> $a->score();
            }
        );

        return $actions;
    }

    private function fromFinding(
        DiagnosticFinding $finding
    ): PriorityAction {
        return new PriorityAction(
            findingId: $finding->id,
            title: $finding->title,
            priority: $finding->priorityLabel(),
            impact: $finding->impact(),
            effort: $finding->effort(),
            actions:
                (new FixPlanAdvisor())
                    ->primaryForFinding($finding)
                    ->actions,
        );
    }
}
