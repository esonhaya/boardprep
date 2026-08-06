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

        $board =
            $repository->board(
                $specification->board
            ) ?? [];

        $subject =
            $repository->subject(
                $specification->board,
                $specification->subject
            ) ?? [];

        if (!empty($board)) {
            BlueprintIntegrityValidator::validate(
                $board
            );
        }

        if (!empty($subject)) {
            BlueprintIntegrityValidator::validate(
                $subject
            );
        }

        return [

            "board" => $board,

            "subjects" => [

                $specification->subject =>
                    $subject,

            ],

        ];

    }
}
