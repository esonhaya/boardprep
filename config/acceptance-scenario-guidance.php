<?php

declare(strict_types=1);

/* Route launch aids for the canonical scenarios in docs/HUMAN_TESTER_READINESS.md.
 * Outcome criteria remain in that single checklist and are loaded at runtime.
 */
return [
    'MVP-01' => [
        'title' => 'Find your way into practice',
        'why' => 'Check that a learner can understand where to begin and reach board practice.',
        'routes' => [
            ['label' => 'Home', 'path' => '/'],
            ['label' => 'Exam selection', 'path' => '/exams'],
            ['label' => 'LET exam', 'path' => '/exams?exam=LET'],
            ['label' => 'Practice setup', 'path' => '/quiz?exam=LET'],
        ],
    ],
    'MVP-02' => [
        'title' => 'Set up a practice quiz',
        'why' => 'Check that board, subject, question count, and the start action are understandable.',
        'routes' => [['label' => 'LET practice setup', 'path' => '/quiz?exam=LET']],
    ],
    'MVP-03' => [
        'title' => 'Answer and read feedback',
        'why' => 'Check that selecting, submitting, and moving to the next answer is clear.',
        'routes' => [['label' => 'LET practice setup', 'path' => '/quiz?exam=LET']],
    ],
    'MVP-04' => [
        'title' => 'Finish and review results',
        'why' => 'Check that scores, explanations, and missed-answer review are useful.',
        'routes' => [['label' => 'LET practice setup', 'path' => '/quiz?exam=LET']],
    ],
    'MVP-05' => [
        'title' => 'Revisit learning activity',
        'why' => 'Check that a completed attempt remains understandable across learner pages.',
        'routes' => [
            ['label' => 'Quiz history', 'path' => '/history'],
            ['label' => 'Progress', 'path' => '/progress'],
            ['label' => 'Study dashboard', 'path' => '/study'],
            ['label' => 'Study materials', 'path' => '/study-library'],
            ['label' => 'Flashcards', 'path' => '/flashcards'],
            ['label' => 'Data and privacy', 'path' => '/privacy'],
        ],
    ],
    'MVP-06' => [
        'title' => 'Try exam simulation',
        'why' => 'Check the timer, unanswered questions, review, and final submission as a learner.',
        'routes' => [['label' => 'LET simulation setup', 'path' => '/quiz?exam=LET']],
    ],
    'MVP-07' => [
        'title' => 'Complete a second attempt',
        'why' => 'Check that retrying practice does not replace or change an earlier result.',
        'routes' => [['label' => 'LET practice setup', 'path' => '/quiz?exam=LET']],
    ],
    'MVP-08' => [
        'title' => 'Recover an active attempt',
        'why' => 'Check that refresh and browser history do not lose answers or create duplicates.',
        'routes' => [['label' => 'LET practice setup', 'path' => '/quiz?exam=LET']],
    ],
    'MVP-09' => [
        'title' => 'Check stale navigation safety',
        'why' => 'Check that an old page action is rejected safely with a clear way back.',
        'routes' => [['label' => 'Quiz history', 'path' => '/history']],
    ],
    'MVP-10' => [
        'title' => 'Check empty and unavailable states',
        'why' => 'Check that an unavailable topic or action explains what the learner can do next.',
        'route_note' => 'These are deliberate negative tests. The topic does not exist, the quiz action is invalid, and the flashcard set is empty. A clear message, safe recovery, or not-found response is expected; these URLs are not normal pages that should be added.',
        'routes' => [
            ['label' => 'No-match LET topic (intentional empty state)', 'path' => '/quiz?exam=LET&topic=__no_such_topic__'],
            ['label' => 'Invalid quiz action (intentional rejected route)', 'path' => '/quiz?action=not-a-real-action'],
            ['label' => 'Empty flashcard review (intentional empty state)', 'path' => '/flashcards/review?include_new=0'],
        ],
    ],
    'MVP-11' => [
        'title' => 'Use BoardPrep on a phone',
        'why' => 'Check that key learner actions are visible and comfortable to tap on a real phone.',
        'routes' => [
            ['label' => 'Exam selection', 'path' => '/exams'],
            ['label' => 'Study', 'path' => '/study'],
            ['label' => 'Practice', 'path' => '/quiz?exam=LET'],
            ['label' => 'Flashcards', 'path' => '/flashcards'],
            ['label' => 'Quiz history', 'path' => '/history'],
            ['label' => 'Progress', 'path' => '/progress'],
            ['label' => 'Data and privacy', 'path' => '/privacy'],
        ],
    ],
    'MVP-12' => [
        'title' => 'Use keyboard and accessibility support',
        'why' => 'Check focus, labels, headings, and error feedback with the available input method.',
        'routes' => [
            ['label' => 'Home and navigation', 'path' => '/'],
            ['label' => 'Practice setup', 'path' => '/quiz?exam=LET'],
            ['label' => 'Flashcards', 'path' => '/flashcards'],
            ['label' => 'Data and privacy', 'path' => '/privacy'],
        ],
    ],
];
