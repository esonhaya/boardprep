<?php

declare(strict_types=1);

namespace App\Services\Flashcard;

use App\Core\App;
use App\Repositories\QuestionRepository;
use App\Services\Shared\QuestionValidationService;
use App\Services\Question\QuestionEligibilityIndex;
use App\Services\Question\QuestionEligibilityService;
use App\Services\Study\StudyLibraryService;

final class FlashcardDerivationService
{
    /** @var array<string, array>|null */
    private ?array $studyMaterials = null;

    /** @var array<string, array>|null */
    private ?array $studyLinksByQuestion = null;

    /** @var array<string, array> */
    private array $statusCache = [];

    /** @var array<string, array|null> */
    private array $cardCache = [];

    private FlashcardExclusionRegistry $exclusions;
    private ?QuestionEligibilityIndex $eligibilityIndex = null;
    private bool $useSharedStudyLibrary;

    public function __construct(
        private ?QuestionRepository $questions = null,
        ?array $studyMaterials = null,
        ?array $studyLinks = null,
        ?array $exclusions = null
    )
    {
        $this->useSharedStudyLibrary = $studyMaterials === null && $studyLinks === null;
        $this->studyMaterials = $studyMaterials === null ? null : self::indexStudyMaterials($studyMaterials);
        $this->studyLinksByQuestion = $studyLinks === null ? null : self::indexStudyLinks($studyLinks);
        $this->exclusions = new FlashcardExclusionRegistry($exclusions);
    }

    public function forQuestionId(string $questionId): ?array
    {
        $question = $this->questions?->find($questionId);

        if ($question === null) {
            return null;
        }

        return $this->deriveMany([$question])[0] ?? null;
    }

