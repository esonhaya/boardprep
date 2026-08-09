<?php

declare(strict_types=1);

namespace Tools\Doctor\Simulation\Registry;

use Tools\Doctor\Simulation\Scenarios\ApplicationSmokeScenario;
use Tools\Doctor\Simulation\Scenarios\HomePageScenario;
use Tools\Doctor\Simulation\Scenarios\HttpStatusScenario;
use Tools\Doctor\Simulation\Scenarios\QuizLifecycleScenario;

final class DefaultScenarioRegistry implements ScenarioRegistry
{
    public function all(): array
    {
        return [
            HomePageScenario::class,
            HttpStatusScenario::class,
            ApplicationSmokeScenario::class,
            QuizLifecycleScenario::class,
        ];
    }
}
