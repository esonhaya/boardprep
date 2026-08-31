<?php

declare(strict_types=1);

namespace App\Services\Question\Authoring;

use App\Services\Question\QuestionService;

final class QuestionAuthoringPersistence
{
    public static function persist(int|string $id, array $question): ?array
    {
        if ($id > 0) {
            return QuestionService::update($id, $question);
        }

        return QuestionService::save($question);
    }
}
