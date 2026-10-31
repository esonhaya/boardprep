<?php

declare(strict_types=1);

namespace App\Services\Learning;

use DateTimeImmutable;
use InvalidArgumentException;

/** Deterministic, explainable schedule builder; it never changes academic data. */
final class StudyCalendarPlanner
{
    public const MAX_FUTURE_SESSIONS = 28;
    public const MAX_WINDOW_DAYS = 42;
    public const MAX_COMPLETED_HISTORY = 100;

    /** @return array<string,mixed> */
    public static function validateConfiguration(array $input, array $boards, string $today): array
    {
        self::requireDate($today, 'Current date');
        $requestedBoard = self::text($input['board_id'] ?? '');
        $board = null;
        foreach ($boards as $candidate) {
            if (!is_array($candidate)) continue;
            if (strcasecmp($requestedBoard, (string) ($candidate['id'] ?? '')) === 0
                || strcasecmp($requestedBoard, (string) ($candidate['code'] ?? '')) === 0) {
                $board = $candidate;
                break;
            }
        }
        if (!is_array($board)) {
            throw new InvalidArgumentException('Choose an available examination board.');
        }

        $boardId = self::text($board['id'] ?? '');
        $boardCode = self::text($board['code'] ?? $boardId);
        $availableSubjects = [];
        foreach ((array) ($board['practice_subjects'] ?? []) as $subject) {
            if (!is_array($subject)) continue;
            $id = strtolower(self::text($subject['id'] ?? ''));
            if ($id !== '' && (int) ($subject['available_questions'] ?? 1) > 0) {
                $availableSubjects[$id] = [
                    'id' => $id,
                    'code' => self::text($subject['code'] ?? ''),
                    'name' => self::text($subject['name'] ?? $id),
                ];
            }
        }
        if ($availableSubjects === []) {
            throw new InvalidArgumentException('This board has no currently available practice subjects.');
        }

        $examDate = self::optionalDate($input['exam_date'] ?? '', 'Target exam date');
        $startDate = self::optionalDate($input['start_date'] ?? '', 'Study start date') ?? $today;
        if ($startDate < $today) {
            throw new InvalidArgumentException('Study start date cannot be in the past.');
        }
        $latestAllowed = (new DateTimeImmutable($today))->modify('+365 days')->format('Y-m-d');
        if ($startDate > $latestAllowed || ($examDate !== null && $examDate > $latestAllowed)) {
            throw new InvalidArgumentException('Study dates must be within the next 365 days.');
        }
        if ($examDate !== null && $examDate < $startDate) {
            throw new InvalidArgumentException('Target exam date must be on or after the study start date.');
        }

        $rawWeekdays = $input['weekdays'] ?? null;
        if (!is_array($rawWeekdays)) {
            throw new InvalidArgumentException('Select at least one available weekday.');
        }
        $weekdays = [];
        foreach ($rawWeekdays as $weekday) {
            if (!is_scalar($weekday)) throw new InvalidArgumentException('Available weekdays are invalid.');
            $value = filter_var((string) $weekday, FILTER_VALIDATE_INT);
            if ($value === false || $value < 1 || $value > 7) {
                throw new InvalidArgumentException('Available weekdays are invalid.');
            }
            $weekdays[$value] = $value;
        }
        sort($weekdays, SORT_NUMERIC);
        if ($weekdays === []) {
            throw new InvalidArgumentException('Select at least one available weekday.');
        }

        $dailyMinutes = filter_var(
            is_scalar($input['daily_minutes'] ?? null) ? (string) $input['daily_minutes'] : '',
            FILTER_VALIDATE_INT
        );
        if ($dailyMinutes === false || $dailyMinutes < 15 || $dailyMinutes > 240) {
            throw new InvalidArgumentException('Daily study time must be between 15 and 240 minutes.');
        }

        $intensity = strtolower(self::text($input['review_intensity'] ?? 'balanced'));
        if (!in_array($intensity, ['light', 'balanced', 'intensive'], true)) {
            throw new InvalidArgumentException('Choose light, balanced, or intensive review.');
        }

        $rawRestDates = $input['rest_dates'] ?? [];
        if (is_string($rawRestDates)) {
            $rawRestDates = preg_split('/[\s,;]+/', trim($rawRestDates), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        }
        if (!is_array($rawRestDates) || count($rawRestDates) > 90) {
            throw new InvalidArgumentException('Enter up to 90 optional rest dates.');
        }
        $restDates = [];
        foreach ($rawRestDates as $date) {
            if (!is_scalar($date)) throw new InvalidArgumentException('Rest dates are invalid.');
            $canonical = self::requireDate(trim((string) $date), 'Rest date');
            if ($canonical < $today || $canonical > $latestAllowed) {
                throw new InvalidArgumentException('Rest dates must be today or within the next 365 days.');
            }
            $restDates[$canonical] = $canonical;
        }
        sort($restDates, SORT_STRING);

        $prioritiesByBoard = $input['subject_priorities'] ?? [];
        if (is_array($prioritiesByBoard) && isset($prioritiesByBoard[$boardId]) && is_array($prioritiesByBoard[$boardId])) {
            $prioritiesByBoard = $prioritiesByBoard[$boardId];
        }
        if (!is_array($prioritiesByBoard)) {
            throw new InvalidArgumentException('Subject priorities are invalid.');
        }
        $priorities = [];
        foreach ($prioritiesByBoard as $subjectId => $priority) {
            if (!is_string($subjectId) || !isset($availableSubjects[strtolower($subjectId)])) {
                throw new InvalidArgumentException('A selected subject is not available for this board.');
            }
            if (!is_scalar($priority)) throw new InvalidArgumentException('Subject priorities are invalid.');
            $normalized = strtolower(trim((string) $priority));
            if (!in_array($normalized, ['low', 'normal', 'high'], true)) {
                throw new InvalidArgumentException('Choose low, normal, or high for each subject priority.');
            }
            $priorities[strtolower($subjectId)] = $normalized;
        }
        foreach ($availableSubjects as $subjectId => $_subject) {
            $priorities[$subjectId] ??= 'normal';
        }
        ksort($priorities, SORT_STRING);

        return [
            'schema_version' => 1,
            'board_id' => $boardId,
            'board_code' => $boardCode,
            'board_name' => self::text($board['name'] ?? $boardCode),
            'exam_date' => $examDate,
            'start_date' => $startDate,
            'weekdays' => array_values($weekdays),
            'daily_minutes' => $dailyMinutes,
            'review_intensity' => $intensity,
            'rest_dates' => array_values($restDates),
            'subject_priorities' => $priorities,
            'subjects' => $availableSubjects,
        ];
    }

    /** @return array{sessions:list<array<string,mixed>>,window_end_date:string,performance_status:string,explanations:list<string>} */
    public static function generate(array $configuration, array $evidence, string $today): array
    {
        $startDate = (string) ($configuration['start_date'] ?? $today);
        $examDate = is_string($configuration['exam_date'] ?? null) ? $configuration['exam_date'] : null;
        $hardEnd = $examDate ?? (new DateTimeImmutable($startDate))->modify('+13 days')->format('Y-m-d');
        $windowEnd = min(
            $hardEnd,
            (new DateTimeImmutable($startDate))->modify('+' . (self::MAX_WINDOW_DAYS - 1) . ' days')->format('Y-m-d')
        );
        $availableSubjects = is_array($configuration['subjects'] ?? null) ? $configuration['subjects'] : [];
        $priorities = is_array($configuration['subject_priorities'] ?? null) ? $configuration['subject_priorities'] : [];
        $subjectPool = [];
        foreach ($availableSubjects as $subjectId => $subject) {
            if (!is_array($subject) || !isset($priorities[$subjectId])) continue;
            $weight = match ($priorities[$subjectId]) { 'high' => 3, 'normal' => 2, default => 1 };
            for ($i = 0; $i < $weight; $i++) $subjectPool[] = (string) $subjectId;
        }
        if ($subjectPool === []) {
            throw new InvalidArgumentException('No subjects are available for this study plan.');
        }

        $attemptCount = max(0, min(100000, (int) ($evidence['attempt_count'] ?? 0)));
        $performanceStatus = $attemptCount < 3 ? 'INSUFFICIENT_DATA' : 'EVIDENCE_AVAILABLE';
        $focusTopics = $performanceStatus === 'EVIDENCE_AVAILABLE' && is_array($evidence['focus_topics'] ?? null)
            ? $evidence['focus_topics']
            : [];
        $dueCount = max(0, min(100000, (int) ($evidence['due_flashcard_count'] ?? 0)));
        $mistakeCount = max(0, min(100000, (int) ($evidence['mistake_count'] ?? 0)));
        $reviewCadence = match ($configuration['review_intensity'] ?? 'balanced') {
            'light' => 7,
            'intensive' => 2,
            default => 4,
        };

        $weekdays = array_fill_keys(array_map('intval', (array) ($configuration['weekdays'] ?? [])), true);
        $restDates = array_fill_keys(array_map('strval', (array) ($configuration['rest_dates'] ?? [])), true);
        $sessions = [];
        $sessionNumber = 0;
        $weeklyCounts = [];
        $weeklyCapacity = count($weekdays) >= 3
            ? min(5, count($weekdays) - 1)
            : count($weekdays);
        $date = new DateTimeImmutable($startDate);
        $end = new DateTimeImmutable($windowEnd);
        while ($date <= $end && count($sessions) < self::MAX_FUTURE_SESSIONS) {
            $dateString = $date->format('Y-m-d');
            $weekKey = $date->format('o-W');
            if (isset($weekdays[(int) $date->format('N')])
                && !isset($restDates[$dateString])
                && ($weeklyCounts[$weekKey] ?? 0) < $weeklyCapacity) {
                $subjectId = $subjectPool[$sessionNumber % count($subjectPool)];
                $subject = $availableSubjects[$subjectId];
                $topic = self::focusForSubject($focusTopics, $subject, $configuration);
                $kind = 'PRACTICE';
                if ($dueCount > 0 && $sessionNumber % $reviewCadence === 0) {
                    $kind = 'FLASHCARDS';
                } elseif ($mistakeCount > 0 && $sessionNumber % max(3, $reviewCadence + 1) === 1) {
                    $kind = 'MISTAKES';
                }
                $action = match ($kind) {
                    'FLASHCARDS' => '/flashcards/review?' . http_build_query(['board' => $configuration['board_code']]),
                    'MISTAKES' => '/mistake-notebook/review',
                    default => '/quiz?' . http_build_query(array_filter([
                        'exam' => $configuration['board_code'],
                        'subject' => $subjectId,
                        'topic' => $topic['topic'] ?? null,
                    ], static fn(mixed $value): bool => is_string($value) && $value !== '')),
                };
                $reason = match ($kind) {
                    'FLASHCARDS' => 'A due flashcard review was recorded for this board when the plan was generated.',
                    'MISTAKES' => 'Your saved mistake or skipped-question recovery data supports a review session.',
                    default => $topic !== null
                        ? 'This topic is a focus area in your recorded performance history.'
                        : ($performanceStatus === 'INSUFFICIENT_DATA'
                            ? 'Practice builds a baseline; at least three completed quizzes are needed for trend-based focus.'
                            : 'Subject practice follows your selected priority and current board catalog.'),
                };
                $sessions[] = [
                    'task_id' => 'task-' . substr(hash('sha256', $dateString . "\0" . $subjectId . "\0" . $kind . "\0" . (string) ($topic['topic'] ?? '')), 0, 24),
                    'scheduled_date' => $dateString,
                    'board_id' => (string) ($configuration['board_id'] ?? ''),
                    'board_code' => (string) ($configuration['board_code'] ?? ''),
                    'board_name' => (string) ($configuration['board_name'] ?? ''),
                    'subject_id' => $subjectId,
                    'subject_name' => (string) ($subject['name'] ?? $subjectId),
                    'kind' => $kind,
                    'topic' => $topic['topic'] ?? null,
                    'topic_average' => $topic['average'] ?? null,
                    'evidence_attempts' => $topic === null ? 0 : (int) ($topic['attempts'] ?? $attemptCount),
                    'duration_minutes' => (int) ($configuration['daily_minutes'] ?? 30),
                    'status' => 'planned',
                    'action' => $action,
                    'reason' => $reason,
                    'user_modified' => false,
                    'completed_at' => null,
                ];
                $sessionNumber++;
                $weeklyCounts[$weekKey] = ($weeklyCounts[$weekKey] ?? 0) + 1;
            }
            $date = $date->modify('+1 day');
        }
        if ($sessions === []) {
            throw new InvalidArgumentException('Your dates include no available study days. Add a weekday or remove a rest day.');
        }
        return [
            'sessions' => $sessions,
            'window_end_date' => $windowEnd,
            'performance_status' => $performanceStatus,
            'explanations' => [
                $performanceStatus === 'INSUFFICIENT_DATA'
                    ? 'Performance focus is unavailable until three completed quizzes are recorded.'
                    : 'Focus topics come from recorded attempt history; no official exam prediction is made.',
                'The schedule leaves an available study day open in weeks with three or more selected weekdays so missed work can be rescheduled without stacking sessions.',
                ($evidence['due_flashcard_status'] ?? 'AVAILABLE') !== 'AVAILABLE'
                    ? 'The due-card queue could not be checked when this plan was generated; no flashcard count or review requirement is assumed.'
                    : ($dueCount > 0 ? 'Flashcard sessions are scheduled from the learner’s existing due queue.' : 'No due flashcards were found for this board at generation time.'),
                $mistakeCount > 0 ? 'Mistake sessions link to existing learner recovery records.' : 'No saved mistake evidence was found at generation time.',
            ],
        ];
    }

    public static function validScheduledDate(string $date, array $configuration, string $today): bool
    {
        try { $date = self::requireDate($date, 'Study date'); } catch (InvalidArgumentException) { return false; }
        if ($date < $today) return false;
        $limit = is_string($configuration['exam_date'] ?? null)
            ? $configuration['exam_date']
            : (new DateTimeImmutable($today))->modify('+90 days')->format('Y-m-d');
        if ($date > $limit) return false;
        if (!in_array((int) (new DateTimeImmutable($date))->format('N'), array_map('intval', (array) ($configuration['weekdays'] ?? [])), true)) return false;
        return !in_array($date, (array) ($configuration['rest_dates'] ?? []), true);
    }

    private static function focusForSubject(array $topics, array $subject, array $configuration): ?array
    {
        $subjectAliases = array_fill_keys(array_filter(array_map(
            static fn(mixed $value): string => strtolower(trim((string) $value)),
            [$subject['id'] ?? '', $subject['name'] ?? '', $subject['code'] ?? '']
        )), true);
        $boardAliases = array_fill_keys(array_filter(array_map(
            static fn(mixed $value): string => strtolower(trim((string) $value)),
            [$configuration['board_id'] ?? '', $configuration['board_code'] ?? '', $configuration['board_name'] ?? '']
        )), true);
        foreach ($topics as $topic) {
            if (!is_array($topic)) continue;
            $topicSubject = strtolower(self::text($topic['subject'] ?? ''));
            $topicBoard = strtolower(self::text($topic['board'] ?? ''));
            if (!isset($subjectAliases[$topicSubject]) || !isset($boardAliases[$topicBoard])) continue;
            if (trim(self::text($topic['topic'] ?? '')) === '' || (int) ($topic['attempts'] ?? 0) < 2) continue;
            return $topic;
        }
        return null;
    }

    private static function optionalDate(mixed $value, string $label): ?string
    {
        if (!is_scalar($value) || trim((string) $value) === '') return null;
        return self::requireDate(trim((string) $value), $label);
    }

    private static function requireDate(string $value, string $label): string
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = DateTimeImmutable::getLastErrors();
        if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $date->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException($label . ' must use the valid YYYY-MM-DD format.');
        }
        return $value;
    }

    private static function text(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }
}
