<?php

declare(strict_types=1);

namespace App\Services\Question\Query;

final class QuestionQueryFilters
{
    public function __construct(
        public readonly string $search,
        public readonly string $domain,
        public readonly string $difficulty,
        public readonly string $topic,
        public readonly string $status,
        public readonly string $board,
        public readonly string $subject
    ) {
    }

    public static function from(array $filters): self
    {
        return new self(
            self::text($filters['search'] ?? ''),
            self::text($filters['domain'] ?? ''),
            self::text($filters['difficulty'] ?? ''),
            self::text($filters['topic'] ?? ''),
            self::text($filters['status'] ?? ''),
            self::text($filters['board'] ?? ''),
            self::text($filters['subject'] ?? '')
        );
    }

    private static function text(mixed $value): string
    {
        if (!is_scalar($value) && $value !== null) {
            return '';
        }

        return trim((string) ($value ?? ''));
    }
}
