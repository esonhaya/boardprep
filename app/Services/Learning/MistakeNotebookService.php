<?php

declare(strict_types=1);

namespace App\Services\Learning;

use App\Services\Shared\TaxonomyStorageService;

/** Builds question-level mistake summaries from the learner's existing attempt history. */
final class MistakeNotebookService
{
    /** @return list<array<string,mixed>> */
    public static function summaries(array $attempts): array
    {
        $groups = [];
        foreach (LearningHistoryService::ordered($attempts) as $attempt) {
            $board = self::boardId($attempt['board'] ?? $attempt['learning_context']['board'] ?? '');
            $subject = self::taxonomyId('subjects', $attempt['subject'] ?? $attempt['learning_context']['subject'] ?? '');
            $attemptId = is_scalar($attempt['id'] ?? null) ? trim((string) $attempt['id']) : '';
            if ($attemptId === '') {
                continue;
            }
            $review = is_array($attempt['review'] ?? null) ? $attempt['review'] : [];
            foreach ($review as $item) {
                if (!is_array($item) || !is_array($item['question'] ?? null)) {
                    continue;
                }
                $question = $item['question'];
                $questionId = is_scalar($question['id'] ?? null) ? trim((string) $question['id']) : '';
                if ($questionId === '') {
                    continue;
                }
                $itemBoard = $board !== '' ? $board : self::boardId($question['taxonomy']['board_id'] ?? $question['board'] ?? '');
                $itemSubject = $subject !== '' ? $subject : self::taxonomyId('subjects', $question['taxonomy']['subject_id'] ?? $question['subject'] ?? '');
                $variant = self::variantContext($attempt, $question, $itemBoard);
                $key = self::contextKey($itemBoard, $itemSubject, $questionId);
                $groups[$key] ??= [
                    'question_id' => $questionId,
                    'board' => $itemBoard,
                    'subject' => $itemSubject,
                    'events' => [],
                    'question' => $question,
                ];
                $groups[$key]['events'][] = [
                    'attempt_id' => $attemptId,
                    'date' => LearningHistoryService::dateOf($attempt),
                    'timestamp' => LearningHistoryService::timestampOf($attempt),
                    'answered' => ($item['answered'] ?? false) === true,
                    'correct' => ($item['correct'] ?? false) === true,
                    'selected_answer' => is_scalar($item['userAnswer'] ?? null) ? (string) $item['userAnswer'] : '',
                    'correct_answer' => is_scalar($item['correctAnswer'] ?? null)
                        ? (string) $item['correctAnswer']
                        : (is_scalar($question['answer'] ?? null) ? (string) $question['answer'] : ''),
                    'question' => $question,
                    'notes_practice' => is_array($attempt['notes_practice'] ?? null) ? $attempt['notes_practice'] : null,
                    'recovery_practice' => is_array($attempt['mistake_recovery'] ?? null) ? $attempt['mistake_recovery'] : null,
                    ...$variant,
                ];
                // History is newest first. Keep the first snapshot for current display.
                if (count($groups[$key]['events']) === 1) {
                    $groups[$key]['question'] = $question;
                }
            }
        }

        $items = [];
        foreach ($groups as $group) {
            $events = $group['events']; // newest first
            $mistakeIndexes = [];
            foreach ($events as $eventIndex => $event) {
                if ($event['answered'] && !$event['correct']) $mistakeIndexes[] = $eventIndex;
            }
            $mistakes = array_map(static fn(int $index): array => $events[$index], $mistakeIndexes);
            if ($mistakes === []) {
                continue;
            }
            $answerEvents = array_values(array_filter($events, static fn(array $event): bool => $event['answered']));
            $latest = $answerEvents[0] ?? $events[0];
            $latestMistake = $mistakes[0];
            $oldestMistakeEventIndex = $mistakeIndexes[array_key_last($mistakeIndexes)];
            $laterAnswered = false;
            $laterCorrect = false;
            foreach ($events as $eventIndex => $event) {
                // Events are newest-first, including stable storage-order tie breaking.
                if ($eventIndex < $oldestMistakeEventIndex && $event['answered']) {
                    $laterAnswered = true;
                    if ($event['correct']) {
                        $laterCorrect = true;
                    }
                }
            }
            $status = $latest['correct']
                ? 'CORRECT_ON_MOST_RECENT_RETRY'
                : (count($answerEvents) > 1 ? 'STILL_NEEDS_PRACTICE' : 'NOT_YET_RETRIED');
            $question = $group['question'];
            $taxonomy = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
            $topicId = self::scalar($taxonomy['topic_id'] ?? $question['topic'] ?? '');
            $domainId = self::scalar($taxonomy['domain_id'] ?? $question['domain'] ?? '');
            $items[] = [
                'question_id' => $group['question_id'],
                'board' => $group['board'],
                'subject' => $group['subject'],
                'topic_id' => $topicId,
                'topic' => $topicId !== '' ? TaxonomyStorageService::label('topics', $topicId) : 'Topic not recorded',
                'domain_id' => $domainId,
                'domain' => $domainId !== '' ? TaxonomyStorageService::label('domains', $domainId) : '',
                'question_text' => is_scalar($question['question'] ?? null) ? (string) $question['question'] : '',
                'wrong_count' => count($mistakes),
                'attempt_count' => count($answerEvents),
                'skipped_count' => count(array_filter($events, static fn(array $event): bool => !$event['answered'])),
                'reviewed' => $laterAnswered,
                'subsequently_correct' => $laterCorrect,
                'latest_status' => $status,
                'latest_attempt_id' => (string) ($latest['attempt_id'] ?? ''),
                'latest_mistake_attempt_id' => (string) ($latestMistake['attempt_id'] ?? ''),
                'last_mistake_at' => $latestMistake['date'],
                'latest_outcome' => $latest['correct'] ? 'CORRECT' : 'INCORRECT',
                'latest_mistake_variant_label' => (string) ($latestMistake['variant_label'] ?? ''),
                'question_snapshot' => $question,
                'events' => $events,
            ];
        }

        usort($items, static function (array $a, array $b): int {
            $timeOrder = LearningHistoryService::timestampOf(['completed_at' => (string) ($b['last_mistake_at'] ?? '')])
                <=> LearningHistoryService::timestampOf(['completed_at' => (string) ($a['last_mistake_at'] ?? '')]);
            return $timeOrder !== 0 ? $timeOrder : strcmp((string) $a['question_id'], (string) $b['question_id']);
        });

        return $items;
    }

