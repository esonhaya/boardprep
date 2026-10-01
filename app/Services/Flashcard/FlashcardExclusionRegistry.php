<?php

declare(strict_types=1);

namespace App\Services\Flashcard;

use App\Core\App;

/**
 * Explicit, reviewable exceptions to flashcard derivation.
 *
 * A failed validator can never become a justified exclusion by itself. The
 * record must be present in this registry and satisfy the exclusion contract.
 */
final class FlashcardExclusionRegistry
{
    private const REASON_CODES = [
        'NOT_FLASHCARD_SUITABLE',
        'VISUAL_ONLY',
        'COMPOSITE_CONTEXT_ONLY',
        'ADMINISTRATIVE',
        'INTENTIONALLY_NON_CARD_CONTENT',
        'OTHER_DOCUMENTED_EXCEPTION',
    ];

    /** @var array<string, array>|null */
    private ?array $byEntity = null;

    public function __construct(private ?array $records = null)
    {
    }

    public function find(array $question): ?array
    {
        $this->index();
        $id = self::string($question['id'] ?? null);
        $board = self::board($question);
        $record = $id === '' ? null : ($this->byEntity[$id] ?? null);
        if (!is_array($record) || self::board($record) !== $board || !self::valid($record)) {
            return null;
        }

        return $record;
    }

    /** @return array<int, array{valid: bool, record: array, errors: string[]}> */
    public function validateAll(): array
    {
        $this->index();
        $result = [];
        foreach ($this->records ?? [] as $record) {
            if (!is_array($record)) {
                $result[] = ['valid' => false, 'record' => [], 'errors' => ['record-not-object']];
                continue;
            }
            $result[] = ['valid' => self::valid($record, $errors), 'record' => $record, 'errors' => $errors];
        }
        return $result;
    }

    /** @return array<string, array> */
    public function records(): array
    {
        $this->index();
        return $this->byEntity ?? [];
    }

    private function index(): void
    {
        if ($this->byEntity !== null) {
            return;
        }
        $this->records ??= App::storage()->all('flashcard-exclusions');
        $this->byEntity = [];
        foreach ($this->records as $record) {
            if (!is_array($record)) {
                continue;
            }
            $id = self::string($record['entity_id'] ?? null);
            if ($id !== '' && !isset($this->byEntity[$id])) {
                $this->byEntity[$id] = $record;
            }
        }
    }

    private static function valid(array $record, ?array &$errors = null): bool
    {
        $errors = [];
        foreach (['entity_id', 'board_id', 'reason_code', 'human_readable_reason', 'evidence', 'review_status'] as $field) {
            if (!array_key_exists($field, $record)) {
                $errors[] = 'missing-' . $field;
            }
        }
        if (!in_array((string) ($record['reason_code'] ?? ''), self::REASON_CODES, true)) {
            $errors[] = 'invalid-reason-code';
        }
        if (!is_array($record['evidence'] ?? null) || $record['evidence'] === []) {
            $errors[] = 'missing-evidence';
        }
        if (self::string($record['human_readable_reason'] ?? null) === '') {
            $errors[] = 'empty-human-readable-reason';
        }
        if (self::string($record['review_status'] ?? null) === '') {
            $errors[] = 'missing-review-status';
        }
        return $errors === [];
    }

    private static function board(array $record): string
    {
        $taxonomy = is_array($record['taxonomy'] ?? null) ? $record['taxonomy'] : [];
        return strtolower(self::string($record['board_id'] ?? $taxonomy['board_id'] ?? $record['board'] ?? ''));
    }

    private static function string(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }
}
