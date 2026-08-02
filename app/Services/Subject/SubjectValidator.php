<?php

declare(strict_types=1);

namespace App\Services\Subject;

use App\Repositories\SubjectRepository;
use InvalidArgumentException;

class SubjectValidator
{
    public static function validateCreate(
        array $data
    ): void {

        self::validateName(
            $data["name"] ?? ""
        );

        $repository =
            new SubjectRepository();

        if (
            $repository->existsByName(
                trim($data["name"])
            )
        ) {

            throw new InvalidArgumentException(
                "A subject with this name already exists."
            );

        }

    }

    public static function validateUpdate(
        string $id,
        array $data
    ): void {

        self::validateName(
            $data["name"] ?? ""
        );

        $repository =
            new SubjectRepository();

        if (
            $repository->existsByName(
                trim($data["name"]),
                $id
            )
        ) {

            throw new InvalidArgumentException(
                "A subject with this name already exists."
            );

        }

    }

    private static function validateName(
        string $name
    ): void {

        $name = trim(
            $name
        );

        if ($name === "") {

            throw new InvalidArgumentException(
                "Subject name is required."
            );

        }

        if (
            strlen($name) > 100
        ) {

            throw new InvalidArgumentException(
                "Subject name must not exceed 100 characters."
            );

        }

    }
}
