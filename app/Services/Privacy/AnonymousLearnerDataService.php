<?php

declare(strict_types=1);

namespace App\Services\Privacy;

use App\Contracts\StorageInterface;
use App\Core\App;
use App\Core\Database;
use App\Services\Learning\LearnerIdentityService;
use RuntimeException;

final class AnonymousLearnerDataService
{
    private const COLLECTIONS = [
        'attempts' => 'quiz_attempts',
        'weakness' => 'weakness_summaries',
        'flashcard_state' => 'flashcard_reviews',
    ];

    public function __construct(
        private StorageInterface $storage,
        private Database $database,
        private string $learnerId
    ) {
        if (preg_match('/\Alearner-[a-f0-9]{32}\z/', $learnerId) !== 1) {
            throw new RuntimeException('Learner identity is unavailable.');
        }
    }

    public static function current(): self
    {
        if (\App\Services\Accounts\AccountFeatureGate::enabled()
            && \App\Services\Accounts\AccountSessionService::isAuthenticated()) {
            throw new RuntimeException('Signed-in learner data is managed through the account controls.');
        }

        return new self(
            App::storage(),
            App::database(),
            LearnerIdentityService::current()
        );
    }

    public function summary(): array
    {
        $summary = [];
        foreach (self::COLLECTIONS as $collection => $label) {
            $summary[$label] = count($this->ownedRecords($collection));
        }

        return $summary;
    }

    public function export(): array
    {
        $records = [];
        foreach (self::COLLECTIONS as $collection => $label) {
            $records[$label] = array_map(
                static function (array $record) use ($collection): array {
                    unset($record['user_id'], $record['session_id']);
                    if ($collection !== 'attempts') {
                        unset($record['id']);
                    }

                    return $record;
                },
                $this->ownedRecords($collection)
            );
        }

        return [
            'format' => 'boardprep-anonymous-learner-data-v1',
            'exported_at' => gmdate(DATE_ATOM),
            'scope' => 'Saved quiz attempts, derived weakness summaries, and flashcard review state for the current browser session.',
            'records' => $records,
        ];
    }

    public function clear(): array
    {
        $operation = function (): array {
            $deleted = [];
            foreach (self::COLLECTIONS as $collection => $label) {
                $deleted[$label] = $this->deleteOwnedRecords($collection);
            }

            if (array_sum($this->summary()) !== 0) {
                throw new RuntimeException('Learner data remains after deletion.');
            }

            return $deleted;
        };

        if ($this->database->usingSqlite() || $this->database->usingMysql()) {
            return $this->database->transaction($operation);
        }

        return $operation();
    }

    private function ownedRecords(string $collection): array
    {
        return array_values(array_filter(
            $this->storage->where($collection, ['user_id' => $this->learnerId]),
            'is_array'
        ));
    }

    private function deleteOwnedRecords(string $collection): int
    {
        $deleted = 0;
        foreach ($this->ownedRecords($collection) as $record) {
            $id = is_scalar($record['id'] ?? null) ? trim((string) $record['id']) : '';
            if ($id === '') {
                throw new RuntimeException('A learner data record cannot be identified safely.');
            }

            if ($this->storage->delete($collection, $id)) {
                $deleted++;
                continue;
            }

            if ($this->storage->find($collection, $id) !== null) {
                throw new RuntimeException('A learner data record could not be deleted.');
            }
        }

        return $deleted;
    }
}
