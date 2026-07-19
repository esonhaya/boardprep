<?php

class LearningCoachService
{

    public static function build(
        array $analytics,
        array $weaknesses,
        array $recommendations
    ): array
    {

        $messages = [];


        $average =
            $analytics["averageScore"] ?? 0;


        if ($average == 0) {

            $messages[] =
                "Welcome! Start taking quizzes so I can understand how you learn.";

        }
        elseif ($average < 60) {

            $messages[] =
                "Don't worry about low scores yet. Building strong fundamentals is the fastest path to improvement.";

        }
        elseif ($average < 80) {

            $messages[] =
                "You're improving steadily. Focus on consistency rather than speed.";

        }
        else {

            $messages[] =
                "Excellent work! You're ready to challenge yourself with more difficult questions.";

        }


        if (!empty($weaknesses)) {

            $messages[] =
                "I've identified topics that need more practice based on your quiz history.";

        }


        foreach ($recommendations as $recommendation) {

            $messages[] = $recommendation;

        }


        return $messages;

    }

}
