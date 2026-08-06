<?php

declare(strict_types=1);

use App\Core\App;
use App\Repositories\BlueprintRepository;

final class BlueprintResolverService
{
    public static function resolve(
        QuizSpecification $specification
    ): array {

        $repository =
            App::container()->get(
                BlueprintRepository::class
            );

        return [

            'board' =>

                $repository->board(
                    $specification->board
                ),

            'subject' =>

                $repository->subject(
                    $specification->board,
                    $specification->subject
                ),

        ];

    }
}
