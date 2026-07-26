<?php

class SubjectRepository
{

    private const FILE =
        __DIR__ .
        "/../../storage/subjects/subjects.json";


    public static function all(): array
    {

        if (!file_exists(self::FILE)) {

            return [];

        }


        return json_decode(
            file_get_contents(self::FILE),
            true
        ) ?? [];

    }

}
