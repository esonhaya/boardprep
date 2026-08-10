<?php

declare(strict_types=1);

namespace Tools\Doctor\Advisor;

use Tools\Doctor\Diagnostics\RemediationSummary;
use Tools\Doctor\DTO\DoctorResult;

final class RemediationAdvisor
{
    public function summarize(
        DoctorResult $report
    ): RemediationSummary {
        $actions =
            (new ActionPriorityAdvisor())
                ->prioritize($report);

        return new RemediationSummary(
            actions: $actions,
        );
    }
}
