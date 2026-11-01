<?php

declare(strict_types=1);

namespace App\Services\Learning;

/** Builds bounded calendar review evidence from learner-owned attempt snapshots. */
final class StudyCalendarEvidenceService
{
    /** @return array<string,int> counts keyed by available selected-board subject id */
    public static function mistakeSubjectCounts(array $attempts, array $configuration): array
    {
        $aliases = array_fill_keys(array_map('strtolower', array_filter([
            (string) ($configuration['board_id'] ?? ''),
            (string) ($configuration['board_code'] ?? ''),
            (string) ($configuration['board_name'] ?? ''),
        ])), true);
        $availableSubjects = is_array($configuration['subjects'] ?? null) ? $configuration['subjects'] : [];
        $allowedSubjects = array_fill_keys(array_map('strtolower', array_keys($availableSubjects)), true);
        $questionSets = [];
        foreach ($attempts as $attempt) {
            if (!is_array($attempt) || !self::completed($attempt['completed'] ?? true)) continue;
            $attemptBoard = MistakeNotebookService::boardId(
                $attempt['board'] ?? $attempt['learning_context']['board'] ?? ''
            );
            if ($attemptBoard !== '' && !isset($aliases[strtolower($attemptBoard)])) continue;
            $attemptSubject = MistakeNotebookService::taxonomyId(
                'subjects',
                $attempt['subject'] ?? $attempt['learning_context']['subject'] ?? ''
            );
            foreach (is_array($attempt['review'] ?? null) ? $attempt['review'] : [] as $item) {
                if (!is_array($item) || !is_array($item['question'] ?? null)) continue;
                $answered = $item['answered'] ?? null;
                $correct = $item['correct'] ?? null;
                if (!in_array($answered, [true, false], true) || ($answered === true && $correct !== false)) continue;
                $question = $item['question'];
                $questionId = is_scalar($question['id'] ?? null) ? trim((string) $question['id']) : '';
                if ($questionId === '') continue;
                $taxonomy = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
                $board = $attemptBoard !== '' ? $attemptBoard : MistakeNotebookService::boardId(
                    $taxonomy['board_id'] ?? $question['board'] ?? ''
                );
                if ($board === '' || !isset($aliases[strtolower($board)])) continue;
                $questionSubject = MistakeNotebookService::taxonomyId(
                    'subjects',
                    $taxonomy['subject_id'] ?? $question['subject'] ?? ''
                );
                $subject = $questionSubject !== '' ? $questionSubject : $attemptSubject;
                $subject = strtolower($subject);
                if ($subject === '' || !isset($allowedSubjects[$subject])) continue;
                $questionSets[$subject][$questionId] = true;
            }
        }

        $counts = [];
        foreach ($questionSets as $subject => $questions) $counts[$subject] = count($questions);
        ksort($counts, SORT_STRING);
        return $counts;
    }

    private static function completed(mixed $value): bool
    {
        return $value === true || $value === 1 || $value === '1' || $value === 'true';
    }
}
