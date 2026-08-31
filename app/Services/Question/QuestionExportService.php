<?php

declare(strict_types=1);

namespace App\Services\Question;

use App\Core\App;
use App\Repositories\QuestionRepository;
use App\Services\Question\QuestionEligibilityService;

final class QuestionExportService
{
    public static function export(): void
    {
        $questions =
            App::container()
                ->get(QuestionRepository::class)
                ->all();
        $questions = array_map(static function (array $question): array {
            $eligibility = QuestionEligibilityService::metadata($question);
            if ($eligibility !== []) {
                $question['_eligibility'] = $eligibility;
            }
            return $question;
        }, $questions);

        header("Content-Type: application/json");
        header("Content-Disposition: attachment; filename=boardprep_questions.json");

        echo json_encode(
            $questions,
            JSON_PRETTY_PRINT
        );

        exit;
    }
}
