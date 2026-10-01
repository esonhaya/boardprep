<?php

declare(strict_types=1);

namespace App\Services\RepositoryHealth\Engine;

use App\Core\App;
use App\Repositories\QuestionRepository;
use App\Services\RepositoryHealth\DTO\RepositoryContext;

class RepositoryContextFactory
{
    public static function create(): RepositoryContext
    {
        $storage = App::storage();
        $collections = [];
        foreach (self::collectionNames() as $collection) {
            $collections[$collection] = $storage->all($collection);
        }

        // QuestionRepository remains the production question projection for
        // callers that need reuse/statistics overlays. The raw collection is
        // retained for integrity checks so the scan still has one snapshot.
        $collections['questions'] = array_values(array_filter($collections['questions'] ?? [], 'is_array'));
        $context = self::fromCollections($collections);
        $context->questions = (new QuestionRepository($storage))->all();
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
            'sources', 'content-fit-audits', 'flashcard-exclusions',
        ];
    }
}
