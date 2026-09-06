<?php

declare(strict_types=1);

namespace App\Services\Flashcard;

use App\Repositories\FlashcardStateRepository;
use App\Services\Learning\WeaknessService;
use DateTimeImmutable;
use InvalidArgumentException;

final class FlashcardService
{
    public function __construct(
        private FlashcardDerivationService $derivation,
        private FlashcardStateRepository $states,
        private FlashcardScheduler $scheduler
    ) {
    }

    /** @return array<string,mixed> */
    public function review(
        string $cardKey,
        string $rating,
        ?DateTimeImmutable $now = null,
        ?string $questionId = null
    ): array {
        $card = $this->resolveCard($cardKey, $questionId);
        $state = $this->states->find($cardKey)
            ?? $this->scheduler->newState(
                $card['card_key'],
                $card['question_id'],
                $card['card_type']
            );

        if (($state['suspended'] ?? false) === true) {
            throw new InvalidArgumentException('Suspended flashcards cannot be reviewed.');
        }

        // Scheduling state is mutable, but card identity and source linkage are
        // always taken from the current canonical derivation.
        $state['card_key'] = $card['card_key'];
        $state['question_id'] = $card['question_id'];
        $state['card_type'] = $card['card_type'];

        $transition = $this->scheduler->schedule(
            $state,
            $rating,
            $now ?? new DateTimeImmutable('now')
        );
        $this->states->save($transition['state']);

        return $transition['result'];
    }

    /** @return array<string,mixed> */
    public function card(string $cardKey, ?string $questionId = null): array
    {
        return $this->resolveCard($cardKey, $questionId);
    }

    /** @return list<array<string,mixed>> */
    public function dueCards(
        array $filters = [],
        int $limit = 20,
        ?DateTimeImmutable $now = null
    ): array {
        return $this->dueSelection($filters, $limit, $now)['cards'];
    }

    /** @return array{cards:list<array<string,mixed>>,due_count:int,new_count:int,total_count:int} */
    public function dueSelection(
        array $filters = [],
        int $limit = 20,
        ?DateTimeImmutable $now = null
    ): array {
        if ($limit < 1) {
            throw new InvalidArgumentException('Due-card limit must be positive.');
        }

        $now = $now ?? new DateTimeImmutable('now');
        $includeNew = (bool) ($filters['include_new'] ?? true);
        unset($filters['include_new']);
        $allowedFilters = ['board', 'subject', 'topic'];
        foreach ($filters as $name => $value) {
            if (!in_array($name, $allowedFilters, true)
                || !is_scalar($value)
                || trim((string) $value) === '') {
                throw new InvalidArgumentException('Unsupported flashcard filter.');
            }
        }

        $states = [];
        foreach ($this->states->all() as $state) {
            $key = (string) ($state['card_key'] ?? '');
            if ($key !== '') {
                $states[$key] = $state;
            }
        }

        return $this->selectFromCards(
            $this->derivation->all($filters),
            $states,
            $includeNew,
            false,
            $limit,
            $now
        );
    }

