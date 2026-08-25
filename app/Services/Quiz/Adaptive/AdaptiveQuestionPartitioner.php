<?php

declare(strict_types=1);

final class AdaptiveQuestionPartitioner
{
    /**
     * @param array<int,array<string,mixed>> $questions
     * @param array<int,string> $priorityTopics
     * @return array{priority: array<int,array<string,mixed>>, normal: array<int,array<string,mixed>>}
     */
    public static function partition(array $questions, array $priorityTopics): array
    {
        $priority = [];
        $normal = [];

        foreach ($questions as $question) {
            $topic = AdaptiveTopicNormalizer::normalize($question["topic"] ?? "");

            if (in_array($topic, $priorityTopics, true)) {
                $priority[] = $question;
            } else {
                $normal[] = $question;
            }
        }

        return [
            "priority" => $priority,
            "normal" => $normal,
        ];
    }
}
