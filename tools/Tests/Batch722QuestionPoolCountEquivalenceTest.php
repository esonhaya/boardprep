<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

$base = [
    'status' => 'active',
    'explanation' => 'A synthetic explanation used only by this isolated test.',
    'difficulty' => 'medium',
    'taxonomy' => [
        'board_id' => 'civil-service',
        'subject_id' => 'verbal-ability',
    ],
];
$make = static fn(string $id, string $text, array $extra = []): array => array_replace_recursive(
    $base,
    [
        'id' => $id,
        'question' => $text,
        'choices' => ['Alpha', 'Beta'],
        'answer' => 'Alpha',
    ],
    $extra
);

$questions = [
    $make('trimmed-choices', 'A prompt with surrounding whitespace.', [
        'choices' => [' Alpha ', 'Beta  '],
        'answer' => 'Alpha  ',
    ]),
    $make('options-only', 'An options-only prompt.', [
        'choices' => null,
        'answer' => null,
        'options' => [
            ['text' => ' First ', 'correct' => false],
            ['text' => ' Second ', 'correct' => true],
        ],
    ]),
    $make('duplicate-choice', 'Duplicate choices are rejected.', [
        'choices' => ['Same', ' same  '],
    ]),
    $make('unmatched-answer', 'An unmatched answer is rejected.', [
        'answer' => 'Gamma',
    ]),
    $make('duplicate-prompt', 'A shared   normalized prompt.', []),
    $make('duplicate-prompt-later', ' a shared normalized prompt. ', []),
    $make('same-prompt-other-subject', 'A shared   normalized prompt.', [
        'taxonomy' => ['subject_id' => 'numerical-ability'],
    ]),
    $make('inactive', 'Inactive questions are rejected.', ['status' => 'draft']),
    $make('invalid-difficulty', 'Invalid difficulty is rejected.', ['difficulty' => 'expert']),
    $make('missing-explanation', 'Missing explanations are rejected.', ['explanation' => '  ']),
];
$subjects = [
    'verbal-ability' => \App\Services\Shared\TaxonomyStorageService::label('subjects', 'verbal-ability'),
    'numerical-ability' => \App\Services\Shared\TaxonomyStorageService::label('subjects', 'numerical-ability'),
];
$countRequest = new SelectionRequest('*', null, [], 1);
$batched = QuestionPoolFilter::countBySubject($questions, $subjects, $countRequest);
$separate = [];
foreach ($subjects as $subjectId => $subjectName) {
    $separate[$subjectId] = count(QuestionPoolFilter::filter(
        $questions,
        new SelectionRequest($subjectName, null, [], 1)
    ));
}
if ($batched !== $separate || $batched !== ['verbal-ability' => 3, 'numerical-ability' => 1]) {
    throw new RuntimeException('Count-only pool validation diverged from independent selection filtering.');
}

$crossBoard = $make('cross-board-placement', 'An explicitly shared prompt.', [
    'taxonomy' => ['board_id' => 'nursing', 'subject_id' => 'nursing-fundamentals'],
    'civil_service_levels' => ['PROFESSIONAL'],
]);
$clerical = $make('professional-clerical', 'Professional clerical exclusion.', [
    'taxonomy' => ['board_id' => 'civil-service', 'subject_id' => 'clerical-ability'],
    'civil_service_levels' => ['PROFESSIONAL'],
]);
$eligibleIndex = new \App\Services\Question\QuestionEligibilityIndex([
    [
        'question_ids' => ['cross-board-placement'],
        'exam_ids' => ['CSE'],
        'subject_ids' => ['civil-service' => 'verbal-ability'],
    ],
], [], [], []);
$boardRequest = new SelectionRequest(
    '*',
    null,
    [],
    1,
    exam: 'civil-service',
    civilServiceLevel: 'PROFESSIONAL'
);
$boardSubjects = [
    'verbal-ability' => $subjects['verbal-ability'],
    'clerical-ability' => \App\Services\Shared\TaxonomyStorageService::label('subjects', 'clerical-ability'),
];
$boardQuestions = [$crossBoard, $clerical];
$boardCounts = QuestionPoolFilter::countBySubject($boardQuestions, $boardSubjects, $boardRequest, $eligibleIndex);
$boardSeparate = [];
foreach ($boardSubjects as $subjectId => $subjectName) {
    $boardSeparate[$subjectId] = count(QuestionPoolFilter::filter(
        $boardQuestions,
        new SelectionRequest(
            $subjectName,
            null,
            [],
            1,
            exam: 'civil-service',
            civilServiceLevel: 'PROFESSIONAL'
        ),
        $eligibleIndex
    ));
}
if ($boardCounts !== $boardSeparate || $boardCounts !== ['verbal-ability' => 1, 'clerical-ability' => 0]) {
    throw new RuntimeException('Count-only validation changed cross-board or CSE-level eligibility.');
}

echo "[PASS] Count-only subject pools match independent selection across normalized choices, options, invalid content, and per-subject deduplication.\n";
echo "[PASS] Board-scoped count-only pools preserve explicit shared placements and CSE professional exclusions.\n";
