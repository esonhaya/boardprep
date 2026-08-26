<?php
declare(strict_types=1);
namespace App\Services\Quality\Validators;
use App\Services\Quality\Validators\Choice\ChoiceListReader;
use App\Services\Quality\Validators\Choice\ChoiceValidationPipeline;
class ChoiceValidator {
    public static function validate(array $question): array {
        return ChoiceValidationPipeline::validate($question, ChoiceListReader::read($question));
    }
}
