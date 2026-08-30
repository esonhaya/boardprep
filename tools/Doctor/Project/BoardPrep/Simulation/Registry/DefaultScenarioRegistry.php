<?php

declare(strict_types=1);

namespace Tools\Doctor\Project\BoardPrep\Simulation\Registry;

use Tools\Doctor\Project\BoardPrep\Simulation\Scenarios\ApplicationSmokeScenario;
use Tools\Doctor\Project\BoardPrep\Simulation\Scenarios\HomePageScenario;
use Tools\Doctor\Project\BoardPrep\Simulation\Scenarios\HttpStatusScenario;
use Tools\Doctor\Project\BoardPrep\Simulation\Scenarios\LearningSurfaceScenario;
use Tools\Doctor\Project\BoardPrep\Simulation\Scenarios\LearnerPersonaScenario;
use Tools\Doctor\Project\BoardPrep\Simulation\Scenarios\QuestionEditorScenario;
use Tools\Doctor\Project\BoardPrep\Simulation\Scenarios\QuizLifecycleScenario;

final class DefaultScenarioRegistry
{
    public static function create(): ScenarioRegistry
    {
        return new ScenarioRegistry([
            HomePageScenario::class,
            HttpStatusScenario::class,
            LearningSurfaceScenario::class,
            LearnerPersonaScenario::class,
            ApplicationSmokeScenario::class,
            QuizLifecycleScenario::class,
            QuestionEditorScenario::class,
        ]);
    }
}
