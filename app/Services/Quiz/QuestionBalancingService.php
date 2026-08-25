<?php

declare(strict_types=1);

final class QuestionBalancingService
{
    /**
     * Balance questions by rotating across topics while preserving the
     * existing difficulty-filtering and randomized-group behavior.
     *
     * @param array<int,array<string,mixed>> $questions
     * @param array<string,mixed> $options
     * @return array<int,array<string,mixed>>
     */
    public static function balance(
        array $questions,
        array $options = []
    ): array {
        $difficulty = QuestionBalanceDifficultyResolver::resolve($options);
        $filtered = QuestionBalanceDifficultyFilter::filter($questions, $difficulty);
        $groups = QuestionBalanceGrouper::groupByTopic($filtered);
        $groups = QuestionBalanceShuffler::shuffleGroups($groups);

        return QuestionBalanceRoundRobin::balance($groups);
    }
}
