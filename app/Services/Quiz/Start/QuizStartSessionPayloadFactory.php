<?php

declare(strict_types=1);

namespace App\Services\Quiz\Start;

final class QuizStartSessionPayloadFactory
{
    public static function create(
        object $specification,
        array $questions,
        array $coverage = [],
        array $issues = [],
        array $studyContext = []
    ): array
    {
        $timeLimitMinutes = $specification->mode === 'exam'
            ? max(0, (int) ($specification->timeLimitMinutes ?? 0))
            : 0;
        $timeLimitSeconds = $timeLimitMinutes * 60;
        $repeatedContentCount = count(array_filter(
            $questions,
            static fn(mixed $question): bool => is_array($question)
                && ($question['_selection_duplicate_content_fallback'] ?? false) === true
        ));
        $selectionNotice = null;
        if ($repeatedContentCount > 0) {
            $selectionNotice = sprintf(
                'This session includes %d repeated question item%s because the selected pool had too few distinct questions to meet the requested count.',
                $repeatedContentCount,
                $repeatedContentCount === 1 ? '' : 's'
            );
        } elseif (count($questions) < max(0, (int) $specification->questionCount)) {
            $selectionNotice = sprintf(
                'This session contains %d of %d requested eligible questions because the selected pool was too small.',
                count($questions),
                max(0, (int) $specification->questionCount)
            );
        }
        $studyReturnUrl = is_scalar($studyContext['return_url'] ?? null)
            ? (string) $studyContext['return_url']
            : '';
        if (!str_starts_with($studyReturnUrl, '/study-library?material=')
            && preg_match('#\A/my-notes\?note=note-[a-f0-9]{32}\z#', $studyReturnUrl) !== 1) {
            $studyReturnUrl = '';
        }
        $notesPractice = self::notesPracticeContext($studyContext['notes_practice'] ?? null, $questions);

        return [
            'id' => 'quiz-' . bin2hex(random_bytes(8)),
            'board' => $specification->board,
            'subject' => $specification->subject,
            'domain' => $specification->domain,
            'topics' => $specification->topics,
            'mode' => $specification->mode,
            'session_type' => $specification->mode === 'exam' ? 'exam_simulation' : 'quiz',
            'timer_mode' => $timeLimitSeconds > 0 ? 'timed' : 'untimed',
            'time_limit_minutes' => $timeLimitMinutes,
            'time_limit_seconds' => $timeLimitSeconds,
            'deadline_epoch' => $timeLimitSeconds > 0 ? time() + $timeLimitSeconds : null,
            'time_expired' => false,
            'let_variant' => $specification->letVariant,
            'let_level' => $specification->letVariant['level'] ?? null,
            'let_specialization_id' => $specification->letVariant['specialization_id'] ?? null,
            'civil_service_level' => $specification->civilServiceLevel,
            'difficulty' => $specification->difficulty,
            'requested_question_count' => $specification->questionCount,
            'question_count' => count($questions),
            'question_ids' => QuizStartQuestionIdExtractor::fromQuestions($questions),
            'started_at' => date('c'),
            'blueprint_coverage' => $coverage,
            'blueprint_issues' => $issues,
            'selection_notice' => $selectionNotice,
            'study_return_url' => $studyReturnUrl,
            'notes_practice' => $notesPractice,
        ];
    }

    private static function notesPracticeContext(mixed $context, array $questions): ?array
    {
        if (!is_array($context)
            || preg_match('/\Anote-[a-f0-9]{32}\z/', (string) ($context['note_id'] ?? '')) !== 1) {
            return null;
        }
        $questionIds = [];
        foreach ($questions as $question) {
            if (is_array($question) && is_scalar($question['id'] ?? null)) $questionIds[(string) $question['id']] = true;
        }
        $reviewByQuestion = [];
        foreach (is_array($context['question_notes'] ?? null) ? $context['question_notes'] : [] as $questionId => $item) {
            $questionId = (string) $questionId;
            if (!isset($questionIds[$questionId]) || !is_array($item)) continue;
            $reviewByQuestion[$questionId] = [
                'topic' => self::shortText($item['topic'] ?? '', 120),
                'excerpt' => self::shortText($item['excerpt'] ?? '', 260),
                'possible_answer_discrepancy' => ($item['possible_answer_discrepancy'] ?? false) === true,
            ];
        }
        return [
            'note_id' => (string) $context['note_id'],
            'note_title' => self::shortText($context['note_title'] ?? 'My notes', 120),
            'topics' => self::textList($context['topics'] ?? []),
            'limited_topics' => self::textList($context['limited_topics'] ?? []),
            'uncovered_topics' => self::textList($context['uncovered_topics'] ?? []),
            'question_notes' => $reviewByQuestion,
        ];
    }

    private static function textList(mixed $values): array
    {
        if (!is_array($values)) return [];
        $result = [];
        foreach ($values as $value) {
            if (!is_scalar($value)) continue;
            $text = self::shortText((string) $value, 120);
            if ($text !== '' && !in_array($text, $result, true)) $result[] = $text;
            if (count($result) >= 30) break;
        }
        return $result;
    }

    private static function shortText(mixed $value, int $limit): string
    {
        if (!is_scalar($value)) return '';
        $text = trim((string) $value);
        if (function_exists('mb_strlen') && mb_strlen($text, 'UTF-8') > $limit) {
            return mb_substr($text, 0, $limit, 'UTF-8');
        }
        if (!function_exists('mb_strlen') && strlen($text) > $limit) return substr($text, 0, $limit);
        return $text;
    }
}
