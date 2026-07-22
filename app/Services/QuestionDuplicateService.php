<?php

class QuestionDuplicateService
{

    public static function find(
        array $question
    ): array
    {

        return
            QuestionRepository::findDuplicates(
                $question
            );

    }

}
