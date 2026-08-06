<?php

declare(strict_types=1);

final class CoverageTracker
{
    private array $coverage = [];

    public function add(
        array $questions
    ): void {

        foreach ($questions as $question) {

            foreach (

                [
                    "subject",
                    "domain",
                    "topic",
                    "concept",
                    "difficulty",
                ]

                as $field

            ) {

                $value =
                    strtolower(
                        (string) (
                            $question[$field]
                            ?? "__unknown__"
                        )
                    );

                $this->coverage[$field][$value] =
                    ($this->coverage[$field][$value] ?? 0)
                    + 1;

            }

        }

    }

    public function count(
        string $field,
        string $value
    ): int {

        return

            $this->coverage
                [strtolower($field)]
                [strtolower($value)]

            ??

            0;

    }

    public function all(): array
    {

        return $this->coverage;

    }
}
