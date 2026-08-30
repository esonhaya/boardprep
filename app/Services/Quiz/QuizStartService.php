<?php

declare(strict_types=1);

use App\Core\App;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Repositories\QuestionRepository;
use App\Services\Quiz\Start\QuizStartPreparationService;
use App\Services\Quiz\Start\QuizStartSessionWriter;
use App\Services\Quiz\Start\QuizStartViewModelFactory;

class QuizStartService
{
    public static function start(): void
    {
        $questions = App::container()->get(QuestionRepository::class)->all();
        $preparation = QuizStartPreparationService::prepare(Request::all(), $questions);

        if ($preparation->isEmpty()) {
            QuizStartSessionWriter::clear();
            SessionService::flash(
                'error',
                'No questions matched those quiz settings. Try a broader topic, difficulty, or subject.'
            );
            Response::redirect('/quiz', 302);
        }

        QuizStartSessionWriter::write(
            $preparation->specification,
            $preparation->questions
        );
        QuizNavigationService::reset();

        View::render(
            'quiz/index',
            QuizStartViewModelFactory::create(
                $preparation->specification,
                $preparation->questions
            )
        );
    }
}
