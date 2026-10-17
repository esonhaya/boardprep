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

    /** @var array<string, array<int, array>> */
    private array $derivedByQuestion = [];

    /** @var array<string, array<string, array<int, array>>> */
    private array $overridesByQuestionBoard = [];

    /** @var array<string, array<string, true>>|null */
    private ?array $potentialQuestionIdsByExam = null;

    public function __construct(array $entries, array $blueprints = [], array $derivedEntries = [], array $overrides = [])
    {
        foreach ($entries as $entry) {
            if (!is_array($entry) || !is_array($entry['question_ids'] ?? null)) {
                continue;
            }

            foreach ($entry['question_ids'] as $questionId) {
                if (!is_scalar($questionId)) {
                    continue;
                }
                $questionEntry = $entry;
                $questionMappings = is_array($entry['question_mappings'] ?? null) ? $entry['question_mappings'] : [];
                $override = $questionMappings[(string) $questionId] ?? null;
                if (is_array($override)) {
                    if (is_array($override['exam_ids'] ?? null)) {
                        $questionEntry['exam_ids'] = $override['exam_ids'];
                    }
                    if (is_array($override['subject_ids'] ?? null)) {
                        $questionEntry['subject_ids'] = $override['subject_ids'];
                    }
                    if (is_array($override['placements'] ?? null)) {
                        $questionEntry['placements'] = $override['placements'];
                        if (!is_array($questionEntry['subject_ids'] ?? null)) {
                            $questionEntry['subject_ids'] = [];
                        }
                        foreach ($override['placements'] as $boardId => $placement) {
                            if (is_array($placement) && is_scalar($placement['subject_id'] ?? null)) {
                                $questionEntry['subject_ids'][(string) $boardId] = (string) $placement['subject_id'];
                            }
                        }
                    }
                    if (is_array($override['variant_placements'] ?? null)) {
                        $questionEntry['variant_placements'] = $override['variant_placements'];
                    }
                }
                $this->entriesByQuestion[(string) $questionId][] = $questionEntry;
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

        foreach ($derivedEntries as $entry) {
            if (is_array($entry) && ($entry['record_type'] ?? '') === 'RELATIONSHIP') {
                $this->derivedByQuestion[(string) ($entry['question_id'] ?? '')][] = $entry;
            }
        }
        foreach ($overrides as $override) {
            if (!is_array($override)) {
                continue;
            }
            $questionId = (string) ($override['question_id'] ?? '');
            $board = self::normalize((string) ($override['board_id'] ?? ''));
            if ($questionId !== '' && $board !== '') {
                $this->overridesByQuestionBoard[$questionId][$board][] = $override;
            }
        }
    }

    public function forExam(array $question, string $exam): ?array
    {
        $exam = self::normalize($exam);
        $id = (string) ($question['id'] ?? '');
        $overrides = $this->overridesByQuestionBoard[$id][$exam] ?? [];
        foreach ($overrides as $override) {
            if (($override['override_type'] ?? '') === 'EXCLUDE') {
                return null;
            }
        }

        foreach ($this->entriesByQuestion[$id] ?? [] as $entry) {
            $exams = array_map(
                [self::class, 'normalize'],
                is_array($entry['exam_ids'] ?? null) ? $entry['exam_ids'] : []
            );
            if (!in_array($exam, $exams, true)) {
                continue;
            }

            $subjects = is_array($entry['subject_ids'] ?? null) ? $entry['subject_ids'] : [];
            $placements = is_array($entry['placements'] ?? null) ? $entry['placements'] : [];
            $placement = is_array($placements[$exam] ?? null) ? $placements[$exam] : [];
            $effectiveSubject = $this->resolvedSubject(
                $question,
                $exam,
                (string) ($placement['subject_id'] ?? $subjects[$exam] ?? $entry['subject_id'] ?? '')
            );
            // Older same-board eligibility rows may list the eligible board
            // while leaving its subject placement blank. The canonical
            // taxonomy is authoritative only for that same-board case. Never
            // carry a source-board subject across a reusable placement.
            if (trim($effectiveSubject) === '') {
                $taxonomy = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
                $canonicalBoard = self::normalize((string) ($taxonomy['board_id'] ?? $question['board'] ?? ''));
                if ($canonicalBoard === $exam) {
                    $effectiveSubject = (string) ($taxonomy['subject_id'] ?? $question['subject_id'] ?? '');
                }
            }
            return [
                'exam_id' => $exam,
                'subject_id' => $effectiveSubject,
                'placement' => $placement,
                'variant_placements' => is_array($entry['variant_placements'] ?? null)
                    ? $entry['variant_placements']
                    : [],
            ];
        }

        foreach ($overrides as $override) {
            if (($override['override_type'] ?? '') === 'INCLUDE' && is_array($override['placement'] ?? null)) {
                return [
                    'exam_id' => $exam,
                    'subject_id' => (string) ($override['placement']['subject_id'] ?? ''),
                    'eligibility_source' => 'MANUAL_INCLUDE',
                    'placement' => $override['placement'],
                ];
            }
        }

        foreach ($this->derivedByQuestion[$id] ?? [] as $entry) {
            if (self::normalize((string) ($entry['target_board'] ?? '')) !== $exam) {
                continue;
            }
            $placement = is_array($entry['target_placement'] ?? null) ? $entry['target_placement'] : [];
            return [
                'exam_id' => $exam,
                'subject_id' => (string) ($placement['subject_id'] ?? ''),
                'eligibility_source' => 'CONCEPT_INHERITED',
                'relationship_id' => (string) ($entry['id'] ?? ''),
                'placement' => $placement,
            ];
        }

        $taxonomy = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
        $board = self::normalize((string) ($taxonomy['board_id'] ?? $question['board'] ?? ''));
        return $board === $exam
            ? [
                'exam_id' => $exam,
                'subject_id' => (string) ($taxonomy['subject_id'] ?? $question['subject_id'] ?? ''),
                'placement' => [],
            ]
            : null;
    }

    /**
     * Return false only when neither canonical taxonomy nor an indexed
     * eligibility record can place this question on the requested board.
     * The normal forExam() resolver remains the final eligibility authority.
     */
    public function mayBeEligibleForExam(array $question, string $exam): bool
    {
        $exam = self::normalize($exam);
        if ($exam === '') {
            return true;
        }

        $taxonomy = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
        $canonicalBoard = self::normalize((string) ($taxonomy['board_id'] ?? $question['board'] ?? ''));
        if ($canonicalBoard === $exam) {
            return true;
        }

        $questionId = is_scalar($question['id'] ?? null) ? (string) $question['id'] : '';
        if ($questionId === '') {
            return false;
        }

        $candidateIdsByExam = $this->potentialQuestionIdsByExam();
        return isset($candidateIdsByExam[$exam][$questionId]);
    }

    /**
     * Return a per-exam candidate ID set suitable for a later exact
     * `forExam()` check.  This is deliberately an over-inclusive index:
     * excluded IDs may be present, and the production resolver remains the
     * final authority.  It lets Repository Health avoid feeding unrelated
     * questions through runtime normalization and eligibility checks once
     * per board.
     *
     * @return array<string, array<string, true>>
     */
    public function potentialQuestionIdsByExam(): array
    {
        if ($this->potentialQuestionIdsByExam !== null) {
            return $this->potentialQuestionIdsByExam;
        }

        $idsByExam = [];
        foreach ($this->entriesByQuestion as $questionId => $entries) {
            foreach ($entries as $entry) {
                foreach ((array) ($entry['exam_ids'] ?? []) as $exam) {
                    if (is_scalar($exam)) {
                        $normalizedExam = self::normalize((string) $exam);
                        if ($normalizedExam !== '') {
                            $idsByExam[$normalizedExam][$questionId] = true;
                        }
                    }
                }
            }
        }
        foreach ($this->overridesByQuestionBoard as $questionId => $boards) {
            foreach ($boards as $boardId => $_overrides) {
                $idsByExam[$boardId][$questionId] = true;
            }
        }
        foreach ($this->derivedByQuestion as $questionId => $entries) {
            foreach ($entries as $entry) {
                $exam = self::normalize((string) ($entry['target_board'] ?? ''));
                if ($exam !== '') {
                    $idsByExam[$exam][$questionId] = true;
                }
            }
        }
        ksort($idsByExam, SORT_STRING);
        foreach ($idsByExam as &$questionIds) {
            ksort($questionIds, SORT_STRING);
        }
        unset($questionIds);
        return $this->potentialQuestionIdsByExam = $idsByExam;
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
        return [
            'cse' => 'civil-service',
            'let' => 'let',
            'cle' => 'criminologist',
            'nle' => 'nursing',
            'ple' => 'psychometrician',
            'ce' => 'civil-engineering',
            'me' => 'mechanical-engineering',
            'ree' => 'registered-electrical-engineering',
            'arch' => 'architecture',
        ][$value] ?? $value;
    }
}
