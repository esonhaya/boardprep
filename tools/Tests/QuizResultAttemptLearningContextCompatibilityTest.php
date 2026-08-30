<?php

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
App\Core\Autoloader::register();

use App\Services\Quiz\QuizLearningContextService;

$attempt = QuizResultAttemptFactory::create(
    [
        'id' => 's1',
        'subject' => 'English',
    ],
    [
        [
            'id' => 'q1',
            'taxonomy' => [
                'topic_id' => 'Grammar',
            ],
        ],
    ],
    [
        'score' => 1,
        'total' => 1,
    ]
);

$enriched = QuizLearningContextService::enrichAttempt(
    $attempt,
    [
        'id' => 's1',
        'subject' => 'English',
    ],
    [
        [
            'id' => 'q1',
            'taxonomy' => [
                'topic_id' => 'Grammar',
            ],
        ],
    ]
);

if (
    $enriched['topic'] !== 'Grammar'
    || $enriched['subject'] !== 'English'
) {
    throw new RuntimeException(
        'learning context compatibility failed'
    );
}

echo "[PASS] Result attempt remains compatible with learning-context enrichment.\n";
