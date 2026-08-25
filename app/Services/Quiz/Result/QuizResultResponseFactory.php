<?php

declare(strict_types=1);

final class QuizResultResponseFactory
{
    /**
     * @param array<string,mixed> $summary
     * @return array{summary:array,review:array}
     */
    public static function create(array $summary): array
    {
        return [
            "summary" => $summary,
            "review" => $summary["results"] ?? [],
        ];
    }
}
