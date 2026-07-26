<?php

class QuestionValidator
{

    public static function validate(array $question): array
    {

        $issues = [];

        $issues = array_merge(
            $issues,
            ContentValidator::validate($question)
        );

        $issues = array_merge(
            $issues,
            ChoiceValidator::validate($question)
        );

        $issues = array_merge(
            $issues,
            MetadataValidator::validate($question)
        );

        $issues = array_merge(
            $issues,
            TaxonomyValidator::validate($question)
        );

        return $issues;

    }

}
