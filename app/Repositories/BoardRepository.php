<?php

class BoardRepository
{

    private const FILE =
        __DIR__ . "/../../storage/boards/boards.json";

    public static function all(): array
    {

        if (!file_exists(self::FILE)) {

            return [];

        }

        $contents =
            file_get_contents(
                self::FILE
            );

        if (!$contents) {

            return [];

        }

        $boards =
            json_decode(
                $contents,
                true
            );

        if (!is_array($boards)) {

            return [];

        }

        foreach ($boards as &$board) {

            $board = self::normalize(
                $board
            );

        }

        unset($board);

        return $boards;

    }

    public static function find(
        string $id
    ): ?array
    {

        foreach (
            self::all()
            as $board
        ) {

            if (
                $board["id"] === $id
            ) {

                return $board;

            }

        }

        return null;

    }

    public static function exists(
        string $id
    ): bool
    {

        return self::find(
            $id
        ) !== null;

    }

    public static function saveAll(
        array $boards
    ): void
    {

        $directory =
            dirname(
                self::FILE
            );

        if (
            !is_dir(
                $directory
            )
        ) {

            mkdir(
                $directory,
                0777,
                true
            );

        }

        foreach ($boards as &$board) {

            $board = self::normalize(
                $board
            );

        }

        unset($board);

        file_put_contents(

            self::FILE,

            json_encode(

                $boards,

                JSON_PRETTY_PRINT
                |
                JSON_UNESCAPED_SLASHES

            )

        );

    }

    public static function setStatus(
        string $id,
        string $status
    ): void
    {

        $boards =
            self::all();

        foreach (
            $boards
            as &$board
        ) {

            if (
                $board["id"] === $id
            ) {

                $board["status"] =
                    $status;

                break;

            }

        }

        unset($board);

        self::saveAll(
            $boards
        );

    }

    private static function normalize(
        array $board
    ): array
    {

        return [

            "id" =>
                $board["id"] ?? "",

            "name" =>
                $board["name"] ?? "",

            "description" =>
                $board["description"] ?? "",

            "status" =>
                $board["status"] ?? "active",

            "subjects" =>
                $board["subjects"] ?? []

        ];

    }

}
