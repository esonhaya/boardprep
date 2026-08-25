<?php
declare(strict_types=1);
final class StudySessionContract
{
    public static function valid(array $session): bool
    {
        return isset($session["topic"], $session["subject"]);
    }
}
