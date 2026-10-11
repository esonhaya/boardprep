<?php

declare(strict_types=1);

namespace App\Services\Board;

use App\Core\App;
use App\Repositories\BoardRepository;
use App\Repositories\BoardSubjectRepository;
use App\Repositories\SubjectRepository;
use App\Repositories\QuestionRepository;
use App\Repositories\BlueprintRepository;
use App\Services\Question\QuestionEligibilityIndex;
use App\Services\Question\QuestionEligibilityService;
use App\Services\Shared\TaxonomyStorageService;

final class BoardViewService
{
    public static function all(): array
    {
        $boards = App::container()
            ->get(BoardRepository::class)
            ->all();
        $context = self::context();

        return array_map(
            static fn(array $board): array => self::withSubjects($board, $context),
            $boards
        );
    }

    public static function find(
        string $id
    ): ?array {
        $board = App::container()
            ->get(BoardRepository::class)
            ->find($id);

        if ($board === null) {
            return null;
        }

        return self::withSubjects($board, self::context());
    }

    /** @return array{relations: array, subjects: array<string, array>, questions: array, questions_by_id: array<string, array>, candidate_ids: array, blueprints: array, eligibility: QuestionEligibilityIndex} */
    private static function context(): array
    {
        $boardSubjects = App::container()
            ->get(BoardSubjectRepository::class)
            ->all();
        $subjects = [];
        foreach (App::container()->get(SubjectRepository::class)->all() as $subject) {
            if (!is_array($subject)) {
                continue;
            }
            $id = (string) ($subject['id'] ?? '');
            if ($id !== '' && !array_key_exists($id, $subjects)) {
                $subjects[$id] = $subject;
            }
        }

        $questions = App::container()->get(QuestionRepository::class)->all();
        $blueprints = App::container()->get(BlueprintRepository::class)->all();
        $eligibility = new QuestionEligibilityIndex(
            App::storage()->all('question-eligibility'),
            $blueprints,
            \App\Services\Question\QuestionReuseEligibilityResolver::productionDerivedRelationships(),
            App::storage()->all('question-reuse-overrides')
        );
        $questionsById = [];
        $candidateIds = $eligibility->potentialQuestionIdsByExam();
        foreach ($questions as $question) {
            if (!is_array($question)) {
                continue;
            }
            $questionId = trim((string) ($question['id'] ?? ''));
            if ($questionId !== '') {
                $questionsById[$questionId] = $question;
            }
            $taxonomy = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
            $canonicalBoard = self::normalizeBoard((string) ($taxonomy['board_id'] ?? $question['board'] ?? ''));
            if ($canonicalBoard !== '' && $questionId !== '') {
                $candidateIds[$canonicalBoard][$questionId] = true;
            }
        }

        return [
            'relations' => $boardSubjects,
            'subjects' => $subjects,
            'questions' => $questions,
            'questions_by_id' => $questionsById,
            'candidate_ids' => $candidateIds,
            'blueprints' => $blueprints,
            'eligibility' => $eligibility,
        ];
    }

