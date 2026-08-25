<?php

declare(strict_types=1);

final class AdaptiveQuizService
{
    /**
     * Prioritize questions matching the learner's known weak topics.
     *
     * Non-adaptive specifications retain the original question order.
     *
     * @param array<int,array<string,mixed>> $questions
     * @return array<int,array<string,mixed>>
     */
    public static function prioritize(
        array $questions,
        QuizSpecification $specification
    ): array {
        if (!$specification->adaptive) {
            return $questions;
        }

        return AdaptivePriorityBuilder::build(
            $questions,
            WeaknessService::all()
        );
    }
}
