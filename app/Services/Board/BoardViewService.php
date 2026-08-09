<?php

declare(strict_types=1);

namespace App\Services\Board;

use App\Core\App;
use App\Repositories\BoardRepository;
use App\Repositories\BoardSubjectRepository;
use App\Repositories\SubjectRepository;

final class BoardViewService
{
    public static function all(): array
    {
        $boards = App::container()
            ->get(BoardRepository::class)
            ->all();

        return array_map(
            [self::class, "withSubjects"],
            $boards
        );
    }

    public static function find(
        string $id
    ): ?array {
        $board = App::container()
            ->get(BoardRepository::class)
            ->find($id);

        if ($board === null) {
            return null;
        }

        return self::withSubjects($board);
    }

    private static function withSubjects(
        array $board
    ): array {
        $relations = App::container()
            ->get(BoardSubjectRepository::class)
            ->where([
                "board_id" => $board["id"],
            ]);

        $subjects = App::container()
            ->get(SubjectRepository::class);

        $board["subjects"] = array_values(
            array_filter(
                array_map(
                    static function (array $relation) use ($subjects): ?array {
                        $subject = $subjects->find(
                            (string) ($relation["subject_id"] ?? "")
                        );

                        if ($subject === null) {
                            return null;
                        }

                        return [
                            "id" => $subject["id"],
                            "code" => $subject["code"] ?? "",
                            "name" => $subject["name"] ?? "",
                            "settings" => $relation["settings"] ?? [],
                        ];
                    },
                    $relations
                )
            )
        );

        return $board;
    }
}