    /** @return list<array<string,mixed>> */
    public static function skipped(array $attempts, ?array $mistakes = null): array
    {
        $mistakeKeys = [];
        foreach ($mistakes ?? self::summaries($attempts) as $mistake) {
            foreach (is_array($mistake['events'] ?? null) ? $mistake['events'] : [] as $event) {
                if (!is_array($event) || ($event['answered'] ?? false) !== true || ($event['correct'] ?? true) !== false) continue;
                $mistakeKeys[self::contextKey(
                    (string) $mistake['board'],
                    (string) $mistake['subject'],
                    (string) $mistake['question_id'],
                    self::recoveryVariantKey($event, (string) $mistake['board'])
                )] = true;
            }
        }
        $groups = [];
        foreach (LearningHistoryService::ordered($attempts) as $attempt) {
            $board = self::boardId($attempt['board'] ?? '');
            $subject = self::taxonomyId('subjects', $attempt['subject'] ?? '');
            $attemptId = is_scalar($attempt['id'] ?? null) ? trim((string) $attempt['id']) : '';
            if ($attemptId === '') continue;
            foreach (is_array($attempt['review'] ?? null) ? $attempt['review'] : [] as $item) {
                if (!is_array($item) || ($item['answered'] ?? true) !== false || !is_array($item['question'] ?? null)) continue;
                $question = $item['question'];
                $id = is_scalar($question['id'] ?? null) ? trim((string) $question['id']) : '';
                if ($id === '') continue;
                $itemBoard = $board !== '' ? $board : self::boardId($question['taxonomy']['board_id'] ?? '');
                $itemSubject = $subject !== '' ? $subject : self::taxonomyId('subjects', $question['taxonomy']['subject_id'] ?? '');
                $variant = self::variantContext($attempt, $question, $itemBoard);
                $key = self::contextKey($itemBoard, $itemSubject, $id, self::recoveryVariantKey($variant, $itemBoard));
                if (isset($mistakeKeys[$key])) continue;
                $groups[$key] ??= [
                    'question_id' => $id,
                    'board' => $itemBoard,
                    'subject' => $itemSubject,
                    'topic_id' => self::scalar($question['taxonomy']['topic_id'] ?? $question['topic'] ?? ''),
                    'question_text' => is_scalar($question['question'] ?? null) ? (string) $question['question'] : '',
                    'question_snapshot' => $question,
                    'attempt_id' => $attemptId,
                    'date' => LearningHistoryService::dateOf($attempt),
                    'count' => 0,
                    ...$variant,
                ];
                $groups[$key]['count']++;
            }
        }
        return array_values($groups);
    }

