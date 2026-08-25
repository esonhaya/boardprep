<?php

declare(strict_types=1);

final class QuestionBalanceShuffler
{
    /**
     * @param array<string,array<int,array<string,mixed>>> $groups
     * @return array<string,array<int,array<string,mixed>>>
     */
    public static function shuffleGroups(array $groups): array
    {
        foreach ($groups as &$group) {
            shuffle($group);
        }

        unset($group);

        return $groups;
    }
}
