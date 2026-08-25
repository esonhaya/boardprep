<?php
declare(strict_types=1);
final class StudySessionTopicResolver
{
    public static function resolve(string $topic): string
    {
        return trim($topic);
    }
}
