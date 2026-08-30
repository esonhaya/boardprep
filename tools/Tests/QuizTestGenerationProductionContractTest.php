<?php

declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();
if (!(class_exists('QuizGenerationService') && method_exists('QuizGenerationService','generate') && class_exists('QuizSpecification'))) { exit("[FAIL] Generation production contract unavailable.\n"); }
echo "[PASS] Generation production contract verified.\n";
