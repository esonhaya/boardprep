<?php

declare(strict_types=1);

namespace App\Services\RepositoryHealth\Engine;

use App\Core\App;
use App\Services\RepositoryHealth\DTO\RepositoryContext;

class RepositoryContextFactory
{
    public static function create(?RepositoryHealthProfiler $profiler = null): RepositoryContext
    {
        $storage = App::storage();
        $collections = [];
        foreach (self::collectionNames() as $collection) {
            $profiler?->increment('collection_loads.' . $collection);
            if ($profiler !== null) {
                if ($collection === 'questions') $profiler->increment('question_store_loads');
                if ($collection === 'question-reuse-metadata') $profiler->increment('reuse_metadata_loads');
                if (str_starts_with($collection, 'taxonomy/')) $profiler->increment('taxonomy_loads');
                if ($collection === 'sources') $profiler->increment('source_loads');
                if (in_array($collection, ['study-materials', 'study-question-links'], true)) $profiler->increment('study_loads');
                if (str_starts_with($collection, 'source-readiness')) $profiler->increment('readiness_loads');
                if (in_array($collection, ['question-eligibility', 'concept-derived-eligibility', 'question-reuse-overrides'], true)) $profiler->increment('eligibility_collection_loads');
            }
            $collections[$collection] = $profiler === null
                ? $storage->all($collection)
                : $profiler->measure('collection_load.' . $collection, static fn(): array => $storage->all($collection));
        }

        // QuestionRepository remains the production question projection for
        // callers that need reuse/statistics overlays. The raw collection is
        // retained for integrity checks so the scan still has one snapshot.
        $collections['questions'] = array_values(array_filter($collections['questions'] ?? [], 'is_array'));
        $context = self::fromCollections($collections);
        // Keep the production projection populated for context consumers, but
        // make its extra repository reads visible in profile mode.
        if ($profiler !== null) {
            $profiler->increment('question_store_loads', 1);
            $profiler->increment('reuse_metadata_loads', 1);
            $profiler->increment('question_statistics_loads', 1);
            $context->questions = $profiler->measure(
                'question_repository_projection',
                static fn(): array => (new \App\Repositories\QuestionRepository($storage))->all()
            );
        } else {
            $context->questions = (new \App\Repositories\QuestionRepository($storage))->all();
        }
        return $context;
    }

    /** @param array<string, array> $collections */
    public static function fromCollections(array $collections): RepositoryContext
    {
        $context = new RepositoryContext();
        $context->collections = $collections;
        $context->questions = array_values(array_filter($collections['questions'] ?? [], 'is_array'));
        $context->boards = array_values(array_filter($collections['boards'] ?? [], 'is_array'));
        $context->subjects = array_values(array_filter($collections['subjects'] ?? [], 'is_array'));
        $context->blueprints = array_values(array_filter($collections['blueprints'] ?? [], 'is_array'));
        $context->taxonomy = [
            'domains' => array_values(array_filter($collections['taxonomy/domains'] ?? [], 'is_array')),
            'topics' => array_values(array_filter($collections['taxonomy/topics'] ?? [], 'is_array')),
            'concepts' => array_values(array_filter($collections['taxonomy/concepts'] ?? [], 'is_array')),
        ];
        return $context;
    }

    /** @return string[] */
    public static function collectionNames(): array
    {
        return [
            'questions', 'boards', 'subjects', 'board-subjects', 'blueprints',
            'taxonomy/domains', 'taxonomy/topics', 'taxonomy/concepts',
            'question-eligibility', 'concept-derived-eligibility',
            'question-reuse-overrides', 'question-reuse-metadata',
            'board-concept-coverage', 'study-materials', 'study-question-links',
            'sources', 'content-fit-audits', 'flashcard-exclusions', 'source-readiness',
            'source-readiness-psychometrician',
            'source-readiness-mechanical-engineering',
            'source-readiness-let',
        ];
    }
}
