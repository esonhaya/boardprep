<?php

declare(strict_types=1);

namespace App\Services\Question;

use App\Services\Question\Authoring\QuestionIdentityGenerator;

final class QuestionMetadataBuilderService
{
    public static function build(int|string $id, ?array $existing, string $now): array
    {
        return [
            'id' => QuestionIdentityGenerator::resolve($id, $existing),
            'status' => $existing['status'] ?? 'active',
            'source' => $existing['source'] ?? 'manual',
            'timesUsed' => $existing['timesUsed'] ?? 0,
            'timesCorrect' => $existing['timesCorrect'] ?? 0,
            'timesIncorrect' => $existing['timesIncorrect'] ?? 0,
            'bookmarks' => $existing['bookmarks'] ?? 0,
            'reports' => $existing['reports'] ?? 0,
            'helpfulExplanation' => $existing['helpfulExplanation'] ?? 0,
            'notHelpfulExplanation' => $existing['notHelpfulExplanation'] ?? 0,
            'createdAt' => $existing['createdAt'] ?? $now,
            'updatedAt' => $now,
        ];
    }
}
