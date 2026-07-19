<?php

class QuizNavigationService
{
    public static function next(): bool
    {
        if (!isset($_SESSION["currentQuestion"])) {
            return false;
        }

        $_SESSION["currentQuestion"]++;

        return true;
    }

    public static function current(): int
    {
        return $_SESSION["currentQuestion"] ?? 0;
    }

    public static function isLastQuestion(): bool
    {
        return self::current() >= count($_SESSION["questions"]) - 1;
    }

    public static function reset(): void
    {
        $_SESSION["currentQuestion"] = 0;
    }
}
