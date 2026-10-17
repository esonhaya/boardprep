<?php
declare(strict_types=1);
namespace App\Services\Quality\Validators\Choice;
final class ChoiceDuplicateDetector {
    public static function validate(array $choices): array {
        $values = array_map(
            static function (mixed $choice): string {
                // Case and whitespace are presentation-only. Preserve all
                // punctuation because exact transcription questions can use
                // punctuation as the tested meaning (for example, record IDs).
                return strtolower(preg_replace('/\s+/u', ' ', trim((string) $choice)) ?? trim((string) $choice));
            },
            array_values(array_filter($choices, 'is_scalar'))
        );
        return count(array_unique($values))===count($values) ? [] : [
            ChoiceIssueFactory::create("error","duplicate-choices","Question contains choices equal after case and whitespace normalization.")
        ];
    }
}