    /** Resolve a review anchor only when the owning learner has that missed or skipped record. */
    public static function detail(array $attempts, string $attemptId, string $questionId): ?array
    {
        $anchor = null;
        $anchorItem = null;
        foreach ($attempts as $attempt) {
            if (!is_array($attempt) || (string) ($attempt['id'] ?? '') !== $attemptId) continue;
            $anchor = $attempt;
            foreach (is_array($attempt['review'] ?? null) ? $attempt['review'] : [] as $item) {
                if (!is_array($item) || !is_array($item['question'] ?? null)
                    || (string) ($item['question']['id'] ?? '') !== $questionId) continue;
                $answered = ($item['answered'] ?? false) === true;
                $incorrect = $answered && ($item['correct'] ?? false) === false;
                $skipped = !$answered;
                if ($incorrect || $skipped) {
                    $anchorItem = $item;
                    break;
                }
            }
            break;
        }
        if (!is_array($anchor) || !is_array($anchorItem)) return null;

        $anchorQuestion = is_array($anchorItem['question'] ?? null) ? $anchorItem['question'] : [];
        $anchorTaxonomy = is_array($anchorQuestion['taxonomy'] ?? null) ? $anchorQuestion['taxonomy'] : [];
        $board = self::boardId($anchor['board'] ?? $anchorTaxonomy['board_id'] ?? '');
        $subject = self::taxonomyId('subjects', $anchor['subject'] ?? $anchorTaxonomy['subject_id'] ?? '');
        $variant = self::variantContext($anchor, $anchorQuestion, $board);
        $allItems = self::summaries($attempts);
        $summary = null;
        foreach ($allItems as $item) {
            if ((string) $item['question_id'] === $questionId
                && (string) $item['board'] === $board
                && (string) $item['subject'] === $subject) {
                $summary = $item;
                break;
            }
        }
        $timelineEvents = is_array($summary['events'] ?? null) ? $summary['events'] : [];
        $contextSummary = $summary;
        if ($contextSummary !== null) {
            $variantKey = self::recoveryVariantKey($variant, $board);
            $contextEvents = array_values(array_filter(
                $timelineEvents,
                static fn(array $event): bool => self::recoveryVariantKey($event, $board) === $variantKey
            ));
            $contextAnswers = array_values(array_filter($contextEvents, static fn(array $event): bool => ($event['answered'] ?? false) === true));
            $contextMistakes = array_values(array_filter(
                $contextAnswers,
                static fn(array $event): bool => ($event['correct'] ?? true) === false
            ));
            if ($contextMistakes === []) {
                $contextSummary = null;
            } else {
                $latestContextAnswer = $contextAnswers[0] ?? $contextEvents[0];
                $contextSummary['wrong_count'] = count($contextMistakes);
                $contextSummary['attempt_count'] = count($contextAnswers);
                $contextSummary['skipped_count'] = count(array_filter($contextEvents, static fn(array $event): bool => ($event['answered'] ?? false) !== true));
                $contextSummary['latest_status'] = ($latestContextAnswer['correct'] ?? false) === true
                    ? 'CORRECT_ON_MOST_RECENT_RETRY'
                    : (count($contextAnswers) > 1 ? 'STILL_NEEDS_PRACTICE' : 'NOT_YET_RETRIED');
                $contextSummary['latest_mistake_variant_label'] = (string) ($contextMistakes[0]['variant_label'] ?? '');
            }
        }
        return [
            'attempt' => $anchor,
            'item' => $anchorItem,
            'board' => $board,
            'subject' => $subject,
            'mistake_summary' => $contextSummary,
            'events' => $timelineEvents,
            'variant' => $variant,
            'is_mistake' => ($anchorItem['answered'] ?? false) === true && ($anchorItem['correct'] ?? false) === false,
            'is_recoverable' => true,
        ];
    }

