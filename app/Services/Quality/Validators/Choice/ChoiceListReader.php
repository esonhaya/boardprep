<?php
declare(strict_types=1);
namespace App\Services\Quality\Validators\Choice;
final class ChoiceListReader {
    public static function read(array $question): array {
        $choices = $question["choices"] ?? [];
        return is_array($choices) ? array_values($choices) : [];
    }
}
