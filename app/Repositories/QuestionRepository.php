<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\StorageInterface;

class QuestionRepository extends BaseRepository
{
    protected string $collection = 'questions';

    public function __construct(
        StorageInterface $storage
    ) {
        parent::__construct($storage);
    }

    public function all(): array
    {
        return array_values(array_filter(
            $this->storage->all($this->collection),
            'is_array'
        ));
    }

    public function find(string $id): ?array
    {
        foreach ($this->all() as $record) {
            $recordId = $record['id'] ?? null;
            if (is_scalar($recordId) && (string) $recordId === $id) {
                return $record;
            }
        }

        return null;
    }

    public function where(array $criteria): array
    {
        return array_values(array_filter(
            $this->all(),
            fn (array $record): bool => $this->matches($record, $criteria)
        ));
    }

    private function matches(array $record, array $criteria): bool
    {
        foreach ($criteria as $key => $expected) {
            $actual = $record;
            foreach (explode('.', (string) $key) as $segment) {
                if (!is_array($actual) || !array_key_exists($segment, $actual)) {
                    return false;
                }
                $actual = $actual[$segment];
            }
            if ($actual !== $expected) {
                return false;
            }
        }

        return true;
    }

    public function byBoard(
        string $boardId
    ): array {

        return $this->where([
            'taxonomy.board_id' => $boardId,
        ]);

    }

    public function bySubject(
        string $subjectId
    ): array {

        return $this->where([
            'taxonomy.subject_id' => $subjectId,
        ]);

    }

    public function byDomain(
        string $domainId
    ): array {

        return $this->where([
            'taxonomy.domain_id' => $domainId,
        ]);

    }

    public function byTopic(
        string $topicId
    ): array {

        return $this->where([
            'taxonomy.topic_id' => $topicId,
        ]);

    }

    public function byConcept(
        string $conceptId
    ): array {

        return $this->where([
            'taxonomy.concept_id' => $conceptId,
        ]);

    }

    public function byDifficulty(
        string $difficulty
    ): array {

        return $this->where([
            'difficulty' => $difficulty,
        ]);

    }

    public function approved(): array
    {

        return $this->where([
            'status' => 'approved',
        ]);

    }
}
