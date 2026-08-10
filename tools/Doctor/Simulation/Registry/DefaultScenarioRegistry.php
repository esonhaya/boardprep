<?php

declare(strict_types=1);

namespace Tools\Doctor\Simulation\Registry;

use Tools\Doctor\Simulation\Scenarios\ApplicationSmokeScenario;
use Tools\Doctor\Simulation\Scenarios\HomePageScenario;
use Tools\Doctor\Simulation\Scenarios\HttpStatusScenario;
use Tools\Doctor\Simulation\Scenarios\QuestionEditorScenario;
use Tools\Doctor\Simulation\Scenarios\QuizLifecycleScenario;

final class DefaultScenarioRegistry
{
    public static function create(): ScenarioRegistry
    {
        return new ScenarioRegistry([
            HomePageScenario::class,
            HttpStatusScenario::class,
            ApplicationSmokeScenario::class,
            QuizLifecycleScenario::class,
            QuestionEditorScenario::class,
        ]);
    }
}
