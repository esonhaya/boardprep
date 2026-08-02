<?php

declare(strict_types=1);

namespace App\Services\Subject;

use App\Constants\Status;
use App\Repositories\SubjectRepository;
use App\Support\Slugger;

class SubjectService
{
    public static function all(): array
    {
        return (new SubjectRepository())->all();
    }

    public static function find(
        string $id
    ): ?array {

        return (new SubjectRepository())->find(
            $id
        );

    }

    public static function create(
        array $data
    ): void {

        SubjectValidator::validateCreate(
            $data
        );

        (new SubjectRepository())->create(
            [
                "id" => Slugger::slug(
                    trim($data["name"])
                ),

                "name" => trim(
                    $data["name"]
                ),

                "description" => trim(
                    $data["description"] ?? ""
                ),

                "status" => Status::ACTIVE,
            ]
        );

    }

    public static function update(
        string $id,
        array $data
    ): void {

        SubjectValidator::validateUpdate(
            $id,
            $data
        );

        (new SubjectRepository())->update(
            $id,
            [
                "name" => trim(
                    $data["name"]
                ),

                "description" => trim(
                    $data["description"] ?? ""
                ),
            ]
        );

    }

    public static function archive(
        string $id
    ): void {

        (new SubjectRepository())->archive(
            $id
        );

    }

    public static function activate(
        string $id
    ): void {

        (new SubjectRepository())->activate(
            $id
        );

    }
}
