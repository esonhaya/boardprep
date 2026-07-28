<?php

declare(strict_types=1);

namespace App\Services\RepositoryHealth\Engine;

use App\Core\App;
use App\Repositories\BlueprintRepository;
use App\Repositories\BoardRepository;
use App\Repositories\QuestionRepository;
use App\Repositories\SubjectRepository;
use App\Services\RepositoryHealth\DTO\RepositoryContext;

class RepositoryContextFactory
{
    public static function create(): RepositoryContext
    {
        $context = new RepositoryContext();

        $context->questions = QuestionRepository::all();

        if (class_exists(BoardRepository::class)) {
            $context->boards = BoardRepository::all();
        }

        if (class_exists(SubjectRepository::class)) {
            $context->subjects = SubjectRepository::all();
        }

        if (class_exists(BlueprintRepository::class)) {
            $context->blueprints = BlueprintRepository::all();
        }

        $taxonomy = [];

        if (file_exists('database/taxonomy/domains.json')) {
            $taxonomy['domains'] = App::storage()->read(
                'database/taxonomy/domains.json'
            );
        }

        if (file_exists('database/taxonomy/topics.json')) {
            $taxonomy['topics'] = App::storage()->read(
                'database/taxonomy/topics.json'
            );
        }

        if (file_exists('database/taxonomy/concepts.json')) {
            $taxonomy['concepts'] = App::storage()->read(
                'database/taxonomy/concepts.json'
            );
        }

        $context->taxonomy = $taxonomy;

        return $context;
    }
}
