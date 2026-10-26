<?php

declare(strict_types=1);

/**
 * Replaces baseline random choices only with candidates from the same already
 * filtered request pool and difficulty bucket. Blueprint allocation, session
 * counts, eligibility and the non-adaptive selection path remain authoritative.
 */
final class AdaptiveQuestionSelectionService
{
    public static function apply(
        array $selected,
        array $eligiblePool,
        SelectionRequest $request,
        array $context
    ): array {
        if ($selected === [] || $eligiblePool === []
            || ((array) ($context['weak_topics'] ?? []) === []
                && (array) ($context['question_exposure'] ?? []) === [])) {
            return array_values($selected);
        }

        $slotsByDifficulty = [];
        foreach ($selected as $index => $question) {
            if (!is_array($question)) {
                continue;
            }
            $difficulty = self::difficulty($question);
            if ($difficulty !== '') {
                $slotsByDifficulty[$difficulty][] = $index;
            }
        }

        if ($slotsByDifficulty === []) {
            return array_values($selected);
        }

        $poolByDifficulty = [];
        foreach ($eligiblePool as $question) {
            if (!is_array($question)) {
                continue;
            }
            $difficulty = self::difficulty($question);
            if ($difficulty !== '') {
                $poolByDifficulty[$difficulty][] = $question;
            }
        }

        foreach ($slotsByDifficulty as $difficulty => $slots) {
            $candidates = $poolByDifficulty[$difficulty] ?? [];
            if (count($candidates) < count($slots)) {
                continue;
            }

            $ranked = [];
            foreach ($candidates as $candidate) {
                self::insertTopCandidate($ranked, [
                    'question' => $candidate,
                    'priority' => self::weaknessPriority($candidate, $request, $context),
                    'exposure' => self::exposure($candidate, $context),
                    'id' => self::questionId($candidate),
                ], count($slots));
            }

            foreach ($slots as $offset => $slot) {
                $selected[$slot] = $ranked[$offset]['question'];
            }
        }

        return array_values($selected);
    }

    /** Keep only the best candidates needed for this request's difficulty slots. */
    private static function insertTopCandidate(array &$ranked, array $candidate, int $limit): void
    {
        $count = count($ranked);
        if ($limit <= 0
            || ($count >= $limit && self::compareRanked($candidate, $ranked[$count - 1]) >= 0)) {
            return;
        }

        $low = 0;
        $high = $count;
        while ($low < $high) {
            $middle = intdiv($low + $high, 2);
            if (self::compareRanked($candidate, $ranked[$middle]) < 0) {
                $high = $middle;
            } else {
                $low = $middle + 1;
            }
        }

        array_splice($ranked, $low, 0, [$candidate]);
        if (count($ranked) > $limit) {
            array_pop($ranked);
        }
    }

    /** Negative means the left candidate should be selected first. */
    private static function compareRanked(array $left, array $right): int
    {
        $countOrder = $left['exposure']['count'] <=> $right['exposure']['count'];
        if ($countOrder !== 0) {
            return $countOrder;
        }
        $priorityOrder = $right['priority'] <=> $left['priority'];
        if ($priorityOrder !== 0) {
            return $priorityOrder;
        }
        $recencyOrder = $right['exposure']['last_attempt_index']
            <=> $left['exposure']['last_attempt_index'];
        if ($recencyOrder !== 0) {
            return $recencyOrder;
        }
        return strnatcasecmp($left['id'], $right['id']);
    }

    private static function weaknessPriority(array $question, SelectionRequest $request, array $context): int
    {
        $taxonomy = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
        $topic = strtolower(trim(self::text($taxonomy['topic_id'] ?? $question['topic'] ?? '')));
        $subject = AdaptiveQuizService::subjectKey($taxonomy['subject_id'] ?? $question['subject'] ?? '');
        if ($topic === '' || $subject === '') {
            return 0;
        }

        $priority = 0;
        foreach (is_array($context['weak_topics'] ?? null) ? $context['weak_topics'] : [] as $row) {
            if (!is_array($row)
                || strtolower(trim(self::text($row['topic'] ?? ''))) !== $topic
                || AdaptiveQuizService::boardKey($row['board'] ?? '') !== AdaptiveQuizService::boardKey($request->exam)
                || AdaptiveQuizService::subjectKey($row['subject'] ?? '') !== $subject
                || !self::variantMatches($row, $request)) {
                continue;
            }
            $priority = max($priority, max(0, (int) ($row['priority'] ?? 0)));
        }

        return $priority;
    }

    /** @return array{count:int,last_attempt_index:int} */
    private static function exposure(array $question, array $context): array
    {
        $id = self::questionId($question);
        $exposure = is_array($context['question_exposure'] ?? null)
            ? ($context['question_exposure'][$id] ?? null)
            : null;
        if (!is_array($exposure)) {
            return ['count' => 0, 'last_attempt_index' => PHP_INT_MAX];
        }

        return [
            'count' => max(0, (int) ($exposure['count'] ?? 0)),
            'last_attempt_index' => max(0, (int) ($exposure['last_attempt_index'] ?? 0)),
        ];
    }

    private static function variantMatches(array $row, SelectionRequest $request): bool
    {
        $variant = is_array($request->letVariant) ? $request->letVariant : [];
        if (strtolower(trim(self::text($row['let_level'] ?? '')))
            !== strtolower(trim(self::text($variant['level'] ?? '')))
            || strtolower(trim(self::text($row['let_specialization'] ?? '')))
            !== strtolower(trim(self::text($variant['specialization_id'] ?? '')))) {
            return false;
        }

        return strtoupper(trim(self::text($row['civil_service_level'] ?? '')))
            === strtoupper(trim((string) ($request->civilServiceLevel ?? '')));
    }

    private static function difficulty(array $question): string
    {
        return strtolower(trim(self::text($question['difficulty'] ?? '')));
    }

    private static function questionId(array $question): string
    {
        return trim(self::text($question['id'] ?? ''));
    }

    private static function text(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}
