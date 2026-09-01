<?php

declare(strict_types=1);

namespace App\Services\Question;

use App\Core\App;

final class QuestionEligibilityService
{
    public static function metadata(array $question): array
    {
        $id = (string) ($question['id'] ?? '');
        foreach (App::storage()->all('question-eligibility') as $entry) {
            if (is_array($entry) && self::containsId($entry['question_ids'] ?? [], $id)) {
                return [
                    'exam_ids' => array_values($entry['exam_ids'] ?? []),
                    'subject_ids' => is_array($entry['subject_ids'] ?? null) ? $entry['subject_ids'] : [],
                ];
            }
        }
        return [];
    }

    public static function persist(array $question, array $metadata): void
    {
        $id = (string) ($question['id'] ?? '');
        $examIds = array_values(array_filter(
            $metadata['exam_ids'] ?? [],
            static fn(mixed $exam): bool => is_scalar($exam) && trim((string) $exam) !== ''
        ));
        if ($id === '' || $examIds === []) {
            return;
        }
        $records = App::storage()->all('question-eligibility');
        foreach ($records as &$record) {
            if (!is_array($record) || !self::containsId($record['question_ids'] ?? [], $id)) {
                continue;
            }
            $record['exam_ids'] = $examIds;
            $record['subject_ids'] = is_array($metadata['subject_ids'] ?? null) ? $metadata['subject_ids'] : [];
            App::storage()->replace('question-eligibility', $records);
            return;
        }
        $records[] = [
            'id' => 'eligibility-question-' . preg_replace('/[^a-z0-9-]+/i', '-', $id),
            'question_ids' => [$id],
            'exam_ids' => $examIds,
            'subject_ids' => is_array($metadata['subject_ids'] ?? null) ? $metadata['subject_ids'] : [],
        ];
        App::storage()->replace('question-eligibility', $records);
    }

    public static function forExam(array $question, string $exam): ?array
    {
        $exam = self::normalize($exam);
        $id = (string) ($question['id'] ?? '');
        foreach (App::storage()->all('question-eligibility') as $entry) {
            if (!is_array($entry) || !self::containsId($entry['question_ids'] ?? [], $id)) {
                continue;
            }
            $exams = array_map([self::class, 'normalize'], is_array($entry['exam_ids'] ?? null) ? $entry['exam_ids'] : []);
            if (in_array($exam, $exams, true)) {
                $subjects = is_array($entry['subject_ids'] ?? null) ? $entry['subject_ids'] : [];
                return [
                    'exam_id' => $exam,
                    'subject_id' => self::resolvedSubject($question, $exam, (string) ($subjects[$exam] ?? $entry['subject_id'] ?? '')),
                ];
            }
        }

        $taxonomy = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
        $board = self::normalize((string) ($taxonomy['board_id'] ?? $question['board'] ?? ''));
        return $board === $exam ? ['exam_id' => $exam, 'subject_id' => (string) ($taxonomy['subject_id'] ?? $question['subject_id'] ?? '')] : null;
    }

    /** Resolve a legacy umbrella subject to a blueprint section when taxonomy provides it. */
    private static function resolvedSubject(array $question, string $exam, string $subject): string
    {
        $taxonomy = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
        $domain = (string) ($taxonomy['domain_id'] ?? '');
        if ($domain === '') return $subject;
        foreach (App::storage()->all('blueprints') as $blueprint) {
            if (($blueprint['board_id'] ?? $blueprint['board'] ?? '') !== $exam) continue;
            foreach (($blueprint['sections'] ?? []) as $section) {
                if (($section['id'] ?? '') === $domain || ($section['domain_id'] ?? '') === $domain) return (string) $section['id'];
            }
        }
        return $subject;
    }

    public static function eligible(array $questions, string $exam): array
    {
        return array_values(array_filter($questions, static fn(array $question): bool => self::forExam($question, $exam) !== null));
    }

    private static function containsId(mixed $ids, string $id): bool
    {
        return is_array($ids) && in_array($id, array_map('strval', $ids), true);
    }

    private static function normalize(string $value): string
    {
        $value = strtolower(trim($value));
        return ['cse' => 'civil-service', 'let' => 'let'][$value] ?? $value;
    }
}
