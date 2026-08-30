<?php
declare(strict_types=1);
final class QuestionPoolFilter
{
    public static function filter(array $questions, SelectionRequest $request): array
    {
        return array_values(array_filter($questions, static function(mixed $question) use ($request): bool {
            // Repository data can contain legacy or partially written entries.
            // They are not quiz candidates and must not reach the typed
            // selection/session pipeline or the quiz view.
            if (!is_array($question) || !is_scalar($question['id'] ?? null) || trim((string) $question['id']) === '') {
                return false;
            }

            $taxonomy = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
            $subject = $question['subject'] ?? $taxonomy['subject_id'] ?? null;
            $domain = $question['domain'] ?? $taxonomy['domain_id'] ?? null;
            $topic = $question['topic'] ?? $taxonomy['topic_id'] ?? null;
            $status = strtolower((string)($question['status'] ?? 'active'));
            return in_array($status, ['active','approved'], true)
                && self::same($subject, $request->subject)
                && (
                    $request->domain === null
                    || self::same($domain, $request->domain)
                    || $request->topic === null
                )
                && (
                    $request->topic === null
                    || self::same($topic, $request->topic)
                );
        }));
    }

    private static function same(mixed $actual, ?string $expected): bool
    {
        return is_scalar($actual)
            && strcasecmp(trim((string) $actual), trim((string) $expected)) === 0;
    }
}
