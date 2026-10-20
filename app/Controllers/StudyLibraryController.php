<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\App;
use App\Core\Response;
use App\Core\View;
use App\Repositories\QuestionRepository;
use App\Services\Study\SourceRegistryService;
use App\Services\Study\StudyLibraryService;
use App\Services\Study\ExamIntelligenceService;
use App\Services\Shared\TaxonomyStorageService;

final class StudyLibraryController extends BaseDeveloperController
{
    public static function index(): void
    {
        $examInput = Request::queryString('exam');
        $subjectInput = Request::queryString('subject');
        $topicInput = Request::queryString('topic');
        $materialId = Request::queryString('material');
        $search = substr(trim(Request::queryString('q')), 0, 120);
        $board = $examInput !== '' ? self::boardFor($examInput) : null;
        $subject = $subjectInput !== '' ? self::subjectFor($subjectInput) : null;
        $scopeValid = ($examInput === '' || $board !== null)
            && ($subjectInput === '' || ($board !== null && $subject !== null));
        $topicValid = true;
        $exam = is_array($board) ? (string) $board['id'] : $examInput;
        $subjectId = is_array($subject) ? (string) $subject['id'] : $subjectInput;
        $materials = [];
        if ($scopeValid) {
            if ($subjectId !== '') {
                $materials = array_values(array_filter(
                    StudyLibraryService::all($exam),
                    static fn(array $material): bool => self::materialMatchesSubject($material, $exam, $subjectId)
                ));
                $scopeValid = TaxonomyStorageService::hasBoardSubject((string) $board['id'], $subjectId)
                    || $materials !== [];
                if ($scopeValid && $materialId !== '') {
                    $materials = array_values(array_filter(
                        $materials,
                        static fn(array $material): bool => (string) ($material['id'] ?? '') === $materialId
                    ));
                }
            } elseif ($materialId === '') {
                $materials = StudyLibraryService::all($exam !== '' ? $exam : null);
            } elseif ($exam !== '') {
                $materials = array_values(array_filter(
                    StudyLibraryService::all($exam),
                    static fn(array $material): bool => (string) ($material['id'] ?? '') === $materialId
                ));
            } else {
                $materials = array_values(array_filter([StudyLibraryService::find($materialId)]));
            }
            if ($topicInput !== '') {
                $materials = array_values(array_filter($materials, static function (array $material) use ($topicInput): bool {
                    $lesson = is_array($material['lesson'] ?? null) ? $material['lesson'] : [];
                    if (strtolower(trim((string) ($material['taxonomy']['topic_id'] ?? ''))) === strtolower($topicInput)) {
                        return true;
                    }
                    foreach ((array) ($lesson['sections'] ?? []) as $section) {
                        if (!is_array($section)) continue;
                        if (strcasecmp((string) ($section['id'] ?? ''), $topicInput) === 0
                            || strcasecmp((string) ($section['heading'] ?? ''), $topicInput) === 0) {
                            return true;
                        }
                    }
                    return false;
                }));
                $topicValid = $materials !== [];
                if (!$topicValid) $materials = [];
            }
        }
        if ($materialId === '' && $search !== '') {
            $needle = function_exists('mb_strtolower') ? mb_strtolower($search) : strtolower($search);
            $materials = array_values(array_filter($materials, static function (array $material) use ($needle): bool {
                $parts = [
                    (string) ($material['title'] ?? ''),
                    (string) ($material['summary'] ?? ''),
                ];
                foreach ((array) ($material['notes'] ?? []) as $note) {
                    if (!is_array($note)) {
                        continue;
                    }
                    foreach (['heading', 'body', 0, 1] as $key) {
                        if (is_scalar($note[$key] ?? null)) {
                            $parts[] = (string) $note[$key];
                        }
                    }
                }
                $lesson = is_array($material['lesson'] ?? null) ? $material['lesson'] : [];
                foreach (['learning_objectives', 'common_misconceptions', 'question_patterns'] as $key) {
                    foreach ((array) ($lesson[$key] ?? []) as $text) if (is_scalar($text)) $parts[] = (string) $text;
                }
                foreach ((array) ($lesson['sections'] ?? []) as $section) {
                    if (!is_array($section)) continue;
                    foreach (['heading', 'body'] as $key) if (is_scalar($section[$key] ?? null)) $parts[] = (string) $section[$key];
                }
                $content = implode(' ', $parts);
                $content = function_exists('mb_strtolower') ? mb_strtolower($content) : strtolower($content);
                return str_contains($content, $needle);
            }));
        }
        if ($exam !== '') {
            $signals = ExamIntelligenceService::all($exam);
            $materials = array_map(static function (array $material) use ($exam, $signals): array {
                $material['priority'] = ExamIntelligenceService::priority($material, $exam, $signals);
                return $material;
            }, $materials);
        }
        View::render('study/library', [
            'pageTitle' => 'Study Materials | BoardPrep',
            'materials' => $materials,
            'exam' => $exam,
            'subject' => $subjectId,
            'topic' => $topicInput,
            'subjectName' => is_array($subject) ? (string) ($subject['name'] ?? $subject['id'] ?? '') : $subjectInput,
            'boardName' => is_array($board) ? (string) ($board['name'] ?? $board['id'] ?? '') : '',
            'boardCode' => is_array($board) ? (string) ($board['code'] ?? $board['id'] ?? '') : '',
            'scopeValid' => $scopeValid,
            'topicValid' => $topicValid,
            'search' => $search,
            'materialRequested' => $materialId !== '',
        ]);
    }

