<?php

declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();
if (!(class_exists('QuizSubmissionService') && method_exists('QuizSubmissionService','submit'))) { exit("[FAIL] Submission production contract unavailable.\n"); }
echo "[PASS] Submission production contract verified.\n";
