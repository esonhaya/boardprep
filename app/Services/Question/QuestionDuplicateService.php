<?php

declare(strict_types=1);

namespace App\Services\Question;

use App\Core\App;
use App\Repositories\QuestionRepository;

class QuestionDuplicateService
{
    public static function find(
        array $question
    ): array {

        $duplicates = [];

        $repository =
            App::container()
                ->get(
                    QuestionRepository::class
                );

        foreach (
            $repository->all()
            as $existing
        ) {

            if (

                ($existing["id"] ?? "")

                ===

                ($question["id"] ?? "")

            ) {

                continue;

            }

            if (

                trim(
                    strtolower(
                        $existing["question"] ?? ""
                    )
                )

                ===

                trim(
                    strtolower(
                        $question["question"] ?? ""
                    )
                )

            ) {

                $duplicates[] =
                    $existing;

            }

        }

        return $duplicates;

    }
}
