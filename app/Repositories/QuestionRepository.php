<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\StorageInterface;

class QuestionRepository extends BaseRepository
{
    protected string $collection = 'questions';
    /** @var array<string, array>|null */
    private ?array $byId = null;

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
        if ($this->byId === null) {
            $this->byId = [];
            foreach ($this->all() as $record) {
                $recordId = $record['id'] ?? null;
                if (is_scalar($recordId)
                    && !array_key_exists((string) $recordId, $this->byId)) {
                    $this->byId[(string) $recordId] = $record;
                }
            }
        }

        return $this->byId[$id] ?? null;
    }

    public function create(array $data): array
    {
        $created = parent::create($data);
        $this->byId = null;
        return $created;
    }

    public function update(string $id, array $data): ?array
    {
        $updated = parent::update($id, $data);
        $this->byId = null;
        return $updated;
    }

    public function replaceAll(array $records): void
    {
        parent::replaceAll($records);
        $this->byId = null;
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

    public function updateStatistics(array $entries): void
    {
        $correctById = [];
        foreach ($entries as $entry) {
            $id = is_scalar($entry['question_id'] ?? null)
                ? trim((string) $entry['question_id'])
                : '';
            if ($id !== '') {
                $correctById[$id][] = $entry['correct'] ?? null;
            }
        }

        $ids = [];
        foreach ($correctById as $id => $results) {
            array_push($ids, ...array_fill(0, count($results), $id));
        }

        $this->storage->updateBatch(
            $this->collection,
            $ids,
            static function (array $question) use (&$correctById): array {
                $id = (string) $question['id'];
                return \App\Services\Question\Statistics\QuestionStatisticsUpdater::apply(
                    $question,
                    array_shift($correctById[$id])
                );
            }
        );
    }
}
