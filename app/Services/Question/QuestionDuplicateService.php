<?php

declare(strict_types=1);

namespace App\Services\Question;

use App\Core\App;
use App\Repositories\QuestionRepository;

class QuestionDuplicateService
{
    public static function find(array $question): array
    {
        $duplicates = [];

        $repository = App::container()->get(
            QuestionRepository::class
        );

        $targetId = (string) ($question['id'] ?? '');

        $targetQuestion = self::normalizeText(
            (string) ($question['question'] ?? '')
        );

        if ($targetQuestion === '') {
            return [];
        }

        foreach ($repository->all() as $existing) {
            if (!is_array($existing)) {
                continue;
            }

            $existingId = (string) ($existing['id'] ?? '');

            if (
                $targetId !== ''
                && $existingId === $targetId
            ) {
                continue;
            }

            $existingQuestion = self::normalizeText(
                (string) ($existing['question'] ?? '')
            );

            if (
                $existingQuestion === ''
                || $existingQuestion !== $targetQuestion
            ) {
                continue;
            }

            $duplicates[] = $existing;
        }

        return $duplicates;
    }

    private static function normalizeText(string $text): string
    {
        $text = trim($text);
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;

        return strtolower($text);
    }
}
