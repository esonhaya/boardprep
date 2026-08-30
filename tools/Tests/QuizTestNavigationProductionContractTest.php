<?php

declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();
if (!(class_exists('QuizNavigationService') && method_exists('QuizNavigationService','current') && method_exists('QuizNavigationService','isLastQuestion'))) { exit("[FAIL] Navigation production contract unavailable.\n"); }
echo "[PASS] Navigation production contract verified.\n";
