<?php

declare(strict_types=1);

namespace Tools\Doctor\Project\BoardPrep\Simulation;

final class SimulationResult
{
    private array $steps = [];

    private array $failures = [];

    public function record(
        string $description,
        bool $passed,
        ?string $failure = null
    ): void {
        $this->steps[] = [
            'description' => $description,
            'passed' => $passed,
        ];

        if (
            !$passed
            && $failure !== null
        ) {
            $this->failures[] = $failure;
        }
    }

    public function passed(): bool
    {
        return $this->failCount() === 0;
    }

    public function steps(): array
    {
        return $this->steps;
    }

    public function failures(): array
    {
        return $this->failures;
    }

    public function passCount(): int
    {
        return count(
            array_filter(
                $this->steps,
                fn(array $step): bool =>
                    $step['passed'] === true
            )
        );
    }

    public function failCount(): int
    {
        return count(
            array_filter(
                $this->steps,
                fn(array $step): bool =>
                    $step['passed'] === false
            )
        );
    }
}
