<?php

declare(strict_types=1);

final class QuestionBalanceTopicResolver
{
    public static function resolve(array $question): string
    {
        $topic = strtolower(trim((string) ($question["topic"] ?? "")));

        return $topic !== "" ? $topic : "__unknown__";
    }
}
