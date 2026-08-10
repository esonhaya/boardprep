<?php

declare(strict_types=1);

namespace Tools\Doctor\Diagnostics;

final class DiagnosticSummary
{
    /**
     * @param DiagnosticFinding[] $findings
     */
    public function __construct(
        public readonly array $findings,
    ) {
    }

    public function count(): int
    {
        return count($this->findings);
    }

    public function criticalCount(): int
    {
        return count(
            array_filter(
                $this->findings,
                static fn(DiagnosticFinding $finding): bool =>
                    $finding->isCritical()
            )
        );
    }

    public function errorCount(): int
    {
        return count(
            array_filter(
                $this->findings,
                static fn(DiagnosticFinding $finding): bool =>
                    $finding->isError()
            )
        );
    }

    public function warningCount(): int
    {
        return count(
            array_filter(
                $this->findings,
                static fn(DiagnosticFinding $finding): bool =>
                    $finding->isWarning()
            )
        );
    }

    public function infoCount(): int
    {
        return count(
            array_filter(
                $this->findings,
                static fn(DiagnosticFinding $finding): bool =>
                    $finding->isInfo()
            )
        );
    }

    public function highestImpact(): int
    {
        $impact = 0;

        foreach ($this->findings as $finding) {
            $impact = max(
                $impact,
                $finding->impact()
            );
        }

        return $impact;
    }

    public function highestPriority(): string
    {
        $highest = null;

        foreach ($this->findings as $finding) {
            if (
                $highest === null
                || $finding->impact() > $highest->impact()
            ) {
                $highest = $finding;
            }
        }

        return $highest?->priorityLabel() ?? 'None';
    }

    public function topFinding(): ?DiagnosticFinding
    {
        $top = null;

        foreach ($this->findings as $finding) {
            if (
                $top === null
                || $finding->impact() > $top->impact()
            ) {
                $top = $finding;
            }
        }

        return $top;
    }

    /**
     * @return array<string,int>
     */
    public function categoryCounts(): array
    {
        $counts = [];

        foreach ($this->findings as $finding) {
            $category = $finding->category;

            $counts[$category] =
                ($counts[$category] ?? 0) + 1;
        }

        ksort($counts);

        return $counts;
    }

    /**
     * @return array<string,int>
     */
    public function severityCounts(): array
    {
        return [
            'CRITICAL' => $this->criticalCount(),
            'ERROR' => $this->errorCount(),
            'WARNING' => $this->warningCount(),
            'INFO' => $this->infoCount(),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        $top = $this->topFinding();

        return [
            'count' => $this->count(),
            'critical' => $this->criticalCount(),
            'errors' => $this->errorCount(),
            'warnings' => $this->warningCount(),
            'info' => $this->infoCount(),
            'highest_impact' => $this->highestImpact(),
            'highest_priority' => $this->highestPriority(),
            'top_finding' => $top?->toArray(),
            'categories' => $this->categoryCounts(),
            'severities' => $this->severityCounts(),
        ];
    }
}
