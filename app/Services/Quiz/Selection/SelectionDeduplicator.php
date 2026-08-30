<?php

declare(strict_types=1);

final class SelectionDeduplicator
{
    public static function unique(
        array $questions
    ): array {

        $seen = [];
        $unique = [];

        foreach ($questions as $question) {

            if (!is_array($question)) {
                continue;
            }

            $id =
                $question["id"] ?? null;

            if ($id !== null && !is_scalar($id)) {
                continue;
            }

            if ($id !== null && trim((string) $id) === '') {
                continue;
            }

            $key = $id === null ? null : (string) $id;

            if (
                $key !== null &&
                isset($seen[$key])
            ) {
                continue;
            }

            if ($key !== null) {
                $seen[$key] = true;
            }

            $unique[] = $question;

        }

        return $unique;

    }
}
