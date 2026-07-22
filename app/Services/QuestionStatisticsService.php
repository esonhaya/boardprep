<?php

class QuestionStatisticsService
{

    public static function recordAnswer(
        int $questionId,
        bool $correct
    ): void
    {

        $question =
            QuestionRepository::find(
                $questionId
            );

        if (!$question) {

            return;

        }

        $question["timesUsed"] =
            ($question["timesUsed"] ?? 0)
            + 1;

        if ($correct) {

            $question["timesCorrect"] =
                ($question["timesCorrect"] ?? 0)
                + 1;

        } else {

            $question["timesIncorrect"] =
                ($question["timesIncorrect"] ?? 0)
                + 1;

        }

        $question["updatedAt"] =
            date("c");

        QuestionRepository::update(

            $questionId,

            $question

        );

    }

}
