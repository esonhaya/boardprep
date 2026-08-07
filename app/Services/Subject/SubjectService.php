<?php

declare(strict_types=1);

namespace App\Services\Subject;

use App\Constants\Status;
use App\Core\App;
use App\Repositories\SubjectRepository;
use App\Support\Slugger;

final class SubjectService
{
    public static function all(): array
    {
        return App::container()
            ->get(SubjectRepository::class)
            ->all();
    }

    public static function find(
        string $id
    ): ?array {
        return App::container()
            ->get(SubjectRepository::class)
            ->find($id);
    }

    public static function create(
        array $data
    ): void {
        SubjectValidator::validateCreate(
            $data
        );

        App::container()
            ->get(SubjectRepository::class)
            ->create([
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
            ]);
    }

    public static function update(
        string $id,
        array $data
    ): void {
        SubjectValidator::validateUpdate(
            $id,
            $data
        );

        App::container()
            ->get(SubjectRepository::class)
            ->update(
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
        App::container()
            ->get(SubjectRepository::class)
            ->archive($id);
    }

    public static function activate(
        string $id
    ): void {
        App::container()
            ->get(SubjectRepository::class)
            ->activate($id);
    }
}
