<?php
declare(strict_types=1);
namespace App\Services\Quality\Validators\Choice;
final class ChoiceAnswerValidator {
    public static function validate(array $question, array $choices): array {
        return in_array($question["answer"] ?? "", $choices, true) ? [] : [
            ChoiceIssueFactory::create("error","invalid-answer","Answer does not match any choice.")
        ];
    }
}
