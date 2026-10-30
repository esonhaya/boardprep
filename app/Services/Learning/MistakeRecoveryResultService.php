<?php

declare(strict_types=1);

namespace App\Services\Learning;

use App\Services\Shared\TaxonomyStorageService;

/** Creates a transparent recovery summary from the quiz's already graded result. */
final class MistakeRecoveryResultService
{
    public static function create(array $context, array $review, array $attempts): array
    {
        $correct = 0;
        $incorrect = 0;
        $unanswered = 0;
        $practiced = [];
        $revisit = [];
        $questions = [];
        foreach ($review as $item) {
            if (!is_array($item) || !is_array($item['question'] ?? null)) continue;
            $question = $item['question'];
            $questionId = is_scalar($question['id'] ?? null) ? (string) $question['id'] : '';
            $taxonomy = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
            $topicId = is_scalar($taxonomy['topic_id'] ?? null) ? trim((string) $taxonomy['topic_id']) : '';
            $topic = $topicId !== '' ? TaxonomyStorageService::label('topics', $topicId) : 'Topic not recorded';
            $answered = ($item['answered'] ?? false) === true;
            $isCorrect = $answered && ($item['correct'] ?? false) === true;
            if (!$answered) $unanswered++;
            elseif ($isCorrect) $correct++;
            else $incorrect++;
            $practiced[$topic] = true;
            if (!$isCorrect) $revisit[$topic] = true;
            $questions[] = [
                'question_id' => $questionId,
                'topic' => $topic,
                'outcome' => !$answered ? 'UNANSWERED' : ($isCorrect ? 'CORRECT' : 'INCORRECT'),
                'selected_answer' => is_scalar($item['userAnswer'] ?? null) ? (string) $item['userAnswer'] : '',
                'correct_answer' => is_scalar($item['correctAnswer'] ?? null)
                    ? (string) $item['correctAnswer']
                    : (is_scalar($question['answer'] ?? null) ? (string) $question['answer'] : ''),
            ];
        }
        $board = MistakeNotebookService::boardId($context['board'] ?? '');
        $subject = MistakeNotebookService::taxonomyId('subjects', $context['subject'] ?? '');
        $topicId = MistakeNotebookService::taxonomyId('topics', $context['topic_id'] ?? '');
        $level = is_scalar($context['civil_service_level'] ?? null) ? (string) $context['civil_service_level'] : null;
        $letLevel = strtoupper(is_scalar($context['let_level'] ?? null) ? trim((string) $context['let_level']) : '');
        $letSpecialization = is_scalar($context['let_specialization_id'] ?? null)
            ? trim((string) $context['let_specialization_id'])
            : '';
        $letVariant = $letLevel !== '' ? ['level' => $letLevel, 'specialization_id' => $letSpecialization] : null;
        $variantKey = is_scalar($context['let_variant_key'] ?? null)
            ? (string) $context['let_variant_key']
            : null;
        $mode = ($context['mode'] ?? '') === 'related_topics' ? 'RELATED_QUESTIONS' : 'RETRY_MISSED_QUESTIONS';

        return [
            'mode' => $mode,
            'board' => $board,
            'subject' => $subject,
            'topic_id' => $topicId,
            'let_level' => $letLevel,
            'let_specialization_id' => $letSpecialization,
            'let_variant_key' => $variantKey,
            'source_question_ids' => is_array($context['source_question_ids'] ?? null)
                ? array_values(array_filter($context['source_question_ids'], 'is_string'))
                : [],
            'focus_question_id' => is_scalar($context['focus_question_id'] ?? null)
                ? (string) $context['focus_question_id'] : '',
            'topics_practiced' => array_keys($practiced),
            'topics_to_revisit' => array_keys($revisit),
            'correct' => $correct,
            'incorrect' => $incorrect,
            'unanswered' => $unanswered,
            'questions' => $questions,
            'remaining_eligible_mistakes' => $board !== ''
                ? (new MistakePracticePoolService())->remainingEligibleCount($attempts, $board, $subject, $level, $topicId, $variantKey, $letVariant)
                : 0,
        ];
    }
}
