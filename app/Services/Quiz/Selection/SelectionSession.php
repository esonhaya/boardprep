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
                    mixed $question
                ): bool {

                    if (!is_array($question)) {
                        return false;
                    }

                    $id =
                        $question["id"] ?? null;

                    if ($id === null || !is_scalar($id) || trim((string) $id) === '') {
                        return true;
                    }

                    return !isset(
                        $this->selectedIds[(string) $id]
                    );

                }

            )

        );

    }

    public function reserve(
        array $questions
    ): void {

        foreach ($questions as $question) {

            if (is_array($question) && is_scalar($question["id"] ?? null) && trim((string) $question["id"]) !== '') {

                $this->selectedIds[
                    (string) $question["id"]
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
