<?php

declare(strict_types=1);

final class DifficultyBucketSelector
{
    public static function select(
        array $pool,
        array $quotas,
        int $questionCount
    ): array {

        $selected = [];
        $usedIds = [];

        foreach ($quotas as $difficulty => $quota) {

            if ($quota <= 0) {
                continue;
            }

            $matches =
                array_values(
                    array_filter(
                        $pool,
                        static function (
                            array $question
                        ) use (
                            $difficulty
                        ): bool {

                            if ($difficulty === 'mixed') {
                                return true;
                            }

                            return strtolower(
                                (string) (
                                    $question['difficulty']
                                    ?? ''
                                )
                            ) === $difficulty;
                        }
                    )
                );

            shuffle($matches);

            foreach ($matches as $question) {

                if (
                    count($selected)
                    >= $questionCount
                ) {
                    break 2;
                }

                $id =
                    (string) (
                        $question['id'] ?? ''
                    );

                if (
                    $id !== ''
                    && isset($usedIds[$id])
                ) {
                    continue;
                }

                $selected[] = $question;

                if ($id !== '') {
                    $usedIds[$id] = true;
                }

                $quota--;

                if ($quota <= 0) {
                    break;
                }
            }
        }

        return [
            'questions' => $selected,
            'usedIds' => $usedIds,
        ];
    }
}
