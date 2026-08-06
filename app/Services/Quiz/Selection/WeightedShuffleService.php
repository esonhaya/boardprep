<?php

declare(strict_types=1);

final class WeightedShuffleService
{
    public static function shuffle(
        array $questions
    ): array {

        shuffle(
            $questions
        );

        return array_values(
            $questions
        );

    }
}
