<?php

class RecommendationService
{
    public static function nextTopic(
        array $mastery
    ): ?string
    {
        if (empty($mastery)) {
            return null;
        }

        uasort(
            $mastery,
            function ($a, $b) {
                return $a["percentage"] <=> $b["percentage"];
            }
        );

        return array_key_first($mastery);
    }

    public static function weakestTopics(
        array $mastery,
        int $limit = 3
    ): array
    {
        uasort(
            $mastery,
            function ($a, $b) {
                return $a["percentage"] <=> $b["percentage"];
            }
        );

        return array_slice(
            array_keys($mastery),
            0,
            $limit
        );
    }
}
