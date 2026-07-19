<?php

class LearningHistoryService
{

    public static function recent(int $limit = 10): array
    {

        $attempts = AttemptRepository::all();

        usort(
            $attempts,
            fn($a, $b) =>
                strtotime($b["date"])
                <=>
                strtotime($a["date"])
        );

        return array_slice(
            $attempts,
            0,
            $limit
        );

    }

}
