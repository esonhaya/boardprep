<?php

declare(strict_types=1);

namespace App\Services\Board;

use App\Core\App;
use App\Repositories\BoardRepository;
use App\Services\Shared\BoardValidator;

final class BoardService
{
    public static function all(): array
    {
        return App::container()
            ->get(BoardRepository::class)
            ->all();
    }

    public static function find(
        string $id
    ): ?array {
        return App::container()
            ->get(BoardRepository::class)
            ->find($id);
    }

    public static function create(
        array $data
    ): void {
        BoardValidator::validate($data);

        $repository = App::container()
            ->get(BoardRepository::class);

        $repository->create([
            "id" => self::generateId(
                $data["name"]
            ),
            "name" => trim(
                $data["name"]
            ),
            "description" => trim(
                $data["description"] ?? ""
            ),
            "status" => "active",
            "subjects" => [],
        ]);
    }

    public static function archive(
        string $id
    ): void {
        App::container()
            ->get(BoardRepository::class)
            ->archive($id);
    }

    public static function activate(
        string $id
    ): void {
        App::container()
            ->get(BoardRepository::class)
            ->activate($id);
    }

    private static function generateId(
        string $name
    ): string {
        $id = strtolower(
            trim($name)
        );

        $id = preg_replace(
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
