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
        array $studyContext = [],
        ?string $selectionExplanation = null
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
        $mistakeRecovery = self::mistakeRecoveryContext(
            $studyContext['mistake_recovery'] ?? null,
            $questions,
            $specification
        );
        if ($mistakeRecovery !== null) {
            $studyReturnUrl = '/mistake-notebook';
        }

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
            'adaptive_selection_explanation' => $specification->mode === 'practice'
                && $specification->adaptive
                ? (is_string($selectionExplanation) ? substr(trim($selectionExplanation), 0, 500) : '')
                : '',
            'study_return_url' => $studyReturnUrl,
            'notes_practice' => $notesPractice,
            'mistake_recovery' => $mistakeRecovery,
        ];
    }

    private static function mistakeRecoveryContext(mixed $context, array $questions, object $specification): ?array
    {
        if (!is_array($context) || !in_array($context['mode'] ?? '', ['retry_missed', 'related_topics'], true)) {
            return null;
        }
        $board = is_scalar($context['board'] ?? null) ? trim((string) $context['board']) : '';
        if ($board === '' || strtolower($board) !== strtolower((string) ($specification->board ?? ''))) return null;
        $selectedIds = [];
        foreach ($questions as $question) {
            if (is_array($question) && is_scalar($question['id'] ?? null)) {
                $id = trim((string) $question['id']);
                if ($id !== '') $selectedIds[$id] = true;
            }
        }
        $sourceIds = [];
        foreach (is_array($context['source_question_ids'] ?? null) ? $context['source_question_ids'] : [] as $rawId) {
            if (!is_scalar($rawId)) continue;
            $id = trim((string) $rawId);
            if ($id !== '' && !in_array($id, $sourceIds, true)) $sourceIds[] = $id;
            if (count($sourceIds) >= 200) break;
        }
        $focusId = is_scalar($context['focus_question_id'] ?? null) ? trim((string) $context['focus_question_id']) : '';
        if ($focusId !== '' && !in_array($focusId, $sourceIds, true)) {
            $providedSourceIds = is_array($context['source_question_ids'] ?? null) ? $context['source_question_ids'] : [];
            $focusWasProvided = false;
            foreach ($providedSourceIds as $rawId) {
                if (is_scalar($rawId) && trim((string) $rawId) === $focusId) {
                    $focusWasProvided = true;
                    break;
                }
            }
            if ($focusWasProvided) {
                $sourceIds = array_slice(array_merge([$focusId], $sourceIds), 0, 200);
            }
        }
        if ($focusId !== '' && !in_array($focusId, $sourceIds, true)) $focusId = '';
        $subject = is_scalar($context['subject'] ?? null) ? trim((string) $context['subject']) : '';
        $topicId = \App\Services\Learning\MistakeNotebookService::taxonomyId('topics', $context['topic_id'] ?? '');
        $level = strtoupper(is_scalar($context['civil_service_level'] ?? null) ? trim((string) $context['civil_service_level']) : '');
        if (!in_array($level, ['', 'PROFESSIONAL', 'SUBPROFESSIONAL'], true)) $level = '';
        $letVariant = is_array($specification->letVariant ?? null) ? $specification->letVariant : [];
        $letLevel = (string) ($letVariant['level'] ?? '');
        $letSpecialization = (string) ($letVariant['specialization_id'] ?? '');
        return [
            'mode' => (string) $context['mode'],
            'board' => $board,
            'subject' => $subject,
            'topic_id' => $topicId,
            'civil_service_level' => $level,
            'let_level' => $letLevel,
            'let_specialization_id' => $letSpecialization,
            'let_variant_key' => $letLevel !== '' ? \App\Services\Learning\MistakeNotebookService::letVariantKey($letLevel, $letSpecialization) : 'UNSCOPED',
            'focus_question_id' => $focusId,
            'source_question_ids' => $sourceIds,
            'selected_question_ids' => array_keys($selectedIds),
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
