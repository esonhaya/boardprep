<?php

declare(strict_types=1);

namespace App\Services\Question;

/**
 * Request-scoped lookup index for immutable eligibility and blueprint data.
 */
final class QuestionEligibilityIndex
{
    /** @var array<string, array<int, array>> */
    private array $entriesByQuestion = [];

    /** @var array<string, array<int, array>> */
    private array $blueprintsByBoard = [];

    public function __construct(array $entries, array $blueprints = [])
    {
        foreach ($entries as $entry) {
            if (!is_array($entry) || !is_array($entry['question_ids'] ?? null)) {
                continue;
            }

            foreach ($entry['question_ids'] as $questionId) {
                if (!is_scalar($questionId)) {
                    continue;
                }
                $this->entriesByQuestion[(string) $questionId][] = $entry;
            }
        }

        foreach ($blueprints as $blueprint) {
            if (!is_array($blueprint)) {
                continue;
            }
            $boardId = (string) ($blueprint['board_id'] ?? $blueprint['board'] ?? '');
            if ($boardId !== '') {
                $this->blueprintsByBoard[$boardId][] = $blueprint;
            }
        }
    }

    public function forExam(array $question, string $exam): ?array
    {
        $exam = self::normalize($exam);
        $id = (string) ($question['id'] ?? '');

        foreach ($this->entriesByQuestion[$id] ?? [] as $entry) {
            $exams = array_map(
                [self::class, 'normalize'],
                is_array($entry['exam_ids'] ?? null) ? $entry['exam_ids'] : []
            );
            if (!in_array($exam, $exams, true)) {
                continue;
            }

            $subjects = is_array($entry['subject_ids'] ?? null) ? $entry['subject_ids'] : [];
            return [
                'exam_id' => $exam,
                'subject_id' => $this->resolvedSubject(
                    $question,
                    $exam,
                    (string) ($subjects[$exam] ?? $entry['subject_id'] ?? '')
                ),
            ];
        }

        $taxonomy = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
        $board = self::normalize((string) ($taxonomy['board_id'] ?? $question['board'] ?? ''));
        return $board === $exam
            ? ['exam_id' => $exam, 'subject_id' => (string) ($taxonomy['subject_id'] ?? $question['subject_id'] ?? '')]
            : null;
    }

    private function resolvedSubject(array $question, string $exam, string $subject): string
    {
        $taxonomy = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
        $domain = (string) ($taxonomy['domain_id'] ?? '');
        if ($domain === '') {
            return $subject;
        }

        foreach ($this->blueprintsByBoard[$exam] ?? [] as $blueprint) {
            foreach (($blueprint['sections'] ?? []) as $section) {
                if (($section['id'] ?? '') === $domain || ($section['domain_id'] ?? '') === $domain) {
                    return (string) ($section['id'] ?? '');
                }
            }
        }

        return $subject;
    }

    private static function normalize(string $value): string
    {
        $value = strtolower(trim($value));
        return ['cse' => 'civil-service', 'let' => 'let'][$value] ?? $value;
    }
}
