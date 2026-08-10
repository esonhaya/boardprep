<?php

declare(strict_types=1);

namespace Tools\Doctor\Output;

use Tools\Doctor\Advisor\ActionPriorityAdvisor;
use Tools\Doctor\Advisor\DiagnosisAdvisor;
use Tools\Doctor\Advisor\FixPlanAdvisor;
use Tools\Doctor\Advisor\RemediationAdvisor;
use Tools\Doctor\DTO\DoctorResult;

final class V2ReportBuilder
{
    /**
     * @return array<string,mixed>
     */
    public function build(
        DoctorResult $result
    ): array {
        $diagnosis =
            (new DiagnosisAdvisor())
                ->report($result);

        $fixPlanAdvisor =
            new FixPlanAdvisor();

        $fixPlans =
            $fixPlanAdvisor
                ->all($result);

        $priorityActions =
            (new ActionPriorityAdvisor())
                ->prioritize($result);

        $remediation =
            (new RemediationAdvisor())
                ->summarize($result);

        return [
            'diagnostics' =>
                $result->diagnostics()->toArray(),

            'diagnosis' => [
                'primary' => $diagnosis['primary'],
                'recommendations' =>
                    $diagnosis['recommendations'],
                'quick_wins' =>
                    $diagnosis['quick_wins'],
            ],

            'fix_plan' =>
                $fixPlanAdvisor
                    ->primary($result)
                    ->toArray(),

            'fix_plans' => [
                'count' => $fixPlans->count(),
                'items' => $fixPlans->toArray(),
            ],

            'priority_actions' => [
                'count' => count($priorityActions),
                'items' => array_map(
                    static fn($action): array =>
                        $action->toArray(),
                    $priorityActions
                ),
            ],

            'remediation' =>
                $remediation->toArray(),
        ];
    }
}
