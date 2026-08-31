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
        $materials = StudyLibraryService::all($exam !== '' ? $exam : null);
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
