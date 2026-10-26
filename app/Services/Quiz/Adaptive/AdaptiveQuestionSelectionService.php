<?php

declare(strict_types=1);

/**
 * Replaces baseline random choices only with candidates from the same already
 * filtered request pool and difficulty bucket. Blueprint allocation, session
 * counts, eligibility and the non-adaptive selection path remain authoritative.
 */
final class AdaptiveQuestionSelectionService
{
    private const RECENT_EXPOSURE_PENALTY_PER_ATTEMPT = 12;
    private const MOST_RECENT_EXPOSURE_PENALTY = 12;
    private const TOPIC_CONCENTRATION_PENALTY = 18;
    private const MAX_TOPIC_CONCENTRATION_PENALTY = 36;

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
        $seenPoolIds = [];
        foreach ($eligiblePool as $question) {
            if (!is_array($question)) {
                continue;
            }
            $id = self::questionId($question);
            if ($id === '' || isset($seenPoolIds[$id])) {
                continue;
            }
            $seenPoolIds[$id] = true;
            $difficulty = self::difficulty($question);
            if ($difficulty !== '') {
                $topicKey = self::topicKey($question);
                $groupKey = $topicKey !== '' ? 'topic:' . $topicKey : 'unclassified';
                $poolByDifficulty[$difficulty][$groupKey][] = [
                    'question' => $question,
                    'base_score' => self::baseScore($question, $request, $context),
                    'exposure' => self::exposure($question, $context),
                    'topic_key' => $topicKey,
                    'id' => $id,
                ];
            }
        }

        // Preserve fallback slots whose eligible difficulty pool is too small.
        // Their IDs must remain reserved when another bucket is ranked.
        $fixedSlots = [];
        foreach ($slotsByDifficulty as $difficulty => $slots) {
            $candidateCount = 0;
            foreach ($poolByDifficulty[$difficulty] ?? [] as $group) {
                $candidateCount += count($group);
            }
            if ($candidateCount < count($slots)) {
                foreach ($slots as $slot) {
                    $id = self::questionId($selected[$slot] ?? []);
                    if ($id !== '') {
                        $fixedSlots[$id] = true;
                    }
                }
            }
        }

        $usedIds = $fixedSlots;
        $selectedTopicCounts = [];
        foreach ($slotsByDifficulty as $difficulty => $slots) {
            $candidateGroups = $poolByDifficulty[$difficulty] ?? [];
            $candidateCount = 0;
            foreach ($candidateGroups as &$group) {
                usort($group, static fn(array $left, array $right): int =>
                    ($right['base_score'] <=> $left['base_score'])
                    ?: self::compareTieBreak($left, $right)
                );
                $candidateCount += count($group);
            }
            unset($group);
            if ($candidateCount < count($slots)) {
                continue;
            }

            $groupPositions = array_fill_keys(array_keys($candidateGroups), 0);
            foreach ($slots as $slot) {
                $bestIndex = null;
                $bestGroup = null;
                $bestScore = PHP_INT_MIN;
                foreach ($candidateGroups as $groupKey => $group) {
                    $position = $groupPositions[$groupKey];
                    while (isset($group[$position]) && isset($usedIds[$group[$position]['id']])) {
                        $position++;
                    }
                    $groupPositions[$groupKey] = $position;
                    if (!isset($group[$position])) {
                        continue;
                    }
                    $candidate = $group[$position];
                    $topicCount = $candidate['topic_key'] !== ''
                        ? (int) ($selectedTopicCounts[$candidate['topic_key']] ?? 0)
                        : 0;
                    $topicPenalty = min(
                        self::MAX_TOPIC_CONCENTRATION_PENALTY,
                        $topicCount * self::TOPIC_CONCENTRATION_PENALTY
                    );
                    $score = $candidate['base_score'] - $topicPenalty;
                    if ($bestIndex === null || $score > $bestScore
                        || ($score === $bestScore && self::compareTieBreak($candidate, $candidateGroups[$bestGroup][$bestIndex]) < 0)) {
                        $bestIndex = $position;
                        $bestGroup = $groupKey;
                        $bestScore = $score;
                    }
                }
                if ($bestIndex === null || $bestGroup === null) {
                    continue;
                }
                $candidate = $candidateGroups[$bestGroup][$bestIndex];
                $selected[$slot] = $candidate['question'];
                $usedIds[$candidate['id']] = true;
                $groupPositions[$bestGroup] = $bestIndex + 1;
                if ($candidate['topic_key'] !== '') {
                    $selectedTopicCounts[$candidate['topic_key']] = ($selectedTopicCounts[$candidate['topic_key']] ?? 0) + 1;
                }
            }
        }

        return array_values($selected);
    }

    private static function baseScore(array $question, SelectionRequest $request, array $context): int
    {
        $exposure = self::exposure($question, $context);
        $countPenalty = min(3, $exposure['count']) * self::RECENT_EXPOSURE_PENALTY_PER_ATTEMPT;
        $recencyPenalty = $exposure['count'] === 0
            ? 0
            : ($exposure['last_attempt_index'] === 0
                ? self::MOST_RECENT_EXPOSURE_PENALTY
                : ($exposure['last_attempt_index'] <= 2 ? intdiv(self::MOST_RECENT_EXPOSURE_PENALTY, 2) : 0));

        return self::weaknessPriority($question, $request, $context) - $countPenalty - $recencyPenalty;
    }

    /** Negative means the left candidate wins a tied relevance/diversity score. */
    private static function compareTieBreak(array $left, array $right): int
    {
        $countOrder = $left['exposure']['count'] <=> $right['exposure']['count'];
        if ($countOrder !== 0) {
            return $countOrder;
        }
        $priorityOrder = $right['base_score'] <=> $left['base_score'];
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

    private static function topicKey(array $question): string
    {
        $taxonomy = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
        $topic = strtolower(trim(self::text($taxonomy['topic_id'] ?? $question['topic'] ?? '')));
        $subject = AdaptiveQuizService::subjectKey($taxonomy['subject_id'] ?? $question['subject'] ?? '');
        return $topic === '' || $subject === '' ? '' : $subject . "\0" . $topic;
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
