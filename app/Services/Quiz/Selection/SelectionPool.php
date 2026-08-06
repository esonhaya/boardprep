<?php

declare(strict_types=1);

final class SelectionPool
{
    public static function create(
        array $questions,
        SelectionRequest $request
    ): array {

        return array_values(

            array_filter(

                $questions,

                static function (
                    array $question
                ) use (
                    $request
                ): bool {

                    if (
                        $request->domain !== null &&
                        ($question["domain"] ?? null) !== $request->domain
                    ) {
                        return false;
                    }

                    if (
                        $request->topic !== null &&
                        ($question["topic"] ?? null) !== $request->topic
                    ) {
                        return false;
                    }

                    if (
                        $request->concept !== null &&
                        ($question["concept"] ?? null) !== $request->concept
                    ) {
                        return false;
                    }

                    return true;

                }

            )

        );

    }
}
