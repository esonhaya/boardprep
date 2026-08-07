<?php

declare(strict_types=1);

namespace App\Services\Shared;

use App\Core\App;
use App\Repositories\BoardRepository;
use Exception;

final class BoardValidator
{
    public static function validate(
        array $data
    ): void {
        $name = trim(
            $data["name"] ?? ""
        );

        if ($name === "") {
            throw new Exception(
                "Board name is required."
            );
        }

        if (strlen($name) > 100) {
            throw new Exception(
                "Board name is too long."
            );
        }

        $description = trim(
            $data["description"] ?? ""
        );

        if (strlen($description) > 1000) {
            throw new Exception(
                "Board description is too long."
            );
        }

        $generatedId = strtolower(
            preg_replace(
                "/[^a-z0-9]+/",
                "-",
                $name
            )
        );

        $generatedId = trim(
            $generatedId,
            "-"
        );

        if (
            App::container()
                ->get(BoardRepository::class)
                ->exists($generatedId)
        ) {
            throw new Exception(
                "A board with this name already exists."
            );
        }
    }
}
