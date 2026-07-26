<?php

class ValidatorRegistry
{
    public static function entityValidators(): array
    {
        return [

            ContentValidator::class,

            ChoiceValidator::class,

            MetadataValidator::class,

            TaxonomyValidator::class,

        ];
    }

    public static function repositoryValidators(): array
    {
        return [

            DuplicateValidator::class,

        ];
    }
}
