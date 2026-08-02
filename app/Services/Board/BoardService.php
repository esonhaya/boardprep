<?php

class BoardService
{

    public static function all(): array
    {

        return BoardRepository::all();

    }

    public static function find(
        string $id
    ): ?array
    {

        return BoardRepository::find(
            $id
        );

    }

    public static function create(
        array $data
    ): void
    {

        BoardValidator::validate(
            $data
        );

        $boards =
            BoardRepository::all();

        $boards[] = [

            "id" =>
                self::generateId(
                    $data["name"]
                ),

            "name" =>
                trim($data["name"]),

            "description" =>
                trim($data["description"] ?? ""),

            "status" =>
                "active",

            "subjects" =>
                []

        ];

        BoardRepository::saveAll(
            $boards
        );

    }

    public static function archive(
        string $id
    ): void
    {

        BoardRepository::setStatus(
            $id,
            "archived"
        );

    }

    public static function activate(
        string $id
    ): void
    {

        BoardRepository::setStatus(
            $id,
            "active"
        );

    }

    private static function generateId(
        string $name
    ): string
    {

        $id =
            strtolower(
                trim($name)
            );

        $id =
            preg_replace(
                "/[^a-z0-9]+/",
                "-",
                $id
            );

        return trim(
            $id,
            "-"
        );

    }

}
