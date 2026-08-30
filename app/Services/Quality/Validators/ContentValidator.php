<?php

declare(strict_types=1);

namespace App\Services\Quality\Validators;

use App\Services\Quality\Validators\Content\ContentValidationPipeline;

class ContentValidator
{
    public static function validate(array $question): array
    {
        return ContentValidationPipeline::validate($question);
    }
}
