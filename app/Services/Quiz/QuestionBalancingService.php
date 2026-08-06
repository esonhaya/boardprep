<?php

declare(strict_types=1);

final class QuestionBalancingService
{
    public static function balance(
        array $questions,
        array $options = []
    ): array {

        $difficulty =
            strtolower(
                $options["difficulty"] ?? "mixed"
            );

        if ($difficulty !== "mixed") {

            $questions = array_values(

                array_filter(

                    $questions,

                    static fn(array $question): bool =>
                        strtolower(
                            $question["difficulty"] ?? ""
                        ) === $difficulty

                )

            );

        }

        $groups = [];

        foreach ($questions as $question) {

            $topic =
                strtolower(
                    trim(
                        $question["topic"]
                        ?? "__unknown__"
                    )
                );

            $groups[$topic][] =
                $question;

        }

        foreach ($groups as &$group) {
            shuffle($group);
        }

        unset($group);

        $balanced = [];

        while (!empty($groups)) {

            foreach (array_keys($groups) as $topic) {

                if (empty($groups[$topic])) {

                    unset($groups[$topic]);

                    continue;

                }

                $balanced[] =
                    array_shift(
                        $groups[$topic]
                    );

            }

        }

        return $balanced;

    }
}
