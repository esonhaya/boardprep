<?php

declare(strict_types=1);

final class AdaptiveQuestionOrderer
{
    /**
     * @param array<int,array<string,mixed>> $priority
     * @param array<int,array<string,mixed>> $normal
     * @return array<int,array<string,mixed>>
     */
    public static function merge(array $priority, array $normal): array
    {
        shuffle($priority);
        shuffle($normal);

        return array_values(array_merge($priority, $normal));
    }
}
