<?php

declare(strict_types=1);

namespace App\Services\Board;

use App\Core\App;
use App\Repositories\BoardRepository;

final class BoardViewService
{
    public static function all(): array
    {
        return App::container()
            ->get(BoardRepository::class)
            ->all();
    }
}
