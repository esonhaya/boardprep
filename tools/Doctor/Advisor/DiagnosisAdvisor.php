<?php

declare(strict_types=1);

namespace Tools\Doctor\Advisor;

use Tools\Doctor\Diagnostics\DiagnosticFinding;
use Tools\Doctor\Diagnostics\DiagnosticSummary;
use Tools\Doctor\DTO\DoctorResult;

final class DiagnosisAdvisor
{
    public function diagnose(
        DoctorResult $report
    ): DiagnosticSummary {
        return $report->diagnostics();
    }

    public function primaryFinding(
        DoctorResult $report
    ): ?DiagnosticFinding {
        return $report
            ->diagnostics()
            ->topFinding();
    }

    /**
     * @return array<string,mixed>
     */
    public function primaryDiagnosis(
        DoctorResult $report
    ): array {
        $finding = $this->primaryFinding($report);

        if ($finding === null) {
            return [
                'status' => 'HEALTHY',
                'message' => 'No diagnostic findings detected.',
                'finding' => null,
            ];
        }

        return [
            'status' => $finding->severity,
            'id' => $finding->id,
            'title' => $finding->title,
            'category' => $finding->category,
            'priority' => $finding->priorityLabel(),
            'impact' => $finding->impact(),
            'effort' => $finding->effort(),
            'message' => $finding->message,
            'file' => $finding->file,
            'symbol' => $finding->symbol,
            'recommendation' => $finding->recommendation,
            'evidence' => $finding->evidence,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function report(
        DoctorResult $report
    ): array {
        $summary = $this->diagnose($report);

        return [
            'health' => $report->health(),
            'diagnostics' => $summary->toArray(),
            'primary' => $this->primaryDiagnosis($report),
            'recommendations' =>
                (new RecommendationAdvisor())
                    ->recommendations($report),
            'quick_wins' =>
                QuickWinAdvisor::fromFindings(
                    $report->findings()->all()
                ),
        ];
    }
}
