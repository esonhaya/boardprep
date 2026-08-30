<?php

declare(strict_types=1);

namespace App\Services\Question\Authoring;

final class QuestionAuthoringDecision
{
    public static function allows(array $result): bool
    {
        return ($result['valid'] ?? false) === true
            && empty($result['errors'] ?? [])
            && empty($result['duplicates'] ?? []);
    }
}
