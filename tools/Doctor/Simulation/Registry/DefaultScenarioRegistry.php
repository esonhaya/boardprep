<?php

declare(strict_types=1);

namespace Tools\Doctor\Simulation\Registry;

use Tools\Doctor\Simulation\Scenarios\ApplicationSmokeScenario;
use Tools\Doctor\Simulation\Scenarios\HttpStatusScenario;

final class DefaultScenarioRegistry
{
    public static function create(): ScenarioRegistry
    {
        return new ScenarioRegistry([
            ApplicationSmokeScenario::class,
            HttpStatusScenario::class,
        ]);
    }
}
