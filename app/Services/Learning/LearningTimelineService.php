<?php

class LearningTimelineService
{

    public static function build(
        array $attempts
    ): array
    {

        $timeline = [];

        foreach($attempts as $attempt)
        {

            $timeline[] = [

                "date" =>
                    $attempt["date"],

                "percentage" =>
                    (int)$attempt["percentage"],

                "mode" =>
                    $attempt["mode"],

                "topic" =>
                    $attempt["topic"]

            ];

        }

        usort(

            $timeline,

            fn($a,$b)=>

                strtotime($a["date"])

                <=>

                strtotime($b["date"])

        );

        return $timeline;

    }

}
