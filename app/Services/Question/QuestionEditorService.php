<?php

declare(strict_types=1);

namespace App\Services\Question;

use App\Core\App;
use App\Repositories\QuestionRepository;

final class QuestionEditorService
{
    public static function find(
        string $id
    ): ?array {

        return App::container()
            ->get(QuestionRepository::class)
            ->find($id);

    }
}
