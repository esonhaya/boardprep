<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();
require dirname(__DIR__, 2) . '/bootstrap/app.php';

use App\Core\App;
use App\Repositories\QuestionRepository;
use App\Services\Flashcard\FlashcardDerivationService;
use App\Services\Flashcard\FlashcardService;

function optimizationCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$deriver = App::container()->get(FlashcardDerivationService::class);
$service = App::container()->get(FlashcardService::class);
$all = $deriver->all();

optimizationCheck($all !== [], 'unfiltered flashcard catalog was empty');
optimizationCheck(
    count(array_unique(array_column($all, 'card_key'))) === count($all),
    'unfiltered flashcard catalog contains duplicate keys'
);

$filtered = $deriver->all([
    'board' => 'let',
    'subject' => 'english',
    'topic' => 'subject-verb-agreement',
]);
$expectedFiltered = array_values(array_filter(
    $all,
    static fn(array $card): bool => ($card['board'] ?? '') === 'let'
        && ($card['subject'] ?? '') === 'english'
        && ($card['topic'] ?? '') === 'subject-verb-agreement'
));
optimizationCheck($filtered === $expectedFiltered, 'filtered card output changed');

$topic = (string) ($filtered[0]['topic'] ?? '');
$topicFiltered = $deriver->all([], [$topic]);
$expectedTopicFiltered = array_values(array_filter(
    $all,
    static fn(array $card): bool => ($card['topic'] ?? '') === $topic
));
optimizationCheck($topicFiltered === $expectedTopicFiltered, 'topic-prefilter output changed');

$now = new DateTimeImmutable('2026-09-06T00:00:00+00:00');
$allSelection = $service->dueSelection([], count($all), $now);
$selection = $service->dueSelection([], 10, $now);
optimizationCheck(
    ($allSelection['total_count'] ?? -1) === count($all)
        && ($allSelection['due_count'] ?? -1) + ($allSelection['new_count'] ?? -1) === count($all)
        && array_column($selection['cards'], 'card_key') === array_column(array_slice($allSelection['cards'], 0, 10), 'card_key'),
    'unfiltered due selection contract changed'
);

$questionIds = array_column(array_slice($allSelection['cards'], 0, 10), 'question_id');
$missed = $service->selectionForQuestionIds($questionIds, 10, $now);
optimizationCheck(
    array_column($missed['cards'], 'card_key') === array_column(array_slice($allSelection['cards'], 0, 10), 'card_key')
        && ($missed['total_count'] ?? -1) === 10,
    'question-id selection contract changed'
);

$repository = App::container()->get(QuestionRepository::class);
$question = $repository->find((string) $questionIds[0]);
optimizationCheck($question !== null, 'single-card question fixture was unavailable');
optimizationCheck(
    $repository->find((string) $questionIds[0]) === $question,
    'question ID index returned inconsistent records'
);
$single = $service->card((string) $all[0]['card_key']);
optimizationCheck($single === $all[0], 'single-card lookup output changed');

echo "[PASS] flashcard selection output, filters, counts, question-ID selection, and direct lookup remain invariant.\n";