    /** @return array{cards:list<array<string,mixed>>,due_count:int,new_count:int,total_count:int} */
    public function selectionForQuestionIds(array $questionIds, int $limit = 20, ?DateTimeImmutable $now = null): array
    {
        $cards = [];
        $seen = [];
        foreach ($questionIds as $questionId) {
            if (!is_scalar($questionId)) {
                continue;
            }
            $id = trim((string) $questionId);
            if ($id === '' || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $card = $this->derivation->forQuestionId($id);
            if ($card !== null) {
                $cards[] = $card;
            }
        }

        return $this->selectFromCards(
            $cards,
            $this->statesByKey(),
            true,
            true,
            $limit,
            $now ?? new DateTimeImmutable('now')
        );
    }

    /** @return array{cards:list<array<string,mixed>>,due_count:int,new_count:int,total_count:int} */
    public function weaknessSelection(int $limit = 20, ?DateTimeImmutable $now = null): array
    {
        $weakTopics = $this->weakTopics();
        if ($weakTopics === []) {
            return $this->emptySelection();
        }

        $cards = $this->derivation->all([], array_keys($weakTopics));

        return $this->selectFromCards(
            $cards,
            $this->statesByKey(),
            true,
            false,
            $limit,
            $now ?? new DateTimeImmutable('now')
        );
    }

    public function isWeaknessCard(string $cardKey): bool
    {
        $card = $this->card($cardKey);

        return isset($this->weakTopics()[(string) ($card['topic'] ?? '')]);
    }

    /** @return array<string,mixed>|null */
    public function suspend(string $cardKey, ?string $questionId = null): ?array
    {
        $card = $this->resolveCard($cardKey, $questionId);
        $state = $this->states->find($cardKey)
            ?? $this->scheduler->newState($card['card_key'], $card['question_id'], $card['card_type']);
        $state['suspended'] = true;

        return $this->publicState($this->states->save($state));
    }

    /** @return array<string,mixed>|null */
    public function resume(string $cardKey, ?string $questionId = null): ?array
    {
        $this->resolveCard($cardKey, $questionId);
        $state = $this->states->find($cardKey);
        if ($state === null) {
            return null;
        }
        $state['suspended'] = false;

        return $this->publicState($this->states->save($state));
    }

    public function reset(string $cardKey, ?string $questionId = null): bool
    {
        $this->resolveCard($cardKey, $questionId);

        return $this->states->delete($cardKey);
    }

    /** @return array<string,mixed> */
    private function resolveCard(string $cardKey, ?string $questionId = null): array
    {
        $cardKey = trim($cardKey);
        $parts = explode(':', $cardKey);
        if (count($parts) !== 5 || $parts[0] !== 'q' || $parts[1] === '') {
            throw new InvalidArgumentException('Malformed flashcard key.');
        }
        if ($questionId !== null && trim($questionId) !== $parts[1]) {
            throw new InvalidArgumentException('Question and card key do not match.');
        }

        $card = $this->derivation->forQuestionId($parts[1]);
        if ($card === null || $card['card_key'] !== $cardKey) {
            throw new InvalidArgumentException('Unknown or ineligible flashcard.');
        }

        return $card;
    }

    /** @return array<string,array<string,mixed>> */
    private function statesByKey(): array
    {
        $states = [];
        foreach ($this->states->all() as $state) {
            $key = (string) ($state['card_key'] ?? '');
            if ($key !== '') {
                $states[$key] = $state;
            }
        }

        return $states;
    }

    /** @return array<string,bool> */
    private function weakTopics(): array
    {
        $topics = [];
        foreach (WeaknessService::all() as $topic => $stats) {
            if (is_array($stats) && (int) ($stats['accuracy'] ?? 100) < 70) {
                $topics[(string) $topic] = true;
            }
        }

        return $topics;
    }

    /** @return array{cards:list<array<string,mixed>>,due_count:int,new_count:int,total_count:int} */
    private function selectFromCards(
        array $cards,
        array $states,
        bool $includeNew,
        bool $includeFuture,
        int $limit,
        DateTimeImmutable $now
    ): array {
        if ($limit < 1) {
            throw new InvalidArgumentException('Due-card limit must be positive.');
        }

        $candidates = [];
        $dueCount = 0;
        $newCount = 0;
        foreach ($cards as $card) {
            if (!is_array($card)) {
                continue;
            }
            $state = $states[(string) ($card['card_key'] ?? '')] ?? null;
            if (is_array($state) && ($state['suspended'] ?? false) === true) {
                continue;
            }

            $group = null;
            $dueTimestamp = PHP_INT_MAX;
            if ($state === null || $this->isNewState($state)) {
                if (!$includeNew) {
                    continue;
                }
                $group = 2;
                $newCount++;
            } else {
                $nextDue = $this->dateFrom($state['next_due_at'] ?? null);
                $dueTimestamp = $nextDue?->getTimestamp() ?? $now->getTimestamp();
                if ($this->isDue($state, $now)) {
                    $group = $dueTimestamp < $now->getTimestamp() ? 0 : 1;
                    $dueCount++;
                } elseif ($includeFuture) {
                    $group = 3;
                }
            }
            if ($group === null) {
                continue;
            }

            $card['review_state'] = $this->publicState(
                $state ?? $this->scheduler->newState(
                    $card['card_key'],
                    $card['question_id'],
                    $card['card_type']
                )
            );
            $candidates[] = [$group, $dueTimestamp, (string) $card['card_key'], $card];
        }

        usort($candidates, static fn(array $left, array $right): int =>
            ($left[0] <=> $right[0]) ?: ($left[1] <=> $right[1]) ?: strcmp($left[2], $right[2])
        );

        return [
            'cards' => array_values(array_map(
                static fn(array $candidate): array => $candidate[3],
                array_slice($candidates, 0, min($limit, 100))
            )),
            'due_count' => $dueCount,
            'new_count' => $newCount,
            'total_count' => count($candidates),
        ];
    }

    /** @return array{cards:list<array<string,mixed>>,due_count:int,new_count:int,total_count:int} */
    private function emptySelection(): array
    {
        return ['cards' => [], 'due_count' => 0, 'new_count' => 0, 'total_count' => 0];
    }

    private function isNewState(array $state): bool
    {
        return strtolower((string) ($state['state'] ?? 'new')) === 'new'
            && ($state['next_due_at'] ?? null) === null;
    }

    private function isDue(array $state, DateTimeImmutable $now): bool
    {
        $nextDue = $this->dateFrom($state['next_due_at'] ?? null);

        return $nextDue === null || $nextDue->getTimestamp() <= $now->getTimestamp();
    }

    private function dateFrom(mixed $value): ?DateTimeImmutable
    {
        if (!is_scalar($value) || trim((string) $value) === '') {
            return null;
        }

        try {
            return new DateTimeImmutable((string) $value);
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return array<string,mixed> */
    private function publicState(?array $state): array
    {
        if ($state === null) {
            return [];
        }

        unset($state['id'], $state['user_id']);

        return $state;
    }
}
