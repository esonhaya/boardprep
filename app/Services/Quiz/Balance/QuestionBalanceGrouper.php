<?php

declare(strict_types=1);

final class QuestionBalanceGrouper
{
    /**
     * @param array<int,array<string,mixed>> $questions
     * @return array<string,array<int,array<string,mixed>>>
     */
    public static function groupByTopic(array $questions): array
    {
        $groups = [];

        foreach ($questions as $question) {
            $topic = QuestionBalanceTopicResolver::resolve($question);
            $groups[$topic][] = $question;
        }

        return $groups;
    }
}
