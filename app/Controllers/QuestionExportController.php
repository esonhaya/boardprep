<?php

declare(strict_types=1);

use App\Core\App;
use App\Repositories\QuestionRepository;

class QuestionExportController
{
    public static function export(): void
    {

        $questions =
            App::container()
                ->get(
                    QuestionRepository::class
                )
                ->all();

        header(
            "Content-Type: application/json"
        );

        header(
            "Content-Disposition: attachment; filename=boardprep_questions.json"
        );

        echo json_encode(

            $questions,

            JSON_PRETTY_PRINT

        );

        exit;

    }
}
