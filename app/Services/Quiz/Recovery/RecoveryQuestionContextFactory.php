<?php
declare(strict_types=1);

final class RecoveryQuestionContextFactory
{
    public static function create(array $question): RecoveryQuestionContext
    {
        $taxonomy = is_array($question['taxonomy'] ?? null)
            ? $question['taxonomy']
            : [];

        return new RecoveryQuestionContext(
            status: strtolower((string)($question['status'] ?? 'active')),
            subject: self::value($question, $taxonomy, 'subject', 'subject_id'),
            domain: self::value($question, $taxonomy, 'domain', 'domain_id'),
            topic: self::value($question, $taxonomy, 'topic', 'topic_id'),
            concept: self::value($question, $taxonomy, 'concept', 'concept_id')
        );
    }

    private static function value(
        array $question,
        array $taxonomy,
        string $directKey,
        string $taxonomyKey
    ): ?string {
        $value = $question[$directKey] ?? $taxonomy[$taxonomyKey] ?? null;
        return $value === null ? null : (string)$value;
    }
}
