<?php

class Config
{
    public const STORAGE =
        __DIR__ . "/../../storage/";

    public const QUESTIONS =
        self::STORAGE . "questions.json";

    public const ATTEMPTS =
        self::STORAGE . "attempts.json";

    public const PROGRESS =
        self::STORAGE . "progress.json";
}
