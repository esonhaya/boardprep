<?php

declare(strict_types=1);

namespace Tools\Doctor\Advisor;

use Tools\Doctor\Diagnostics\DiagnosticFinding;
use Tools\Doctor\DTO\DoctorResult;

final class RecommendationAdvisor
{
    /**
     * @param DiagnosticFinding[] $findings
     *
     * @return string[]
     */
    public function fromFindings(
        array $findings
    ): array {
        $recommendations = [];

        foreach ($findings as $finding) {
            foreach (
                FixRecommendationAdvisor::forFinding($finding)
                as $recommendation
            ) {
                $recommendations[] = $recommendation;
            }
        }

        return array_values(
            array_unique($recommendations)
        );
    }

    /**
     * @return string[]
     */
    public function recommendations(
        DoctorResult $report
    ): array {
        return $this->fromFindings(
            $report->findings()->all()
        );
    }
}
