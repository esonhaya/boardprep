<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

use App\Services\Quiz\Start\QuizStartInputNormalizer;

$legacyReview = QuizStartInputNormalizer::normalize([
    'exam' => 'LET',
    'mode' => 'review',
    'adaptive' => '1',
    'count' => 5,
]);
if (($legacyReview['mode'] ?? null) !== 'practice'
    || ($legacyReview['adaptive'] ?? false) !== true
    || ($legacyReview['count'] ?? null) !== 5) {
    throw new RuntimeException('Legacy review links did not enter the supported feedback-enabled practice flow.');
}

$examSimulation = QuizStartInputNormalizer::normalize([
    'exam' => 'LET',
    'mode' => 'exam',
    'adaptive' => '1',
    'count' => 10,
]);
if (($examSimulation['mode'] ?? null) !== 'exam'
    || ($examSimulation['adaptive'] ?? true) !== false) {
    throw new RuntimeException('Exam simulation mode or its fixed blueprint policy changed.');
}

echo "[PASS] legacy review mode maps to counted practice; exam mode remains separate.\n";
