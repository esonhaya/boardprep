<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . "/app/Core/Autoloader.php";
\App\Core\Autoloader::register();

use App\Services\Quiz\QuizResultActionService;
use App\Services\Quiz\Start\QuizStartInputNormalizer;

$actions = QuizResultActionService::build(
    [
        'topics' => ['Grammar'],
        'subject' => ['not-a-subject'],
        'mode' => 'invalid-mode',
        'difficulty' => 'invalid-difficulty',
        'question_count' => '7',
    ],
    ['percentage' => 40]
);

$query = [];
parse_str((string) parse_url($actions[0]['url'] ?? '', PHP_URL_QUERY), $query);
$normalized = QuizStartInputNormalizer::normalize($query);

if ($normalized['subject'] !== 'English'
    || $normalized['topics'] !== ['Grammar']
    || $normalized['mode'] !== 'practice'
    || $normalized['difficulty'] !== 'mixed'
    || $normalized['count'] !== 7) {
    throw new RuntimeException(
        'post-quiz action did not use the quiz-start contract: '
        . json_encode($query)
    );
}

$cseActions = QuizResultActionService::build(
    [
        'board' => 'CSE',
        'civil_service_level' => 'SUBPROFESSIONAL',
        'subject' => 'clerical-ability',
        'mode' => 'exam',
        'difficulty' => 'mixed',
        'question_count' => 150,
    ],
    ['percentage' => 70]
);
$cseQuery = [];
parse_str((string) parse_url($cseActions[0]['url'] ?? '', PHP_URL_QUERY), $cseQuery);
if (($cseQuery['exam'] ?? '') !== 'CSE' || ($cseQuery['civil_service_level'] ?? '') !== 'SUBPROFESSIONAL') {
    throw new RuntimeException('post-quiz retry dropped Civil Service level context.');
}

echo "[PASS] post-quiz action preserves context through quiz-start normalization.\n";
