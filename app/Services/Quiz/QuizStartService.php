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
        $input = Request::all();
        $exam = strtolower(trim((string) ($input['exam'] ?? '')));
        if (in_array($exam, ['cse', 'civil-service'], true)) {
            $level = strtoupper(trim((string) ($input['civil_service_level'] ?? $input['civilServiceLevel'] ?? '')));
            if (!in_array($level, ['PROFESSIONAL', 'SUBPROFESSIONAL'], true)) {
                QuizStartSessionWriter::clear();
                SessionService::flash('error', 'Select Professional or Subprofessional for a Civil Service quiz.');
                Response::redirect('/quiz', 302);
                return;
            }
            if ($level === 'PROFESSIONAL' && in_array(strtolower(trim((string) ($input['subject'] ?? ''))), ['clerical-ability', 'clerical ability'], true)) {
                QuizStartSessionWriter::clear();
                SessionService::flash('error', 'Clerical Ability is available only for the Subprofessional examination.');
                Response::redirect('/quiz', 302);
                return;
            }
        }
        $questions = App::container()->get(QuestionRepository::class)->all();
        try {
            $preparation = QuizStartPreparationService::prepare($input, $questions);
        } catch (\InvalidArgumentException $exception) {
            QuizStartSessionWriter::clear();
            SessionService::flash('error', $exception->getMessage());
            Response::redirect('/quiz', 302);
        }

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
            $preparation->questions,
            $preparation->coverage,
            $preparation->issues
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
