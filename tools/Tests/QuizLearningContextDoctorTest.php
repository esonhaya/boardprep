<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . "/app/Core/Autoloader.php";
\App\Core\Autoloader::register();

if (!\Tools\Doctor\Project\BoardPrep\Checks\QuizLearningContextCheck::run()) {
    exit(1);
}
