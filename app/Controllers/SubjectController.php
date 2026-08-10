<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Services\Subject\SubjectService;
use App\Services\Subject\SubjectViewService;

class SubjectController extends BaseDeveloperController
{
    public static function index(): void
    {
        $search =
            trim((string) Request::query(
                "search",
                ""
            ));

        $subjects =
            SubjectViewService::all();

        if ($search !== "") {
            $needle =
                strtolower($search);

            $subjects =
                array_values(
                    array_filter(
                        $subjects,
                        static function (array $subject) use ($needle): bool {
                            foreach (
                                [
                                    "id",
                                    "code",
                                    "name",
                                    "description",
                                ] as $field
                            ) {
                                if (
                                    str_contains(
                                        strtolower(
                                            (string) ($subject[$field] ?? "")
                                        ),
                                        $needle
                                    )
                                ) {
                                    return true;
                                }
                            }

                            return false;
                        }
                    )
                );
        }

        self::renderDeveloper(
            "developer/subjects/index",
            [
                "subjects" => $subjects,
                "search" => $search,
            ]
        );
    }

    public static function create(): void
    {
        self::renderDeveloper(
            "developer/subjects/create"
        );
    }

    public static function save(): void
    {
        SubjectService::create(
            Request::input()
        );

        self::developerRedirect(
            "subjects"
        );
    }

    public static function edit(): void
    {
        $subject =
            SubjectService::find(
                (string) Request::query(
                    "id",
                    ""
                )
            );

        if ($subject === null) {
            self::developerRedirect(
                "subjects"
            );
        }

        self::renderDeveloper(
            "developer/subjects/edit",
            [
                "subject" => $subject,
            ]
        );
    }

    public static function update(): void
    {
        SubjectService::update(
            (string) Request::query(
                "id",
                ""
            ),
            Request::input()
        );

        self::developerRedirect(
            "subjects"
        );
    }

    public static function archive(): void
    {
        SubjectService::archive(
            (string) Request::query(
                "id",
                ""
            )
        );

        self::developerRedirect(
            "subjects"
        );
    }

    public static function activate(): void
    {
        SubjectService::activate(
            (string) Request::query(
                "id",
                ""
            )
        );

        self::developerRedirect(
            "subjects"
        );
    }

    public static function show(): void
    {
        $subject =
            SubjectService::find(
                (string) Request::query(
                    "id",
                    ""
                )
            );

        if ($subject === null) {
            self::developerRedirect(
                "subjects"
            );
        }

        self::renderDeveloper(
            "developer/subjects/view",
            [
                "subject" => $subject,
            ]
        );
    }
}
