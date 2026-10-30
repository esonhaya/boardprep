<?php

declare(strict_types=1);

namespace App\Services\Learning;

use App\Core\App;
use App\Repositories\QuestionRepository;
use App\Services\Question\QuestionEligibilityService;
use App\Services\Shared\TaxonomyStorageService;

/** Builds small retry and same-taxonomy pools, leaving final selection to BoardPrep's quiz path. */
final class MistakePracticePoolService
{
    /** @return array{questions:list<array>,eligibility_index:\App\Services\Question\QuestionEligibilityIndex,source_question_ids:list<string>} */
    public function retry(
        array $attempts,
        string $board,
        string $subject = '',
        ?string $civilServiceLevel = null,
        string $focusQuestionId = '',
        string $topicId = '',
        ?array $summaries = null,
        ?string $variantKey = null,
        ?array $letVariant = null
    ): array
    {
        $eligibility = QuestionEligibilityService::index();
        if (!self::variantRequestValid($board, $variantKey, $letVariant)) {
            return ['questions' => [], 'eligibility_index' => $eligibility, 'source_question_ids' => []];
        }
        $mistakes = $summaries ?? MistakeNotebookService::summaries($attempts);
        $targetIds = [];
        foreach ($mistakes as $mistake) {
            if ((string) $mistake['board'] !== $board
                || ($subject !== '' && (string) $mistake['subject'] !== $subject)
                || ($topicId !== '' && (string) $mistake['topic_id'] !== $topicId)
                || ($focusQuestionId !== '' && (string) $mistake['question_id'] !== $focusQuestionId)) {
                continue;
            }
            foreach (is_array($mistake['events'] ?? null) ? $mistake['events'] : [] as $event) {
                if (!is_array($event) || ($event['answered'] ?? false) !== true || ($event['correct'] ?? true) !== false
                    || !self::eventMatchesVariant($event, $board, $civilServiceLevel, $variantKey)) continue;
                $targetIds[(string) $mistake['question_id']] = true;
            }
        }
        foreach (MistakeNotebookService::skipped($attempts, $mistakes) as $item) {
            if ((string) ($item['board'] ?? '') !== $board
                || ($subject !== '' && (string) ($item['subject'] ?? '') !== $subject)
                || ($topicId !== '' && (string) ($item['topic_id'] ?? '') !== $topicId)
                || ($focusQuestionId !== '' && (string) ($item['question_id'] ?? '') !== $focusQuestionId)
                || !self::eventMatchesVariant($item, $board, $civilServiceLevel, $variantKey)) continue;
            $targetIds[(string) $item['question_id']] = true;
        }
        if ($targetIds === []) {
            return ['questions' => [], 'eligibility_index' => $eligibility, 'source_question_ids' => []];
        }

        $repository = App::container()->get(QuestionRepository::class);
        $canonical = $repository->findMany(array_keys($targetIds));
        $request = new \SelectionRequest(
            subject: $subject !== '' ? $subject : '*',
            domain: null,
            difficultyDistribution: [],
            questionCount: max(1, count($targetIds)),
            exam: $board,
            letVariant: $letVariant,
            civilServiceLevel: $civilServiceLevel
        );
        $eligible = \QuestionPoolFilter::filter(array_values($canonical), $request, $eligibility);
        return [
            'questions' => array_values(array_filter($eligible, static fn(array $question): bool => isset($targetIds[(string) ($question['id'] ?? '')]))),
            'eligibility_index' => $eligibility,
            'source_question_ids' => array_keys($targetIds),
        ];
    }

