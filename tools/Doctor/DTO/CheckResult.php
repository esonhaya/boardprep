<?php

declare(strict_types=1);

namespace Tools\Doctor\DTO;

use Tools\Doctor\Diagnostics\DiagnosticFinding;
use Tools\Doctor\Diagnostics\DiagnosticFindingCollection;

final class CheckResult
{

    /**
     * @param string[] $details
     * @param string[] $recommendations
     */
    public function __construct(
        public string $title,
        public string $status,
        public string $summary = "",
        public array $details = [],
        public array $recommendations = [],
        public int $score = 100,
        public DiagnosticFindingCollection $findings = new DiagnosticFindingCollection(),
        public string $scope = "PROJECT",
    ) {
    }

    public function addFinding(
        DiagnosticFinding $finding
    ): void {
        $this->findings->add($finding);
    }

    /**
     * @param DiagnosticFinding[] $findings
     */
    public function addFindings(
        array $findings
    ): void {
        $this->findings->addMany($findings);
    }

    public function findingCount(): int
    {
        return count($this->findings);
    }

    public function hasFindings(): bool
    {
        return $this->findingCount() > 0;
    }
}
