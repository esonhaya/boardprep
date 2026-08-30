<?php
declare(strict_types=1);
namespace App\Services\Quality\Validators\Choice;
final class ChoiceAnswerValidator {
    public static function validate(array $question, array $choices): array {
        $answer = $question['answer'] ?? null;
        $normalized = static fn(mixed $value): string => is_scalar($value)
            ? strtolower(preg_replace('/\s+/', ' ', trim((string) $value)) ?? trim((string) $value))
            : '';
        return $normalized($answer) !== '' && in_array($normalized($answer), array_map($normalized, $choices), true) ? [] : [
            ChoiceIssueFactory::create("error","invalid-answer","Answer does not match any choice.")
        ];
    }
}
