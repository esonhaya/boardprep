<?php
declare(strict_types=1);
final class QuestionPoolFilter
{
    public static function filter(array $questions, SelectionRequest $request): array
    {
        return array_values(array_filter($questions, static function(array $question) use ($request): bool {
            $taxonomy = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
            $subject = $question['subject'] ?? $taxonomy['subject_id'] ?? null;
            $domain = $question['domain'] ?? $taxonomy['domain_id'] ?? null;
            $status = strtolower((string)($question['status'] ?? 'active'));
            return in_array($status, ['active','approved'], true)
                && (string)$subject === (string)$request->subject
                && (
                    $request->domain === null
                    || (string)$domain === (string)$request->domain
                    || $request->questionCount > 0
                );
        }));
    }
}
