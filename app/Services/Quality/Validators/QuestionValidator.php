<?php

declare(strict_types=1);

namespace App\Services\Quality\Validators;

final class QuestionValidator
{
    /** @return array<int, array{severity:string,type:string,message:string}> */
    public static function validate(array $question): array
    {
        return array_merge(
            ContentValidator::validate($question),
            ChoiceValidator::validate($question),
            MetadataValidator::validate($question),
            TaxonomyValidator::validate($question)
        );
    }
}
