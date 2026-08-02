<?php

declare(strict_types=1);

namespace App\Services\Subject;

class SubjectViewService
{
    public static function index(): array
    {
        return [
            "subjects" => SubjectService::all(),
        ];
    }

    public static function create(): array
    {
        return [];
    }

    public static function edit(
        array $subject
    ): array {

        return [
            "subject" => $subject,
        ];

    }

    public static function view(
        array $subject
    ): array {

        return [
            "subject" => $subject,
        ];

    }
}
