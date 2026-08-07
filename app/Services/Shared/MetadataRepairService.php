<?php

declare(strict_types=1);

use App\Core\App;
use App\Repositories\QuestionRepository;

final class MetadataRepairService
{
    public static function repair(): array
    {
        $repository =
            App::container()
                ->get(
                    QuestionRepository::class
                );

        $processor =
            new MetadataRepairProcessor();

        $report =
            new MetadataRepairReport();

        $questions =
            $processor->process(
                $repository->all(),
                $report
            );

        foreach ($questions as $question) {

            $id =
                trim(
                    (string) (
                        $question["id"]
                        ?? ""
                    )
                );

            if (
                $id === ""
                ||
                !$report->hasChanges($id)
            ) {
                continue;
            }

            $repository->update(
                $id,
                $question
            );

        }

        return $report->summary();
    }
}
