<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\View;
use App\Services\Study\SourceRegistryService;
use App\Services\Study\StudyLibraryService;

final class StudyLibraryController extends BaseDeveloperController
{
    public static function index(): void
    {
        $exam = Request::queryString('exam');
        View::render('study/library', [
            'pageTitle' => 'Study Materials | BoardPrep',
            'materials' => StudyLibraryService::all($exam !== '' ? $exam : null),
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
        ]);
    }
}
