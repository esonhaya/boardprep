<?php
declare(strict_types=1);
namespace App\Services\Quality\Validators\Choice;
final class ChoiceEntryValidator {
    public static function validate(array $choices): array {
        $issues=[];
        foreach ($choices as $index=>$choice) {
            if (trim($choice)==="") $issues[]=ChoiceIssueFactory::create("error","empty-choice","Choice ".($index+1)." is empty.");
            if ($choice!==trim($choice)) $issues[]=ChoiceIssueFactory::create("info","choice-whitespace","Choice ".($index+1)." contains unnecessary whitespace.");
        }
        return $issues;
    }
}
