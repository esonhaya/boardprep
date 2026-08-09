<?php

declare(strict_types=1);

namespace App\Services\Subject;

use App\Core\App;
use App\Repositories\SubjectRepository;

final class SubjectViewService
{
    public static function all(): array
    {
        return App::container()
            ->get(SubjectRepository::class)
            ->all();
    }
}
