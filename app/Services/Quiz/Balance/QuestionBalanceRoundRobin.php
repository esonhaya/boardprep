<?php

declare(strict_types=1);

final class QuestionBalanceRoundRobin
{
    /**
     * @param array<string,array<int,array<string,mixed>>> $groups
     * @return array<int,array<string,mixed>>
     */
    public static function balance(array $groups): array
    {
        $balanced = [];

        while ($groups !== []) {
            foreach (array_keys($groups) as $topic) {
                if ($groups[$topic] === []) {
                    unset($groups[$topic]);
                    continue;
                }

                $balanced[] = array_shift($groups[$topic]);
            }
        }

        return $balanced;
    }
}
