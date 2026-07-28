<?php

abstract class JsonRepository
{
    protected const FILE = '';

    protected static function allRows(): array
    {
        if (!file_exists(static::FILE)) {
            return [];
        }

        $rows = json_decode(
            file_get_contents(static::FILE),
            true
        );

        return is_array($rows)
            ? $rows
            : [];
    }

    protected static function saveRows(
        array $rows
    ): void {

        $directory = dirname(
            static::FILE
        );

        if (!is_dir($directory)) {

            mkdir(
                $directory,
                0777,
                true
            );

        }

        file_put_contents(

            static::FILE,

            json_encode(

                array_values($rows),

                JSON_PRETTY_PRINT
                |
                JSON_UNESCAPED_SLASHES

            )

        );

    }

    protected static function findRow(
        string $id
    ): ?array {

        foreach (
            static::allRows()
            as $row
        ) {

            if (
                ($row["id"] ?? "")
                ===
                $id
            ) {

                return $row;

            }

        }

        return null;

    }

    protected static function existsRow(
        string $id
    ): bool {

        return
            static::findRow($id)
            !==
            null;

    }

    protected static function updateRow(
        string $id,
        callable $callback
    ): void {

        $rows =
            static::allRows();

        foreach (
            $rows
            as &$row
        ) {

            if (
                ($row["id"] ?? "")
                !==
                $id
            ) {
                continue;
            }

            $row =
                $callback($row);

            break;

        }

        static::saveRows(
            $rows
        );

    }

    protected static function deleteRow(
        string $id
    ): void {

        static::saveRows(

            array_filter(

                static::allRows(),

                fn($row) =>
                    ($row["id"] ?? "")
                    !==
                    $id

            )

        );

    }

    protected static function appendRow(
        array $row
    ): void {

        $rows =
            static::allRows();

        $rows[] =
            $row;

        static::saveRows(
            $rows
        );

    }
}
