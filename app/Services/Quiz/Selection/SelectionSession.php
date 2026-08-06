<?php

declare(strict_types=1);

final class SelectionSession
{
    private array $selectedIds = [];

    public function available(
        array $questions
    ): array {

        return array_values(

            array_filter(

                $questions,

                function (
                    array $question
                ): bool {

                    $id =
                        $question["id"] ?? null;

                    if ($id === null) {
                        return true;
                    }

                    return !isset(
                        $this->selectedIds[$id]
                    );

                }

            )

        );

    }

    public function reserve(
        array $questions
    ): void {

        foreach ($questions as $question) {

            if (
                isset($question["id"])
            ) {

                $this->selectedIds[
                    $question["id"]
                ] = true;

            }

        }

    }

    public function selectedIds(): array
    {

        return array_keys(
            $this->selectedIds
        );

    }
}
