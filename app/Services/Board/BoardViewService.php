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

    /** @return array{relations: array, subjects: array<string, array>, questions: array, blueprints: array, eligibility: QuestionEligibilityIndex} */
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
            $blueprints
        );

        return [
            'relations' => $boardSubjects,
            'subjects' => $subjects,
            'questions' => $questions,
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
        $eligibilityByQuestionId = [];
        $questions = [];
        foreach ($allQuestions as $question) {
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
        $board["available_questions"] = count($questions);
        $board["available_subjects"] = count($availableSubjectIds);
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
}
