<?php

declare(strict_types=1);

final class AdaptivePriorityBuilder
{
    /**
     * @param array<int,array<string,mixed>> $questions
     * @return array<int,array<string,mixed>>
     */
    public static function build(
        array $questions,
        array $weaknesses
    ): array {
        $topics = AdaptiveWeaknessTopicResolver::resolve($weaknesses);
        $parts = AdaptiveQuestionPartitioner::partition($questions, $topics);

        return AdaptiveQuestionOrderer::merge(
            $parts["priority"],
            $parts["normal"]
        );
    }
}
