<?php

declare(strict_types=1);

namespace App\Services\Learning\Recommendation;

final class LearningAttemptContext
{
    /** @var array<string,string>|null */
    private static ?array $boardAliasIndex = null;
    /** @var array<string,string>|null */
    private static ?array $subjectAliasIndex = null;
    public static function fromAttempt(array $attempt): array
    {
        $learning = is_array($attempt['learning_context'] ?? null)
            ? $attempt['learning_context']
            : [];
        $letVariant = self::firstArray([
            $attempt['let_variant'] ?? null,
            $learning['let_variant'] ?? null,
        ]);

        return [
            'subject' => self::text($attempt['subject'] ?? ($learning['subject'] ?? '')),
            'topic' => self::topic($attempt, $learning),
            'board' => self::firstText([
                $attempt['board'] ?? null,
                $attempt['exam'] ?? null,
                $learning['board'] ?? null,
            ]),
            'let_level' => self::firstText([
                $attempt['let_level'] ?? null,
                $attempt['let_variant']['level'] ?? null,
                $learning['let_level'] ?? null,
                $letVariant['level'] ?? null,
            ]),
            'let_specialization' => self::firstText([
                $attempt['let_specialization_id'] ?? null,
                $attempt['let_specialization'] ?? null,
                $attempt['let_variant']['specialization_id'] ?? null,
                $learning['let_specialization_id'] ?? null,
                $learning['let_specialization'] ?? null,
                $letVariant['specialization_id'] ?? null,
            ]),
            'civil_service_level' => self::firstText([
                $attempt['civil_service_level'] ?? null,
                $learning['civil_service_level'] ?? null,
            ]),
        ];
    }

    /** Resolve a review question using the exact eligibility placement saved at session start. */
    public static function fromQuestion(array $question, array $attemptContext): array
    {
        $context = $attemptContext;
        $saved = is_array($question['attempt_context'] ?? null)
            ? $question['attempt_context']
            : [];
        $savedBoard = self::text($saved['board'] ?? '');
        $savedSubject = self::text($saved['subject'] ?? '');
        $savedPlacement = $savedBoard !== '' && $savedSubject !== '' && $savedSubject !== '*';
        $context['question_placement_resolved'] = $savedPlacement;
        foreach ([
            'board' => 'board',
            'subject' => 'subject',
            'let_level' => 'let_level',
            'let_specialization' => 'let_specialization',
            'civil_service_level' => 'civil_service_level',
        ] as $source => $target) {
            $value = self::text($saved[$source] ?? '');
            if ($value !== '') {
                $existing = self::text($context[$target] ?? '');
                // Preserve a learner-facing subject label when it resolves to
                // the exact same configured subject. This keeps existing
                // practice URLs stable while the saved eligibility ID still
                // replaces an ambiguous or different cross-board subject.
                if ($target === 'subject' && $existing !== '' && $existing !== '*'
                    && self::canonicalSubject($existing) === self::canonicalSubject($value)) {
                    continue;
                }
                $context[$target] = $value;
            }
        }

        if ($savedPlacement) {
            return $context;
        }

        $taxonomy = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
        $questionBoard = self::canonicalBoard(self::text($taxonomy['board_id'] ?? $question['board'] ?? ''));
        $attemptBoard = self::canonicalBoard(self::text($context['board'] ?? ''));
        $taxonomySubject = self::text($taxonomy['subject_id'] ?? $question['subject_id'] ?? '');
        $subject = self::text($context['subject'] ?? '');

        // Legacy snapshots may use canonical taxonomy only when its board
        // matches the attempt. A mixed-board question without the saved
        // session-time placement cannot be safely assigned to the attempt.
        if ($questionBoard !== '' && ($attemptBoard === '' || $questionBoard !== $attemptBoard)) {
            $context['subject'] = '';
            $context['question_placement_resolved'] = false;
        } elseif ($subject !== '' && $subject !== '*') {
            $context['question_placement_resolved'] = true;
        } elseif ($taxonomySubject !== '' && $questionBoard !== '' && $questionBoard === $attemptBoard) {
            $context['subject'] = $taxonomySubject;
            $context['question_placement_resolved'] = true;
        }

        return $context;
    }

    private static function topic(array $attempt, array $learning): string
    {
        foreach ([$attempt['topic'] ?? null, $learning['topic'] ?? null, $attempt['domain'] ?? null] as $value) {
            $text = self::text($value);
            if ($text !== '') {
                return $text;
            }
        }

        return 'General';
    }

    private static function text(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }

    private static function firstText(array $values): string
    {
        foreach ($values as $value) {
            $text = self::text($value);
            if ($text !== '') {
                return $text;
            }
        }

        return '';
    }

    private static function firstArray(array $values): array
    {
        foreach ($values as $value) {
            if (is_array($value)) {
                return $value;
            }
        }

        return [];
    }

    private static function canonicalBoard(string $board): string
    {
        $board = strtolower(trim($board));
        if (self::$boardAliasIndex === null) {
            self::$boardAliasIndex = [];
            foreach (\App\Services\Shared\TaxonomyStorageService::boards() as $record) {
                if (!is_array($record)) {
                    continue;
                }
                $id = strtolower(trim(self::text($record['id'] ?? '')));
                if ($id === '') {
                    continue;
                }
                self::$boardAliasIndex[$id] = $id;
                foreach (['code', 'name'] as $field) {
                    $alias = strtolower(trim(self::text($record[$field] ?? '')));
                    if ($alias !== '') {
                        self::$boardAliasIndex[$alias] = $id;
                    }
                }
            }
        }
        return self::$boardAliasIndex[$board] ?? $board;
    }

    private static function canonicalSubject(string $subject): string
    {
        $subject = strtolower(trim($subject));
        if (self::$subjectAliasIndex === null) {
            self::$subjectAliasIndex = [];
            foreach (\App\Services\Shared\TaxonomyStorageService::subjects() as $record) {
                if (!is_array($record)) {
                    continue;
                }
                $id = strtolower(trim(self::text($record['id'] ?? '')));
                if ($id === '') {
                    continue;
                }
                foreach (['id', 'name', 'code'] as $field) {
                    $alias = strtolower(trim(self::text($record[$field] ?? '')));
                    if ($alias !== '') {
                        self::$subjectAliasIndex[$alias] = $id;
                    }
                }
            }
        }
        return self::$subjectAliasIndex[$subject] ?? $subject;
    }
}
