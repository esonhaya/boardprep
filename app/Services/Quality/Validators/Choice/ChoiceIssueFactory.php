<?php
declare(strict_types=1);
namespace App\Services\Quality\Validators\Choice;
final class ChoiceIssueFactory {
    public static function create(string $severity, string $type, string $message): array {
        return ["severity"=>$severity, "type"=>$type, "message"=>$message];
    }
}
