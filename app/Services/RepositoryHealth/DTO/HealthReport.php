<?php

declare(strict_types=1);

namespace App\Services\RepositoryHealth\DTO;

class HealthReport
{
    public array $issues = [];

    public RepositoryStatistics $statistics;

    public float $healthScore = 100;
}
