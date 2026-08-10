<?php

declare(strict_types=1);

namespace Tools\Doctor\DTO;

use Tools\Doctor\Diagnostics\DiagnosticFinding;
use Tools\Doctor\Diagnostics\DiagnosticFindingCollection;
use Tools\Doctor\Diagnostics\DiagnosticSummary;

final class DoctorResult
{
    /**
     * @param CheckResult[] $checks
     */
    public function __construct(
        public array $checks = [],
        public array $trend = [],
    ) {
    }

    public function add(
        CheckResult $check
    ): void {
        $this->checks[] = $check;
    }

    public function passCount(): int
    {
        return count(
            array_filter(
                $this->checks,
                fn(CheckResult $check) =>
                    $check->status === 'PASS'
            )
        );
    }

    public function warningCount(): int
    {
        return count(
            array_filter(
                $this->checks,
                fn(CheckResult $check) =>
                    $check->status === 'WARNING'
            )
        );
    }

    public function failCount(): int
    {
        return count(
            array_filter(
                $this->checks,
                fn(CheckResult $check) =>
                    $check->status === 'FAIL'
            )
        );
    }

    public function infoCount(): int
    {
        return count(
            array_filter(
                $this->checks,
                fn(CheckResult $check) =>
                    $check->status === 'INFO'
            )
        );
    }

    public function health(): int
    {
        $score = 100;

        foreach ($this->checks as $check) {
            if ($check->status === 'FAIL') {
                $score -= 15;
            }

            if ($check->status === 'WARNING') {
                $score -= 2;
            }
        }

        return max(
            0,
            min(
                100,
                $score
            )
        );
    }

    public function findings(): DiagnosticFindingCollection
    {
        $findings = new DiagnosticFindingCollection();

        foreach ($this->checks as $check) {
            $findings->addMany(
                $check->findings->all()
            );
        }

        return $findings;
    }

    public function diagnostics(): DiagnosticSummary
    {
        return new DiagnosticSummary(
            $this->findings()->all()
        );
    }

    public function findingCount(): int
    {
        return $this->diagnostics()->count();
    }

    /**
     * @return DiagnosticFinding[]
     */
    public function findingsBySeverity(
        string $severity
    ): array {
        return $this->findings()->bySeverity($severity);
    }

    /**
     * @return DiagnosticFinding[]
     */
    public function findingsByCategory(
        string $category
    ): array {
        return $this->findings()->byCategory($category);
    }
}
