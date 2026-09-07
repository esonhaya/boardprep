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
        $questions = array_values(array_filter(
            $this->storage->all($this->collection),
            'is_array'
        ));

        $statistics = [];
        foreach ($this->storage->all('question-statistics') as $record) {
            if (is_array($record) && is_scalar($record['id'] ?? null)) {
                $statistics[(string) $record['id']] = $record;
            }
        }

        return array_map(
            static function (array $question) use ($statistics): array {
                $id = (string) ($question['id'] ?? '');
                $statistic = $statistics[$id] ?? null;
                if (!is_array($statistic)) {
                    return $question;
                }

                foreach (['timesUsed', 'timesCorrect', 'timesIncorrect', 'updatedAt'] as $field) {
                    if (array_key_exists($field, $statistic)) {
                        $question[$field] = $statistic[$field];
                    }
                }

                return $question;
            },
            $questions
        );
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
        (new QuestionStatisticsRepository($this->storage))->recordAnswers($entries, $this->all());
    }
}