    /** Return only explicitly saved variant evidence; absent legacy metadata stays unscoped. */
    public static function variantContext(array $attempt, array $question, string $board): array
    {
        $attemptContext = is_array($question['attempt_context'] ?? null) ? $question['attempt_context'] : [];
        $learningContext = is_array($attempt['learning_context'] ?? null) ? $attempt['learning_context'] : [];
        $letVariant = is_array($attempt['let_variant'] ?? null) ? $attempt['let_variant'] : [];
        $levelValue = $attempt['let_level'] ?? $letVariant['level'] ?? $learningContext['let_level'] ?? $attemptContext['let_level'] ?? '';
        $specializationValue = $attempt['let_specialization_id']
            ?? $attempt['let_specialization']
            ?? $letVariant['specialization_id']
            ?? $learningContext['let_specialization_id']
            ?? $learningContext['let_specialization']
            ?? $attemptContext['let_specialization']
            ?? $attemptContext['let_specialization_id']
            ?? '';
        $level = strtoupper(self::scalar($levelValue));
        $specialization = self::scalar($specializationValue);
        $civilServiceLevel = strtoupper(self::scalar(
            $attempt['civil_service_level'] ?? $learningContext['civil_service_level'] ?? $attemptContext['civil_service_level'] ?? ''
        ));

        if (strtolower($board) !== 'let') {
            $civilServiceVariantLabel = match ($civilServiceLevel) {
                'PROFESSIONAL' => 'Professional',
                'SUBPROFESSIONAL' => 'Subprofessional',
                default => in_array(strtolower($board), ['cse', 'civil-service'], true) ? 'Civil Service level not recorded' : '',
            };
            return [
                'let_level' => '',
                'let_specialization' => '',
                'let_variant_key' => '',
                'let_variant_label' => '',
                'variant_label' => $civilServiceVariantLabel,
                'civil_service_level' => $civilServiceLevel,
            ];
        }

        if ($level === '' && $specialization === '') {
            return [
                'let_level' => '',
                'let_specialization' => '',
                'let_variant_key' => 'UNSCOPED',
                'let_variant_label' => 'LET variant not recorded',
                'variant_label' => 'LET variant not recorded',
                'civil_service_level' => '',
            ];
        }

        $validated = \App\Services\Quiz\LET\LETVariantRegistry::validateConfiguration([
            'level' => $level,
            'specialization_id' => $specialization,
        ]);
        $configuration = is_array($validated['configuration'] ?? null) ? $validated['configuration'] : [];
        if (($validated['valid'] ?? false) !== true) {
            return [
                'let_level' => $level,
                'let_specialization' => $specialization,
                'let_variant_key' => 'UNAVAILABLE',
                'let_variant_label' => 'Saved LET variant unavailable',
                'variant_label' => 'Saved LET variant unavailable',
                'civil_service_level' => '',
            ];
        }

        $level = (string) ($configuration['level'] ?? $level);
        $specialization = (string) ($configuration['specialization_id'] ?? '');
        $specializationRecord = $specialization !== ''
            ? \App\Services\Quiz\LET\LETVariantRegistry::find($specialization)
            : null;
        $label = ucfirst(strtolower($level));
        if ($specializationRecord !== null) {
            $label .= ' · ' . self::scalar($specializationRecord['official_name'] ?? $specializationRecord['name'] ?? $specialization);
        } elseif ($level === \App\Services\Quiz\LET\LETVariantRegistry::ELEMENTARY) {
            $label .= ' · General and Professional Education';
        }

        return [
            'let_level' => $level,
            'let_specialization' => $specialization,
            'let_variant_key' => self::letVariantKey($level, $specialization),
            'let_variant_label' => $label,
            'variant_label' => $label,
            'civil_service_level' => '',
        ];
    }

