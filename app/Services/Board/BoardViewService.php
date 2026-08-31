<?php

declare(strict_types=1);

namespace App\Services\Board;

use App\Core\App;
use App\Repositories\BoardRepository;
use App\Repositories\BoardSubjectRepository;
use App\Repositories\SubjectRepository;
use App\Repositories\QuestionRepository;

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

        $allQuestions = App::container()->get(QuestionRepository::class)->all();
        $boardId = strtolower((string) ($board["id"] ?? ""));
        $boardCode = strtolower((string) ($board["code"] ?? ""));
        $questions = array_values(array_filter($allQuestions, static function (array $question) use ($boardId, $boardCode): bool {
            if (strtolower((string) ($question["status"] ?? "active")) === "archived") {
                return false;
            }
            $taxonomy = is_array($question["taxonomy"] ?? null) ? $question["taxonomy"] : [];
            $questionBoard = strtolower((string) ($taxonomy["board_id"] ?? $question["board"] ?? ""));
            return $questionBoard === $boardId || $questionBoard === $boardCode;
        }));
        $availableSubjectIds = [];
        $questionCountsBySubject = [];
        foreach ($questions as $question) {
            $taxonomy = is_array($question["taxonomy"] ?? null) ? $question["taxonomy"] : [];
            $subjectId = trim((string) ($taxonomy["subject_id"] ?? $question["subject"] ?? ""));
            if ($subjectId !== "") {
                $availableSubjectIds[$subjectId] = true;
                $questionCountsBySubject[strtolower($subjectId)] = ($questionCountsBySubject[strtolower($subjectId)] ?? 0) + 1;
            }
        }
        foreach ($board["subjects"] as &$subject) {
            $subjectId = strtolower((string) ($subject["id"] ?? ""));
            $subject["available_questions"] = $questionCountsBySubject[$subjectId] ?? 0;
        }
        unset($subject);
        $board["available_questions"] = count($questions);
        $board["available_subjects"] = count($availableSubjectIds);
        $board["study_status"] = $board["available_questions"] > 0 ? "ready" : "coming_soon";

        return $board;
    }
}