    /**
     * Related practice requires an exact shared topic or concept ID, then reruns
     * each candidate through the normal board/subject eligibility filter.
     * @return array{questions:list<array>,eligibility_index:\App\Services\Question\QuestionEligibilityIndex,source_question_ids:list<string>,topics:list<string>}
     */
    public function related(
        array $attempts,
        string $board,
        string $subject = '',
        ?string $civilServiceLevel = null,
        string $focusQuestionId = '',
        string $topicId = '',
        int $limit = 20,
        ?array $summaries = null,
        ?string $variantKey = null,
        ?array $letVariant = null
    ): array
    {
        $eligibility = QuestionEligibilityService::index();
        if (!self::variantRequestValid($board, $variantKey, $letVariant)) {
            return ['questions' => [], 'eligibility_index' => $eligibility, 'source_question_ids' => [], 'topics' => []];
        }
        $mistakes = $summaries ?? MistakeNotebookService::summaries($attempts);
        $sourceQuestions = [];
        $allMissedIds = [];
        foreach ($mistakes as $mistake) {
            if ((string) $mistake['board'] === $board
                && ($subject === '' || (string) $mistake['subject'] === $subject)
                && ($topicId === '' || (string) $mistake['topic_id'] === $topicId)) {
                foreach (is_array($mistake['events'] ?? null) ? $mistake['events'] : [] as $event) {
                    if (!is_array($event) || ($event['answered'] ?? false) !== true || ($event['correct'] ?? true) !== false) continue;
                    $questionId = (string) $mistake['question_id'];
                    $allMissedIds[$questionId] = true;
                    if (!self::eventMatchesVariant($event, $board, $civilServiceLevel, $variantKey)) continue;
                    if (($focusQuestionId === '' || $questionId === $focusQuestionId) && is_array($event['question'] ?? null)) {
                        $sourceQuestions[$questionId] = $event['question'];
                    }
                }
            }
        }
        foreach (MistakeNotebookService::skipped($attempts, $mistakes) as $item) {
            if ((string) ($item['board'] ?? '') !== $board
                || ($subject !== '' && (string) ($item['subject'] ?? '') !== $subject)
                || ($topicId !== '' && (string) ($item['topic_id'] ?? '') !== $topicId)) continue;
            $questionId = (string) ($item['question_id'] ?? '');
            if ($questionId === '') continue;
            $allMissedIds[$questionId] = true;
            if (!self::eventMatchesVariant($item, $board, $civilServiceLevel, $variantKey)) continue;
            if (($focusQuestionId === '' || $questionId === $focusQuestionId) && is_array($item['question_snapshot'] ?? null)) {
                $sourceQuestions[$questionId] = $item['question_snapshot'];
            }
        }
        if ($sourceQuestions === []) {
            return ['questions' => [], 'eligibility_index' => $eligibility, 'source_question_ids' => [], 'topics' => []];
        }

        $topicIds = [];
        $conceptIds = [];
        foreach ($sourceQuestions as $question) {
            $taxonomy = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
            $topic = self::scalar($taxonomy['topic_id'] ?? '');
            if ($topic !== '') $topicIds[$topic] = TaxonomyStorageService::label('topics', $topic);
            foreach (['concept_id', 'concept_ids'] as $field) {
                $values = is_array($taxonomy[$field] ?? null) ? $taxonomy[$field] : [$taxonomy[$field] ?? null];
                foreach ($values as $concept) {
                    $concept = self::scalar($concept);
                    if ($concept !== '') $conceptIds[$concept] = $concept;
                }
            }
        }
        if ($topicIds === [] && $conceptIds === []) {
            return ['questions' => [], 'eligibility_index' => $eligibility, 'source_question_ids' => array_keys($sourceQuestions), 'topics' => []];
        }

        $allQuestions = App::container()->get(QuestionRepository::class)->all();
        $relatedCandidates = [];
        foreach ($allQuestions as $question) {
            if (!is_array($question)) continue;
            $id = self::scalar($question['id'] ?? '');
            if ($id === '' || isset($allMissedIds[$id])) continue;
            $taxonomy = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
            $topic = self::scalar($taxonomy['topic_id'] ?? '');
            $questionConcepts = [];
            foreach (['concept_id', 'concept_ids'] as $field) {
                $values = is_array($taxonomy[$field] ?? null) ? $taxonomy[$field] : [$taxonomy[$field] ?? null];
                foreach ($values as $value) {
                    $value = self::scalar($value);
                    if ($value !== '') $questionConcepts[$value] = true;
                }
            }
            if (isset($topicIds[$topic]) || array_intersect_key($conceptIds, $questionConcepts) !== []) {
                $relatedCandidates[] = $question;
            }
        }

        $request = new \SelectionRequest(
            subject: $subject !== '' ? $subject : '*',
            domain: null,
            difficultyDistribution: [],
            questionCount: max(1, count($relatedCandidates)),
            exam: $board,
            letVariant: $letVariant,
            civilServiceLevel: $civilServiceLevel
        );
        $relatedById = [];
        foreach (\QuestionPoolFilter::filter($relatedCandidates, $request, $eligibility) as $question) {
            $id = self::scalar($question['id'] ?? '');
            if ($id !== '') $relatedById[$id] = $question;
        }

        $seenCounts = self::seenCounts($attempts, $board, $subject);
        uasort($relatedById, static function (array $left, array $right) use ($seenCounts): int {
            $leftId = (string) ($left['id'] ?? '');
            $rightId = (string) ($right['id'] ?? '');
            $seenOrder = ($seenCounts[$leftId] ?? 0) <=> ($seenCounts[$rightId] ?? 0);
            return $seenOrder !== 0 ? $seenOrder : strcmp($leftId, $rightId);
        });

        return [
            'questions' => array_values(array_slice($relatedById, 0, max(1, min(20, $limit)))),
            'eligibility_index' => $eligibility,
            'source_question_ids' => array_keys($sourceQuestions),
            'topics' => array_values($topicIds),
        ];
    }