    public static function letVariantKey(string $level, string $specialization = ''): string
    {
        return strtoupper(trim($level)) . '|' . trim($specialization);
    }

    public static function recoveryVariantKey(array $context, string $board): string
    {
        if (strtolower($board) === 'let') {
            return is_scalar($context['let_variant_key'] ?? null) ? (string) $context['let_variant_key'] : 'UNSCOPED';
        }
        if (in_array(strtolower($board), ['cse', 'civil-service'], true)) {
            $level = strtoupper(self::scalar($context['civil_service_level'] ?? ''));
            return $level !== '' ? 'CSE|' . $level : 'CSE|UNRECORDED';
        }
        return '';
    }

    /** @return list<array<string,mixed>> */
    public static function filtered(array $items, array $filters): array
    {
        $board = self::boardId($filters['board'] ?? '');
        $subject = self::taxonomyId('subjects', $filters['subject'] ?? '');
        $topic = self::taxonomyId('topics', $filters['topic'] ?? '');
        $recent = ($filters['recent'] ?? '') === '1';
        $repeated = ($filters['repeated'] ?? '') === '1';
        $reviewed = ($filters['reviewed'] ?? '') === '1';
        $corrected = ($filters['corrected'] ?? '') === '1';
        $cutoff = time() - (30 * 86400);

        return array_values(array_filter($items, static function (array $item) use ($board, $subject, $topic, $recent, $repeated, $reviewed, $corrected, $cutoff): bool {
            if ($board !== '' && (string) ($item['board'] ?? '') !== $board) return false;
            if ($subject !== '' && (string) ($item['subject'] ?? '') !== $subject) return false;
            if ($topic !== '' && (string) ($item['topic_id'] ?? '') !== $topic) return false;
            if ($recent && LearningHistoryService::timestampOf(['completed_at' => (string) ($item['last_mistake_at'] ?? '')]) < $cutoff) return false;
            if ($repeated && (int) ($item['wrong_count'] ?? 0) < 2) return false;
            if ($reviewed && ($item['reviewed'] ?? false) !== true) return false;
            if ($corrected && ($item['latest_status'] ?? '') !== 'CORRECT_ON_MOST_RECENT_RETRY') return false;
            return true;
        }));
    }

    public static function boardId(mixed $value): string
    {
        return self::taxonomyId('boards', $value);
    }

    public static function taxonomyId(string $collection, mixed $value): string
    {
        $raw = self::scalar($value);
        if ($raw === '') return '';
        $records = match ($collection) {
            'boards' => TaxonomyStorageService::boards(),
            'subjects' => TaxonomyStorageService::subjects(),
            'topics' => TaxonomyStorageService::topics(),
            default => [],
        };
        foreach ($records as $record) {
            if (!is_array($record)) continue;
            foreach (['id', 'code', 'slug', 'short_code', 'name'] as $field) {
                if (is_scalar($record[$field] ?? null) && strcasecmp(trim((string) $record[$field]), $raw) === 0) {
                    return trim((string) ($record['id'] ?? $raw));
                }
            }
        }
        return $raw;
    }

    private static function contextKey(string $board, string $subject, string $questionId, string $variantKey = ''): string
    {
        return strtolower($board) . "\0" . strtolower($subject) . "\0" . $questionId . "\0" . $variantKey;
    }

    private static function scalar(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }
}
