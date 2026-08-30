<?php

declare(strict_types=1);

namespace App\Controllers;
use App\Core\Request;
use App\Core\Response;
use App\Services\Question\QuestionEditorService;
use App\Services\Question\QuestionQueryService;

class QuestionInspectorController extends BaseDeveloperController
{
    public static function index(): void
    {
        $id = Request::queryString('id');

        if ($id === '') {
            self::renderDeveloper(
                'developer/question-inspector-list',
                [
                    'pageTitle' => 'Question Inspector',
                    'questions' => QuestionQueryService::getQuestions([]),
                ]
            );
            return;
        }

        $question = QuestionEditorService::find($id);

        if ($question === null) {
            Response::redirect('/question-inspector');
        }

        self::renderDeveloper(
            'developer/question-inspector',
            [
                'pageTitle' => 'Question Inspector',
                'question' => $question,
            ]
        );
    }
}
