<?php

declare(strict_types=1);

namespace App\Services\Question;

use App\Core\App;
use App\Repositories\QuestionRepository;

class QuestionService
{
    /* KEEP EVERYTHING ABOVE validateForSave() UNCHANGED */

    public static function validateForSave(
        array $question
    ): array {

        $validation =
            self::validate(
                $question
            );

        return [

            "valid" =>
                $validation["valid"],

            "errors" =>
                $validation["errors"],

            "duplicates" =>
                self::findDuplicates(
                    $question
                )

        ];

    }

    /* KEEP EVERYTHING BELOW UNCHANGED */
}
