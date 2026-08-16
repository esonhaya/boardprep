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

    public function passCount(
        string $scope = 'PROJECT'
    ): int {
        return count(
            array_filter(
                $this->checksByScope($scope),
                fn(CheckResult $check) =>
                    $check->status === 'PASS'
            )
        );
    }

    public function warningCount(
        string $scope = 'PROJECT'
    ): int {
        return count(
            array_filter(
                $this->checksByScope($scope),
                fn(CheckResult $check) =>
                    $check->status === 'WARNING'
            )
        );
    }

    public function failCount(
        string $scope = 'PROJECT'
    ): int {
        return count(
            array_filter(
                $this->checksByScope($scope),
                fn(CheckResult $check) =>
                    $check->status === 'FAIL'
            )
        );
    }

    public function infoCount(
        string $scope = 'PROJECT'
    ): int {
        return count(
            array_filter(
                $this->checksByScope($scope),
                fn(CheckResult $check) =>
                    $check->status === 'INFO'
            )
        );
    }

    /**
     * Project health remains the compatibility default.
     */
    public function health(): int
    {
        return $this->healthForScope('PROJECT');
    }

    public function doctorHealth(): int
    {
        return $this->healthForScope('DOCTOR');
    }

    public function healthForScope(
        string $scope
    ): int {
        $score = 100;

        foreach (
            $this->checksByScope($scope)
            as $check
        ) {
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

    public function findings(
        string $scope = 'PROJECT'
    ): DiagnosticFindingCollection {
        $findings = new DiagnosticFindingCollection();

        foreach (
            $this->checksByScope($scope)
            as $check
        ) {
            $findings->addMany(
                $check->findings->all()
            );
        }

        return $findings;
    }

    /**
     * @return CheckResult[]
     */
    private function checksByScope(
        string $scope
    ): array {
        return array_values(
            array_filter(
                $this->checks,
                static fn(CheckResult $check): bool =>
                    $check->scope === $scope
            )
        );
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
