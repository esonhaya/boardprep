<?php

declare(strict_types=1);

namespace Tools\Doctor\Simulation\Registry;

use Tools\Doctor\Simulation\SimulationScenario;

final class ScenarioRegistry
{
    /**
     * @var array<int, class-string<SimulationScenario>>
     */
    private array $scenarios = [];

    /**
     * @param array<int, class-string<SimulationScenario>> $scenarios
     */
    public function __construct(
        array $scenarios = []
    ) {
        foreach ($scenarios as $scenario) {
            $this->register($scenario);
        }
    }

    /**
     * @param class-string<SimulationScenario> $scenario
     */
    public function register(
        string $scenario
    ): void {
        if (
            !is_a(
                $scenario,
                SimulationScenario::class,
                true
            )
        ) {
            throw new \InvalidArgumentException(
                "Simulation scenario must extend "
                . SimulationScenario::class
                . ": {$scenario}"
            );
        }

        if (
            !in_array(
                $scenario,
                $this->scenarios,
                true
            )
        ) {
            $this->scenarios[] = $scenario;
        }
    }

    /**
     * @return array<int, class-string<SimulationScenario>>
     */
    public function all(): array
    {
        return $this->scenarios;
    }

    public function count(): int
    {
        return count($this->scenarios);
    }

    public function clear(): void
    {
        $this->scenarios = [];
    }
}
