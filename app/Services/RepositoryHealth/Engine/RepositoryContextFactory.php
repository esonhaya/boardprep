<?php

declare(strict_types=1);

namespace App\Services\RepositoryHealth\Engine;

use App\Core\App;
use App\Repositories\BlueprintRepository;
use App\Repositories\BoardRepository;
use App\Repositories\QuestionRepository;
use App\Services\RepositoryHealth\DTO\RepositoryContext;

class RepositoryContextFactory
{
    public static function create(): RepositoryContext
    {
        $context = new RepositoryContext();

        $storage = App::storage();

        $context->questions =
            (new QuestionRepository($storage))->all();

        $context->boards =
            (new BoardRepository($storage))->all();

        $context->blueprints =
            (new BlueprintRepository($storage))->all();

        $taxonomy = [];

        foreach (
            ['domains', 'topics', 'concepts']
            as $file
        ) {
            $taxonomy[$file] = $storage->all(
                "taxonomy/$file"
            );
        }

        $context->taxonomy = $taxonomy;

        return $context;
    }
}
