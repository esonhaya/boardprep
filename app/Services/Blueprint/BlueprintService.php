<?php

declare(strict_types=1);

namespace App\Services\Blueprint;

use App\Core\App;
use App\Repositories\BlueprintRepository;
use App\Services\Shared\BlueprintValidator;

class BlueprintService
{
    public static function all(): array
    {
        return App::container()
            ->get(BlueprintRepository::class)
            ->all();
    }

    public static function create(array $data): array
    {
        $boardId = trim(
            (string) ($data["board"] ?? "")
        );

        $subjectId = trim(
            (string) ($data["subject"] ?? "")
        );

        $name = trim(
            (string) ($data["name"] ?? "")
        );

        $version = self::nextVersion(
            $boardId,
            $subjectId
        );

        $id = self::generateId(
            $boardId,
            $subjectId,
            $version
        );

        $blueprint = [
            "id" => $id,

            "scope" => "subject",

            "board_id" => $boardId,

            "subject_id" => $subjectId,

            "board" => $boardId,

            "subject" => $subjectId,

            "name" => $name,

            "version" => $version,

            "status" => "active",

            "questionCount" => (int) (
                $data["questionCount"] ?? 0
            ),

            "difficulty" => [
                "easy" => (int) (
                    $data["easy"] ?? 0
                ),

                "medium" => (int) (
                    $data["medium"] ?? 0
                ),

                "hard" => (int) (
                    $data["hard"] ?? 0
                ),
            ],

            "topicWeights" => [],

            "conceptWeights" => [],
        ];

        $validation =
            BlueprintValidator::validate(
                $blueprint
            );

        if (!$validation["valid"]) {

            return [
                "success" => false,
                "errors" => $validation["errors"],
            ];

        }

        App::container()
            ->get(BlueprintRepository::class)
            ->create($blueprint);

        return [
            "success" => true,
            "blueprint" => $blueprint,
        ];
    }

    private static function generateId(
        string $boardId,
        string $subjectId,
        int $version
    ): string {

        $boardId = preg_replace(
            '/[^a-zA-Z0-9_-]+/',
            '-',
            trim($boardId)
        ) ?? "board";

        $subjectId = preg_replace(
            '/[^a-zA-Z0-9_-]+/',
            '-',
            trim($subjectId)
        ) ?? "subject";

        return strtolower(
            $boardId
            . "-"
            . $subjectId
            . "-v"
            . $version
        );
    }

    private static function nextVersion(
        string $boardId,
        string $subjectId
    ): int {

        $highest = 0;

        $repository =
            App::container()
                ->get(BlueprintRepository::class);

        foreach (
            $repository->versions(
                $boardId,
                $subjectId
            ) as $blueprint
        ) {

            $highest = max(
                $highest,
                (int) (
                    $blueprint["version"] ?? 0
                )
            );

        }

        return $highest + 1;
    }
}