    /** @return list<array<string,mixed>> */
    public function forQuestionIds(array $questionIds): array
    {
        $questions = [];
        $seen = [];
        foreach ($questionIds as $questionId) {
            if (!is_scalar($questionId)) {
                continue;
            }
            $id = trim((string) $questionId);
            if ($id === '' || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $question = $this->questions?->find($id);
            if ($question !== null) {
                $questions[] = $question;
            }
        }

        return $this->deriveMany($questions);
    }

    public function all(array $filters = [], array $topicIds = []): array
    {
        $questions = $this->questions->all();
        $selectedIndexes = [];
        $boardsByQuestion = [];
        $topicIds = array_fill_keys(array_values(array_filter(
            array_map(static fn(mixed $id): string => is_scalar($id) ? trim((string) $id) : '', $topicIds)
        )), true);

        foreach ($questions as $index => $question) {
            if (!$this->matchesFilters($question, $filters)
                || ($topicIds !== [] && !isset($topicIds[$this->topicId($question)]))) {
                continue;
            }

            $selectedIndexes[] = $index;
            if (!$this->useSharedStudyLibrary) {
                continue;
            }

            $questionId = is_scalar($question['id'] ?? null) ? trim((string) $question['id']) : '';
            if ($questionId === '') {
                continue;
            }
            $cacheKey = self::statusCacheKey($question);
            if (array_key_exists($cacheKey, $this->cardCache)
                || !$this->derivationStatusForKey($question, $cacheKey)['derivable']) {
                continue;
            }
            $taxonomy = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
            $board = $taxonomy['board_id'] ?? $question['board'] ?? null;
            $boardsByQuestion[$questionId] = is_scalar($board) ? (string) $board : null;
        }

        $referencesByQuestion = $this->useSharedStudyLibrary
            ? StudyLibraryService::forQuestionsByBoard($boardsByQuestion)
            : [];
        $cards = [];
        foreach ($selectedIndexes as $index) {
            $question = $questions[$index];
            $questionId = is_scalar($question['id'] ?? null) ? trim((string) $question['id']) : '';
            $card = $this->useSharedStudyLibrary
                ? $this->deriveWithStudyReferences($question, $referencesByQuestion[$questionId] ?? [])
                : $this->derive($question);
            if ($card !== null) {
                $cards[] = $card;
            }
        }

        return $cards;
    }

    /** @param array<int, array<string, mixed>> $questions @return list<array<string,mixed>> */
    private function deriveMany(array $questions): array
    {
        $referencesByQuestion = [];
        if ($this->useSharedStudyLibrary) {
            $boardsByQuestion = [];
            foreach ($questions as $question) {
                $questionId = is_scalar($question['id'] ?? null) ? trim((string) $question['id']) : '';
                if ($questionId === '') {
                    continue;
                }
                $cacheKey = self::statusCacheKey($question);
                if (array_key_exists($cacheKey, $this->cardCache)
                    || !$this->derivationStatusForKey($question, $cacheKey)['derivable']) {
                    continue;
                }
                $taxonomy = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
                $board = $taxonomy['board_id'] ?? $question['board'] ?? null;
                $boardsByQuestion[$questionId] = is_scalar($board) ? (string) $board : null;
            }
            $referencesByQuestion = StudyLibraryService::forQuestionsByBoard($boardsByQuestion);
        }

        $cards = [];
        foreach ($questions as $question) {
            $questionId = is_scalar($question['id'] ?? null) ? trim((string) $question['id']) : '';
            $card = $this->useSharedStudyLibrary
                ? $this->deriveWithStudyReferences($question, $referencesByQuestion[$questionId] ?? [])
                : $this->derive($question);
            if ($card !== null) {
                $cards[] = $card;
            }
        }

        return $cards;
    }

    private function deriveWithStudyReferences(array $question, array $references): ?array
    {
        $cacheKey = self::statusCacheKey($question);
        if (array_key_exists($cacheKey, $this->cardCache)) {
            return $this->cardCache[$cacheKey];
        }

        $status = $this->derivationStatusForKey($question, $cacheKey);
        return $this->deriveCardForStatus($question, $cacheKey, $status, $references);
    }

    private function topicId(array $question): string
    {
        $taxonomy = is_array($question['taxonomy'] ?? null)
            ? $question['taxonomy']
            : [];

        return (string) ($taxonomy['topic_id'] ?? $question['topic'] ?? '');
    }

    private function matchesFilters(array $question, array $filters): bool
    {
        if (isset($filters['board'])) $this->eligibilityIndex ??= QuestionEligibilityService::index();
        return FlashcardScopeMatcher::matches($question, $filters, $this->eligibilityIndex);
    }

    public function derive(array $question): ?array
    {
        $cacheKey = self::statusCacheKey($question);
        if (array_key_exists($cacheKey, $this->cardCache)) {
            return $this->cardCache[$cacheKey];
        }

        $status = $this->derivationStatusForKey($question, $cacheKey);
        return $this->deriveCardForStatus($question, $cacheKey, $status);
    }

    /**
     * Resolve the status and derived card together so integrity scans do not
     * hash and validate the same question twice.
     *
     * @return array{card:?array,status:array{derivable:bool,justified:bool,eligible:bool,reason:string,exclusion:?array}}
     */
    public function deriveWithStatus(array $question): array
    {
        $cacheKey = self::statusCacheKey($question);
        $status = $this->derivationStatusForKey($question, $cacheKey);
        if (array_key_exists($cacheKey, $this->cardCache)) {
            return ['card' => $this->cardCache[$cacheKey], 'status' => $status];
        }

        return [
            'card' => $this->deriveCardForStatus($question, $cacheKey, $status),
            'status' => $status,
        ];
    }

    /**
     * @param array{derivable:bool,justified:bool,eligible:bool,reason:string,exclusion:?array} $status
     */
    private function deriveCardForStatus(array $question, string $cacheKey, array $status, ?array $studyReferences = null): ?array
    {
        if (!$status['derivable']) {
            $this->cardCache[$cacheKey] = null;
            return null;
        }

        $cardType = $this->archetype($question);
        $revision = is_scalar($question['flashcard_revision'] ?? null)
            ? trim((string) $question['flashcard_revision'])
            : 'r1';
        $taxonomy = is_array($question['taxonomy'] ?? null)
            ? $question['taxonomy']
            : [];

        $card = [
            'card_key' => FlashcardCardKeyBuilder::build($question, $cardType, $revision),
            'question_id' => (string) $question['id'],
            'card_type' => $cardType,
            'board' => (string) ($taxonomy['board_id'] ?? $question['board'] ?? ''),
            'subject' => (string) ($taxonomy['subject_id'] ?? $question['subject'] ?? ''),
            'domain' => (string) ($taxonomy['domain_id'] ?? $question['domain'] ?? ''),
            'topic' => (string) ($taxonomy['topic_id'] ?? $question['topic'] ?? ''),
            'concept_id' => (string) ($taxonomy['concept_id'] ?? $question['concept'] ?? ''),
            'prompt' => $this->flashcardPrompt($question),
            'answer' => (string) $question['answer'],
            'explanation' => $this->rationale($question),
            'difficulty' => (string) ($question['difficulty'] ?? ''),
            'revision' => $revision,
            'source_ids' => $this->sourceIds($question),
            'study_references' => $studyReferences ?? $this->studyReferences($question),
            'content_blocks' => is_array($question['content_blocks'] ?? null)
                ? $question['content_blocks']
                : [],
        ];

        $card = FlashcardCardValidator::validate($card) === [] ? $card : null;
        $this->cardCache[$cacheKey] = $card;
        return $card;
    }

    /**
     * Exposes the existing derivation gates to integrity tooling without
     * changing which questions can become cards.
     *
     * @return array{derivable:bool, justified:bool, eligible:bool, reason:string, exclusion:?array}
     */
    public function derivationStatus(array $question): array
    {
        $cacheKey = self::statusCacheKey($question);
        return $this->derivationStatusForKey($question, $cacheKey);
    }

    private function derivationStatusForKey(array $question, string $cacheKey): array
    {
        if (isset($this->statusCache[$cacheKey])) {
            return $this->statusCache[$cacheKey];
        }

        $status = strtolower((string) ($question['status'] ?? 'active'));
        if ($status === 'archived' || !in_array($status, ['active', 'approved'], true)) {
            return $this->statusCache[$cacheKey] = ['derivable' => false, 'justified' => false, 'eligible' => false, 'reason' => 'inactive', 'exclusion' => null];
        }
        if (!QuestionValidationService::validate($question)['valid']) {
            return $this->statusCache[$cacheKey] = ['derivable' => false, 'justified' => false, 'eligible' => true, 'reason' => 'invalid-question', 'exclusion' => null];
        }
        if (!is_scalar($question['answer'] ?? null) || trim((string) $question['answer']) === '') {
            return $this->statusCache[$cacheKey] = ['derivable' => false, 'justified' => false, 'eligible' => true, 'reason' => 'missing-answer', 'exclusion' => null];
        }
        if ($this->rationale($question) === '') {
            return $this->statusCache[$cacheKey] = ['derivable' => false, 'justified' => false, 'eligible' => true, 'reason' => 'missing-rationale', 'exclusion' => null];
        }
        if (!is_array($question['taxonomy'] ?? null)
            || trim((string) ($question['taxonomy']['concept_id'] ?? '')) === '') {
            return $this->statusCache[$cacheKey] = ['derivable' => false, 'justified' => false, 'eligible' => true, 'reason' => 'missing-concept', 'exclusion' => null];
        }

        $exclusion = $this->exclusions->find($question);
        if ($exclusion !== null) {
            return $this->statusCache[$cacheKey] = ['derivable' => false, 'justified' => true, 'eligible' => true, 'reason' => 'explicit-exclusion', 'exclusion' => $exclusion];
        }

        $intent = strtolower(trim((string) ($question['cognitive_intent'] ?? '')));
        if (in_array($intent, ['recall', 'remember', 'recognize', 'classify'], true)
            && ($question['multistep'] ?? false) !== true
            && ($question['data_interpretation'] ?? false) !== true
            && ($question['passage_dependent'] ?? false) !== true
            && ($question['content_blocks'] ?? []) === []) {
            return $this->statusCache[$cacheKey] = ['derivable' => false, 'justified' => false, 'eligible' => true, 'reason' => 'low-value-intent', 'exclusion' => null];
        }

        return $this->statusCache[$cacheKey] = ['derivable' => true, 'justified' => false, 'eligible' => true, 'reason' => '', 'exclusion' => null];
    }

    private static function statusCacheKey(array $question): string
    {
        $id = (string) ($question['id'] ?? '');
        return $id . ':' . sha1((string) json_encode($question, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function archetype(array $question): string
    {
        $intent = strtolower(trim((string) ($question['cognitive_intent'] ?? '')));
        if ($intent === 'misconception') {
            return 'misconception';
        }
        if (($question['numeric'] ?? false) === true
            || ($question['data_interpretation'] ?? false) === true
            || $this->hasBlockType($question, ['equation', 'table', 'chart'])) {
            return 'formula_or_data';
        }
        if (($question['multistep'] ?? false) === true
            || preg_match('/\b(analyze|apply|compare|diagnose|distinguish|evaluate|infer|interpret|synthesize)\b/', $intent) === 1) {
            return 'reasoning';
        }

        return 'concept';
    }

    private function rationale(array $question): string
    {
        foreach (['explanation', 'helpfulExplanation'] as $field) {
            if (is_scalar($question[$field] ?? null) && trim((string) $question[$field]) !== '') {
                return trim((string) $question[$field]);
            }
        }

        return '';
    }

    /**
     * Keep the question wording as the card prompt while masking a long
     * answer that is already present in the stem.  This is card metadata only
     * and prevents a derived card from revealing its answer before review.
     */
    private function flashcardPrompt(array $question): string
    {
        $prompt = trim((string) ($question['question'] ?? ''));
        $answer = trim((string) ($question['answer'] ?? ''));
        if ($prompt === '' || mb_strlen($answer) < 4) {
            return $prompt;
        }

        $masked = preg_replace('/' . preg_quote($answer, '/') . '/iu', '[answer]', $prompt);
        return is_string($masked) && $masked !== '' ? $masked : $prompt;
    }

    private function sourceIds(array $question): array
    {
        return array_values(array_filter(
            $question['source_ids'] ?? [],
            static fn(mixed $id): bool => is_scalar($id) && trim((string) $id) !== ''
        ));
    }

    private function studyReferences(array $question): array
    {
        $questionId = is_scalar($question['id'] ?? null) ? trim((string) $question['id']) : '';
        if ($questionId === '') {
            return [];
        }
        if ($this->useSharedStudyLibrary) {
            $taxonomy = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
            $boardValue = $taxonomy['board_id'] ?? $question['board'] ?? null;
            $boardId = is_scalar($boardValue)
                ? (string) $boardValue
                : null;
            return StudyLibraryService::forQuestion($questionId, $boardId);
        }

        if ($this->studyMaterials === null) {
            $this->studyMaterials = self::indexStudyMaterials(App::storage()->all('study-materials'));
        }

        if ($this->studyLinksByQuestion === null) {
            $this->studyLinksByQuestion = [];
            foreach (App::storage()->all('study-question-links') as $link) {
                if (!is_array($link)) {
                    continue;
                }
                $linkedQuestionId = (string) ($link['question_id'] ?? '');
                if ($linkedQuestionId === '') {
                    continue;
                }
                $this->studyLinksByQuestion[$linkedQuestionId][] = $link;
            }
        }

        $references = [];
        foreach ($this->studyLinksByQuestion[$questionId] ?? [] as $link) {
            $materialId = (string) ($link['material_id'] ?? '');
            if (!isset($this->studyMaterials[$materialId])) {
                continue;
            }
            $references[] = [
                'material_id' => $materialId,
                'title' => (string) ($this->studyMaterials[$materialId]['title'] ?? ''),
                'source_ids' => array_values($link['source_ids'] ?? []),
            ];
        }

        return $references;
    }

    /** @return array<string, array<int, array>> */
    private static function indexStudyLinks(array $links): array
    {
        $indexed = [];
        foreach ($links as $link) {
            if (!is_array($link)) {
                continue;
            }
            $questionId = (string) ($link['question_id'] ?? '');
            if ($questionId !== '') {
                $indexed[$questionId][] = $link;
            }
        }
        return $indexed;
    }

    /** @return array<string, array> */
    private static function indexStudyMaterials(array $materials): array
    {
        $indexed = [];
        foreach ($materials as $material) {
            if (!is_array($material) || ($material['status'] ?? 'published') !== 'published') {
                continue;
            }
            $id = trim((string) ($material['id'] ?? ''));
            if ($id !== '') {
                $indexed[$id] = $material;
            }
        }
        return $indexed;
    }

    private function hasBlockType(array $question, array $types): bool
    {
        foreach ($question['content_blocks'] ?? [] as $block) {
            if (is_array($block) && in_array($block['type'] ?? null, $types, true)) {
                return true;
            }
        }

        return false;
    }
}