    private static function withSubjects(
        array $board,
        array $context
    ): array {
        $relations = array_values(array_filter(
            $context['relations'],
            static fn(mixed $relation): bool => is_array($relation)
                && ($relation['board_id'] ?? null) === ($board['id'] ?? null)
        ));
        $subjects = $context['subjects'];

        $board["subjects"] = array_values(
            array_filter(
                array_map(
                    static function (array $relation) use ($subjects): ?array {
                        $subject = $subjects[(string) ($relation["subject_id"] ?? "")] ?? null;

                        if ($subject === null) {
                            return null;
                        }

                        return [
                            "id" => $subject["id"],
                            "code" => $subject["code"] ?? "",
                            "name" => $subject["name"] ?? "",
                            "settings" => $relation["settings"] ?? [],
                        ];
                    },
                    $relations
                )
            )
        );

        $allQuestions = $context['questions'];
        $eligibilityIndex = $context['eligibility'];
        $boardId = strtolower((string) ($board["id"] ?? ""));
        $boardCode = strtolower((string) ($board["code"] ?? ""));
        $candidateIds = [];
        foreach ([$boardId, $boardCode] as $boardKey) {
            $normalizedBoard = self::normalizeBoard($boardKey);
            foreach ($context['candidate_ids'][$normalizedBoard] ?? [] as $questionId => $_included) {
                $candidateIds[(string) $questionId] = true;
            }
        }
        $eligibilityByQuestionId = [];
        $questions = [];
        foreach (array_keys($candidateIds) as $questionId) {
            $question = $context['questions_by_id'][$questionId] ?? null;
            if (!is_array($question)) {
                continue;
            }
            if (strtolower((string) ($question["status"] ?? "active")) === "archived") {
                continue;
            }
            $eligibility = QuestionEligibilityService::forExam($question, $boardId, $eligibilityIndex)
                ?? ($boardCode !== '' ? QuestionEligibilityService::forExam($question, $boardCode, $eligibilityIndex) : null);
            if ($eligibility === null) {
                continue;
            }
            $questions[] = $question;
            $eligibilityByQuestionId[(string) ($question['id'] ?? '')] = $eligibility;
        }
        $availableSubjectIds = [];
        $questionCountsBySubject = [];
        foreach ($questions as $question) {
            $eligibility = $eligibilityByQuestionId[(string) ($question['id'] ?? '')] ?? null;
            $subjectId = trim((string) ($eligibility['subject_id'] ?? ''));
            if ($subjectId !== "") {
                $availableSubjectIds[$subjectId] = true;
                $questionCountsBySubject[strtolower($subjectId)] = ($questionCountsBySubject[strtolower($subjectId)] ?? 0) + 1;
            }
        }
        foreach ($board["subjects"] as &$subject) {
            $subjectId = strtolower((string) ($subject["id"] ?? ""));
            $subject["available_questions"] = $questionCountsBySubject[$subjectId] ?? 0;
        }
        unset($subject);
        $practiceSubjects = [];
        foreach ($questionCountsBySubject as $subjectId => $questionCount) {
            $subjectRecord = $context['subjects'][$subjectId] ?? null;
            $name = is_array($subjectRecord) ? trim((string) ($subjectRecord['name'] ?? '')) : '';
            $code = is_array($subjectRecord) ? trim((string) ($subjectRecord['code'] ?? '')) : '';
            if ($name === '') {
                foreach ($context['blueprints'] as $candidateBlueprint) {
                    if (strtolower((string) ($candidateBlueprint['board_id'] ?? '')) !== $boardId) {
                        continue;
                    }
                    foreach ((array) ($candidateBlueprint['sections'] ?? []) as $section) {
                        if (is_array($section) && strtolower((string) ($section['id'] ?? '')) === $subjectId) {
                            $name = trim((string) ($section['name'] ?? $section['label'] ?? $section['title'] ?? ''));
                            break 2;
                        }
                    }
                }
            }
            if ($name === '') {
                $name = TaxonomyStorageService::label('subjects', $subjectId);
            }
            $practiceSubjects[] = [
                'id' => $subjectId,
                'code' => $code !== '' ? $code : $subjectId,
                'name' => $name,
                'available_questions' => $questionCount,
            ];
        }
        usort($practiceSubjects, static fn(array $left, array $right): int => strcasecmp($left['name'], $right['name']));
        $board["available_questions"] = count($questions);
        $board["available_subjects"] = count($availableSubjectIds);
        $board['practice_subjects'] = $practiceSubjects;
        $blueprint = null;
        foreach ($context['blueprints'] as $candidate) {
            if (!is_array($candidate)
                || ($candidate['scope'] ?? null) !== 'board'
                || ($candidate['board_id'] ?? null) !== (string) $board['id']
                || ($candidate['status'] ?? null) !== \App\Constants\Status::ACTIVE) {
                continue;
            }
            $blueprint = $candidate;
            break;
        }
        $readiness = ExamContentReadinessService::evaluate(
            $board,
            $allQuestions,
            $relations,
            $blueprint,
            $questions,
            $eligibilityIndex,
            $eligibilityByQuestionId
        );
        $board["content_readiness"] = $readiness;
        $board["study_status"] = $readiness['status'] === 'STUDY_READY' ? "ready" : "coming_soon";

        return $board;
    }

    private static function normalizeBoard(string $board): string
    {
        $board = strtolower(trim($board));
        return [
            'cse' => 'civil-service',
            'let' => 'let',
            'cle' => 'criminologist',
            'nle' => 'nursing',
            'ple' => 'psychometrician',
            'ce' => 'civil-engineering',
            'me' => 'mechanical-engineering',
            'ree' => 'registered-electrical-engineering',
            'arch' => 'architecture',
        ][$board] ?? $board;
    }
}
