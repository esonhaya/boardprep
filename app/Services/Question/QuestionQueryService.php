<?php

declare(strict_types=1);

namespace App\Services\Question;

use App\Core\App;
use App\Repositories\QuestionRepository;
use App\Services\Question\Query\QuestionQueryFilters;
use App\Services\Question\Query\QuestionQueryPipeline;

class QuestionQueryService
{
    public static function getQuestions(array $filters): array
    {
        $questions = App::container()
            ->get(QuestionRepository::class)
            ->all();

        return QuestionQueryPipeline::apply(
            $questions,
            QuestionQueryFilters::from($filters)
        );
    }
}
