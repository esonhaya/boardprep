<?php
declare(strict_types=1);
namespace App\Services\Quality\Validators\Choice;
final class ChoiceDuplicateDetector {
    public static function validate(array $choices): array {
        return count(array_unique($choices))===count($choices) ? [] : [
            ChoiceIssueFactory::create("warning","duplicate-choices","Question contains duplicate choices.")
        ];
    }
}
