<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\StorageInterface;
use App\Services\Question\QuestionReuseMetadataService;

class QuestionRepository extends BaseRepository
{
    protected string $collection = 'questions';
    /** @var array<string, array|null> */
    private array $byId = [];

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

        $reuseByQuestion = [];
        foreach ($this->storage->all('question-reuse-metadata') as $record) {
            if (!is_array($record) || !is_scalar($record['question_id'] ?? null)) {
                continue;
            }
            $metadata = $record['reuse'] ?? $record;
            if (is_array($metadata)
                && QuestionReuseMetadataService::validate($metadata) === []) {
                $reuseByQuestion[(string) $record['question_id']] = $metadata;
            }
        }

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
            array_map(
                static function (array $question) use ($reuseByQuestion): array {
                    $id = (string) ($question['id'] ?? '');
                    if (!array_key_exists('reuse', $question) && isset($reuseByQuestion[$id])) {
                        $question['reuse'] = $reuseByQuestion[$id];
                    }
                    return $question;
                },
                $questions
            )
        );
    }

    public function find(string $id): ?array
    {
        return $this->findMany([$id])[$id] ?? null;
    }

    /** @param list<string|int> $ids @return array<string, array> */
    public function findMany(array $ids): array
    {
        $requested = $this->normalizeLookupIds($ids);
        $missing = $this->missingLookupIds($requested);
        if ($missing !== []) {
            $questions = $this->loadQuestionRecords($missing);
            if ($questions !== []) {
                $this->hydrateSelectedQuestions($questions);
            }
            $this->cacheRequestedQuestions($requested, $questions);
        }

        return $this->cachedQuestions($requested);
    }

    /** @param list<string|int> $ids @return array<string, true> */
    private function normalizeLookupIds(array $ids): array
    {
        $requested = [];
        foreach ($ids as $candidate) {
            if (is_scalar($candidate) && (string) $candidate !== '') {
                $requested[(string) $candidate] = true;
            }
        }

        return $requested;
    }

    /** @param array<string, true> $requested @return array<string, true> */
    private function missingLookupIds(array $requested): array
    {
        $missing = [];
        foreach ($requested as $id => $_) {
            if (!array_key_exists($id, $this->byId)) {
                $missing[$id] = true;
            }
        }

        return $missing;
    }

    /** @param array<string, true> $missing @return array<string, array> */
    private function loadQuestionRecords(array $missing): array
    {
        $questions = [];
        $records = $this->storage->all($this->collection);
        foreach ($records as $record) {
            if (!is_array($record) || !is_scalar($record['id'] ?? null)) {
                continue;
            }
            $id = (string) $record['id'];
            if (isset($missing[$id])) {
                $questions[$id] = $record;
                unset($missing[$id]);
                if ($missing === []) {
                    break;
                }
            }
        }
        unset($records, $record);

        return $questions;
    }

    /** @param array<string, array> $questions */
    private function hydrateSelectedQuestions(array &$questions): void
    {
        $selectedIds = array_fill_keys(array_keys($questions), true);
        $reuseByQuestion = [];
        foreach ($this->storage->all('question-reuse-metadata') as $record) {
            if (!is_array($record) || !is_scalar($record['question_id'] ?? null)) {
                continue;
            }
            $id = (string) $record['question_id'];
            $metadata = $record['reuse'] ?? $record;
            if (isset($selectedIds[$id]) && is_array($metadata)
                && QuestionReuseMetadataService::validate($metadata) === []) {
                $reuseByQuestion[$id] = $metadata;
            }
        }

        $statistics = [];
        foreach ($this->storage->all('question-statistics') as $record) {
            if (is_array($record) && is_scalar($record['id'] ?? null)
                && isset($selectedIds[(string) $record['id']])) {
                $statistics[(string) $record['id']] = $record;
            }
        }

        foreach ($questions as $id => &$question) {
            if (!array_key_exists('reuse', $question) && isset($reuseByQuestion[$id])) {
                $question['reuse'] = $reuseByQuestion[$id];
            }
            foreach (['timesUsed', 'timesCorrect', 'timesIncorrect', 'updatedAt'] as $field) {
                if (array_key_exists($field, $statistics[$id] ?? [])) {
                    $question[$field] = $statistics[$id][$field];
                }
            }
        }
        unset($question);
    }

    /** @param array<string, true> $requested @param array<string, array> $questions */
    private function cacheRequestedQuestions(array $requested, array $questions): void
    {
        foreach ($requested as $id => $_) {
            if (!array_key_exists($id, $this->byId)) {
                $this->byId[$id] = $questions[$id] ?? null;
            }
        }
    }

    /** @param array<string, true> $requested @return array<string, array> */
    private function cachedQuestions(array $requested): array
    {
        $found = [];
        foreach ($requested as $id => $_) {
            if (is_array($this->byId[$id] ?? null)) {
                $found[$id] = $this->byId[$id];
            }
        }

        return $found;
    }

    public function create(array $data): array
    {
        $created = parent::create($data);
        $this->byId = [];
        return $created;
    }

    public function update(string $id, array $data): ?array
    {
        $updated = parent::update($id, $data);
        $this->byId = [];
        return $updated;
    }

    public function replaceAll(array $records): void
    {
        parent::replaceAll($records);
        $this->byId = [];
    }

    public function delete(string $id): bool
    {
        $deleted = parent::delete($id);
        $this->byId = [];
        return $deleted;
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

    public function persistReuseMetadata(array $question, array $reuse): void
    {
        if (!isset($this->storage)) {
            return;
        }
        $questionId = (string)($question['id'] ?? '');
        if ($questionId === '' || QuestionReuseMetadataService::validate($reuse) !== []) {
            return;
        }
        $records = [];
        $updated = false;
        foreach ($this->storage->all('question-reuse-metadata') as $record) {
            if (!is_array($record)) continue;
            if ((string)($record['question_id'] ?? '') === $questionId) {
                if (!$updated) {
                    $records[] = [
                        'id' => (string)($record['id'] ?? 'question-reuse-' . $questionId),
                        'question_id' => $questionId,
                        'reuse' => $reuse,
                    ];
                    $updated = true;
                }
                continue;
            }
            $records[] = $record;
        }
        if (!$updated) {
            $records[] = [
                'id' => 'question-reuse-' . $questionId,
                'question_id' => $questionId,
                'reuse' => $reuse,
            ];
        }
        $this->storage->replace('question-reuse-metadata', $records);
        $this->byId = [];
    }
}
