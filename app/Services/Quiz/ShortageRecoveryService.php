<?php

declare(strict_types=1);

final class ShortageRecoveryService
{
    public static function recover(
        array $selected,
        array $pool,
        int $required
    ): array {

        if (count($selected) >= $required) {
            return array_slice($selected, 0, $required);
        }

        $used = [];

        foreach ($selected as $question) {
            if (isset($question["id"])) {
                $used[$question["id"]] = true;
            }
        }

        foreach ($pool as $question) {

            $id = $question["id"] ?? null;

            if (
                $id !== null &&
                isset($used[$id])
            ) {
                continue;
            }

            $selected[] = $question;

            if ($id !== null) {
                $used[$id] = true;
            }

            if (count($selected) >= $required) {
                break;
            }

        }

        return array_slice($selected, 0, $required);

    }
}
