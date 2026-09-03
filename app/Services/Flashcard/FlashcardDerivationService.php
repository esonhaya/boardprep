<?php

declare(strict_types=1);

namespace App\Services\Flashcard;

use App\Core\App;
use App\Repositories\QuestionRepository;
use App\Services\Shared\QuestionValidationService;
use App\Services\Study\StudyLibraryService;

final class FlashcardDerivationService
{
    /** @var array<string, array>|null */
    private ?array $studyMaterials = null;

    /** @var array<string, array>|null */
    private ?array $studyLinksByQuestion = null;

    public function __construct(private QuestionRepository $questions)
    {
    }

    public function forQuestionId(string $questionId): ?array
    {
        $question = $this->questions->find($questionId);

        return $question === null ? null : $this->derive($question);
    }

    public function all(): array
    {
        $cards = [];

        foreach ($this->questions->all() as $question) {
            $card = $this->derive($question);
            if ($card !== null) {
                $cards[] = $card;
            }
        }

        return $cards;
    }

    public function derive(array $question): ?array
    {
        if (!$this->eligible($question)) {
            return null;
        }

        $cardType = $this->archetype($question);
        $revision = is_scalar($question['flashcard_revision'] ?? null)
            ? trim((string) $question['flashcard_revision'])
            : 'r1';
        $taxonomy = is_array($question['taxonomy'] ?? null)
            ? $question['taxonomy']
            : [];

        return [
            'card_key' => FlashcardCardKeyBuilder::build($question, $cardType, $revision),
            'question_id' => (string) $question['id'],
            'card_type' => $cardType,
            'board' => (string) ($taxonomy['board_id'] ?? $question['board'] ?? ''),
            'subject' => (string) ($taxonomy['subject_id'] ?? $question['subject'] ?? ''),
            'domain' => (string) ($taxonomy['domain_id'] ?? $question['domain'] ?? ''),
            'topic' => (string) ($taxonomy['topic_id'] ?? $question['topic'] ?? ''),
            'concept_id' => (string) ($taxonomy['concept_id'] ?? $question['concept'] ?? ''),
            'prompt' => (string) $question['question'],
            'answer' => (string) $question['answer'],
            'explanation' => $this->rationale($question),
            'difficulty' => (string) ($question['difficulty'] ?? ''),
            'revision' => $revision,
            'source_ids' => $this->sourceIds($question),
            'study_references' => $this->studyReferences((string) $question['id']),
            'content_blocks' => is_array($question['content_blocks'] ?? null)
                ? $question['content_blocks']
                : [],
        ];
    }

    private function eligible(array $question): bool
    {
        $status = strtolower((string) ($question['status'] ?? 'active'));
        if ($status === 'archived' || !in_array($status, ['active', 'approved'], true)) {
            return false;
        }
        if (!QuestionValidationService::validate($question)['valid']) {
            return false;
        }
        if (!is_scalar($question['answer'] ?? null) || trim((string) $question['answer']) === '') {
            return false;
        }
        if ($this->rationale($question) === '') {
            return false;
        }
        if (!is_array($question['taxonomy'] ?? null)
            || trim((string) ($question['taxonomy']['concept_id'] ?? '')) === '') {
            return false;
        }

        $intent = strtolower(trim((string) ($question['cognitive_intent'] ?? '')));
        $lowValueIntent = ['recall', 'remember', 'recognize', 'classify'];
        if (in_array($intent, $lowValueIntent, true)
            && ($question['multistep'] ?? false) !== true
            && ($question['data_interpretation'] ?? false) !== true
            && ($question['passage_dependent'] ?? false) !== true
            && ($question['content_blocks'] ?? []) === []) {
            return false;
        }

        return true;
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

    private function sourceIds(array $question): array
    {
        return array_values(array_filter(
            $question['source_ids'] ?? [],
            static fn(mixed $id): bool => is_scalar($id) && trim((string) $id) !== ''
        ));
    }

    private function studyReferences(string $questionId): array
    {
        if ($this->studyMaterials === null) {
            $this->studyMaterials = [];
            foreach (StudyLibraryService::all() as $material) {
                $this->studyMaterials[(string) ($material['id'] ?? '')] = $material;
            }
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
