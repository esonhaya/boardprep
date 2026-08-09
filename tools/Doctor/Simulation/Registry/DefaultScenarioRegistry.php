<?php

declare(strict_types=1);

namespace Tools\Doctor\Simulation\Registry;

use Tools\Doctor\Simulation\Scenarios\ApplicationSmokeScenario;

final class DefaultScenarioRegistry
{
    public static function create(): ScenarioRegistry
    {
        return new ScenarioRegistry([
            ApplicationSmokeScenario::class,
        ]);
    }
}
