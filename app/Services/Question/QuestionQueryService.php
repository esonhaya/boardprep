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

    /** @return array{items: array<int, array<string, mixed>>, total: int, page: int, per_page: int, pages: int} */
    public static function getQuestionPage(array $filters, int $page = 1, int $perPage = 50): array
    {
        $perPage = max(1, min(100, $perPage));
        $all = self::getQuestions($filters);
        $total = count($all);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($page, $pages));
        return [
            'items' => array_slice($all, ($page - 1) * $perPage, $perPage),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'pages' => $pages,
        ];
    }
}
