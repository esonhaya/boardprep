<?php

declare(strict_types=1);

namespace App\Services\Question;

use App\Core\App;
use App\Repositories\QuestionRepository;
use App\Services\Question\Query\QuestionQueryFilters;
use App\Services\Question\Query\QuestionQueryPipeline;
use App\Services\Question\Query\QuestionValueReader;

final class QuestionSearchService
{
    private static function repository(): QuestionRepository
    {
        return App::container()->get(QuestionRepository::class);
    }

    public static function search(string $keyword): array
    {
        return QuestionQueryPipeline::apply(
            self::repository()->all(),
            QuestionQueryFilters::from(['search' => $keyword])
        );
    }

    public static function filter(
        string $domainId,
        string $difficulty,
        string $topicId
    ): array {
        return QuestionQueryPipeline::apply(
            self::repository()->all(),
            QuestionQueryFilters::from([
                'domain' => $domainId,
                'difficulty' => $difficulty,
                'topic' => $topicId,
            ])
        );
    }

    public static function bySubject(string $subjectId): array
    {
        return self::taxonomyMatch('subject_id', $subjectId);
    }

    public static function byTopic(string $topicId): array
    {
        return self::taxonomyMatch('topic_id', $topicId);
    }

    private static function taxonomyMatch(string $key, string $value): array
    {
        return array_values(
            array_filter(
                self::repository()->all(),
                static fn (mixed $question): bool =>
                    is_array($question)
                    && QuestionValueReader::taxonomy($question, $key) === $value
            )
        );
    }
}
