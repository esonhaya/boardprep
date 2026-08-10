<?php

declare(strict_types=1);

namespace Tools\Doctor\Project\BoardPrep\Checks;

use Tools\Doctor\Context\DoctorContext;
use Tools\Doctor\Contracts\CheckInterface;
use Tools\Doctor\DTO\CheckResult;

final class ProjectStatisticsCheck implements CheckInterface
{
    public function run(): CheckResult
    {
        $snapshot = DoctorContext::snapshot();

        return new CheckResult(
            title: "Project Statistics",
            status: "PASS",
            summary: "Current project size",
            details: [
                "Controllers : " . count($snapshot->controllers),
                "Services    : " . count($snapshot->services),
                "Repositories: " . count($snapshot->repositories),
                "Domains     : " . count($snapshot->domains),
                "Classes     : " . count($snapshot->classes),
                "Interfaces  : " . count($snapshot->interfaces),
                "Traits      : " . count($snapshot->traits),
                "PHP Files   : " . count($snapshot->files),
            ],
            recommendations: []
        );
    }

    public function category(): string
    {
        return "Project";
    }

    public function priority(): int
    {
        return 10;
    }
}
