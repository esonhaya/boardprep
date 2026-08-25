<?php
declare(strict_types=1);
final class StudySessionFactory
{
    public static function create(string $topic): array
    {
        return StudySessionService::fromTopic($topic);
    }
}
