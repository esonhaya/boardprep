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

            $id =
                $question["id"] ?? null;

            if (
                $id !== null &&
                isset($seen[$id])
            ) {
                continue;
            }

            if ($id !== null) {
                $seen[$id] = true;
            }

            $unique[] = $question;

        }

        return $unique;

    }
}
