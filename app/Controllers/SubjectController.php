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
        self::renderDeveloper(
            "developer/subjects/index",
            SubjectViewService::index()
        );
    }

    public static function create(): void
    {
        self::renderDeveloper(
            "developer/subjects/create",
            SubjectViewService::create()
        );
    }

    public static function save(): void
    {
        SubjectService::create(
            Request::post()
        );

        self::developerRedirect(
            "subjects"
        );
    }

    public static function edit(): void
    {
        $subject =
            SubjectService::find(
                Request::get(
                    "id",
                    ""
                )
            );

        if ($subject === null) {

            self::developerRedirect(
                "subjects"
            );

            return;

        }

        self::renderDeveloper(
            "developer/subjects/edit",
            SubjectViewService::edit(
                $subject
            )
        );
    }

    public static function update(): void
    {
        SubjectService::update(
            Request::get(
                "id",
                ""
            ),
            Request::post()
        );

        self::developerRedirect(
            "subjects"
        );
    }

    public static function archive(): void
    {
        SubjectService::archive(
            Request::get(
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
            Request::get(
                "id",
                ""
            )
        );

        self::developerRedirect(
            "subjects"
        );
    }

    public static function view(): void
    {
        $subject =
            SubjectService::find(
                Request::get(
                    "id",
                    ""
                )
            );

        if ($subject === null) {

            self::developerRedirect(
                "subjects"
            );

            return;

        }

        self::renderDeveloper(
            "developer/subjects/view",
            SubjectViewService::view(
                $subject
            )
        );
    }
}
