<?php

declare(strict_types=1);

final class QuestionImportParser
{
    public function parse(
        string $json
    ): array {

        $decoded =
            json_decode(
                $json,
                true
            );

        if (
            !is_array($decoded)
        ) {

            return [];

        }

        return array_values(
            array_filter(
                $decoded,
                "is_array"
            )
        );

    }
}