    public function remainingEligibleCount(
        array $attempts,
        string $board,
        string $subject = '',
        ?string $civilServiceLevel = null,
        string $topicId = '',
        ?string $variantKey = null,
        ?array $letVariant = null
    ): int
    {
        return count($this->retry($attempts, $board, $subject, $civilServiceLevel, '', $topicId, null, $variantKey, $letVariant)['questions']);
    }

    private static function eventMatchesVariant(array $event, string $board, ?string $civilServiceLevel, ?string $variantKey): bool
    {
        if (strtolower($board) === 'let' && $variantKey !== null
            && (string) ($event['let_variant_key'] ?? 'UNSCOPED') !== $variantKey) return false;
        if (in_array(strtolower($board), ['cse', 'civil-service'], true) && $civilServiceLevel !== null) {
            $recordedLevel = strtoupper(self::scalar($event['civil_service_level'] ?? ''));
            if ($recordedLevel !== '' && $recordedLevel !== strtoupper($civilServiceLevel)) return false;
        }
        return true;
    }

    private static function variantRequestValid(string $board, ?string $variantKey, ?array $letVariant): bool
    {
        if (strtolower($board) !== 'let' || $variantKey === null) return true;
        if ($variantKey === 'UNSCOPED') return $letVariant === null;
        if (!is_array($letVariant)) return false;
        return MistakeNotebookService::letVariantKey(
            self::scalar($letVariant['level'] ?? ''),
            self::scalar($letVariant['specialization_id'] ?? '')
        ) === $variantKey;
    }

    /** @return array<string,int> */
    private static function seenCounts(array $attempts, string $board, string $subject): array
    {
        $counts = [];
        foreach (LearningHistoryService::ordered($attempts) as $attempt) {
            if (MistakeNotebookService::boardId($attempt['board'] ?? '') !== $board
                || ($subject !== '' && MistakeNotebookService::taxonomyId('subjects', $attempt['subject'] ?? '') !== $subject)) continue;
            foreach (is_array($attempt['review'] ?? null) ? $attempt['review'] : [] as $item) {
                $question = is_array($item['question'] ?? null) ? $item['question'] : [];
                $id = self::scalar($question['id'] ?? '');
                if ($id !== '') $counts[$id] = ($counts[$id] ?? 0) + 1;
            }
        }
        return $counts;
    }

    private static function scalar(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }
}
