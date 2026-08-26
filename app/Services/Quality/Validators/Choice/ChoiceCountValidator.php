<?php
declare(strict_types=1);
namespace App\Services\Quality\Validators\Choice;
final class ChoiceCountValidator {
    public static function validate(array $choices): array {
        return count($choices) >= 4 ? [] : [
            ChoiceIssueFactory::create("error","missing-choices","Question has fewer than four choices.")
        ];
    }
}
