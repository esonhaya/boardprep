<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();
require dirname(__DIR__, 2) . '/bootstrap/app.php';

use App\Core\App;
use App\Core\Database;
use App\Repositories\FlashcardStateRepository;
use App\Repositories\QuestionRepository;
use App\Services\Flashcard\FlashcardDerivationService;
use App\Services\Flashcard\FlashcardRating;
use App\Services\Flashcard\FlashcardScheduler;
use App\Services\Flashcard\FlashcardService;

function schedulingFail(string $message): never
{
    throw new RuntimeException($message);
}

function schedulingCheck(bool $condition, string $message): void
{
    if (!$condition) {
        schedulingFail($message);
    }
}

function schedulingThrows(callable $callback, string $message): void
{
    try {
        $callback();
    } catch (InvalidArgumentException) {
        return;
    }

    schedulingFail($message);
}

$originalSession = $_SESSION ?? null;
$root = sys_get_temp_dir() . '/boardprep-scheduling-' . bin2hex(random_bytes(6));
$path = $root . '/boardprep.sqlite';
mkdir($root, 0777, true);

try {
    $scheduler = new FlashcardScheduler();
    $now = new DateTimeImmutable('2026-09-03T10:00:00+08:00');
    $new = $scheduler->newState('q:test:reasoning:concept:r1', 'test', 'reasoning');
    $newIntervals = [];
    foreach (FlashcardRating::all() as $rating) {
        $transition = $scheduler->schedule($new, $rating, $now);
        $newIntervals[$rating] = $transition['result']['next_interval'];
        schedulingCheck(
            $transition['state']['review_count'] === 1
                && $transition['state']['algorithm_version'] === FlashcardScheduler::ALGORITHM_VERSION,
            'new-card review contract failed'
        );
    }
    schedulingCheck(
        $newIntervals === ['again' => 0, 'hard' => 1, 'good' => 2, 'easy' => 4],
        'new-card intervals were not deterministic'
    );

    $reviewState = array_merge($new, [
        'review_count' => 3,
        'interval_days' => 10,
        'ease_factor' => 2.5,
        'lapses' => 1,
        'state' => 'review',
        'next_due_at' => $now->modify('+10 days')->format(DATE_ATOM),
    ]);
    $reviewIntervals = [];
    $reviewDue = [];
    foreach (FlashcardRating::all() as $rating) {
        $transition = $scheduler->schedule($reviewState, $rating, $now);
        $reviewIntervals[$rating] = $transition['result']['next_interval'];
        $reviewDue[$rating] = $transition['state']['next_due_at'];
        schedulingCheck(
            $transition['state']['review_count'] === 4
                && $transition['state']['lapses'] === (1 + ($rating === FlashcardRating::AGAIN ? 1 : 0)),
            'review transition counters failed'
        );
    }
    schedulingCheck(
        $reviewIntervals['again'] < $reviewIntervals['hard']
            && $reviewIntervals['hard'] < $reviewIntervals['good']
            && $reviewIntervals['good'] < $reviewIntervals['easy'],
        'rating interval order failed'
    );
    schedulingCheck(
        strtotime($reviewDue['again']) <= strtotime($reviewDue['hard'])
            && strtotime($reviewDue['hard']) <= strtotime($reviewDue['good'])
            && strtotime($reviewDue['good']) <= strtotime($reviewDue['easy']),
        'rating due-date order failed'
    );

    $bounded = $scheduler->schedule(
        array_merge($reviewState, [
            'ease_factor' => 99,
            'interval_days' => FlashcardScheduler::MAX_INTERVAL_DAYS,
        ]),
        FlashcardRating::EASY,
        $now
    );
    schedulingCheck(
        $bounded['state']['ease_factor'] === FlashcardScheduler::MAX_EASE_FACTOR
            && $bounded['state']['interval_days'] === FlashcardScheduler::MAX_INTERVAL_DAYS,
        'scheduler upper bounds failed'
    );
    $lowerBounded = $scheduler->schedule(
        array_merge($reviewState, ['ease_factor' => 0.1]),
        FlashcardRating::AGAIN,
        $now
    );
    schedulingCheck(
        $lowerBounded['state']['ease_factor'] === FlashcardScheduler::MIN_EASE_FACTOR
            && $lowerBounded['state']['interval_days'] >= 0,
        'scheduler lower bounds failed'
    );
    $sequence = $new;
    foreach ([FlashcardRating::AGAIN, FlashcardRating::AGAIN, FlashcardRating::GOOD] as $rating) {
        $sequence = $scheduler->schedule($sequence, $rating, $now)['state'];
    }
    schedulingCheck(
        $sequence['review_count'] === 3
            && $sequence['lapses'] === 2
            && $sequence['state'] === 'review',
        'repeated rating sequence did not preserve monotonic state'
    );

    $_SESSION = ['learner_identity' => 'learner-' . str_repeat('a', 32)];
    $database = new Database([
        'driver' => 'sqlite',
        'sqlite_path' => $path,
        'path' => $root . '/content',
        'migration_path' => dirname(__DIR__, 2) . '/database/migrations',
    ]);
    $states = new FlashcardStateRepository($database->storage());
    $derivation = new FlashcardDerivationService(
        new QuestionRepository(App::storage())
    );
    $service = new FlashcardService($derivation, $states, $scheduler);
    $card = $derivation->forQuestionId('371');
    $cardTwo = $derivation->forQuestionId('373');
    $cardThree = $derivation->forQuestionId('374');
    if ($card === null || $cardTwo === null || $cardThree === null) {
        schedulingFail('canonical scheduling fixtures were unavailable');
    }

    $canonicalAnswer = $card['answer'];
    $first = $service->review($card['card_key'], FlashcardRating::GOOD, $now);
    schedulingCheck(
        $first['review_count'] === 1
            && $first['next_interval'] === 2
            && $states->find($card['card_key']) !== null,
        'first review did not persist'
    );
    $firstState = $states->find($card['card_key']);
    $second = $service->review(
        $card['card_key'],
        FlashcardRating::HARD,
        $now->modify('+3 days')
    );
    $secondState = $states->find($card['card_key']);
    schedulingCheck(
        $second['review_count'] === 2
            && ($secondState['id'] ?? '') === ($firstState['id'] ?? '')
            && ($secondState['algorithm_version'] ?? '') === FlashcardScheduler::ALGORITHM_VERSION,
        'subsequent review did not update the existing state'
    );

    $states->save(array_merge(
        $scheduler->newState($card['card_key'], $card['question_id'], $card['card_type']),
        [
            'review_count' => 2,
            'interval_days' => 5,
            'ease_factor' => 2.3,
            'lapses' => 1,
            'state' => 'review',
            'next_due_at' => $now->format(DATE_ATOM),
        ]
    ));
    $states->save(array_merge(
        $scheduler->newState($cardTwo['card_key'], $cardTwo['question_id'], $cardTwo['card_type']),
        [
            'review_count' => 1,
            'interval_days' => 2,
            'ease_factor' => 2.5,
            'next_due_at' => $now->modify('+5 days')->format(DATE_ATOM),
        ]
    ));
    schedulingCheck($states->find($cardThree['card_key']) === null, 'viewing a card created state');
    $service->suspend($cardTwo['card_key']);
    $suspended = $states->find($cardTwo['card_key']);
    schedulingCheck(
        ($suspended['suspended'] ?? false) === true
            && ($suspended['interval_days'] ?? 0) === 2,
        'suspension did not preserve history'
    );

    $due = $service->dueCards(['board' => $card['board'], 'include_new' => false], 10, $now);
    schedulingCheck(
        count($due) === 1 && $due[0]['card_key'] === $card['card_key'],
        'due selection did not include current due or exclude suspended state'
    );
    $dueWithNew = $service->dueCards(
        ['board' => $card['board'], 'include_new' => true],
        2,
        $now
    );
    $repeatDueWithNew = $service->dueCards(
        ['board' => $card['board'], 'include_new' => true],
        2,
        $now
    );
    schedulingCheck(
        count($dueWithNew) === 2
            && $dueWithNew[0]['card_key'] === $card['card_key']
            && ($dueWithNew[1]['review_state']['review_count'] ?? -1) === 0
            && array_column($dueWithNew, 'card_key') === array_column($repeatDueWithNew, 'card_key'),
        'due ordering, new-card policy, or limit was not deterministic'
    );
    schedulingCheck(
        $service->dueCards(['board' => 'not-a-real-board', 'include_new' => false], 10, $now) === [],
        'board filter was not applied before selection'
    );
    schedulingCheck(
        $service->dueCards(['subject' => $card['subject'], 'include_new' => false], 10, $now)[0]['card_key'] === $card['card_key']
            && $service->dueCards(['topic' => $card['topic'], 'include_new' => false], 10, $now)[0]['card_key'] === $card['card_key'],
        'subject/topic filters were not applied'
    );

    $resumed = $service->resume($cardTwo['card_key']);
    schedulingCheck(
        ($resumed['suspended'] ?? true) === false
            && ($resumed['interval_days'] ?? 0) === 2,
        'resume did not preserve review history'
    );
    $service->suspend($cardTwo['card_key']);

    schedulingThrows(
        fn() => $service->review($card['card_key'], 'invalid', $now),
        'invalid rating was accepted'
    );
    schedulingThrows(
        fn() => $service->review('q:unknown:concept:no-such:r1', FlashcardRating::GOOD, $now),
        'unknown card was accepted'
    );
    schedulingThrows(
        fn() => $service->review('tampered', FlashcardRating::GOOD, $now),
        'malformed card key was accepted'
    );
    schedulingThrows(
        fn() => $service->review($card['card_key'], FlashcardRating::GOOD, $now, '373'),
        'question/card mismatch was accepted'
    );
    schedulingThrows(
        fn() => $service->review($cardTwo['card_key'], FlashcardRating::GOOD, $now),
        'suspended card review was accepted'
    );

    $_SESSION = [];
    schedulingCheck(
        $states->find($card['card_key']) === null,
        'another learner inherited scheduling state'
    );
    $_SESSION = ['learner_identity' => 'learner-' . str_repeat('a', 32)];
    schedulingCheck($service->reset($card['card_key']), 'single-card reset failed');
    schedulingCheck(
        $states->find($card['card_key']) === null
            && $derivation->forQuestionId('371')['answer'] === $canonicalAnswer,
        'reset changed canonical card content'
    );

    echo "[PASS] SM-2-like scheduler covers four ratings, sequences, bounds, and controlled time.\n";
    echo "[PASS] Review service persists first/subsequent transitions with algorithm versioning.\n";
    echo "[PASS] Due selection applies taxonomy filters, limits, deterministic state, and suspension rules.\n";
    echo "[PASS] Invalid review inputs, card tampering, suspended review, and cross-user access are rejected.\n";
    echo "[PASS] Resume/reset preserve learner state boundaries and canonical content.\n";
} finally {
    $_SESSION = $originalSession;
    if (is_file($path)) {
        @unlink($path);
    }
    @rmdir($root);
}
