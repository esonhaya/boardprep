<?php
declare(strict_types=1);
namespace App\Services\Quality\Validators\Choice;
final class ChoiceValidationPipeline {
    public static function validate(array $question, array $choices): array {
        return array_merge(
            ChoiceCountValidator::validate($choices),
            ChoiceEntryValidator::validate($choices),
            ChoiceDuplicateDetector::validate($choices),
            ChoiceAnswerValidator::validate($question,$choices)
        );
    }
}
