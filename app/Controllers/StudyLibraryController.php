<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\View;
use App\Services\Study\SourceRegistryService;
use App\Services\Study\StudyLibraryService;
use App\Services\Study\ExamIntelligenceService;

final class StudyLibraryController extends BaseDeveloperController
{
    public static function index(): void
    {
        $exam = Request::queryString('exam');
        $materialId = Request::queryString('material');
        $search = substr(trim(Request::queryString('q')), 0, 120);
        $materials = $materialId !== ''
            ? array_values(array_filter([StudyLibraryService::find($materialId)]))
            : StudyLibraryService::all($exam !== '' ? $exam : null);
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
                $content = implode(' ', $parts);
                $content = function_exists('mb_strtolower') ? mb_strtolower($content) : strtolower($content);
                return str_contains($content, $needle);
            }));
        }
        if ($exam !== '') {
            $materials = array_map(static function (array $material) use ($exam): array {
                $material['priority'] = ExamIntelligenceService::priority($material, $exam);
                return $material;
            }, $materials);
        }
        View::render('study/library', [
            'pageTitle' => 'Study Materials | BoardPrep',
            'materials' => $materials,
            'exam' => $exam,
            'search' => $search,
            'materialRequested' => $materialId !== '',
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
}
