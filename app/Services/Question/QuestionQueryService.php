<?php

declare(strict_types=1);

namespace App\Services\Question;

use App\Core\App;
use App\Repositories\QuestionRepository;
use App\Services\Question\Query\QuestionQueryFilters;
use App\Services\Question\Query\QuestionQueryPipeline;
use App\Services\Shared\TaxonomyStorageService;

class QuestionQueryService
{
    public static function getQuestions(array $filters): array
    {
        $questions = App::container()
            ->get(QuestionRepository::class)
            ->all();

        return self::applyFilters(
            $questions,
            QuestionQueryFilters::from($filters)
        );
    }

    /** @return array{items: array<int, array<string, mixed>>, total: int, canonical_total: int, page: int, per_page: int, pages: int} */
    public static function getQuestionPage(array $filters, int $page = 1, int $perPage = 50): array
    {
        $perPage = max(1, min(100, $perPage));
        $repositorySnapshot = App::container()
            ->get(QuestionRepository::class)
            ->all();
        $canonicalTotal = count($repositorySnapshot);
        $all = self::applyFilters($repositorySnapshot, QuestionQueryFilters::from($filters));
        $total = count($all);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($page, $pages));
        return [
            'items' => array_slice($all, ($page - 1) * $perPage, $perPage),
            'total' => $total,
            'canonical_total' => $canonicalTotal,
            'page' => $page,
            'per_page' => $perPage,
            'pages' => $pages,
        ];
    }

    /**
     * Apply board and subject filters through the same effective eligibility
     * resolver used by learner pools. A taxonomy label alone never grants a
     * board placement.
     *
     * @param array<int,array<string,mixed>> $questions
     * @return array<int,array<string,mixed>>
     */
    private static function applyFilters(array $questions, QuestionQueryFilters $filters): array
    {
        if ($filters->search !== '') {
            foreach ($questions as $question) {
                if (is_array($question) && is_scalar($question['id'] ?? null)
                    && strcasecmp(trim((string) $question['id']), trim($filters->search)) === 0) {
                    // An exact canonical ID is an unambiguous lookup, even if
                    // the ID is short and appears in other question text.
                    $questions = [$question];
                    $filters = QuestionQueryFilters::from([
                        'domain' => $filters->domain,
                        'difficulty' => $filters->difficulty,
                        'topic' => $filters->topic,
                        'status' => $filters->status,
                        'board' => $filters->board,
                        'subject' => $filters->subject,
                    ]);
                    break;
                }
            }
        }

        $matches = QuestionQueryPipeline::apply($questions, $filters);
        if ($filters->board === '' && $filters->subject === '') {
            return $matches;
        }

        $boards = $filters->board !== ''
            ? [$filters->board]
            : array_values(array_filter(array_map(
                static fn(array $board): string => is_scalar($board['id'] ?? null) ? trim((string) $board['id']) : '',
                TaxonomyStorageService::boards()
            )));
        $index = QuestionEligibilityService::index();
        $subjectFilter = strtolower(trim($filters->subject));

        return array_values(array_filter($matches, static function (array $question) use ($boards, $index, $subjectFilter): bool {
            foreach ($boards as $board) {
                $placement = $index->forExam($question, $board);
                if ($placement === null) {
                    continue;
                }
                if ($subjectFilter === ''
                    || strtolower(trim((string) ($placement['subject_id'] ?? ''))) === $subjectFilter) {
                    return true;
                }
            }
            return false;
        }));
    }
}
