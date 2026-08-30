<?php
declare(strict_types=1);
namespace App\Services\Quality\Validators\Choice;
final class ChoiceDuplicateDetector {
    public static function validate(array $choices): array {
        $values = array_map(
            static fn(mixed $choice): string => strtolower(preg_replace('/\s+/', ' ', trim((string) $choice)) ?? trim((string) $choice)),
            array_values(array_filter($choices, 'is_scalar'))
        );
        return count(array_unique($values))===count($values) ? [] : [
            ChoiceIssueFactory::create("warning","duplicate-choices","Question contains duplicate choices.")
        ];
    }
}
