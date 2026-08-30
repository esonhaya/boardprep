<?php

declare(strict_types=1);

namespace App\Services\Blueprint\Creation;

final class BlueprintCreationInput
{
    public function __construct(
        public readonly string $boardId,
        public readonly string $subjectId,
        public readonly string $name,
        public readonly int $questionCount,
        public readonly int $easy,
        public readonly int $medium,
        public readonly int $hard
    ) {
    }

    public static function from(array $data): self
    {
        return new self(
            self::text($data['board'] ?? ''),
            self::text($data['subject'] ?? ''),
            self::text($data['name'] ?? ''),
            (int) ($data['questionCount'] ?? 0),
            (int) ($data['easy'] ?? 0),
            (int) ($data['medium'] ?? 0),
            (int) ($data['hard'] ?? 0)
        );
    }

    private static function text(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }
}
