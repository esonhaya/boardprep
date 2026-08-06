<?php

declare(strict_types=1);

namespace Tools\Doctor\Architecture;

final class ArchitectureDecisionRecorder
{
    private const FILE =
        './storage/doctor/architecture-log.json';

    public function record(
        string $title,
        string $category,
        string $description
    ): void {

        $entries = [];

        if (is_file(self::FILE)) {

            $entries =
                json_decode(
                    file_get_contents(self::FILE),
                    true
                ) ?? [];

        }

        $entries[] = [

            'timestamp' =>
                date(DATE_ATOM),

            'title' =>
                $title,

            'category' =>
                $category,

            'description' =>
                $description,

        ];

        file_put_contents(

            self::FILE,

            json_encode(

                $entries,

                JSON_PRETTY_PRINT
                | JSON_UNESCAPED_SLASHES

            )

        );

    }

    public function all(): array
    {
        if (!is_file(self::FILE)) {
            return [];
        }

        return json_decode(
            file_get_contents(self::FILE),
            true
        ) ?? [];
    }
}
