<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Core\App;
use App\Core\Request;
use App\Repositories\QuestionRepository;
use App\Services\Board\BoardViewService;
use App\Services\Study\StudyLibraryService;

class HomeController extends BaseController
{
    public static function index(): void
    {
        View::render(
            "home/index",
            [
                "pageTitle" => "BoardPrep | Focused board-exam preparation",
                "boards" => BoardViewService::all(),
                "questionCount" => count(App::container()->get(QuestionRepository::class)->all()),
            ]
        );
    }

    public static function exams(): void
    {
        $boards = BoardViewService::all();
        $examCode = Request::queryString('exam');
        if ($examCode !== '') {
            foreach ($boards as $board) {
                if (strcasecmp((string) ($board['code'] ?? ''), $examCode) !== 0) {
                    if (strcasecmp((string) ($board['id'] ?? ''), $examCode) !== 0) {
                        continue;
                    }
                }

                $subjectsById = [];
                $registeredSubjectIds = [];
                foreach ((array) ($board['subjects'] ?? []) as $subject) {
                    if (is_array($subject) && is_scalar($subject['id'] ?? null)) {
                        $subjectId = strtolower((string) $subject['id']);
                        $subjectsById[$subjectId] = $subject;
                        $registeredSubjectIds[$subjectId] = true;
                    }
                }
                foreach ((array) ($board['practice_subjects'] ?? []) as $practiceSubject) {
                    if (!is_array($practiceSubject) || !is_scalar($practiceSubject['id'] ?? null)) {
                        continue;
                    }
                    $key = strtolower((string) $practiceSubject['id']);
                    $subjectsById[$key] = array_merge($subjectsById[$key] ?? [], $practiceSubject);
                }
                $learnerSubjects = array_values($subjectsById);
                $studyAvailability = StudyLibraryService::lessonAvailabilityBySubject((string) ($board['id'] ?? ''));
                foreach ($learnerSubjects as &$learnerSubject) {
                    $subjectId = strtolower((string) ($learnerSubject['id'] ?? ''));
                    $learnerSubject['study_material_available'] = isset($registeredSubjectIds[$subjectId])
                        || (($studyAvailability[$subjectId]['resources_available'] ?? false) === true);
                }
                unset($learnerSubject);
                usort($learnerSubjects, static fn(array $left, array $right): int => strcasecmp(
                    (string) ($left['name'] ?? $left['id'] ?? ''),
                    (string) ($right['name'] ?? $right['id'] ?? '')
                ));

                View::render('home/exam', [
                    'pageTitle' => (string) ($board['name'] ?? 'Examination'),
                    'board' => $board,
                    'learnerSubjects' => $learnerSubjects,
                    'studyAvailability' => $studyAvailability,
                ]);
                return;
            }
        }

        View::render("home/exams", ["pageTitle" => "Choose an examination", "boards" => $boards]);
    }
}
