<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Services\Question\Statistics\QuestionStatisticsUpdater;

final class QuestionStatisticsRepository extends BaseRepository
{
    protected string $collection = 'question-statistics';

    /** @param array<int, array<string, mixed>> $entries */
    public function recordAnswers(array $entries, array $questions = []): void
    {
        $records = [];
        foreach ($this->all() as $record) {
            if (!is_array($record) || !is_scalar($record['id'] ?? null)) {
                continue;
            }
            $records[(string) $record['id']] = $record;
        }

        $baselines = [];
        foreach ($questions as $question) {
            if (!is_array($question) || !is_scalar($question['id'] ?? null)) {
                continue;
            }
            $id = (string) $question['id'];
            $baselines[$id] = [
                'id' => $id,
                'timesUsed' => $question['timesUsed'] ?? 0,
                'timesCorrect' => $question['timesCorrect'] ?? 0,
                'timesIncorrect' => $question['timesIncorrect'] ?? 0,
            ];
        }

        foreach ($entries as $entry) {
            $id = is_scalar($entry['question_id'] ?? null)
                ? trim((string) $entry['question_id'])
                : '';
            if ($id === '') {
                continue;
            }

            if (!isset($records[$id]) && !isset($baselines[$id])) {
                continue;
            }

            $record = $records[$id] ?? $baselines[$id] ?? ['id' => $id];
            $records[$id] = QuestionStatisticsUpdater::apply(
                $record,
                is_bool($entry['correct'] ?? null) ? $entry['correct'] : null
            );
        }

        $this->replaceAll(array_values($records));
    }
}
