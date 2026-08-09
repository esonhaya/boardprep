<?php

declare(strict_types=1);

namespace App\Services\Learning;

use App\Core\App;
use App\Repositories\AttemptRepository;

class LearningHistoryService
{

    public static function recent(int $limit = 10): array
    {

        $attempts = App::container()->get(AttemptRepository::class)->all();

        usort(
            $attempts,
            fn($a, $b) =>
                strtotime($b["date"])
                <=>
                strtotime($a["date"])
        );

        return array_slice(
            $attempts,
            0,
            $limit
        );

    }

}
