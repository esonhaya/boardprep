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
        return App::container()->get(BlueprintRepository::class)->all();
    }

    public static function create(
        array $data
    ): array {

        $board = trim(
            $data["board"] ?? ""
        );

        $subject = trim(
            $data["subject"] ?? ""
        );

        $name = trim(
            $data["name"] ?? ""
        );

        $version = self::nextVersion(
            $board,
            $subject
        );

        $id = self::generateId(
            $board,
            $subject,
            $version
        );

        $blueprint = [
            "id" => $id,
            "board" => $board,
            "subject" => $subject,
            "name" => $name,
            "version" => $version,

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

        App::container()->get(BlueprintRepository::class)->create(
            $blueprint
        );

        return [
            "success" => true,
            "blueprint" => $blueprint,
        ];
    }

    private static function generateId(
        string $board,
        string $subject,
        int $version
    ): string {

        $board = preg_replace(
            '/\s+/',
            '-',
            trim($board)
        );

        $subject = preg_replace(
            '/\s+/',
            '-',
            trim($subject)
        );

        return strtolower(
            $board
            . "-"
            . $subject
            . "-v"
            . $version
        );
    }

    private static function nextVersion(
        string $board,
        string $subject
    ): int {

        $highest = 0;

        foreach (
            App::container()->get(BlueprintRepository::class)->all()
            as $blueprint
        ) {

            if (
                ($blueprint["board"] ?? "") === $board
                &&
                ($blueprint["subject"] ?? "") === $subject
            ) {
                $highest = max(
                    $highest,
                    (int) (
                        $blueprint["version"] ?? 0
                    )
                );
            }
        }

        return $highest + 1;
    }
}