    public static function practice(): void
    {
        $materialId = Request::queryString('material');
        $sectionId = Request::queryString('section');
        $material = $materialId !== '' ? StudyLibraryService::find($materialId) : null;
        $lesson = is_array($material['lesson'] ?? null) ? $material['lesson'] : [];
        $board = self::boardFor((string) ($lesson['board_id'] ?? ''));
        $subject = self::subjectFor((string) ($lesson['subject_id'] ?? ''));
        $returnUrl = self::lessonUrl($materialId, $lesson, $sectionId);

        if (!is_array($material) || $lesson === [] || $board === null || $subject === null) {
            \SessionService::flash('error', 'This lesson practice link is no longer available. Return to the study library to choose current material.');
            Response::redirect('/study-library', 303);
            return;
        }

        $level = strtoupper(Request::queryString('civil_service_level'));
        if (in_array(strtolower((string) $board['id']), ['civil-service', 'cse'], true)
            && !in_array($level, ['PROFESSIONAL', 'SUBPROFESSIONAL'], true)) {
            \SessionService::flash('error', 'Choose the Civil Service level for this lesson practice set.');
            Response::redirect($returnUrl, 303);
            return;
        }

        $ids = StudyLibraryService::practiceQuestionIds($material, $sectionId);
        $records = $ids === [] ? [] : App::container()->get(QuestionRepository::class)->findMany($ids);
        $questions = [];
        foreach ($ids as $id) {
            if (is_array($records[$id] ?? null)) {
                $questions[] = $records[$id];
            }
        }
        if ($questions === []) {
            \SessionService::flash('error', 'No eligible questions are currently mapped to this lesson section. You can keep reading or return to the subject materials.');
            Response::redirect($returnUrl, 303);
            return;
        }

        $input = [
            'exam' => (string) $board['id'],
            'subject' => (string) $subject['id'],
            'mode' => 'practice',
            'count' => min(10, count($questions)),
        ];
        if ($level !== '') {
            $input['civil_service_level'] = $level;
        }
        \QuizStartService::startFromQuestionPool($input, $questions, [
            'material_id' => $materialId,
            'lesson_id' => (string) ($lesson['id'] ?? ''),
            'section_id' => $sectionId,
            'return_url' => $returnUrl,
        ]);
    }

    public static function developer(): void
    {
        self::renderDeveloper('developer/study-library', [
            'pageTitle' => 'Study Library',
            'materials' => StudyLibraryService::all(),
            'sources' => SourceRegistryService::all(),
            'sourceValidation' => SourceRegistryService::validate(),
            'materialValidation' => StudyLibraryService::validate(),
            'signals' => ExamIntelligenceService::all(),
            'intelligenceValidation' => ExamIntelligenceService::validate(),
        ]);
    }

    private static function boardFor(string $value): ?array
    {
        $value = strtolower(trim($value));
        if ($value === '') return null;
        foreach (TaxonomyStorageService::boards() as $board) {
            if (!is_array($board)) continue;
            foreach (['id', 'code', 'slug', 'short_code'] as $key) {
                if (is_scalar($board[$key] ?? null) && strtolower(trim((string) $board[$key])) === $value) {
                    return $board;
                }
            }
        }
        return null;
    }

    private static function subjectFor(string $value): ?array
    {
        $value = strtolower(trim($value));
        if ($value === '') return null;
        foreach (TaxonomyStorageService::subjects() as $subject) {
            if (!is_array($subject)) continue;
            foreach (['id', 'code', 'name'] as $key) {
                if (is_scalar($subject[$key] ?? null) && strtolower(trim((string) $subject[$key])) === $value) {
                    return $subject;
                }
            }
        }
        return preg_match('/\A[a-z0-9][a-z0-9-]{0,119}\z/', $value) === 1
            ? ['id' => $value, 'name' => TaxonomyStorageService::label('subjects', $value)]
            : null;
    }

    private static function materialMatchesSubject(array $material, string $boardId, string $subjectId): bool
    {
        $lesson = is_array($material['lesson'] ?? null) ? $material['lesson'] : [];
        if ($lesson !== []) {
            return strtolower(trim((string) ($lesson['board_id'] ?? ''))) === strtolower($boardId)
                && strtolower(trim((string) ($lesson['subject_id'] ?? ''))) === strtolower($subjectId);
        }

        $taxonomy = is_array($material['taxonomy'] ?? null) ? $material['taxonomy'] : [];
        if (strtolower(trim((string) ($taxonomy['subject_id'] ?? ''))) !== strtolower($subjectId)) {
            return false;
        }
        $aliases = [
            'civil-service' => ['civil-service', 'cse'],
            'criminologist' => ['criminologist', 'cle'],
            'nursing' => ['nursing', 'nle'],
            'psychometrician' => ['psychometrician', 'ple'],
        ][$boardId] ?? [$boardId];
        foreach ((array) ($material['exams'] ?? []) as $exam) {
            if (is_scalar($exam) && in_array(strtolower(trim((string) $exam)), $aliases, true)) {
                return true;
            }
        }
        return false;
    }

    private static function lessonUrl(string $materialId, array $lesson, string $sectionId): string
    {
        $url = '/study-library?material=' . rawurlencode($materialId);
        $sectionExists = false;
        foreach ((array) ($lesson['sections'] ?? []) as $section) {
            if (is_array($section) && (string) ($section['id'] ?? '') === $sectionId) {
                $sectionExists = true;
                break;
            }
        }
        if ($sectionExists && ($lesson['id'] ?? '') !== '') {
            $url .= '#' . StudyLibraryService::lessonSectionAnchorId((string) $lesson['id'], $sectionId);
        } elseif (($lesson['id'] ?? '') !== '') {
            $url .= '#boardprep-lesson-' . rawurlencode((string) $lesson['id']);
        }
        return $url;
    }
}
