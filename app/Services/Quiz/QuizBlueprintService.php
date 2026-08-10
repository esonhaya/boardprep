<?php

declare(strict_types=1);

use App\Core\App;
use App\Repositories\BlueprintRepository;

class QuizBlueprintService
{
    public static function apply(
        array $options
    ): array
    {

        if (empty($options["blueprint"])) {
            return $options;
        }

        $repository =
            App::container()->get(
                BlueprintRepository::class
            );

        $blueprint =
            $repository->find(
                $options["blueprint"]
            );

        if (!$blueprint) {
            return $options;
        }

        $topics = [];

        foreach (
            $blueprint["sections"] ?? []
            as $section
        ) {

            if (!empty($section["topic"])) {
                $topics[] = $section["topic"];
            }

        }

        if (!empty($topics)) {
            $options["topics"] = $topics;

            if (count($topics) === 1) {
                $options["topic"] = $topics[0];
            }
        }

        return $options;
    }
}
