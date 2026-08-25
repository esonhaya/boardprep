<?php

declare(strict_types=1);

final class AdaptiveWeaknessTopicResolver
{
    /**
     * Resolve topic names from both the current keyed weakness shape
     * and the older list-of-records shape.
     *
     * @param array<mixed> $weaknesses
     * @return array<int,string>
     */
    public static function resolve(array $weaknesses): array
    {
        $topics = [];

        foreach ($weaknesses as $key => $weakness) {
            if (is_array($weakness) && array_key_exists("topic", $weakness)) {
                $topic = AdaptiveTopicNormalizer::normalize($weakness["topic"]);
            } else {
                $topic = AdaptiveTopicNormalizer::normalize($key);
            }

            if ($topic !== "") {
                $topics[] = $topic;
            }
        }

        return array_values(array_unique($topics));
    }
}
