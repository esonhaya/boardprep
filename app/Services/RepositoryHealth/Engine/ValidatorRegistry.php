<?php

declare(strict_types=1);

namespace App\Services\RepositoryHealth\Engine;

use App\Services\Quality\Validators\ChoiceValidator;
use App\Services\Quality\Validators\ContentValidator;
use App\Services\Quality\Validators\DuplicateValidator;
use App\Services\Quality\Validators\MetadataValidator;
use App\Services\Quality\Validators\TaxonomyValidator;

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
